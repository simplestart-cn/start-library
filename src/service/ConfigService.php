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
use think\db\Query;

/**
 * 系统配置管理服务
 * Class ConfigService
 * @package start\service
 */
class ConfigService extends Service
{

    /**
     * 配置数据缓存
     * @var array
     */
    protected $data = [];

    /**
     * 设置配置数据
     * @param string $name 配置名称
     * @param string $value 配置内容
     * @return static
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function set($name, $value = '')
    {
        [$type, $field] = $this->parse($name);
        if (is_array($value)) {
            foreach ($value as $k => $v) $this->set("{$field}.{$k}", $v);
        } else {
            $this->data = [];
            $data = ['name' => $field, 'value' => $value, 'type' => $type];
            $this->save('AdminConfig', $data, 'name', ['type' => $type]);
        }
        return $this;
    }

    /**
     * 读取配置数据
     * @param string $name
     * @return array|mixed|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function get($name)
    {
        [$type, $field, $outer] = $this->parse($name);
        if (empty($this->data)) foreach ($this->app->db->name('AdminConfig')->select() as $vo) {
            $this->data[$vo['type']][$vo['name']] = $vo['value'];
        }
        if (empty($name)) {
            return empty($this->data[$type]) ? [] : ($outer === 'raw' ? $this->data[$type] : array_map(function ($value) {
                return htmlspecialchars($value);
            }, $this->data[$type]));
        } else {
            if (isset($this->data[$type])) {
                if ($field) {
                    if (isset($this->data[$type][$field])) {
                        return $outer === 'raw' ? $this->data[$type][$field] : htmlspecialchars($this->data[$type][$field]);
                    }
                } else {
                    if ($outer === 'raw') foreach ($this->data[$type] as $key => $vo) {
                        $this->data[$type][$key] = htmlspecialchars($vo);
                    }
                    return $this->data[$type];
                }
            }
            return '';
        }
    }

    /**
     * 解析缓存名称
     * @param string $rule 配置名称
     * @param string $type 配置类型
     * @return array
     */
    private function parse($rule, $type = 'base')
    {
        if (stripos($rule, '.') !== false) {
            [$type, $rule] = explode('.', $rule);
        }
        [$field, $outer] = explode('|', "{$rule}|");
        return [$type, $field, strtolower($outer)];
    }

    /**
     * 写入系统日志
     * @param string $action
     * @param string $content
     * @return integer
     */
    public function dolog($action, $content)
    {
        return $this->app->db->name('AdminOperation')->insert([
            'node'     => NodeService::instance()->getCurrent(),
            'action'   => $action,
            'content'  => $content,
            'geoip'    => $this->app->request->ip() ?: '127.0.0.1',
            'user_id' => AuthService::instance()->getUserId() ?: 0,
            'user_name' => AuthService::instance()->getUserName() ?: '-',
        ]);
    }

    /**
     * 打印输出数据到文件
     * @param mixed $data 输出的数据
     * @param boolean $new 强制替换文件
     * @param string|null $file 文件名称
     */
    public function putlog($data,  $file = null, $new = false)
    {
        if (is_null($file)) $file = $this->app->getRootPath() . 'runtime' . DIRECTORY_SEPARATOR . date('Ymd') . '.log';
        $str = (is_string($data) ? $data : ((is_array($data) || is_object($data)) ? print_r($data, true) : var_export($data, true))) . PHP_EOL;
        $new ? file_put_contents($file, $str) : file_put_contents($file, $str, FILE_APPEND);
    }

    /**
     * 判断运行环境
     * @param string $type 运行模式（dev|demo|local）
     * @return boolean
     */
    public function checkRunMode($type = 'dev')
    {
        $domain = $this->app->request->host(true);
        $isDemo = is_numeric(stripos($domain, 'start-admin.com'));
        $isLocal = in_array($domain, ['127.0.0.1', 'localhost']);
        if ($type === 'dev') return $isLocal || $isDemo;
        if ($type === 'demo') return $isDemo;
        if ($type === 'local') return $isLocal;
        return true;
    }

