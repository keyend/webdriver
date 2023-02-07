<?php
namespace mashroom\middleware;
/*
 * 通用全局变量声明
 * @Date: 2020-11-10
 */
use think\App;
use think\Lang;
use think\Config;
use think\Response;
use think\exception\HttpResponseException;

class Constant
{
    /**
     * 全局常量声明
     * @access public
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle($request, \Closure $next)
    {
        if (!isset($_SERVER['REQUEST_TIME']))
            $_SERVER['REQUEST_TIME'] = time();

        if (!app()->runningInConsole()) {
            define('MODULE', app('http')->getName());
            define('CONTROLLER', $request->controller());
            define('ACTION', $request->action());
            define('TIMESTAMP', $_SERVER['REQUEST_TIME']);
            define('IS_POST',   $request->isPost());
            define('IS_PUT',    $request->isPut());
            define('IS_DELETE', $request->isDelete());
            define('IS_GET',    $request->isGet());

            // var_dump($request->server(''));
            // var_dump(MODULE);
            // var_dump(CONTROLLER);
            // var_dump(ACTION);
        }

        if ($request->method(true) == 'OPTIONS') {
            throw new HttpResponseException(Response::create()->code(200));
        }

        // 平台账户
        define('RANGE_PLATFORM', 'platform');
        // 通用账户
        define('RANGE_GENERATE', 'generate');

        event('multiAppBegin');

        return $next($request);
    }
}