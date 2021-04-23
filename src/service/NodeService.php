<?php

// +----------------------------------------------------------------------
// | Simplestart Library
// +----------------------------------------------------------------------
// | 版权所有: http://www.simplestart.cn copyright 2020
// +----------------------------------------------------------------------
// | 开源协议: https://www.apache.org/licenses/LICENSE-2.0.txt
// +----------------------------------------------------------------------
// | 仓库地址: https://github.com/simplestart-cn/start-library
// +----------------------------------------------------------------------

namespace start\service;

use start\Service;
use start\extend\DataExtend;

/**
 * 应用节点服务管理
 * Class NodeService
 * @package start
 */
class NodeService extends Service
{

    public $model = 'start\model\Node';
    
    /**
     * 驼峰转下划线规则
     * @param string $name
     * @return string
     */
    public function nameTolower($name)
    {
        $dots = [];
        foreach (explode('.', strtr($name, '/', '.')) as $dot) {
            $dots[] = trim(preg_replace("/[A-Z]/", "_\\0", $dot), "_");
        }
        return strtolower(join('.', $dots));
    }

    /**
     * 获取当前节点内容
     * @param string $type
     * @return string
     */
    public function getCurrent($type = '')
    {
        $prefix = $this->app->getNamespace();
        $middle = '\\' . $this->nameTolower($this->app->request->controller());
        $suffix = ($type === 'controller') ? '' : ('\\' . $this->app->request->action());
        return strtr(substr($prefix, stripos($prefix, '\\') + 1) . $middle . $suffix, '\\', '/');
    }

    /**
     * 检查并完整节点内容
     * @param string $node
     * @return string
     */
    public function fullnode($node)
    {
        if (empty($node)) return $this->getCurrent();
        if (count($attrs = explode('/', $node)) === 1) {
            return $this->getCurrent('controller') . "/{$node}";
        } else {
            $attrs[1] = $this->nameTolower($attrs[1]);
            return join('/', $attrs);
        }
    }

    /**
     * 获取节点列表
     * @return [type] [description]
     */
    public function getAll($app = '', $force = false)
    {
        list($nodes, $parents, $methods) = [[], [], array_reverse($this->getMethods($app, $force))];
        foreach ($methods as $node => $method) {
            list($count, $parent) = [substr_count($node, '/'), substr($node, 0, strripos($node, '/'))];
            if ($count === 2 && (!empty($method['isauth']) || !empty($method['ismenu']))) {
                in_array($parent, $parents) or array_push($parents, $parent);
                $nodes[$node] = [
                    'node' => $node,
                    'title' => $method['title'],
                    'parent' => $parent,
                    'isauth' => $method['isauth'],
                    'ismenu' => $method['ismenu'],
                    'isview' => $method['isview'],
                    'islogin' => $method['islogin']
                ];
            } elseif ($count === 1 && in_array($parent, $parents)) {
                $nodes[$node] = [
                    'node' => $node,
                    'title' => $method['title'],
                    'parent' => $parent,
                    'isauth' => $method['isauth'],
                    'ismenu' => $method['ismenu'],
                    'isview' => $method['isview'],
                    'islogin' => $method['islogin']
                ];
            }
        }
        foreach (array_keys($nodes) as $key) foreach ($methods as $node => $method) if (stripos($key, "{$node}/") !== false) {
            $parent = substr($node, 0, strripos($node, '/'));
            $nodes[$node] = [
                'node' => $node,
                'title' => $method['title'],
                'parent' => $parent,
                'isauth' => $method['isauth'],
                'ismenu' => $method['ismenu'],
                'isview' => $method['isview'],
                'islogin' => $method['islogin']
            ];
            $nodes[$parent] = [
                'node' => $parent,
                'title' => ucfirst($parent),
                'parent' => '',
                'isauth' => $method['isauth'],
                'ismenu' => (boolean)$method['ismenu'],
                'isview' => $method['isview'],
                'islogin' => $method['islogin']
            ];
        }
        return array_reverse($nodes);
    }

    /**
     * 获取节点树
     * @return [type] [description]
     */
    public function getTree($force = flase)
    {
        $nodes = $this->getAll($force);
        return DataExtend::arr2tree($nodes, 'node', 'parent', 'child');
    }

