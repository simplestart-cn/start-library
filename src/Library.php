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


use think\Request;
use think\Service;
use think\facade\Config;
use think\middleware\SessionInit;
use start\service\RuntimeService;
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
        RuntimeService::instance()->bindRuntime();

        // 绑定插件应用
        $this->app->event->listen('HttpRun', function () {
            $this->app->middleware->add(App::class);
        });
                // 注册系统任务指令
        $this->commands([
            'start\command\Auth',
            'start\command\Clear',
            'start\command\Install',
            'start\command\Version',
            'start\command\Database',
            'start\command\build\App',
            'start\command\build\Cms',
            'start\command\build\Model',
            'start\command\build\Service',
            'start\command\build\Controller'
        ]);

        // 绑定插件路由
        $this->app->bind([
            'think\route\Url' => Url::class,
        ]);

        // 服务提供
        if (is_file($this->app->getRootPath() . 'core' . DIRECTORY_SEPARATOR . 'provider.php')) {
            $this->app->bind(include $this->app->getRootPath() . 'core' . DIRECTORY_SEPARATOR . 'provider.php');
        }

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
        }
    }
}