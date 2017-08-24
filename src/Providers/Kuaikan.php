<?php
/**
 * Created by PhpStorm.
 * User: lihe
 * Date: 2017/8/18
 * Time: 下午4:19
 */
namespace Xt\Publisher\Providers;

use Xt\Publisher\DefaultException;

class Kuaikan extends ProviderAbstract{

    public function verifyToken($token = '', $option = [])
    {
        //验证open_id合法性
        $url = 'http://api.kkmh.com/v1/game/oauth/check_open_id?';

        $param = [
            'app_id' => $this->app_id,
            'open_id' => $option['custom'],
            'access_token' => $token,
        ];

        $sign = $this->productSign($param);
        $param['sign'] = $sign;

        $param_str = '';
        foreach ($param as $key=>$value){
            $param_str .= $key . '=' . $value . '&';
        }

        $param_str = trim($param_str, '&');

        $url = $url . $param_str;

        $response = file_get_contents($url);
        $result = json_decode($response, true);
        if ($result['code'] != 200){
            throw  new  DefaultException($result);
        }
        //如果合法 则获取用户信息
        $userInfo_url = 'http://api.kkmh.com/v1/game/oauth/user_info?';

        $userInfo_param = $token;

        $userInfo_url =$userInfo_url . 'access_token' . '=' . $userInfo_param;
        $res = file_get_contents($userInfo_url);
        $user_result = json_decode($res, true);
        if ($user_result['code'] != 200){
            throw new DefaultException($user_result);
        }

        return [
            'uid' => $user_result['data']['open_id'],
            'username' => $user_result['data']['nickname'],
            'original' => (array)$user_result
        ];
    }

    public function notify()
    {
        //快看订单回调 需要先使用下单接口 确定订单的有效性 然后才可以检查sign
    }

    private function productSign($param){
        //按照参数的ASCII码 从小到大排序
        ksort($param);

        $sign_str = '';
        foreach ($param as $key => $value){
            //如果为空 不参加签名
            if ($value == ''){
                unset($param[$key]);
            }
            $sign_str .= $key . '=' . $value . '&';
        }

        $sign_str = 'key' . '=' . $this->option['secrect_key'];

        return base64_encode(md5($sign_str, true));
    }
}