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
        $data = self::getList($filter);
        $data = DataExtend::arr2tree($data->toArray());
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
        $nodes    = NodeService::instance()->getAll($app, true);
        $dbNodes  = $this->model->select()->toArray();
        $nodeIds  = array_column($dbNodes, 'id');
        $nodeKeys = array_column($dbNodes, 'node');
        $nodeList = array();
        foreach ($nodes as &$item) {
            // 格式化
            $temp['app']    = $app;
            $temp['name']   = str_replace('/', '_', $item['node']);
            $temp['icon']   = $item['ismenu']['icon'] ?? '';
            $temp['sort']   = $item['ismenu']['sort'] ?? 100;
            $temp['title']  = $item['ismenu']['title'] ?? $item['title'];
            $temp['status'] = $item['ismenu']['status'] ?? true;
            $temp['params'] = $item['ismenu']['params'] ?? '';
            $temp['node']   = $item['node'];
            $temp['pnode']  = $item['ismenu']['pnode'] ?? $item['pnode'];
            $temp['path']      = '/' . $item['node'];
            $temp['is_menu']   = (boolean)$item['ismenu'];
            $temp['component'] = isset($item['isview']) ? $item['node'] : '';
            $temp['redirect']  = '';
            $temp['hidden']    = false;
            $temp['no_cache']  = false;

            foreach ($dbNodes as $last) {
                // 保留可能编辑过的字段
                if ($last['node'] == $item['node']) {
                    $temp['id']        = $last['id'];
                    $temp['pid']       = $last['pid'];
                    $temp['icon']      = $last['icon'] ?? $temp['icon'];
                    $temp['sort']      = $last['sort'] == 100 ? $last['sort'] : $temp['sort'];
                    $temp['params']    = $last['params'] ?? $temp['params'];
                    $temp['hidden']    = $last['hidden'];
                    $temp['redirect']  = $last['redirect'];
                    $temp['no_cache']  = $last['no_cache'];
                    $temp['component'] = $last['component'] ?? $temp['component'];
                    $temp['condition'] = $last['condition'];
                }
                // 尝试寻找上级
                if (!empty($temp['pnode'])) {
                    $pkey = array_search($temp['pnode'], $nodeKeys);
                    if ($pkey > -1) {
                        $parent        = $dbNodes[$pkey];
                        $temp['pid']   = $parent['id'];
                        $temp['pnode'] = $parent['node'];
                        // 引入上级
                        if (!in_array($parent['node'], array_column($nodes, 'node'))) {
                            $ppkey            = array_search($parent['pid'], $nodeIds);
                            $parent['pnode']  = $ppkey > -1 ? $dbNodes[$ppkey]['node'] : '';
                            $parent['ismenu'] = $ppkey > -1 ? $dbNodes[$ppkey]['is_menu'] : false;
                            array_push($nodes, $parent);
                            array_push($nodeList, $parent);
                        }
                    }
                }
            }
            array_push($nodeList, $temp);
        }
        // 设置app信息
        $tree = DataExtend::arr2tree($nodeList, 'node', 'pnode', 'children');
        if (count($tree) && $app = AppService::getPackInfo($app)) {
            $tree[0]['icon']  = $app['icon'] ?? '';
            $tree[0]['title'] = $app['title'] ?? $app['name'];
        }
        $menus = $this->saveBuilding($tree, 0);
        return $menus;
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
            $temp              = $data;
            $temp['pid']       = $pid;
            unset($temp['pnode']);
            unset($temp['children']);

            // $temp['app']       = $data['app'];
            // $temp['title']     = $data['title'];
            // $temp['name']      = str_replace('/', '_', $data['node']);
            // $temp['node']      = $data['node'];
            // $temp['path']      = '/' . $data['node'];
            // $temp['is_menu']   = (boolean) $data['ismenu'];
            // $temp['sort']      = isset($data['sort']) ? $data['sort'] : 100;
            // $temp['hidden']    = isset($data['hidden']) ? $data['hidden'] : false;
            // $temp['status']    = isset($data['status']) ? $data['status'] : true;
            // $temp['component'] = isset($data['component']) && !empty($data['component']) ? $data['component'] : $data['isview'] ? $data['node'] : '';
            // $temp['redirect']  = isset($data['redirect']) ? $data['redirect'] : '';
            // $temp['icon']      = isset($data['icon']) ? $data['icon'] : '';
            // $temp['no_cache']  = isset($data['no_cache']) ? (boolean) $data['no_cache'] : false;

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
