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

use think\exception\HttpResponseException;
use start\extend\HttpExtend;
use start\service\AuthService;
use start\service\ConfigService;
use start\service\TokenService;
use start\Storage;
use think\facade\Config;

if (!function_exists('putlog')) {
    /**
     * 打印输出数据到文件
     * @param mixed $data 输出的数据
     * @param boolean $new 强制替换文件
     * @param string $file 保存文件名称
     */
    function putlog($data, $file = null, $new = false)
    {
        ConfigService::instance()->putlog($data, $file, $new);
    }
}
if (!function_exists('dolog')) {
    /**
     * 写入系统日志
     * @param string $action 日志行为
     * @param string $content 日志内容
     * @return boolean
     */
    function dolog($action, $content)
    {
        return ConfigService::instance()->dolog($action, $content);
    }
}
if (!function_exists('get_user')) {
    /**
     * 获取当前管理员ID
     * @param string $node
     * @return boolean
     * @throws ReflectionException
     */
    function get_user()
    {
        return AuthService::instance()->getUser();
    }
}
if (!function_exists('get_user_id')) {
    /**
     * 获取当前管理员ID
     * @param string $node
     * @return boolean
     * @throws ReflectionException
     */
    function get_user_id()
    {
        return AuthService::instance()->getUserId();
    }
}
if (!function_exists('get_user_name')) {
    /**
     * 获取当前管理员名称
     * @param string $node
     * @return boolean
     * @throws ReflectionException
     */
    function get_user_name()
    {
        return AuthService::instance()->getUserName();
    }
}
////////////////将弃用////////////////////////
if (!function_exists('get_admin_id')) {
    /**
     * 获取当前管理员ID
     * @param string $node
     * @return boolean
     * @throws ReflectionException
     */
    function get_admin_id()
    {
        return AuthService::instance()->getUserId();
    }
}
if (!function_exists('get_admin_name')) {
    /**
     * 获取当前管理员名称
     * @param string $node
     * @return boolean
     * @throws ReflectionException
     */
    function get_admin_name()
    {
        return AuthService::instance()->getUserName();
    }
}
///////////////////////////////////////////
if (!function_exists('auth')) {
    /**
     * 访问权限检查
     * @param string $node
     * @return boolean
     * @throws ReflectionException
     */
    function auth($node)
    {
        return AuthService::instance()->check($node);
    }
}
if (!function_exists('conf')) {
    /**
     * 获取或配置系统参数
     * @param string $name 参数名称
     * @param string $value 参数内容
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    function conf($name = '', $value = null)
    {
        if (is_null($value) && is_string($name)) {
            return ConfigService::instance()->get($name);
        } else {
            return ConfigService::instance()->set($name, $value);
        }
    }
}
if (!function_exists('build_token')) {
    /**
     * 生成 CSRF-TOKEN 参数
     * @param string $node
     * @return string
     */
    function build_token($node = null)
    {
        $result = TokenService::instance()->buildFormToken($node);
        return $result['token'] ?? '';
    }
}
if (!function_exists('http_get')) {
    /**
     * 以get模拟网络请求
     * @param string $url HTTP请求URL地址
     * @param array|string $query GET请求参数
     * @param array $options CURL参数
     * @return boolean|string
     */
    function http_get($url, $query = [], $options = [])
    {
        return HttpExtend::get($url, $query, $options);
    }
}
if (!function_exists('http_post')) {
    /**
     * 以post模拟网络请求
     * @param string $url HTTP请求URL地址
     * @param array|string $data POST请求数据
     * @param array $options CURL参数
     * @return boolean|string
     */
    function http_post($url, $data, $options = [])
    {
        return HttpExtend::post($url, $data, $options);
    }
}
if (!function_exists('throw_error')) {
    /**
     * 抛出异常
     * @param  [type]  $msg  异常信息
     * @param  string  $data 异常数据
     * @param  integer $code 异常编码
     */
    function throw_error($msg, $data = '{-null-}', $code = 0)
    {
        if ($data === '{-null-}') $data = new \stdClass();
        throw new HttpResponseException(json([
            'code' => $code, 'msg' => $msg, 'data' => $data,
        ]));
    }
}
if (!function_exists('format_bytes')) {
    /**
     * 文件字节单位转换
     * @param integer $size
     * @return string
     */
    function format_bytes($size)
    {
        if (is_numeric($size)) {
            $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
            for ($i = 0; $size >= 1024 && $i < 4; $i++) $size /= 1024;
            return round($size, 2) . ' ' . $units[$i];
        } else {
            return $size;
        }
    }
}
if (!function_exists('format_datetime')) {
    /**
     * 日期格式标准输出
     * @param string $datetime 输入日期
     * @param string $format 输出格式
     * @return false|string
     */
    function format_datetime($datetime, $format = 'Y-m-d H:i:s')
    {
        if (empty($datetime)) return '-';
        if (is_numeric($datetime)) {
            return date($format, $datetime);
        } else {
            return date($format, strtotime($datetime));
        }
    }
}
if (!function_exists('enbase64url')) {
    /**
     * Base64安全URL编码
     * @param string $string
     * @return string
     */
    function enbase64url(string $string)
    {
        return rtrim(strtr(base64_encode($string), '+/', '-_'), '=');
    }
}
if (!function_exists('debase64url')) {
    /**
     * Base64安全URL解码
     * @param string $string
     * @return string
     */
    function debase64url(string $string)
    {
        return base64_decode(str_pad(strtr($string, '-_', '+/'), strlen($string) % 4, '=', STR_PAD_RIGHT));
    }
}
if (!function_exists('down_file')) {
    /**
     * 下载远程文件到本地
     * @param string $source 远程文件地址
     * @param boolean $force 是否强制重新下载
     * @param integer $expire 强制本地存储时间
     * @return string
     */
    function down_file($source, $force = false, $expire = 0)
    {
        $result = Storage::down($source, $force, $expire);
        return $result['url'] ?? $source;
    }
}
if (!function_exists('encode')) {
    /**
     * 加密 UTF8 字符串
     * @param string $content
     * @return string
     */
    function encode($content)
    {
        if(is_array($content)) {
            $content = json_encode($content);
        }
        list($chars, $length) = ['', strlen($string = iconv('UTF-8', 'GBK//TRANSLIT', $content))];
        for ($i = 0; $i < $length; $i++) $chars .= str_pad(base_convert(ord($string[$i]), 10, 36), 2, 0, 0);
        return $chars;
    }
}
if (!function_exists('decode')) {
    /**
     * 解密 UTF8 字符串
     * @param string $content
     * @return string
     */
    function decode($content)
    {
        $chars = '';
        foreach (str_split($content, 2) as $char) {
            $chars .= chr(intval(base_convert($char, 36, 10)));
        }
        return iconv('GBK//TRANSLIT', 'UTF-8', $chars);
    }
}
/**
 * 系统加密方法
 * @param string $data 要加密的字符串
 * @param string $key 加密密钥
 * @param int $expire 过期时间 单位 秒
 * @return string
 */
