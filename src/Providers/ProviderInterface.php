<?php

namespace Xt\Publisher\Providers;


interface ProviderInterface
{


    public function verifyToken($token = '', $option = []);


    public function notify();


}