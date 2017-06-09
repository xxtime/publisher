<?php

namespace Xt\Publisher\Providers;


abstract class ProviderAbstract implements ProviderInterface
{

    protected $app_id;

    protected $app_key;

    protected $option;

    public function __construct($option = [])
    {
        $this->option = $option;
        if (isset($option['app_id'])) {
            $this->app_id = $option['app_id'];
        }
        if (isset($option['app_key'])) {
            $this->app_key = $option['app_key'];
        }
    }

}