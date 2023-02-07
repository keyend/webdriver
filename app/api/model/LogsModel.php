<?php
namespace app\api\model;
/**
 * 日志
 * @package shareuc.model
 * @author shareuc
 */
use think\Model;
use think\facade\Log;

class LogsModel
{
    /**
     * 添加操作日志
     * @param string type 动作类型
     * @param Array  params 参数
     * @param string message 事件内容
     * @access public
     */
    public function info($message, ...$args)
    {
        foreach($args as $arg) {
            if(gettype($arg) === 'number' || gettype($arg) === 'int' || (is_string($arg) && is_numeric($arg))) {
                $user_id = $arg;
            } elseif (is_string($arg)) {
                $type = $arg;
            } elseif(is_array($arg)) {
                $params = $arg;
            }
        }

        if (!isset($params)) $params = [];
        if (!isset($type)) $type = 'DOSOME';

        if (defined('S2')) {
            $params['username'] = S2;
            $params['realname'] = S7;
            $params['ip'] = SA;
        }

        if (isset($params['lastlogin_ip'])) {
            $ip = $params['lastlogin_ip'];
        } elseif(defined('SA')) {
            $ip = SA;
        } else {
            $ip = '';
        }

        if (!isset($user_id)) {
            if (defined('S1'))
                $user_id = S1;
            elseif(isset($params['user_id']))
                $user_id = $params['user_id'];
            else
                $user_id = 0;
        }

        Log::info("[{$type}][{$user_id}] {$message} " . json_encode($params, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 解析日志内容中的字符
     * @param String message 内容
     * @return String
     * @access protected
     */
    public function parse($message = '', $params = [])
    {
        foreach($params as $key => $value) {
            if (!is_string($value)) {
                $value = json_encode($value);
            }
            $message = str_replace("{{$key}}", $value, $message);
        }

        return $message;
    }

    /**
     * 获取事件类型
     * @param String label  类型标识
     * @return String
     */
    protected function getLabelType($label)
    {
        return lang("logs.type.{$label}");
    }
}