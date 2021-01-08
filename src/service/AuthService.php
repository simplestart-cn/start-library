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

use start\extend\DataExtend;
use start\Service;

/**
 * 系统权限管理服务
 * Class AuthService
 * @package start\service
 */
class AuthService extends Service
{
    public $model = 'start\model\Auth';

    /**
     * 是否已经登录
     * @return boolean
     */
    public function isLogin()
    {
        return $this->getAdminId() > 0;
    }

    /**
     * 是否为超级用户
     * @return boolean
     */
    public function isSuper()
    {
        return $this->app->session->get('admin.is_super', false);
    }

    /**
     * 获取后台用户ID
     * @return integer
     */
    public function getAdminId()
    {
        return intval($this->app->session->get('admin.id', 0));
    }

    /**
     * 获取后台用户名称
     * @return string
     */
    public function getAdminName()
    {
        return $this->app->session->get('admin.name', '');
    }

    /**
     * 添加角色权限
     */
    public static function create($input)
    {
        if(isset($input['id']) && !empty($input['id'])){
            $model = self::getInfo($input['id']);
        }else{
            $model = self::model();
        }
        if($model->save($input)){
            $nodes = [];
            if(isset($input['nodes']) && !empty($input['nodes'])){
                foreach ($input['nodes'] as $value) {
                    $nodes[] = ['auth' => $model->id, 'node' => $value['node'], 'half' => $value['half']];
                }
            }
            NodeService::instance()->model()->where(['auth' => $model->id])->delete();
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
        if(isset($input['id']) && !empty($input['id'])){
            $model = self::getInfo($input['id']);
        }else{
            $model = self::model();
        }
        if($model->save($input)){
            $nodes = [];
            if(isset($input['nodes']) && !empty($input['nodes'])){
                foreach ($input['nodes'] as $value) {
                    $nodes[] = ['auth' => $model->id, 'node' => $value['node'], 'half' => $value['half']];
                }
            }
            NodeService::instance()->model()->where(['auth' => $model->id])->delete();
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
        if(is_string($filter) && strstr($filter, ',') !== false){
            $filter = explode(',',$filter);
        }
        $model = self::model();
        if(!is_array($filter)){
            NodeService::instance()->model()->where(['auth' => $filter])->delete();
            return $model->find($filter)->remove();
        }else{
            NodeService::instance()->model()->where('auth','in',$filter)->delete();
            return $model->where('id', 'in', $filter)->delete();
        }
    }

    /**
     * 获取详情
     * @param  array  $filter [description]
     * @return [type]         [description]
     */
    public static function getInfo($filter=[], $with=[])
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
        $model = self::model();
        if(is_string($auths) && strstr($auths, ',') !== false){
            $auths = explode(',',$auths);
        }
        return $model->nodes()->where('auth', 'in', $auths)->column('node');
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
        return DataExtend::arr2tree(array_reverse($nodes), 'node', 'pnode', 'child');
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
        if ($this->isSuper()) return true;
        $service = NodeService::instance();
        list($real, $nodes) = [$service->fullnode($node), $service->getMethods()];
        foreach ($nodes as $key => $rule) if (stripos($key, '_') !== false) {
            $nodes[str_replace('_', '', $key)] = $rule;
        }
        if (!empty($nodes[$real]['isauth']) || !empty($nodes[$real]['ismenu'])) {
            return in_array($real, $this->app->session->get('admin.nodes', []));
        } else {
            return !(!empty($nodes[$real]['islogin']) && !$this->isLogin());
        }
    }

    /**
     * 初始化用户权限
     * @param boolean $force 强刷权限
     * @return $this
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    // public function apply($force = false)
    // {
    //     if ($force) $this->clearCache();
    //     if (($uid = $this->app->session->get('admin.id'))) {
    //         $user = $this->app->db->name('AdminUser')->where(['id' => $uid])->find();
    //         if (($aids = $user['authorize'])) {
    //             $where = [['status', '=', '1'], ['id', 'in', explode(',', $aids)]];
    //             $subsql = $this->app->db->name('AdminAuth')->field('id')->where($where)->buildSql();
    //             $user['nodes'] = array_unique($this->app->db->name('AdminAuthNode')->whereRaw("auth in {$subsql}")->column('node'));
    //             $this->app->session->set('user', $user);
    //         } else {
    //             $user['nodes'] = [];
    //             $this->app->session->set('user', $user);
    //         }
    //     }
    //     return $this;
    // }

    /**
     * 清理节点缓存
     * @return $this
     */
    // public function clearCache()
    // {
    //     $this->app->cache->delete('admin_auth_node');
    //     return $this;
    // }

    

}