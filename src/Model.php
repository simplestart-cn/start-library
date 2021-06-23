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
use think\Container;
use think\Collection;
use think\facade\Cache;

/**
 * 自定义模型基类
 * Class Model
 * @package start
 */
class Model extends \think\Model
{
    /**
     * 是否Replace
     * @var bool
     */
    private $replace = false;

    /**
     * 关联
     * @var array
     */
    protected $with = [];

    /**
     * 查询
     * @var array
     */
    protected $where = [];

    /**
     * 排序
     */
    protected $order = ['create_time desc'];

    /**
     * 只读
     */
    protected $readonly = ['create_time','update_time'];

    /**
     * 架构函数
     * @access public
     * @param array $data 数据
     */
    public function __construct(array $data = [])
    {
        parent::__construct($data);
        // 固定name属性为模型名(解决TP关联查询alias别名问题)
        if(!empty($this->name)){
            if(empty($this->table)){
                $this->table = $this->name;
            }
            $name       = str_replace('\\', '/', static::class);
            $this->name = basename($name);
        }
        // 执行初始化操作
        $this->initialize();
    }

    // 模型初始化
    protected static function init()
    {
        self::instance()->initialize();
    }
    
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
    function list($filter = [], $order = [], $with = []) {
        $order = is_array($order) ? $order : [$order];
        return $this
            ->filter(array_merge($this->where, $filter))
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
    public function page($filter = [], $order = [], $with = [], $paging = [])
    {
        $order = is_array($order) ? $order : [$order];
        if (!is_array($paging)) {
            $paging = ['page' => (int) $paging];
        }
        if (!isset($paging['page'])) {
            $paging['page'] = input('page', 1, 'trim');
        }
        if (!isset($paging['per_page'])) {
            $paging['per_page'] = input('per_page', 20, 'trim');
        }
        return $this
            ->filter(array_merge($this->where, $filter))
            ->with(array_merge($this->with, $with))
            ->order(array_merge($this->order, $order))
            ->paginate($paging['per_page'], false, [
                'query' => array_merge(\request()->request(), $paging),
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

        if (!is_array($filter)) {
            return $this->with(array_merge($this->with, $with))->find($filter);
        } else {
            return $this->filter(array_merge($this->where, $filter))->with(array_merge($this->with, $with))->find();
        }
    }

    /**
     * 软删除
     */
    public function remove()
    {
        if (isset($this->is_deleted)) {
            return $this->save(['is_deleted' => 1]);
        } else {
            return $this->delete();
        }
    }

    /**
     * 条件查询，支持操作符查询及关联表查询
     * @param  array  $input [description]
     * @return [type]         [description]
     *
     * input 结构支持
     * $input = 1;
     * $input = [
     *     'key1' => 1,
     *     'key2' => [1,2,3],
     *     'key3' => ['!=', 1],
     *     'key4' => ['in', [1,2,3]],
     *     'with.key1' => [1,2,3],
     *     'with.key2' => ['like', "%$string%"]
     *     'key1|key2' => value,
     *     'with1.key1|with2.key2' => value,
     *     'with1.key1|key2' => 
     * ];
     */
    public function filter($input = [])
    {
        if (empty($input)) {
            return $this;
        }

        if (!is_array($input)) {
            return $this->where($input);
        } else if (count($input) > 0) {
            // 数据字典
            $table = $this->getTable();
            $tableFields = Cache::get($table.'_fields');
            if(empty($tableFields) || env('APP_DEBUG')){
                $tableFields = $this->getTableFields();
                Cache::set($table.'_fields', $tableFields);
            }
            $query     = null;  // 查询对象(Query)
            $withQuery = false; // 是否关联查询
            $withModel = [];    // 已关联模型
            foreach ($input as $key => $value) {
                // 参数过滤
                if(stripos($key, '|') === false && stripos($key, '.') === false && !in_array($key, $tableFields)){
                    unset($input[$key]);
                }
                // 关联查询
                if(stripos($key, '|') !== false && stripos($key, '.') !== false) {
                    // 关联 OR 查询
                    $withQuery = true;
                    $orQuery = explode('|', $key);
                    $relation = array();
                    foreach ($orQuery as $orField) {
                        if (stripos($orField, '.') !== false) {
                            list($model, $field) = explode('.', $orField);
                            !isset($relation[$model]) ? $relation[$model] = [] : '';
                            $relation[$model][$field]                     = $value;
                        }else{
                            !isset($relation['this']) ? $relation['this'] = [] : '';
                            $relation['this'][$orField]                   = $value;
                        }
                    }
                    foreach ($relation as $model => $condition) {
                        if($model === 'this'){
                            if (is_null($query)) {
                                $query = $this->parseFilter($this, $condition, $this->getName(), "OR");
                            } else {
                                $query = $this->parseFilter($query, $condition, $this->getName(), "OR");
                            }
                        }else{
                            $relateTable = $this->$model()->getName();
                            if (is_null($query)) {
                                if(in_array($model, $withModel)){
                                    $query = $this->parseFilter($this, $condition, $relateTable, 'OR');
                                }else{
                                    array_push($withModel, $model);
                                    $query = $this->hasWhere($model, $this->parseFilter($this, $condition, $relateTable, 'OR'));
                                }
                            } else {
                                if(in_array($model, $withModel)){
                                    $query = $this->parseFilter($query, $condition, $relateTable, 'OR');
                                }else{
                                    array_push($withModel, $model);
                                    $query = $query->hasWhere($model, $this->parseFilter($query, $condition, $relateTable, 'OR'));
                                }
                            }
                        }
                    }
                    unset($input[$key]);
                }else if (stripos($key, '.') !== false) {
                    // 关联 AND 查询
                    $withQuery = true;
                    list($model, $field) = explode('.', $key);
                    $relateTable = $this->$model()->getName();
                    if (is_null($query)) {
                        if(in_array($model, $withModel)){
                            $this->parseFilter($this, [$field => $value], $relateTable);
                        }else{
                            array_push($withModel, $model);
                            $query = $this->hasWhere($model, $this->parseFilter($this, [$field => $value], $relateTable));
                        }
                    } else {
                        if(in_array($model, $withModel)){
                            $this->parseFilter($query, [$field => $value], $relateTable);
                        }else{
                            array_push($withModel, $model);
                            $query = $query->hasWhere($model, $this->parseFilter($query, [$field => $value], $relateTable));
                        }
                    }
                    unset($input[$key]);
                }
            }
            if($withQuery){
                $query = $query->alias($this->getName());
            }
            // 单表查询
            if (is_null($query)) {
                $query = $this->parseFilter($this, $input, $withQuery ? $this->getName() : '');
            } else {
                $query = $this->parseFilter($query, $input, $withQuery ? $this->getName() : '');
            }
            return $query ?: $this;
        }
    }

    /**
     * 解析查询语句，支持操作符查询及关联表查询
     * @param  [type] $query     [description]
     * @param  array  $condition [description]
     * @param  string $table     [description]
     *  @param string $logic     查询逻辑 AND OR
     * @return [type]            [description]
     */
    private function parseFilter($query, array $condition = [], $table = '', $logic = 'AND')
    {
        
        $operator = ['=', '<>', '>', '>=', '<', '<=', 'like', 'not like', 'in', 'not in', 'between', 'not between', 'null', 'not null', 'exists', 'not exists', 'regexp', 'not regexp'];
        if (!empty($table) && stripos($table, '.') === false) {
            $table .= '.';
        }
        foreach ($condition as $key => $value) {
            if (empty($value) && !is_numeric($value)) {
                continue;
            }
            if (is_array($value)) {
                if (count($value) > 1 && in_array(strtolower($value[0]), $operator)) {
                    $query = $logic === 'AND' ? $query->where($table . $key, $value[0], $value[1]) : $query->whereOr($table . $key, $value[0], $value[1]);
                } else {
                    $query = $logic === 'AND' ? $query->where($table . $key, 'in', $value) : $query->whereOr($table . $key, 'in', $value);
                }
            } else {
                $query = $logic === 'AND' ? $query->where($table . $key, $value) : $query->whereOr($table . $key, $value);
            }
        }
        return $query;
    }

    /**
     * 保存多个数据到当前数据对象
     * ##修复ThinkPHP6.0.7 开启全局查询后saveAll无法自动识别新增或更新的问题##
     * @access public
     * @param iterable $dataSet 数据
     * @param boolean  $replace 是否自动识别更新和写入
     * @return Collection
     * @throws \Exception
     */
    public function saveAll(iterable $dataSet, bool $replace = true): Collection
    {
        $db = $this->db();

        $result = $db->transaction(function () use ($replace, $dataSet) {

            $pk = $this->getPk();

            if (is_string($pk) && $replace) {
                $auto = true;
            }

            $result = [];

            $suffix = $this->getSuffix();

            foreach ($dataSet as $key => $data) {
                if ($this->exists || (!empty($auto) && isset($data[$pk]))) {
                    $model = self::where($pk, $data[$pk])->find();
                    if (!empty($suffix)) {
                        $model->setSuffix($suffix)->save($data);
                        $result[$key] = $model;
                    }else{
                        $model->save($data);
                        $result[$key] = $model;
                    }
                } else {
                    $result[$key] = static::create($data, $this->field, $this->replace, $suffix);
                }
            }

            return $result;
        });

        return $this->toCollection($result);
    }
}