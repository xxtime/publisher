<?php
/**
 * Created by PhpStorm.
 * User: lkl
 * Date: 2017/8/22
 * Time: 16:28
 */
namespace Xt\Publisher\Providers;

use Xt\Publisher\DefaultException;

class Sdk179 extends ProviderAbstract
{
    public function verifyToken($token = '', $option = [])
    {
        $url = 'http://gamesdk.padyun.com/api/?m=api&a=validate_token';

        $param = [
            'appid' => $this->app_id,
            't'   => $token,
        ];

        $response = $this->http_curl_post($url, $param);

        //如果遇到错误 则抛出错误
        if ($response != 'success') {
            throw new DefaultException($response);
        }

        return [
            'uid'      => $option['uid'],
            'username' => '',
            'original' => $option
        ];
    }

    private function http_curl_post($url, $data, $extend = array())
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        $curl_result = curl_exec($ch);
        curl_close($ch);

        return $curl_result;
    }

    public function notify()
    {
        $req = $_REQUEST;

        $data = array(
            'n_time' => $req['n_time'],
            'appid'        => $req['appid'],
            'o_id'   => $req['o_id'],
            't_fee'       => $req['t_fee'],
            'g_name'       => $req['g_name'],
            'g_body'       => $req['g_body'],
            't_status'       => $req['t_status'],
        );

        $sign = $req['o_sign'];

        $str1 = '';
        foreach ($data as $k => $v) {
            $str1 .= "$k=".urlencode($v).'&';
        }
        $str1 = trim($str1, '&');

        $mysign = md5($str1 . $this->app_key);

        if ($sign != $mysign) {
            throw new DefaultException('sign error');
        }

        // 平台参数
        $param['amount'] = $req['t_fee'];                              // 总价.单位: 分
        $param['transaction'] = $req['o_id'];                              // 订单id
        $param['currency'] = 'CNY';                                                         // 货币类型
        $param['reference'] = $req['o_orderid'];                           // 第三方订单ID
        $param['userId'] = '';                                   // 第三方账号ID

        return $param;
    }

    public function success()
    {
        exit('success');
    }
}