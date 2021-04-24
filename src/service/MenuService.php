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
            return $self->model->filter($filter)->order($order)->select();
        } else {
            $user = get_user();
            return $self->model->filter($filter)->where('node', 'in', $user['nodes'])->order($order)->select();
        }
    }

    /**
     * 获取菜单树数据
     * @return [type] [description]
     */
    public static function getTree($filter = ['status' => 1])
    {
        $self  = self::instance();
        $menus = self::getList($filter);
        $menus = DataExtend::arr2tree($menus->toArray());
        if (count($menus) == 1 && isset($menus[0]['children'])) {
            $menus = $menus[0]['children'];
        }
        return $self->formatData($menus);
    }

    /**
     * 获取应用菜单
     * @param  string $app [应用名]
     * @return [type]      [菜单树]
     */
    public static function getAppMenu($app = '')
    {
        $self             = self::instance();
        $filter['status'] = 1;
        if (empty($app)) {
            $apps          = array_merge(AppService::getActive(), ['core']);
            $filter['app'] = ['in', $apps];
        } else {
            $filter['app'] = $app;
        }
        $data = self::getList($filter)->toArray();
        // 补充上级菜单
        $ids = array_column($data, 'id');
        foreach ($data as $item) {
            if($item['pid'] && !in_array($item['pid'], $ids)){
                if($parent = self::model()->find($item['pid'])->toArray()){
                    array_push($ids, $parent['id']);
                    array_push($data, $parent);
                }
            }
        }
        $data = DataExtend::arr2tree($data);
        return $self->formatData($data);
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

    /**
     * 更新自身及下级菜单
     * @param  [type] $input [description]
     * @return [type]        [description]
     */
    public static function update($input)
    {
        if (isset($input['node']) && empty($input['path'])) {
            $input['path'] = $input['node'];
        }
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
        return $model->save($input);
    }

    /**
     * 删除自身及下级菜单
     * @param  [type] $input [description]
     * @return [type]        [description]
     */
    public static function remove($filter)
    {
        if (is_string($filter) && strstr($filter, ',') !== false) {
            $filter = explode(',', $filter);
        }
        $model = self::model();

        self::startTrans();
        try {
            if (!is_array($filter)) {
                // 删除子菜单
                $subIds = self::model()->where('pid', '=', $filter)->column('id');
                if (count($subIds)) {
                    self::remove($subIds);
                }
                // 删除当前记录
                $model->find($filter)->delete();
            } else {
                // 删除子菜单
                $subIds = self::model()->where('pid', 'in', $filter)->column('id');
                if (count($subIds)) {
                    self::remove($subIds);
                }
                // 删除当前记录
                $model->where($model->getPk(), 'in', $filter)->delete();
            }
            self::startCommit();
            return true;
        } catch (\Exception $e) {
            self::startRollback();
            throw_error($e->getMessage());
        }
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
        // 注解菜单
        $nodes    = NodeService::instance()->getAll($app, true);
        $nodeMenu = array();
        foreach ($nodes as $item) {
            $temp              = array();
            $temp['app']       = empty($item['app']) ? $app : $item['app'];
            $temp['name']      = str_replace('/', '_', $item['node']);
            $temp['icon']      = $item['ismenu']['icon'] ?? '';
            $temp['sort']      = $item['ismenu']['sort'] ?? 100;
            $temp['title']     = $item['ismenu']['title'] ?? $item['title'];
            $temp['status']    = $item['ismenu']['status'] ?? true;
            $temp['params']    = $item['ismenu']['params'] ?? '';
            $temp['node']      = $item['node'];
            $temp['parent']    = $item['ismenu']['parent'] ?? $item['parent'];
            $temp['path']      = '/' . $item['node'];
            $temp['is_menu']   = (boolean) $item['ismenu'];
            $temp['component'] = (boolean) $item['isview'] ? $item['node'] : '';
            $temp['redirect']  = '';
            $temp['hidden']    = false;
            $temp['no_cache']  = false;
            $nodeMenu[$item['node']] = $temp;
        }
        // 拓展菜单
        $appInfo = AppService::getPackInfo($app);
        if ($appInfo) {
            $nodeMenu[$app]['icon']  = $appInfo['icon'] ?? '';
            $nodeMenu[$app]['title'] = $appInfo['title'] ?? $appInfo['name'];
            if (isset($appInfo['menu'])) {
                foreach ($appInfo['menu'] as &$extend) {$extend['app'] = $app;}
                $menuExtend = array_combine(array_column($appInfo['menu'], 'node'), array_values($appInfo['menu']));
                $nodeMenu   = array_merge($nodeMenu, $menuExtend);
            }
        }
        // 权限菜单
        $dbNodes   = $this->model->select()->toArray();
        $dbNodes   = array_combine(array_column($dbNodes, 'node'), array_values($dbNodes));
        $dbKeys  = array_combine(array_column($dbNodes, 'id'), array_values($dbNodes));
        foreach ($nodeMenu as &$menu) {
            if(!empty($menu['parent']) && isset($dbNodes[$menu['parent']])){
                $parent         = $dbNodes[$menu['parent']];
                $menu['pid']    = $parent['id'];
                $menu['parent'] = $parent['node'];
                if(!isset($nodeMenu[$parent['node']])){
                    if($parent['pid'] && isset($dbKeys[$parent['pid']])){
                        $parent['parent'] = $dbKeys[$parent['pid']]['node'];
                        unset($parent['create_time']);
                        unset($parent['update_time']);
                    }
                    $nodeMenu[$parent['node']] = $parent;
                }
            }
        }
        // 保存菜单
        $tree = DataExtend::arr2tree($nodeMenu, 'node', 'parent', 'children');
        $menus = $this->saveBuilding($tree, 0);
        return $menus;
    }
    private static function combineMenu($item, $parent)
    {

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
            if ($pid === 0 && empty($data['children'])) {
                continue;
            }
            $temp        = $data;
            $temp['pid'] = $pid;
            unset($temp['parent']);
            unset($temp['children']);
            if (isset($temp['id'])) {
                $model = $this->model->find($temp['id']);
                $model->where(['id' => $temp['id']])->save($temp);
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
