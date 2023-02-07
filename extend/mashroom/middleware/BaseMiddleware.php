<?php
namespace mashroom\middleware;
use app\Request;
use think\App;

class BaseMiddleware
{
    protected $app;

    public function __construct(Request $request, App $app)
    {
        $pathinfo = explode('/', $request->pathinfo());
        $batch = array_splice_value($pathinfo, 1);

        defined('AP_BRANCH') or define('AP_BRANCH', $batch);

        $this->app = $app;
    }
}
