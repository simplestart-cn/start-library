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
 * 系统菜单管理服务
 * Class MenuService
 * @package app\service
 */
class MenuService extends Service
{

    public $model = 'start\model\Menu';

    /**
     * 获取可选菜单节点
     * @param  array  $filter  [description]
     * @param  array  $with    [description]
     * @param  array  $order   [description]
     * @param  [type] $calback [description]
     * @return [type]          [description]
     */
    public static function getList($filter = [], $order = ['sort desc', 'id asc'])
    {
        $self = self::instance();
        if (AuthService::instance()->isOwner()) {
            return $self->model->list($filter, $order);
        } else {
            $nodes = $self->app->session->get('user.nodes', []);
            return $self->model->filter($filter)->where('node', 'in', $nodes)->order($order)->select();
        }
    }

    /**
     * 获取菜单树数据
     * @return [type] [description]
     */
    public static function getTree()
    {
        $self  = self::instance();
        $menus = self::getList(['status' => 1]);
        $menus = DataExtend::arr2tree($menus->toArray());
        if (count($menus) == 1 && isset($menus[0]['children'])) {
            $menus = $menus[0]['children'];
        }
        return $self->formatData($menus);
    }

    /**
     * 获取菜应用菜单
     * @return [type] [description]
     */
    public static function getAppMenu()
    {
        $self   = self::instance();
        $data   = self::getList(['status' => 1]);
        $data   = DataExtend::arr2tree($data->toArray());
        $apps   = $self->formatData($data);
        $access = array();
        foreach ($apps as $item) {
            if (isset($item['children'])) {
                $access = array_merge($access, $item['children']);
            }
        }
        return compact('apps', 'access');
    }

    /**
     * 菜单格式化
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    final private function formatData($menus)
    {
        $routers = [];
        foreach ($menus as $key => $data) {
            $temp              = [];
            $temp['name']      = $data['name'];
            $temp['path']      = $data['path'];
            $temp['component'] = $data['component'] ?: 'layout';
            $temp['node']      = $data['node'];
            if ($data['hidden'] > -1) {
                $temp['hidden'] = (boolean) $data['hidden'];
            }
            if ($data['is_menu'] > -1) {
                $temp['is_menu'] = (boolean) $data['is_menu'];
            }
            if ($data['no_cache'] > -1) {
                $temp['meta']['noCache'] = (boolean) $data['no_cache'];
            }
            if ($data['redirect']) {
                $temp['redirect'] = $data['redirect'];
            }
            // 路由参数拼接
            if (!empty($data['params'])) {
                $temp['path'] .= $data['params'];
            }
            $temp['meta']['title'] = $data['title'];
            $temp['meta']['icon']  = $data['icon'];
            // 递归
            if (isset($data['children']) && count($data['children']) > 0) {
                foreach ($data['children'] as $c) {
                    $temp['children'] = $this->formatData($data['children']);
                }
            }
            $routers[] = $temp;
        }
        return $routers;
    }

    public static function save($input, $field = [])
    {
        if (isset($input['id']) && !empty($input['id'])) {
            $model = self::getInfo($input['id']);
            $list  = self::getList();
            $ids   = DataExtend::getArrSubIds($list, $input['id']);
            if (count($ids) > 0 && isset($input['status'])) {
                self::saveChildren($ids, ['status' => $input['status']]);
            }
        } else {
            $model = self::model();
        }
        return $model->allowField($field)->save($input);
    }

    /**
     * 更新子菜单信息
     * @param  [type] $pid   [description]
     * @param  [type] $input [description]
     * @return [type]        [description]
     */
    protected static function saveChildren($ids, $input, $field = [])
    {
        foreach ($ids as $id) {
            $item = ['id' => $id];
            foreach ($input as $key => $value) {
                if ($key !== 'id') {
                    if (count($field) > 0) {
                        in_array($key, $field) && $item[$key] = $value;
                    } else {
                        $item[$key] = $value;
                    }

                }
            }
            $data[] = $item;
        }
        return self::model()->saveAll($data);
    }
    
    /**
     * 构建菜单
     * 并保留后台可编辑字段
     * @return [type] [description]
     */
    public function building($app = '')
    {
        $nodes     = NodeService::instance()->getAll($app, true);
        $dbNodes = $this->model->select()->toArray();
        $dbKeys = array_column($dbNodes, 'id');
        foreach ($nodes as &$item) {
            $item['app'] = $app;
            foreach ($dbNodes as $last) {
                // 保留可能编辑过的字段
                if ($last['node'] == $item['node']) {
                    $item['id']        = $last['id'];
                    $item['pid']       = $last['pid'];
                    $item['icon']      = $last['icon'];
                    $item['sort']      = $last['sort'];
                    $item['hidden']    = $last['hidden'];
                    $item['status']    = $last['status'];
                    $item['params']    = $last['params'];
                    $item['redirect']  = $last['redirect'];
                    $item['no_cache']  = $last['no_cache'];
                    $item['component'] = $last['component'];
                    $item['condition'] = $last['condition'];
                    // 保留编辑过的上下级关系
                    if ($item['pid'] > 0) {
                        $pkey = array_search($last['pid'], $dbKeys);
                        $parent = $dbNodes[$pkey];
                        $item['pnode'] = $parent['node'];
                        if(!in_array($parent['node'], array_column($nodes, 'node'))){
                            $ppkey = array_search($parent['pid'], $dbKeys);
                            $parent['pnode'] = $ppkey > -1 ? $dbNodes[$ppkey]['node'] : '';
                            $parent['ismenu'] = $ppkey > -1 ? $dbNodes[$ppkey]['is_menu'] : false;
                            array_push($nodes, $parent);
                        }
                    }
                }
            }
        }
        $menus = $this->saveBuilding(DataExtend::arr2tree($nodes, 'node', 'pnode', 'children'), 0);
        return count($menus);
    }

    /**
     * 更新菜单信息
     * @param  [type] $nodes [description]
     * @return [type]        [description]
     */
    private function saveBuilding($nodes = [], $pid = 0)
    {
        $menus = array();
        foreach ($nodes as $key => &$data) {
            $temp              = [];
            $temp['pid']       = $pid;
            $temp['app']       = $data['app'];
            $temp['title']     = $data['title'];
            $temp['name']      = str_replace('/', '_', $data['node']);
            $temp['node']      = $data['node'];
            $temp['path']      = '/' . $data['node'];
            $temp['is_menu']   = (boolean) $data['ismenu'];
            $temp['sort']      = isset($data['sort']) ? $data['sort'] : 100;
            $temp['hidden']    = isset($data['hidden']) ? $data['hidden'] : false;
            $temp['status']    = isset($data['status']) ? $data['status'] : true;
            $temp['component'] = isset($data['component']) ? $data['component'] : '';
            $temp['redirect']  = isset($data['redirect']) ? $data['redirect'] : '';
            $temp['icon']      = isset($data['icon']) ? $data['icon'] : '';
            $temp['no_cache']  = isset($data['no_cache']) ? (boolean) $data['no_cache'] : false;
            if (isset($data['id'])) {
                $model = $this->model->find($data['id']);
                $model->where(['id' => $data['id']])->save($temp);
            } else {
                $model = new $this->model;
                $model->save($temp);
            }
            if ($model->id && isset($data['children']) && count($data['children']) > 0) {
                $temp['children'] = $this->saveBuilding($data['children'], $model->id);
            }
            $menus[] = $temp;
        }
        return $menus;
    }

}
