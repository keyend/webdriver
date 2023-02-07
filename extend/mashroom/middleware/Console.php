<?php
namespace mashroom\middleware;
/*
 * 控制台
 * @Date: 2020-11-10
 */
use think\App;
use think\Lang;
use think\Request;
use app\api\model\UserModel;
use app\api\controller\driver\Auth;
use mashroom\exception\HttpException;

class Console extends BaseMiddleware
{
    protected $request;
    protected $lang;

    /**
     * 中间件验证器
     * @var array
     * @example protected $validate = [
     *              'systemMerchantCreate' => MerchantValidate::class,
     *              'systemMerchantUpdate' => MerchantValidate::class,
     *              'systemUserCreate' => UserValidate::class,
     *              'systemUserUpdate' => UserValidate::class,
     *              'orderSendFactory' => OrderValidate::class
     *          ];
     */
    protected $validate = [];

    /**
    * @param int $num
    * @param mixed $default
    * @return mixed
    * @author xaboy
    * @day 2020-04-10
    */
    protected function getArgument($args, $num, $default = null)
    {
        return isset($args[$num]) ? $args[$num] : $default;
    }

    /**
     * 校验返回交互的令牌
     * @return string
     */
    protected function getToken()
    {
        $token = $this->request->server('HTTP_X_CSRF_TOKEN');
        if ($token) {
            $explane = explode(' ', $token);
            $token = end($explane);
        } else {
            $token = cookie('token');
        }

        if (!$token) throw new HttpException(lang('invalid token'), 50008);

        return $token;
    }

    /**
     * 交互对接
     * @access public
     * @param Request $request
     * @param Closure $next
     * @param ...$args
     * @return Response
     */
    public function handle(Request $request, \Closure $next, ...$args) 
    {
        $this->request = $request;
        $this->request->user = redis()->get("usr." . $this->getToken());

        if (!$this->request->user) {
            throw new HttpException(lang('invalid token'), 50007);
        } elseif(!$this->request->checkAccessToken()) {
            throw new HttpException(lang('invalid token'), 50008);
        } elseif($args) {
            // 附带参数
            $checkAccess = $this->getArgument($args, 0, false);
            /**
             * 附带布尔形参数判断是否验证权限
             */
            if (is_bool($checkAccess)) {
                // 验证账户是否登录
                if (!$this->request->checkLogin()) {
                    throw new HttpException(lang('no logged in'), 50009);
                }

                $rule = $this->request->rule()->getName();

                /**
                 * 权限验证是否存在匹配的权限列
                 * @throw HttpException
                 */
                if (is_string($this->request->user['group_access']) && $this->request->user['group_access'] == 'all') {
                    // 超级管理员跳过此步
                } elseif(is_array($this->request->user['group_access'])) {
                    // 系统停止服务
                    $sysPause = config('common.status', 1);
                    if ($sysPause != 1) {
                        throw new HttpException('The service has been closed or is undergoing maintenance and upgrade.', 50001);
                    }

                    // 应用其它规则校验
                    if (substr($rule, 0, 1) === '/') {
                        $append = $this->request->rule()->getOption('append');
                        if (isset($append['checkRule'])) {
                            $rule = $append['checkRule'];
                        }
                    }

                    // 校验是否有权访问（不做深度验证）
                    if ($checkAccess && !in_array($rule, $this->request->user['group_access'])) {
                        throw new HttpException(lang('no access'), 50011);
                    }
                } else {
                    throw new HttpException(lang('no access'), 50013);
                }

                /**
                 * 定期校验是否
                 * 开启登录模式为独占模式时，时间超过配置间隔检测登录的时间后，将进行校验Token
                 */
                $loggedMode = config('admin.user_logged_mode', 0);
                $expireCalibration = config('admin.user_logged_calibration', 120);

                if (TIMESTAMP - $this->request->user['lastonline_time'] > $expireCalibration) {

                    $orange = $this->app->make(UserModel::class)->find($this->request->user['user_id']);
                    if ($loggedMode && $orange['token'] !== $this->request->user['SESSION_ID']) {
                        // 跳出登录
                        $this->app->make(Auth::class)->logout();

                        throw new HttpException(lang('logged online'), 50012);
                    }

                    $orange->setAttr('lastonline_time', TIMESTAMP);
                    $orange->save();

                    $this->request->user['lastonline_time'] = TIMESTAMP;
                    $this->request->login($this->request->user);
                }

                /**
                 * 声明登录用户的全局变量
                 */
                define('S1', $this->request->user['user_id']);
                define('S6', $this->request->user['group']);
                define('S9', $this->request->user['REMOTE_ADDR']);
                // 自动验证器
                $this->validate($this->request, $rule, $this->request->method(true));
            }
        }

        // 全局TOKEN交互串
        define('S0', $this->request->user['SESSION_ID']);

        return $next($this->request);
    }

    /**
     * 验证器
     * @param String ruleName   权限名
     * @param String method     POST|GET
     * @return mixed
     */
    protected function validate($request, $ruleName, $method)
    {
        $method = strtolower($method);

        if (isset($this->validate[$ruleName])) {
            $validate = $this->validate[$ruleName];
            $validateMethod = 'post';

            if (is_array($validate)) {
                $validate = $validate[0];
                $validateMethod = $validate[1];
            }

            if ($validateMethod === $method) {
                try {
                    validate($validate)->scene($ruleName)->check($request->$method());
                } catch(HttpException $e) {
                    throw new ResponseException($e->getError());
                }
            }
        }
    }
}
