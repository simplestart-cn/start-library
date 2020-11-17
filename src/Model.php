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
     * 关联
     * @var array
     */
    protected $with = [];

    /**
     * 排序
     */
    protected $order = [];

    
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
     * @param     array                         $order     [description]
     * @param     array                         $with    [description]
     * @return    [type]                                  [description]
     */
    public function list($filter = [], $order = [], $with = [])
    {
        $order = is_array($order) ? $order : [$order]; 
        return $this->filter($filter)
        ->with(array_merge($this->with, $with))
        ->order(array_merge($this->order, $order))
        ->select();
    }

    /**
     * 获取分页数据
     * @param     array                         $filter   [description]
     * @param     array                         $order    [description]
     * @param     array                         $with     [description]
     * @param     array                         $paging   [description]
     * @return    [type]                                  [description]
     */
    public function page($filter = [], $order = [], $with = [], $paging=[])
    {
        $order = is_array($order) ? $order : [$order];
        if(!is_array($paging)){
            $paging = ['page' => (int)$paging];
        }
        if(!isset($paging['page'])){
            $paging['page'] = input('page',1,'trim');
        }
        if(!isset($paging['per_page'])){
            $paging['per_page'] = input('per_page',20,'trim');
        }
        return $this->filter($filter)
        ->with(array_merge($this->with, $with))
        ->order(array_merge($this->order, $order))
        ->paginate($paging['per_page'], false, [
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
            return $this->with(array_merge($this->with, $with))->find($filter);
        }else{
            return $this->filter($filter)->with(array_merge($this->with, $with))->find();
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

    /**
     * 条件查询
     * @param  array  $input [description]
     * @return [type]         [description]
     *
     * input 结构支持
     * $input = 1;
     * $input = [
     *     'key1' => 1,
     *     'key2' => [1,2,3]
     *     'key3' => ['!=', 1]
     *     'key4' => ['in', [1,2,3]],
     *      ...
     * ];
     */
    public function filter($input = [])
    {
        if(empty($input)) return $this;
        if(!is_array($input)){
            $this->where($input);
        }else if(count($input) > 0){
            $query = null;
            $fields = $this->getFields(); // 待优化的自动过滤参数: 会增加一次查开销
            $operator = ['=','<>','>','>=','<','<=','like','not like','in','not in', 'between','not between','null','not null','exists','not exists','regexp','not regexp'];
            foreach ($input as $key => $value) {
                if(!isset($fields[$key])){
                    continue;
                }
                if(is_null($query)){
                    if(is_array($value)){
                        if(in_array(strtolower($value[0]), $operator)){
                            $query = $this->where($key, $value[0], $value[1]);
                        }else{
                            $query = $this->where($key, 'in', $value);
                        }
                    }else{
                        $query = $this->where($key, '=', $value);
                    }
                }else{
                    if(is_array($value)){
                        if(in_array(strtolower($value[0]), $operator)){
                            $query = $query->where($key, $value[0], $value[1]);
                        }else{
                            $query = $query->where($key, 'in', $value);
                        }
                    }else{
                        $query = $query->where($key, $value);
                    }
                }
            }
            return $query ?: $this;
        }
        return $this;
    }

}