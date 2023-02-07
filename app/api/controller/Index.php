<?php
namespace app\api\controller;
/*
 * 默认页
 * 
 * @Date: 2021-05-10 20:19:31
 */
use app\api\Controller;
use think\facade\Log;

class Index extends Controller
{
    public function test() {
        return $this->success();
    }

    public function miss() {
        return $this->fail('Not Found!', 404);
    }
}