    /**
     * 设置运行环境模式
     * @param null|boolean $state
     * @return boolean
     */
    public function productMode($state = null)
    {
        if (is_null($state)) {
            return $this->bindRuntime();
        } else {
            return $this->setRuntime([], $state ? 'product' : 'developoer');
        }
    }

    /**
     * 设置实时运行配置
     * @param array|null $map 应用映射
     * @param string|null $run 支持模式
     * @param array|null $uri 域名映射
     * @return boolean 是否调试模式
     */
    public function setRuntime($map = [], $run = null, $uri = [])
    {
        $data = $this->getRuntime();
        if (is_array($map) && count($map) > 0 && count($data['app_map']) > 0) {
            foreach ($data['app_map'] as $kk => $vv) if (in_array($vv, $map)) unset($data['app_map'][$kk]);
        }
        if (is_array($uri) && count($uri) > 0 && count($data['app_uri']) > 0) {
            foreach ($data['app_uri'] as $kk => $vv) if (in_array($vv, $uri)) unset($data['app_uri'][$kk]);
        }
        $file = "{$this->app->getRootPath()}runtime/config.json";
        $data['app_run'] = is_null($run) ? $data['app_run'] : $run;
        $data['app_map'] = is_null($map) ? [] : array_merge($data['app_map'], $map);
        $data['app_uri'] = is_null($uri) ? [] : array_merge($data['app_uri'], $uri);
        file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE));
        return $this->bindRuntime($data);
    }

    /**
     * 获取实时运行配置
     * @param null|string $key
     * @return array
     */
    public function getRuntime($key = null)
    {
        $file = "{$this->app->getRootPath()}runtime/config.json";
        $data = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
        if (empty($data) || !is_array($data)) $data = [];
        if (empty($data['app_map']) || !is_array($data['app_map'])) $data['app_map'] = [];
        if (empty($data['app_uri']) || !is_array($data['app_uri'])) $data['app_uri'] = [];
        if (empty($data['app_run']) || !is_string($data['app_run'])) $data['app_run'] = 'developer';
        return is_null($key) ? $data : ($data[$key] ?? null);
    }

    /**
     * 绑定应用实时配置
     * @param array $data 配置数据
     * @return boolean 是否调试模式
     */
    public function bindRuntime($data = [])
    {
        if (empty($data)) $data = $this->getRuntime();
        // 动态绑定应用
        if (!empty($data['app_map'])) {
            $maps = $this->app->config->get('app.app_map', []);
            if (is_array($maps) && count($maps) > 0 && count($data['app_map']) > 0) {
                foreach ($maps as $kk => $vv) if (in_array($vv, $data['app_map'])) unset($maps[$kk]);
            }
            $this->app->config->set(['app_map' => array_merge($maps, $data['app_map'])], 'app');
        }
        // 动态绑定域名
        if (!empty($data['app_uri'])) {
            $uris = $this->app->config->get('app.domain_bind', []);
            if (is_array($uris) && count($uris) > 0 && count($data['app_uri']) > 0) {
                foreach ($uris as $kk => $vv) if (in_array($vv, $data['app_uri'])) unset($uris[$kk]);
            }
            $this->app->config->set(['domain_bind' => array_merge($uris, $data['app_uri'])], 'app');
        }
        // 动态设置运行模式
        return $this->app->debug($data['app_run'] !== 'product')->isDebug();
    }

    /**
     * 判断实时运行模式
     * @return boolean
     */
    public function isDebug()
    {
        return $this->getRuntime('app_run') !== 'product';
    }

    /**
     * 初始化并运行应用
     * @param \think\App $app
     */
    public function doInit(\think\App $app)
    {
        $app->debug($this->isDebug());
        $response = $app->http->run();
        $response->send();
        $app->http->end($response);
    }
}