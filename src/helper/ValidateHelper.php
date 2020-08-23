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

namespace start\helper;

use start\Helper;
use think\Validate;

/**
 * 快捷输入验证器
 * Class ValidateHelper
 * @package start\helper
 */
class ValidateHelper extends Helper
{
    /**
     * 快捷输入并验证（ 支持 规则 # 别名 ）
     * @param array $rules 验证规则（ 验证信息数组 ）
     * @param string $type 输入方式 ( post. 或 get. )
     * @return array
     *  验证器示例
     *  name.require => message
     *  age.max:100 => message
     *  name.between:1,120 => message
     *  自定义规则
     *  name.value => value // 设置当前值
     *  name.default => 100 // 获取并设置默认值
     *  内置规则
     *  'require'     => ':attribute require',
     *  'must'        => ':attribute must',
     *  'number'      => ':attribute must be numeric',
     *  'integer'     => ':attribute must be integer',
     *  'float'       => ':attribute must be float',
     *  'boolean'     => ':attribute must be bool',
     *  'email'       => ':attribute not a valid email address',
     *  'mobile'      => ':attribute not a valid mobile',
     *  'array'       => ':attribute must be a array',
     *  'accepted'    => ':attribute must be yes,on or 1',
     *  'date'        => ':attribute not a valid datetime',
     *  'file'        => ':attribute not a valid file',
     *  'image'       => ':attribute not a valid image',
     *  'alpha'       => ':attribute must be alpha',
     *  'alphaNum'    => ':attribute must be alpha-numeric',
     *  'alphaDash'   => ':attribute must be alpha-numeric, dash, underscore',
     *  'activeUrl'   => ':attribute not a valid domain or ip',
     *  'chs'         => ':attribute must be chinese',
     *  'chsAlpha'    => ':attribute must be chinese or alpha',
     *  'chsAlphaNum' => ':attribute must be chinese,alpha-numeric',
     *  'chsDash'     => ':attribute must be chinese,alpha-numeric,underscore, dash',
     *  'url'         => ':attribute not a valid url',
     *  'ip'          => ':attribute not a valid ip',
     *  'dateFormat'  => ':attribute must be dateFormat of :rule',
     *  'in'          => ':attribute must be in :rule',
     *  'notIn'       => ':attribute be notin :rule',
     *  'between'     => ':attribute must between :1 - :2',
     *  'notBetween'  => ':attribute not between :1 - :2',
     *  'length'      => 'size of :attribute must be :rule',
     *  'max'         => 'max size of :attribute must be :rule',
     *  'min'         => 'min size of :attribute must be :rule',
     *  'after'       => ':attribute cannot be less than :rule',
     *  'before'      => ':attribute cannot exceed :rule',
     *  'expire'      => ':attribute not within :rule',
     *  'allowIp'     => 'access IP is not allowed',
     *  'denyIp'      => 'access IP denied',
     *  'confirm'     => ':attribute out of accord with :2',
     *  'different'   => ':attribute cannot be same with :2',
     *  'egt'         => ':attribute must greater than or equal :rule',
     *  'gt'          => ':attribute must greater than :rule',
     *  'elt'         => ':attribute must less than or equal :rule',
     *  'lt'          => ':attribute must less than :rule',
     *  'eq'          => ':attribute must equal :rule',
     *  'unique'      => ':attribute has exists',
     *  'regex'       => ':attribute not conform to the rules',
     *  'method'      => 'invalid Request method',
     *  'token'       => 'invalid token',
     *  'fileSize'    => 'filesize not match',
     *  'fileExt'     => 'extensions to upload is not allowed',
     *  'fileMime'    => 'mimetype to upload is not allowed',
     */
    public function init(array $rules, $type = '')
    {
        list($data, $rule, $info, $alias) = [input('',[],'trim'), [], [], ''];
        foreach ($rules as $name => $message) {
            if (stripos($name, '#') !== false) {
                list($name, $alias) = explode('#', $name);
            }
            if (stripos($name, '.') === false) {
                if (is_numeric($name)) {
                    $field = $message;
                    if (is_string($message) && stripos($message, '#') !== false) {
                        list($name, $alias) = explode('#', $message);
                        $field = empty($alias) ? $name : $alias;
                    }
                    $data[$name] = input("{$type}{$field}");
                } else {
                    $data[$name] = $message;
                }
            } else {
                list($_rgx) = explode(':', $name);
                list($_key, $_rule) = explode('.', $name);
                if (in_array($_rule, ['value', 'default'])) {
                    if ($_rule === 'value') {
                        $data[$_key] = $message;
                    } elseif ($_rule === 'default') {
                        $data[$_key] = input($type . ($alias ?: $_key), $message);
                    }
                } else {
                    $info[$_rgx] = $message;
                    $data[$_key] = $data[$_key] ?? input($type . ($alias ?: $_key));
                    $rule[$_key] = empty($rule[$_key]) ? $_rule : "{$rule[$_key]}|{$_rule}";
                }
            }
        }
        $validate = new Validate();
        if ($validate->rule($rule)->message($info)->check($data)) {
            return $data;
        } else {
            $this->controller->error($validate->getError());
        }
    }
}