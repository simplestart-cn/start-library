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
use start\AppService;
use start\extend\DataExtend;

/**
 * 系统权限服务
 * Class AuthService
 * @package app\service
 */
class AuthService extends Service
{

    public $model = 'start\model\Auth';
    
    /**
     * 构建权限
     * 并保留后台可编辑字段
     * @return [type] [description]
     */
    public function building($app = '')
    {
        // 注解权限
        $nodes    = NodeService::instance()->getAll($app, true);
        $authNode = array();
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
            $temp['is_super']  = $item['issuper'];
            $temp['is_admin']  = $item['isadmin'];
            $temp['is_open']   = $item['isopen'];
            $temp['parent']    = $item['ismenu']['parent'] ?? $item['parent'];
            $temp['path']      = '/' . str_replace('_', '/', $item['node']);
            $temp['is_menu']   = isset($item['ismenu']['is_menu']) ? (boolean)$item['ismenu']['is_menu']: (boolean) $item['ismenu'];
            $temp['template']  = isset($item['ismenu']['template']) ? $item['ismenu']['template'] : ((boolean) $item['isview'] ? str_replace('_', '/', $item['node']) : '');
            $temp['redirect']  = $item['ismenu']['redirect'] ?? '';
            $temp['hidden']    = false;
            $temp['no_cache']  = false;
            $authNode[$item['node']] = $temp;
        }
        // 拓展权限
        $appInfo = AppService::getPackInfo($app);
        if ($appInfo) {
            $authNode[$app]['icon']  = $appInfo['icon'] ?? '';
            $authNode[$app]['title'] = $appInfo['title'] ?? $appInfo['name'];
            if (isset($appInfo['menu'])) {
                foreach ($appInfo['menu'] as &$extend) {$extend['app'] = $app;}
                $menuExtend = array_combine(array_column($appInfo['menu'], 'node'), array_values($appInfo['menu']));
                $authNode   = array_merge($authNode, $menuExtend);
            }
        }
        // 权限权限
        $dbNodes   = $this->model->select()->toArray();
        $dbNodes   = array_combine(array_column($dbNodes, 'node'), array_values($dbNodes));
        $dbKeys  = array_combine(array_column($dbNodes, 'id'), array_values($dbNodes));
        foreach ($authNode as &$menu) {
            if(isset($dbNodes[$menu['node']])){
                $menu['id'] = $dbNodes[$menu['node']]['id'];
            }
            if(!empty($menu['parent']) && isset($dbNodes[$menu['parent']])){
                $parent         = $dbNodes[$menu['parent']];
                $menu['pid']    = $parent['id'];
                $menu['parent'] = $parent['node'];
                if(!isset($authNode[$parent['node']])){
                    if($parent['pid'] && isset($dbKeys[$parent['pid']])){
                        $parent['parent'] = $dbKeys[$parent['pid']]['node'];
                        unset($parent['create_time']);
                        unset($parent['update_time']);
                    }
                    $authNode[$parent['node']] = $parent;
                }
            }
        }
        // 保存权限
        $tree = DataExtend::arr2tree($authNode, 'node', 'parent', 'children');
        $auths = $this->saveBuilding($tree, 0);
        return $auths;
    }

    /**
     * 更新权限信息
     * @param  [type] $nodes [description]
     * @return [type]        [description]
     */
    private function saveBuilding($nodes = [], $pid = 0)
    {
        $auths = array();
        foreach ($nodes as $key => &$data) {
            if ($pid === 0 && empty($data['children'])) {
                continue;
            }
            $temp        = $data;
            $temp['pid'] = $pid;
            unset($temp['parent']);
            unset($temp['children']);
            if(isset($temp['create_time'])){
                unset($temp['create_time']);
            }
            if(isset($temp['update_time'])){
                unset($temp['update_time']);
            }
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
            $auths[] = $temp;
        }
        return $auths;
    }

}
