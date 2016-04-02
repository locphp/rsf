<?php

namespace Rsf;

class Controller {

    //用户信息
    protected $login_user = null;
    //当前控制器
    protected $request;
    //当前动作
    protected $response;
    //时间戳
    protected $timestamp;

    /*
     * 初始执行
     */
    public function __construct(Swoole\Request $request, Swoole\Response $response) {
        $this->request = $request;
        $this->response = $response;
        //$this->init_var();
        $this->init_cache();
        //$this->init_timezone();
    }

    public function __destruct() {

    }

    public function __call($name, $arguments) {
        //动作不存在
        $retarr = array(
            'errcode' => 1,
            'errmsg' => 'Action ' . $name . '不存在!',
            'data' => ''
        );
        $this->response($retarr, 404);
    }

    protected function response($data, $code = 200) {
        if ($code !== 200) {
            $this->response->withStatus($code, Http\Http::getStatus($code));
        }
        $this->response->withHeader('Content-Type', 'text/html; charset=' . getini('site/charset'));
        $this->response->withBody(new Http\StringStream($data));
    }

    /*
     * 初始变量
     */

    private function init_var() {
        $this->timestamp = getgpc('s.REQUEST_TIME') ?: time();
        if (filter_input(INPUT_GET, 'page')) {
            $_GET['page'] = max(1, filter_input(INPUT_GET, 'page'));
        }
    }

    /*
     * 初始缓存
     */

    private function init_cache() {
        $caches = getini('cache/default');
        loadcache($caches);
    }

    /*
     * 时区
     */
    private function init_timezone() {
        //php > 5.1
        $timeoffset = getini('settings/timezone');
        $timeoffset && date_default_timezone_set('Etc/GMT' . ($timeoffset > 0 ? '-' : '+') . abs($timeoffset));
    }

    final function checklogin() {
        if ($this->login_user) {
            return $this->login_user;
        }
        $this->login_user = Context::getUser();
        return $this->login_user;
    }

    final function checkacl($controllerName, $actionName, $auth = AUTH) {
        return Rbac::check($controllerName, $actionName, $auth);
    }

}
