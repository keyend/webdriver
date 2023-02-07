<?php
namespace mashroom\provider;

class Model extends \think\Model
{
    /**
     * 服务注入
     * @var Closure[]
     */
    protected static $maker = [];
}