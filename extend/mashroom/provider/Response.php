<?php
namespace mashroom\provider;
/**
 * @package mashroom.provider
 */
use think\contract\Arrayable;
use think\response\Json;

class Response extends Json
{
    /**
     * 发送HTTP状态
     * @access public
     * @param  integer $code 状态码
     * @return $this
     */
    public function code(int $code = 200)
    {
        return parent::code($code);
    }
}