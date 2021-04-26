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
use start\extend\DataExtend;
use think\App;
use think\facade\Cache;
use think\facade\Session;

/**
 * 系统权限管理服务
 * Class AuthService
 * @package start
 */
class AuthService extends Service
{
    public $model = 'start\model\Auth';

    public $user = false;

    /**
     * SESSION及Token两种方式保持登录态
     * @param App $app
     */
    public function __construct(App $app)
    {
        parent::__construct($app);
        $token      = \request()->header('user-token', '');
        $this->user = $app->session->get('user', false);
        if (!$this->user) {
            $this->user = Cache::get($token, false);
        }
    }

    /**
     * 是否已经登录
     * @return boolean
     */
    public function isLogin()
    {
        return !!$this->user;
    }

    /**
     * 是否管理员
     * @return boolean
     */
    public function isAdmin()
    {
        return $this->user && $this->user['is_admin'];
    }

    /**
     * 是否为超级账户
     * @return boolean
     */
    public function isOwner()
    {
        return $this->user && $this->user['is_owner'];
    }

    /**
     * 获取登录账户
     * @return [type] [description]
     */
    public function getUser($force = true)
    {
        if($force && !$this->user){
            throw_error(lang('not_login'), '', -1);
        }
        return $this->user;
    }

    /**
     * 获取账户ID
     * @return integer
     */
    public function getUserId($force = true)
    {
        if($force && !$this->user){
            throw_error(lang('not_login'), '', -1);
        }
        return $this->user ? $this->user['id'] : 0;
    }

    /**
     * 获取账户名称
     * @return string
     */
    public function getUserName($force = true)
    {
        if($force && !$this->user){
            throw_error(lang('not_login'), '', -1);
        }
        return $this->user ? $this->user['name'] : '';
    }

    /**
     * 获取可选权限
     * @param  array  $filter  [description]
     * @param  array  $order   [description]
     * @return [type]          [description]
     */
    public static function getList($filter = [], $order = ['sort asc', 'id asc'])
    {
        $self = self::instance();
        if ($self->isOwner()) {
            return $self->model->list($filter, $order);
        } else {
            $user = get_user();
            $filter['name'] = ['in', $user['authorize']];
            return $self->model->list($filter, $order);
        }
    }

    /**
     * 获取可选权限(分页)
     * @param  array  $filter  [description]
     * @param  array  $order   [description]
     * @return [type]          [description]
     */
    public static function getPage($filter = [], $order = ['sort asc', 'id asc'])
    {
        $self = self::instance();
        if ($self->isOwner()) {
            return $self->model->page($filter, $order);
        } else {
            $user = get_user();
            $filter['name'] = ['in', $user['authorize']];
            return  $self->model->page($filter, $order);
        }
    }

    /**
     * 添加角色权限
     */
    public static function create($input)
    {
        $model = self::model();
        if ($model->save($input)) {
            $nodes = [];
            if (isset($input['nodes']) && !empty($input['nodes'])) {
                foreach ($input['nodes'] as $value) {
                    $nodes[] = [
                        'auth' => $model->name,
                        'node' => $value['node'],
                        'half' => isset($value['half']) ? $value['half'] : 0,
                    ];
                }
            }
            NodeService::instance()->model()->insertAll($nodes);
            return $model;
        }
        return false;
    }

    /**
     * 更新角色权限
     */
    public static function update($input)
    {
        if (isset($input['id']) && !empty($input['id'])) {
            $model = self::getInfo($input['id']);
        } else {
            $model = self::model();
        }
        if ($model->save($input)) {
            $nodes = [];
            if (isset($input['nodes']) && !empty($input['nodes'])) {
                foreach ($input['nodes'] as $value) {
                    $nodes[] = [
                        'auth' => $model->name,
                        'node' => $value['node'],
                        'half' => isset($value['half']) ? $value['half'] : 0,
                    ];
                }
            }
            NodeService::instance()->model()->where(['auth' => $model->name])->delete();
            NodeService::instance()->model()->insertAll($nodes);
            return $model;
        }
        return false;
    }

    /**
     * 删除权限
     * @param  [type] $filter [description]
     * @return [type]     [description]
     */
    public static function remove($filter)
    {
        if (is_string($filter) && strstr($filter, ',') !== false) {
            $filter = explode(',', $filter);
        }
        $model = self::model();
        if (!is_array($filter)) {
            NodeService::instance()->model()->where(['auth' => $filter])->delete();
            return $model->find($filter)->remove();
        } else {
            NodeService::instance()->model()->where('auth', 'in', $filter)->delete();
            return $model->where('id', 'in', $filter)->delete();
        }
    }

