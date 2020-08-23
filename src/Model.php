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
use think\Request;
use think\Container;
use think\db\Query;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\exception\HttpResponseException;

/**
 * 自定义模型基类
 * Class Model
 * @package start
 */
class Model extends \think\Model
{
    /**
     * 应用实例
     * @var App
     */
    protected $app;
    
    /**
     * 初始化服务
     * @return $this
     */
    protected function initialize()
    {
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
     * 获取所有数据
     * @param     array                         $filter   [description]
     * @param     array                         $with     [description]
     * @param     array                         $order    [description]
     * @return    [type]                                  [description]
     */
    public function list($filter = [], $with = [], $order = [])
    {
        return $this->where($filter)->with($with)->order($order)->select();
    }

    /**
     * 获取分页数据
     * @param     array                         $paging   [description]
     * @param     array                         $filter   [description]
     * @param     array                         $with     [description]
     * @param     array                         $order    [description]
     * @return    [type]                                  [description]
     */
    public function page($filter = [], $with = [], $order = [], $paging=[])
    {
        if(!is_array($paging)){
            $paging = ['page' => (int)$paging];
        }
        if(!isset($paging['page'])){
            $paging['page'] = input('per_page',1,'trim');
        }
        if(!isset($paging['per_page'])){
            $paging['per_page'] = input('per_page',15,'trim');
        }
        return $this->where($filter)->with($with)->order($order)->paginate($paging['per_page'], false, [
            'query' => array_merge(\request()->request() , $paging)
        ]);
    }

    /**
     * 获取详情
     * @param     array                         $filter   [description]
     * @param     array                         $with     [description]
     * @return    [type]                                  [description]
     */
    public function info($filter, $with = [])
    {
        if(!is_array($filter)){
            return $this->with($with)->find($filter);
        }else{
            return $this->where($filter)->with($with)->find();
        }
    }

    /**
     * 软删除
     */
    public function remove()
    {
        if(isset($this->is_deleted)){
            return $this->save(['is_deleted' => 1]);
        }else{
            return $this->delete();
        }
    }

}