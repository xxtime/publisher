<?php
/**
 * Created by PhpStorm.
 * User: lihe
 * Date: 2017/8/3
 * Time: 上午11:41
 */
namespace Xt\Publisher\Providers;

use Xt\Publisher\DefaultException;

class Yqq extends ProviderAbstract{

    public function verifyToken($token = '', $option = [])
    {
        $url = 'http://ysdktest.qq.com/auth/qq_check_token?';    //此地址是测试地址,不是正式地址

        $timeStamp = time();

        $sign = strtolower(md5($this->app_key . $timeStamp));
        
        $query = [
            'timestamp' => $timeStamp,
            'appid' => $this->option['app_id'],
            'sig'  => $sign,
            'openid' => $option['custom'],
            'openkey' => $token,
        ];

        $response = file_get_contents($url. http_build_query($query));

        $result = json_decode($response, true);
        if ($result['ret'] != 0){
            throw new DefaultException(json_encode($result));
        }

        return [
            'uid' => $query['openid'],
            'username' => '',
            'original' => (array)$result
        ];
    }


    public function notify()
    {

    }
}