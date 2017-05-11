<?php

namespace Xt\Oauth\Providers;


interface ProviderInterface
{


    public function verifyToken($token = '', $option = []);


}