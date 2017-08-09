<?php
/**
 * Created by PhpStorm.
 * User: lkl
 * Date: 2017/8/9
 * Time: 10:33
 */
namespace Xt\Publisher\Providers;

use Xt\Publisher\DefaultException;

class Jile extends ProviderAbstract{
    public function verifyToken($token = '', $option = [])
    {
        $url = 'https://openapi.shediao.com/user/info';

        $param = [
            'access_token'   => $token,
        ];

        $response = $this->http_curl_post($url, $param);

        $result = json_decode($response, true);

        //如果遇到错误 则抛出错误
        if ($result['status'] != 0) {
            throw new DefaultException($response);
        }

        return [
            'uid'      => $result['username'],
            'username' => $result['nickname'],
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

        $sign = $req['sign'];

        $data = array(
            'trade_no' => $req['trade_no'],
            'appsecret'        => $this->app_key,
            'total_fee'        => $req['total_fee'],
            'fee_type'   => $req['fee_type'],
            'app_id'       => $req['app_id'],
        );

        $str1 = '';
        foreach ($data as $k => $v) {
            $str1 .= "$v";
        }
        $mysign = md5($str1);

        if ($sign != $mysign) {
            throw new DefaultException('sign error');
        }

        // 平台参数
        $param['amount'] = $req['total_fee'];                              // 总价.单位: 分
        $param['transaction'] = $req['cp_trade_no'];                              // 订单id
        $param['currency'] = 'CNY';                                                         // 货币类型
        $param['reference'] = $req['trade_no'];                           // 第三方订单ID
        $param['userId'] = '';                                   // 第三方账号ID

        return $param;
    }

    public function success()
    {
        $arr = array('errno'=>0 , 'errmsg' => '' , 'data' => array('status'=>0));
        exit(json_encode($arr));
    }
}