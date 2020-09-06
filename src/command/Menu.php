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

namespace start\command;

use start\Command;
use start\service\MenuService;
use start\service\NodeService;

use think\console\Input;
use think\console\Output;
use think\console\input\Argument;

/**
 * 数据库修复优化指令
 * Class Database
 * @package start\command
 */
class Menu extends Command
{
    public function configure()
    {
        $this->setName('start:menu');
        $this->addArgument('app', Argument::OPTIONAL, 'App name');
        $this->setDescription('Building app menus.');
    }

    /**
     * @param Input $input
     * @param Output $output
     * @return mixed
     */
    public function execute(Input $input, Output $output)
    {
        $app = $input->getArgument('app');
        $apps = NodeService::instance()->getApps();
        $service = MenuService::instance();
        if(!empty($app)){
        	$output->writeln("start building {$app}...");
        	$res = $service->building($app);
        	$output->writeln("{$app} complete.");
        }else{
        	$output->writeln("start building...");
        	foreach ($apps as $name) {
        		$res = $service->building($name);
        		$output->writeln("{$name} complete.");
        	}
        	$output->writeln("all complete !");
        }
    }

}