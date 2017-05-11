<?php

namespace Xt\Oauth;


use Xt\Oauth\DefaultException;

class Oauth
{

    /**
     * @class
     */
    private $provider;

    /**
     * @var
     */
    private $_provider;

    /**
     * @var
     */
    private $_app_id;

    /**
     * @var
     */
    private $_app_key;


    /**
     * Oauth constructor.
     * @param array $option [provider,app_id,app_key]
     */
    public function __construct($option = [])
    {
        $this->_provider = $option['provider'];
        $this->_app_id = $option['app_id'];
        $this->_app_key = $option['app_key'];

        $class = "\\Xt\\Oauth\\Providers\\" . ucfirst($this->_provider);
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


}