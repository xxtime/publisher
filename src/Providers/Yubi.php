<?php
/**
 * Created by PhpStorm.
 * User: lkl
 * Date: 2017/8/8
 * Time: 15:17
 */

namespace Xt\Publisher\Providers;

use Xt\Publisher\DefaultException;

class Yubi extends ProviderAbstract
{
    public function verifyToken($token = '', $option = [])
    {
        $url = 'http://www.i7game.com/sdk.php/LoginNotify/login_verify';

        $param = [
            'user_id' => $option['user_id'],
            'token'   => $token,
        ];

        $response = $this->http_curl_post($url, $param);

        $result = json_decode($response, true);

        //如果遇到错误 则抛出错误
        if ($result['status'] != 1) {
            throw new DefaultException($response);
        }

        return [
            'uid'      => $result['user_id'],
            'username' => $result['user_account'],
            'original' => $result
        ];
    }

    private function http_curl_post($url, $data, $extend = array())
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_NOBODY, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $curl_result = curl_exec($ch);
        curl_close($ch);

        return $curl_result;
    }

    public function notify()
    {
        $req = $_REQUEST;
        $data = array(
            'out_trade_no' => $req['out_trade_no'],
            'price'        => $req['price'],
            'pay_status'   => $req['pay_status'],
            'extend'       => $req['extend'],
        );

        $sign = $req['sign'];

        $str1 = '';
        foreach ($data as $k => $v) {
            $str1 .= "$v";
        }
        $mysign = md5($str1 . $this->app_key);

        if ($sign != $mysign) {
            throw new DefaultException('sign error');
        }

        // 平台参数
        $param['amount'] = $req['price'];                              // 总价.单位: 分
        $param['transaction'] = $req['extend'];                              // 订单id
        $param['currency'] = 'CNY';                                                         // 货币类型
        $param['reference'] = $req['out_trade_no'];                           // 第三方订单ID
        $param['userId'] = '';                                   // 第三方账号ID

        return $param;
    }

    public function success()
    {
        exit('success');
    }
}
