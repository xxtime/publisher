<?php
/**
 * Created by PhpStorm.
 * User: lkl
 * Date: 2017/8/21
 * Time: 18:35
 */
namespace Xt\Publisher\Providers;

use Xt\Publisher\DefaultException;

class Iqy extends ProviderAbstract
{
    //由于没有账号验证 故直接返回
    public function verifyToken($token = '', $option = [])
    {
        return [
            'uid'      => $option['uid'],
            'username' => '',
            'original' => $option['uid']
        ];
    }

    public function notify()
    {

    }
}