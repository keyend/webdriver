<?php
namespace mashroom\service;
/**
 * mashroom.service 应用服务
 * @version 1.0.0
 */
use app\api\model\LogsModel;

class AppService
{
    /**
     * 应用日志
     *
     * @var object
     */
    protected $logger;

    /**
     * 连接地址列表
     *
     * @var array
     */
    protected $paths;

    /**
     * 服务地址
     *
     * @var string
     */
    protected $host = 'http://127.0.0.1:9515';

    public function __construct($args = [])
    {
        $this->logger = app()->make(LogsModel::class);
    }

    /**
     * 触发器
     *
     * @return void
     */
    public function before() {}

    /**
     * 触发器
     *
     * @return void
     */
    public function after() {}

    /**
     * 返回地址池列表
     *
     * @return void
     */
    public function getPaths()
    {
        return $this->paths;
    }

    /**
     * 返回服务地址
     *
     * @return void
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * 魔术访问
     *
     * @param string $name
     * @param array $arguments
     * @return void
     */
    public function __call($name, $arguments = [])
    {
        $name = ucfirst($name);
        $classname = "\\" . __NAMESPACE__ . "\\{$name}Service";

        if (class_exists($classname)) {
            return self::instance($classname, $arguments);
        }

        throw new \Exception("访问错误：服务[{$name}]不存在!");
    }

    /**
     * 实例化服务
     *
     * @param string $name
     * @param array $arguments
     * @return void
     */
    public static function instance($name, $arguments)
    {
        return new $name(...$arguments);
    }
}