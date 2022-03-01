<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 刘志淳 <chun@engineer.com>
// +----------------------------------------------------------------------

namespace start\command;

use start\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;

abstract class Build extends Command
{
    protected $type;

    abstract protected function getStub();

    protected function configure()
    {
        $this->addArgument('name', Argument::REQUIRED, "The name of the class");
    }

    protected function execute(Input $input, Output $output)
    {
        $name = trim($input->getArgument('name'));

        $classname = $this->getClassName($name);

        $pathname = $this->getPathName($classname);

        if (is_file($pathname)) {
            $output->writeln('<error>' . $this->type . ':' . $classname . ' already exists!</error>');
            return false;
        }

        if (!is_dir(dirname($pathname))) {
            mkdir(dirname($pathname), 0755, true);
        }

        file_put_contents($pathname, $this->buildClass($classname));

        $output->writeln('<info>' . $this->type . ':' . $classname . ' created successfully.</info>');
    }

    protected function buildClass(string $name, string $stubPath = null)
    {
        if(empty($stubPath)){
            $stub = file_get_contents($this->getStub());
        }else{
            $stub = file_get_contents($stubPath);
        }

        $namespace = trim(implode('\\', array_slice(explode('\\', $name), 0, -1)), '\\');

        $class = str_replace($namespace . '\\', '', $name);

        return $this->stub_replace($stub, $class, $namespace);

    }

    protected function stub_replace(string $stub, string $class, string $namespace)
    {
        return str_replace(['{%className%}',  '{%namespace%}', '{%actionSuffix%}', '{%app_namespace%}'], [
            $class,
            $namespace,
            $this->app->config->get('route.action_suffix'),
            $this->app->getNamespace(),
        ], $stub);
    }

    protected function getPathName(string $name): string
    {
        $name = str_replace('app\\', '', $name);

        return $this->app->getBasePath() . ltrim(str_replace('\\', '/', $name), '/') . '.php';
    }

    protected function getClassName(string $name): string
    {
        if (strpos($name, '\\') !== false) {
            return $name;
        }

        if (strpos($name, '@')) {
            [$app, $name] = explode('@', $name);
        } else {
            $app = '';
        }

        if (strpos($name, '/') !== false) {
            $name = str_replace('/', '\\', $name);
        }

        return $this->getNamespace($app) . '\\' . $name;
    }

    protected function getNamespace(string $app): string
    {
        return 'app' . ($app ? '\\' . $app : '');
    }

}