    /**
     * 获取所有应用名称
     * @return [type] [description]
     */
    public function getApps()
    {
        $path = $this->app->getBasePath();
        $apps = [];
        foreach (glob("{$path}*") as $item) {
            if (is_dir($item)) {
                $item = explode(DIRECTORY_SEPARATOR, $item);
                array_push($apps, end($item));
            }
        }
        return $apps;
    }

    /**
     * 控制器方法扫描处理
     * @param boolean $force
     * @return array
     * @throws \ReflectionException
     */
    public function getMethods($app = '',$force = false)
    {
        static $data = [];
        if (!$force) {
            $data = $this->app->cache->get('start_auth_node', []);
            if (count($data) > 0) return $data;
        } else {
            $data = [];
        }
        $ignores = get_class_methods('\start\Controller');
        if(empty($app)){
            $directory = $this->app->getBasePath();
        }else {
            $directory = $app === 'core' ? $this->app->getRootPath() . $app . DIRECTORY_SEPARATOR : $this->app->getBasePath() . $app . DIRECTORY_SEPARATOR;
        }
        foreach ($this->_scanDirectory($directory) as $file) {
            if($app === 'core'){
                if (preg_match("|/(\w+)/controller/(.+)\.php$|i", $file, $matches)) {
                    list(, $namespace, $classname) = $matches;
                    $class = new \ReflectionClass(strtr("{$namespace}/controller/{$classname}", '/', '\\'));
                    $prefix = strtr("{$namespace}/{$this->nameTolower($classname)}", '\\', '/');
                    $data[$prefix] = $this->_parseComment($class->getDocComment(), $classname);
                    foreach ($class->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                        if (in_array($metname = $method->getName(), $ignores)) continue;
                        $data["{$prefix}/{$metname}"] = $this->_parseComment($method->getDocComment(), $metname);
                    }
                }
            }else{
                if (preg_match("|/(\w+)/(\w+)/controller/(.+)\.php$|i", $file, $matches)) {
                    list(, $namespace, $appname, $classname) = $matches;
                    $class = new \ReflectionClass(strtr("{$namespace}/{$appname}/controller/{$classname}", '/', '\\'));
                    $prefix = strtr("{$appname}/{$this->nameTolower($classname)}", '\\', '/');
                    $data[$prefix] = $this->_parseComment($class->getDocComment(), $classname);
                    foreach ($class->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                        if (in_array($metname = $method->getName(), $ignores)) continue;
                        $data["{$prefix}/{$metname}"] = $this->_parseComment($method->getDocComment(), $metname);
                    }
                }
            }
            
        }
        $data = array_change_key_case($data, CASE_LOWER);
        $this->app->cache->set('start_auth_node', $data);
        return $data;
    }

    /**
     * 解析硬节点属性
     * @param string $comment
     * @param string $default
     * @return array
     */
    private function _parseComment($comment, $default = '')
    {

        $text = strtr($comment, "\n", ' ');
        $title = preg_replace('/^\/\*\s*\*\s*\*\s*(.*?)\s*\*.*?$/', '$1', $text);
        foreach (['@auth', '@menu', '@login'] as $find) if (stripos($title, $find) === 0) {
            $title = $default;
        }
        $method =  [
            'title'   => $title ? $title : $default,
            'isauth'  => intval(preg_match('/@auth\s*/i', $text)),
            'ismenu'  => intval(preg_match('/@menu\s*/i', $text)),
            'isview'  => intval(preg_match('/@view\s*/i', $text)),
            'islogin' => intval(preg_match('/@login\s*/i', $text)),
        ];
        // 匹配设置的menu值
        if($method['ismenu']){
            preg_match('/@menu\s\[(.*?)\]\s/i', $text, $menu);
            if(count($menu) > 1){
                $menu = '{'.str_replace('=>',':',str_replace("'", '"', preg_replace("/\s/i", '', $menu[1]))).'}';
                $method['ismenu'] = json_decode($menu, true);
            }
        }
        return $method;
    }

    /**
     * 获取所有PHP文件列表
     * @param string $path 扫描目录
     * @param array $data 额外数据
     * @param string $ext 有文件后缀
     * @return array
     */
    private function _scanDirectory($path, $data = [], $ext = 'php')
    {
        foreach (glob("{$path}*") as $item) {
            if (is_dir($item)) {
                $data = array_merge($data, $this->_scanDirectory("{$item}/"));
            } elseif (is_file($item) && pathinfo($item, PATHINFO_EXTENSION) === $ext) {
                $data[] = strtr($item, '\\', '/');
            }
        }
        return $data;
    }
}