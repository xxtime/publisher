<?php
/**
 * Created by PhpStorm.
 * User: lihe
 * Date: 2017/8/18
 * Time: 下午2:51
 */
namespace Xt\Publisher\Providers;

use Xt\Publisher\DefaultException;

class Meitu extends ProviderAbstract{

    public function verifyToken($token = '', $option = [])
    {
        $url = 'http://www.91wan.com/api/mobile/sdk_oauth.php';

        $flag = $this->productFlag($this->app_id, $option['uid'], $token);
        dd($flag);
        $param = [
            'appid' => $this->app_id,
            'uid' => $option['uid'],
            'state' => $token,
            'flag'  => $flag
        ];
    }

    public function notify()
    {

    }

    //生成flag的值
    private function productFlag($app_id, $uid, $token){
        $md5_str = $app_id . '.' . $uid . '.' . $token . '.' . $this->option['login_key'];
        return md5($md5_str);
    }
}