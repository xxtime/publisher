<?php

namespace Xt\Oauth\Providers;


abstract class ProviderAbstract implements ProviderInterface
{

    protected $app_id;

    protected $app_key;

    public function __construct($option = [])
    {
        $this->app_id = $option['app_id'];
        $this->app_key = $option['app_key'];
    }

}