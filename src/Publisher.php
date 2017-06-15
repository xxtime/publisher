<?php

namespace Xt\Publisher;


use Xt\Publisher\DefaultException;

class Publisher
{

    /**
     * @class
     */
    private $provider;

    /**
     * @var
     */
    private $_app_id;

    /**
     * @var
     */
    private $_app_key;


    /**
     * Publisher constructor.
     * @param string $provider
     * @param array $option
     * @throws DefaultException
     */
    public function __construct($provider = '', $option = [])
    {
        if (isset($option['app_id'])) {
            $this->_app_id = $option['app_id'];
        }
        if (isset($option['app_key'])) {
            $this->_app_key = $option['app_key'];
        }

        $class = "\\Xt\\Publisher\\Providers\\" . ucfirst($provider);
        if (!class_exists($class)) {
            throw new DefaultException('can`t find class');
        }
        $this->provider = new $class($option);
    }


    /**
     * 验证token
     * @param string $token
     * @param array $option
     * @return array('uid'='123456','username'=>'丹妮','original'=>[])
     * @throws DefaultException
     */
    public function verifyToken($token = '', $option = [])
    {
        try {
            return $this->provider->verifyToken($token, $option);
        } catch (DefaultException $e) {
            throw $e;
        }
    }


    /**
     * 通知回调
     * @return mixed
     * @throws \Xt\Publisher\DefaultException
     */
    public function notify()
    {
        try {
            return $this->provider->notify();
        } catch (DefaultException $e) {
            throw $e;
        }
    }


    /**
     * 成功回调响应
     * @return mixed
     * @throws \Xt\Publisher\DefaultException
     */
    public function success()
    {
        try {
            return $this->provider->success();
        } catch (DefaultException $e) {
            throw $e;
        }
    }


    /**
     * 订单构建
     * @param array $parameter
     * @return mixed
     * @throws \Xt\Publisher\DefaultException
     */
    public function tradeBuild($parameter = [])
    {
        try {
            return $this->provider->tradeBuild($parameter);
        } catch (DefaultException $e) {
            throw $e;
        }
    }

}