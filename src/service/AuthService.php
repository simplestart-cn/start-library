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
        return $this->getUserId() > 0;
    }

    /**
     * 是否为超级用户
     * @return boolean
     */
    public function isSuper()
    {
        return $this->app->session->get('user.is_super', false);
    }

    /**
     * 获取后台用户ID
     * @return integer
     */
    public function getUserId()
    {
        return intval($this->app->session->get('user.id', 0));
    }

    /**
     * 获取后台用户名称
     * @return string
     */
    public function getUserName()
    {
        return $this->app->session->get('user.username', '');
    }

    /**
     * 添加角色权限
     */
    public static function save($input, $field = [])
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
     * 删除角色
     * @param  [type] $id [description]
     * @return [type]     [description]
     */
    public static function remove($id)
    {
        if(strstr($id, ',') !== false){
            $id = ['id','in',explode(',',$id)];
        }
        $model = self::model();
        if(!is_array($id)){
            NodeService::instance()->model()->where(['auth' => $id])->delete();
            return $model->find($id)->remove();
        }else{
            NodeService::instance()->model()->where('auth','in',$id)->delete();
            return $model->where($id)->find()->remove();
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
            return in_array($real, $this->app->session->get('user.nodes', []));
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
    public function apply($force = false)
    {
        if ($force) $this->clearCache();
        if (($uid = $this->app->session->get('user.id'))) {
            $user = $this->app->db->name('AdminUser')->where(['id' => $uid])->find();
            if (($aids = $user['authorize'])) {
                $where = [['status', '=', '1'], ['id', 'in', explode(',', $aids)]];
                $subsql = $this->app->db->name('AdminAuth')->field('id')->where($where)->buildSql();
                $user['nodes'] = array_unique($this->app->db->name('AdminAuthNode')->whereRaw("auth in {$subsql}")->column('node'));
                $this->app->session->set('user', $user);
            } else {
                $user['nodes'] = [];
                $this->app->session->set('user', $user);
            }
        }
        return $this;
    }

    /**
     * 清理节点缓存
     * @return $this
     */
    public function clearCache()
    {
        $this->app->cache->delete('admin_auth_node');
        return $this;
    }

    

}