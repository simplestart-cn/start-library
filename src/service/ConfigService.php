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
use think\facade\Cache;

/**
 * 系统配置管理服务
 * Class ConfigService
 * @package start
 */
class ConfigService extends Service
{

    public $model = 'start\model\Config';
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
    public function set($name, $value = '', $field = 'value')
    {
        list($app, $field) = $this->parse($name);
        $model             = self::model()->where(compact('app', 'field'))->find();
        if (!$model || $field === 'all') {
            throw_error(lang('unknow_field'));
        }
        return $model->save([
            'app'   => $app,
            $field => $value,
        ]);
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
        list($app, $field) = $this->parse($name);
        $config = Cache::get($app.'_config') ?: [];
        if (!count($config) || env('DEBUG')) {
            foreach (self::model()->where(compact('app'))->select() as $vo) {
                $config[$vo['app']][$vo['field']] = $vo['value'];
            }
            Cache::set($app.'_config', $config);
        }
        if ($field === 'all') {
            return isset($config[$app]) ? $config[$app] : [];
        }
        return isset($config[$app][$field]) ? $config[$app][$field] : null;
    }

    /**
     * 批量更新
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    public static function updateList($data)
    {
        $apps = array_column($data, 'app');
        foreach ($apps as $app) {
            Cache::delete($app.'_config');
        }
        return self::model()->saveAll($data);
    }

    public static function getAppConfig($app = '')
    {
        if (empty($app)) {
            $apps          = array_merge(AppService::getActive(), ['core']);
            $filter['app'] = ['in', $apps];
        } else {
            $filter['app'] = $app;
        }
        $filter['is_protected'] = 0;
        $data = array();
        $list = self::getList($filter);
        foreach ($list as $item) {
            if($item['is_protected']){
                continue;
            }
            if(isset($data[$item['app']])){
                $data[$item['app']][$item['field']] = $item['value'] ?: $item['default'];
            }else{
                $data[$item['app']] = [];
                $data[$item['app']][$item['field']] = $item['value'] ?: $item['default'];
            }
        }
        return $data;
    }

    /**
     * 解析缓存名称
     * @param string $name 配置名称
     * @param string $app  应用名称
     * @return array
     */
    private function parse($name, $app = 'core')
    {
        if (stripos($name, '.') !== false) {
            [$app, $field] = explode('.', $name);
        }else{
            $app = $name;
        }
        $field = isset($field) && !empty($field) ? $field : 'all';
        return [strtolower($app), strtolower($field)];
    }
    
    /**
     * 打印输出数据到文件
     * @param mixed $data 输出的数据
     * @param boolean $new 强制替换文件
     * @param string|null $file 文件名称
     */
    public function putlog($data, $file = null, $new = false)
    {
        if (is_null($file)) {
            $file = $this->app->getRootPath() . 'runtime' . DIRECTORY_SEPARATOR . date('Ymd') . '.log';
        }

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
        $domain  = $this->app->request->host(true);
        $isDemo  = is_numeric(stripos($domain, 'www.start-admin.com'));
        $isLocal = in_array($domain, ['127.0.0.1', 'localhost']);
        if ($type === 'dev') {
            return $isLocal || $isDemo;
        }

        if ($type === 'demo') {
            return $isDemo;
        }

        if ($type === 'local') {
            return $isLocal;
        }

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
            foreach ($data['app_map'] as $kk => $vv) {
                if (in_array($vv, $map)) {
                    unset($data['app_map'][$kk]);
                }
            }

        }
        if (is_array($uri) && count($uri) > 0 && count($data['app_uri']) > 0) {
            foreach ($data['app_uri'] as $kk => $vv) {
                if (in_array($vv, $uri)) {
                    unset($data['app_uri'][$kk]);
                }
            }

        }
        $file            = "{$this->app->getRootPath()}runtime/config.json";
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
        if (empty($data) || !is_array($data)) {
            $data = [];
        }

        if (empty($data['app_map']) || !is_array($data['app_map'])) {
            $data['app_map'] = [];
        }

        if (empty($data['app_uri']) || !is_array($data['app_uri'])) {
            $data['app_uri'] = [];
        }

        if (empty($data['app_run']) || !is_string($data['app_run'])) {
            $data['app_run'] = 'developer';
        }

        return is_null($key) ? $data : (isset($data[$key]) ? $data[$key] : null);
    }

    /**
     * 绑定应用实时配置
     * @param array $data 配置数据
     * @return boolean 是否调试模式
     */
    public function bindRuntime($data = [])
    {
        if (empty($data)) {
            $data = $this->getRuntime();
        }

        // 动态绑定应用
        if (!empty($data['app_map'])) {
            $maps = $this->app->config->get('app.app_map', []);
            if (is_array($maps) && count($maps) > 0 && count($data['app_map']) > 0) {
                foreach ($maps as $kk => $vv) {
                    if (in_array($vv, $data['app_map'])) {
                        unset($maps[$kk]);
                    }
                }

            }
            $this->app->config->set(['app_map' => array_merge($maps, $data['app_map'])], 'app');
        }
        // 动态绑定域名
        if (!empty($data['app_uri'])) {
            $uris = $this->app->config->get('app.domain_bind', []);
            if (is_array($uris) && count($uris) > 0 && count($data['app_uri']) > 0) {
                foreach ($uris as $kk => $vv) {
                    if (in_array($vv, $data['app_uri'])) {
                        unset($uris[$kk]);
                    }
                }

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