    /**
     * 重置权限组
     * @return [type] [description]
     */
    public static function restore()
    {
        $user = get_user();
        if(!$user['is_owner']){
            throw_error(lang('not_auth'));
        }
        $tree  = DataExtend::arr2tree(MenuService::model()->list(['status' => 1])->toArray());
        $auth = self::combineAuth($tree);
        self::startTrans();
        try {
            // 清理数据
            AuthService::model()->where('id', '>', 0)->delete(true);
            NodeService::model()->where('id', '>', 0)->delete(true);
            // 更新数据
            self::saveBuilding($auth);
            self::startCommit();
            return true;
        } catch (\HttpResponseException $e) {
            self::startRollback();
            throw_error($e->getMessage());
            return false;
        }
    }

    /**
     * 更新权限信息
     * @param  [type] $auth [description]
     * @return [type]        [description]
     */
    private static function saveBuilding($auth = [], $pid = 0)
    {
        foreach ($auth as $data) {
            $data['pid']       = $pid;
            $model = self::create($data);
            if($model && !empty($data['children'])){
                self::saveBuilding($data['children'], $model->id);
            }
        }
        return true;
    }

    /**
     * 构建权限
     * @param  [type] $item [description]
     * @return [type]       [description]
     */
    private static function combineAuth($tree)
    {
        $auth = array();
        foreach ($tree as $item) {
            if(!empty($item['children'])){
                $auth[] = [
                'name'  => $item['name'],
                'title' => $item['title'],
                'sort'  => $item['sort'],
                'nodes' => self::combineNodes($item),
                'children' => self::combineAuth($item['children'])
                ];
            }
        }
        return $auth;
    }

    /**
     * 构建权限节点
     * @param  [type] $item [description]
     * @return [type]       [description]
     */
    private static function combineNodes($item)
    {
        $nodes = array();
        if (isset($item['node'])) {
            $nodes[] = ['node' => $item['node']];
        }
        if (isset($item['children']) && !empty($item['children'])) {
            foreach ($item['children'] as $child) {
                $nodes = array_merge($nodes, self::combineNodes($child));
            }
        }
        return $nodes;
    }

    /**
     * 获取授权节点树
     * @param array $checkeds
     * @return array
     * @throws \ReflectionException
     */
    public function getTree($checkeds = [])
    {
        $nodes = NodeService::instance()->getAll();
        foreach ($nodes as $item) {
            $item['checked'] = in_array($item['node'], $checkeds);
        }
        return DataExtend::arr2tree(array_reverse($nodes), 'node', 'parent', 'child');
    }

    /**
     * 获取详情
     * @param  array  $filter [description]
     * @return [type]         [description]
     */
    public static function getInfo($filter = [], $with = [])
    {
        $model = self::model();
        return $model->info($filter, ['nodes']);
    }
    
    /**
     * 获取授权节点
     * @param  array  $auths [description]
     * @return [type]        [description]
     */
    public static function getNodes($auths = [])
    {
        $self = self::instance();
        if($self->isOwner()){
            return $self->model->nodes()->column('node');
        }
        if (is_string($auths) && strstr($auths, ',') !== false) {
            $auths = explode(',', $auths);
        }
        return $self->model->nodes()->where('auth', 'in', $auths)->column('node');
    }

    /**
     * 检查指定节点授权
     * --- 需要读取缓存或扫描所有节点
     * @param string $node
     * @return boolean
     * @throws \ReflectionException
     */
    public function check($node = '')
    {
        if ($this->isOwner()) {
            return true;
        }
        $service            = NodeService::instance();
        list($real, $nodes) = [$service->fullnode($node), $service->getMethods()];
        foreach ($nodes as $key => $rule) {
            if (stripos($key, '_') !== false) {
                $nodes[str_replace('_', '', $key)] = $rule;
            }
        }
        if (!empty($nodes[$real]['isauth']) || !empty($nodes[$real]['ismenu'])) {
            return in_array($real, $this->app->session->get('user.nodes', []));
        } else {
            return !(!empty($nodes[$real]['islogin']) && !$this->isLogin());
        }
    }

}
