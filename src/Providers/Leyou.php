<?php
/**
 * Created by PhpStorm.
 * User: lkl
 * Date: 2017/8/24
 * Time: 14:36
 */

namespace Xt\Publisher\Providers;

use Xt\Publisher\DefaultException;

class Leyou extends ProviderAbstract
{
    public function verifyToken($token = '', $option = [])
    {
        $url = 'http://game.6y.com.cn/sdk/islogin.php';

        $param = [
            'username' => $option['custom'],
            'memkey'   => $token,
        ];

        $response = $this->http_curl_post($url, $param);


        //如果遇到错误 则抛出错误
        if ($response != 'success') {
            throw new DefaultException($response);
        }

        return [
            'uid'      => $option['custom'],
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
            'orderid' => $req['orderid'],
            'username'        => $req['username'],
            'gameid'   => $req['gameid'],
            'roleid'       => $req['roleid'],
            'serverid'       => $req['serverid'],
            'paytype'       => $req['paytype'],
            'amount'       => $req['amount'],
            'paytime'       => $req['paytime'],
            'attach'       => $req['attach'],
            'appkey'       => $this->app_key,
        );

        $sign = $req['sign'];

        $str1 = '';
        foreach ($data as $k => $v) {
            $str1 .= "$k=".urlencode($v).'&';
        }
        $str1 = trim($str1, '&');
        $mysign = md5($str1);

        if ($sign != $mysign) {
            throw new DefaultException('sign error');
        }

        // 平台参数
        $param['amount'] = $req['amount'];                              // 总价.单位: 分
        $param['transaction'] = $req['attach'];                              // 订单id
        $param['currency'] = 'CNY';                                                         // 货币类型
        $param['reference'] = $req['orderid'];                           // 第三方订单ID
        $param['userId'] = '';                                   // 第三方账号ID

        return $param;
    }

    public function success()
    {
        exit('success');
    }
}