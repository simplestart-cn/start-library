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

use think\App;
use think\Container;

/**
 * 自定义服务基类
 * Class Service
 * @package start
 */
abstract class Service
{
    /**
     * 服务名称
     * @var [type]
     */
    protected $name;

    /**
     * 应用实例
     * @var App
     */
    protected $app;

    /**
     * 服务模型
     * @var [type]
     */
    public $model;


    /**
     * Service constructor.
     * @param App $app
     */
    public function __construct(App $app)
    {
        $this->app = $app;
        // 初始化名称
        if (empty($this->name)) {
            // 当前模型名
            $name       = str_replace('\\', '/', static::class);
            $this->name = basename($name);
        }
        $this->initialize();
    }

    /**
     * 初始化服务
     * @return $this
     */
    protected function initialize()
    {
        $namespace = $this->app->getNamespace();
        if(!empty($this->model)){
            if(is_object($this->model)){
                return $this;
            }
            if(class_exists($this->model)){
                $this->model = Container::getInstance()->make($this->model);
            }else if(class_exists($object = "{$namespace}\\model\\{$this->model}")) {
                $this->model = Container::getInstance()->make($object);
            }else{
                throw_error("[{{$this->model}] does not exist.");
            }
        }else{
            if (class_exists($object = "{$namespace}\\model\\{$this->name}")) {
                $this->model = Container::getInstance()->make($object);
            }
        }
        return $this;
    }

    /**
     * 静态实例对象
     * @param array $args
     * @return static
     */
    public static function instance(...$args)
    {
        return Container::getInstance()->make(static::class, $args);
    }

    /**
     * 获取模型
     * @param  [type] $name 模型名称
     * @return [type]       [description]
     */
    public static function model()
    {
        return self::instance()->model;
    }

    /**
     * 获取列表
     * @param  array  $filter [description]
     * @return [type]         [description]
     */
    public static function getList($filter = [], $with = [], $order=[])
    {
        $model = self::model();
        return $model->list($filter, $with);
    }

    /**
     * 获取分页
     * @param  array  $filter [description]
     * @param  array  $paging [description]
     * @return [type]         [description]
     */
    public static function getPage($filter = [], $with = [], $order = [], $paging = [])
    {
        $model = self::model();
        return $model->page($filter, $with, [], $paging);
    }

    /**
     * 获取详情
     * @param  array  $filter [description]
     * @return [type]         [description]
     */
    public static function getInfo($filter = [], $with = [])
    {
        $model = self::model();
        return $model->info($filter, $with);
    }

    /**
     * 更新记录
     * @param [type] $input  [description]
     * @param array  $field [description]
     */
    public static function save($input, $field = [])
    {
        $model = self::model();
        $pk = $model->getPk();
        if(isset($input[$pk])){
            return $model->allowField($field)->update($input);
        }else{
            return $model->allowField($field)->save($input);
        }
    }

    /**
     * 删除记录
     * @param  [type] $filter [description]
     * @return [type]         [description]
     */
    public static function remove($filter)
    {
        $model = self::model();
        if(!is_array($filter)){
            return $model->find($filter)->remove();
        }else{
            return $model->where($filter)->find()->remove();
        }
    }



}