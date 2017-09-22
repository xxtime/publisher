<?php
/**
 * Created by PhpStorm.
 * User: lkl
 * Date: 2017/8/8
 * Time: 17:27
 */
namespace Xt\Publisher\Providers;

use Xt\Publisher\DefaultException;

class yueyou extends ProviderAbstract
{
    public function verifyToken($token = '', $option = [])
    {
        $url = 'http://rhsdk.yueeyou.com/api/cp/checkToken';

        $time = time();
        $data = [
            'AppID'     => $this->app_id,
            'timestamp' => $time,
            'token'     => $token
        ];

        $str1 = '';
        foreach ($data as $k => $v) {
            $str1 .= "$k=$v&";
        }
        $str1 = trim($str1, '&');
        $data['sign'] = md5($str1 . $this->app_key);

        $param = http_build_query($data);

        $url = $url . $param;
        $response = file_get_contents($url);
        $result = json_decode($response, true);

        //如果有异常 抛出异常
        if ($result['code'] != 1) {
            throw new DefaultException($response);
        }

        return array('uid' => $result['userId'], 'username' => '', 'original' => (array)$result);
    }

    public function notify()
    {
        $req = $_REQUEST;

        $data = array(
            'result'    => $req['result'],
            'amount'    => $req['amount'],
            'channelId' => $req['channelId'],
            'appId'     => $req['appId'],
            'appId'     => $req['appId'],
            'orderId'   => $req['orderId'],
            'cpOrderId' => $req['cpOrderId'],
            'userId'    => $req['userId'],
            'timestamp' => $req['timestamp'],
            'ext'       => $req['ext'],
        );
        ksort($data);
        $sign = $req['sign'];

        $str1 = '';
        foreach ($data as $k => $v) {
            $str1 .= "$k=$v&";
        }
        $str1 = trim($str1, '&');
        $mysign = md5($str1 . $this->app_key);

        if ($sign != $mysign) {
            throw new DefaultException('sign error');
        }

        // 平台参数
        $param['amount'] = $req['amount'];                              // 总价.单位: 分
        $param['transaction'] = $req['cpOrderId'];                              // 订单id
        $param['currency'] = 'CNY';                                                         // 货币类型
        $param['reference'] = $req['orderId'];                           // 第三方订单ID
        $param['userId'] = '';                                   // 第三方账号ID

        return $param;
    }

    public function success()
    {
        exit('OK');
    }
}