function start_encrypt($data, $key = '', $expire = 0)
{
    $key  = md5(empty($key) ? 'Simplestart' : $key);
    $data = base64_encode($data);
    $x    = 0;
    $len  = strlen($data);
    $l    = strlen($key);
    $char = '';

    for ($i = 0; $i < $len; $i++) {
        if ($x == $l) $x = 0;
        $char .= substr($key, $x, 1);
        $x++;
    }

    $str = sprintf('%010d', $expire ? $expire + time() : 0);

    for ($i = 0; $i < $len; $i++) {
        $str .= chr(ord(substr($data, $i, 1)) + (ord(substr($char, $i, 1))) % 256);
    }

    $str = str_replace(array('+', '/', '='), array('-', '_', ''), base64_encode($str));
    return strtoupper(md5($str)) . $str;
}

/**
 * 系统解密方法
 * @param  string $data 要解密的字符串 （必须是start_encrypt方法加密的字符串）
 * @param  string $key 加密密钥
 * @return string
 */
function start_decrypt($data, $key = '')
{
    $key  = md5(empty($key) ? 'Simplestart' : $key);
    $data = substr($data, 32);
    $data = str_replace(array('-', '_'), array('+', '/'), $data);
    $mod4 = strlen($data) % 4;
    if ($mod4) {
        $data .= substr('====', $mod4);
    }
    $data   = base64_decode($data);
    $expire = substr($data, 0, 10);
    $data   = substr($data, 10);

    if ($expire > 0 && $expire < time()) {
        return '';
    }
    $x    = 0;
    $len  = strlen($data);
    $l    = strlen($key);
    $char = $str = '';

    for ($i = 0; $i < $len; $i++) {
        if ($x == $l) $x = 0;
        $char .= substr($key, $x, 1);
        $x++;
    }

    for ($i = 0; $i < $len; $i++) {
        if (ord(substr($data, $i, 1)) < ord(substr($char, $i, 1))) {
            $str .= chr((ord(substr($data, $i, 1)) + 256) - ord(substr($char, $i, 1)));
        } else {
            $str .= chr(ord(substr($data, $i, 1)) - ord(substr($char, $i, 1)));
        }
    }
    return base64_decode($str);
}