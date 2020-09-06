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

namespace start;

use start\service\AuthService;
use start\service\ConfigService;
use think\middleware\SessionInit;
use think\Request;
use think\Service;
use think\facade\Config;
use function Composer\Autoload\includeFile;

/**
 * 模块注册服务
 * Class Library
 * @package start
 */
class Library extends Service
{
    /**
     * 启动服务
     */
    public function boot()
    {
        // 动态绑定运行配置
        ConfigService::instance()->bindRuntime();

        // 绑定插件应用
        $this->app->event->listen('HttpRun', function () {
            $this->app->middleware->add(App::class);
        });
                // 注册系统任务指令
        $this->commands([
            'start\command\Build',
            'start\command\Clear',
            'start\command\Menu',
            'start\command\Version',
            'start\command\Database',
        ]);

        // 绑定插件路由
        $this->app->bind([
            'think\route\Url' => Url::class,
        ]);

    }

    /**
     * 初始化服务
     */
    public function register()
    {
        // 加载中文语言
        $this->app->lang->load(__DIR__ . '/lang/zh-cn.php', 'zh-cn');
        $this->app->lang->load(__DIR__ . '/lang/en-us.php', 'en-us');
        // 输入变量默认过滤
        $this->app->request->filter(['trim']);
        // 判断访问模式，兼容 CLI 访问控制器
        if ($this->app->request->isCli()) {
            if (empty($_SERVER['REQUEST_URI']) && isset($_SERVER['argv'][1])) {
                $this->app->request->setPathinfo($_SERVER['argv'][1]);
            }
        } else {
            // 注册会话初始化中间键
            if ($this->app->request->request('not_init_session', 0) == 0) {
                $this->app->middleware->add(SessionInit::class);
            }
            // 注册访问处理中间键
            $this->app->middleware->add(function (Request $request, \Closure $next) {
                $header = [];
                if (($origin = $request->header('origin', '*')) !== '*') {
                    $header['Access-Control-Allow-Origin'] = $origin;
                    $header['Access-Control-Allow-Methods'] = 'GET,POST,PATCH,PUT,DELETE';
                    $header['Access-Control-Allow-Headers'] = 'Authorization,Content-Type,If-Match,If-Modified-Since,If-None-Match,If-Unmodified-Since,X-Requested-With';
                    $header['Access-Control-Expose-Headers'] = 'User-Form-Token,User-Token,Token';
                }
                // 访问模式及访问权限检查
                if ($request->isOptions()) {
                    return response()->code(204)->header($header);
                } elseif (AuthService::instance()->check()) {
                    return $next($request)->header($header);
                } elseif (AuthService::instance()->isLogin()) {
                    return json(['code' => 0, 'msg' => lang('start_not_auth')])->header($header);
                } else {
                    return json(['code' => 0, 'msg' => lang('start_not_login'), 'url' => url('admin/index/index')])->header($header);
                }
            }, 'route');
        }
    }
}