<?php
/**
 * Created by PhpStorm.
 * User: lihe
 * Date: 2017/8/3
 * Time: 上午11:41
 */
namespace Xt\Publisher\Providers;

use Xt\Publisher\DefaultException;

class Yweixin extends ProviderAbstract
{
    public function verifyToken($token = '', $option = [])
    {
        $url = 'http://ysdktest.qq.com/auth/wx_check_token?';    //此地址是测试地址,不是正式地址

        $timeStamp = time();

        $sign = strtolower(md5($this->app_key . $timeStamp));

        $query = [
            'timestamp' => $timeStamp,
            'appid'     => $this->option['app_id'],
            'sig'       => $sign,
            'openid'    => $option['custom'],
            'openkey'   => $token,
        ];

        $response = file_get_contents($url . http_build_query($query));

        $result = json_decode($response, true);
        if ($result['ret'] != 0) {
            throw new DefaultException(json_encode($result));
        }

        return [
            'uid'      => $query['openid'],
            'username' => '',
            'original' => (array)$result
        ];
    }

    public function notify()
    {
        $req = $_REQUEST;
        $data = array(
            'openid'               => $req['openid'],
            'appid'                => $req['appid'],
            'ts'                   => $req['ts'],
            'payitem'              => $req['payitem'],
            'token'                => $req['token'],
            'billno'               => $req['billno'],
            'version'              => $req['version'],
            'zoneid'               => $req['zoneid'],
            'providetype'          => $req['providetype'],
            'amt'                  => $req['amt'],
            'payamt_coins'         => $req['payamt_coins'],
            'pubacct_payamt_coins' => $req['pubacct_payamt_coins'],

        );

        $sig = $req['sig'];

        $data['billno'] = str_replace('-', '%2D', $data['billno']);
        ksort($data);

        $str1 = '';
        foreach ($data as $k => $v) {
            $str1 .= "$k=$v&";
        }
        $str1 = urlencode(trim($str1, '&'));

        $url = urlencode('/publisher/notify/yqq');
        $str2 = 'GET' . $url . $str1;
        $appkey = $this->app_key . '&';
        $mysig = hash_hmac('sha1', $str2, $appkey);
        $mysig = base64_encode($mysig);

        if ($sig != $mysig) {
            throw new DefaultException('sign error');
        }

        // 平台参数
        $param['amount'] = round($req['amt'] / 10, 2);                              // 总价.单位: 分
        $param['transaction'] = $req['billno'];                              // 订单id
        $param['currency'] = 'CNY';                                                         // 货币类型
        $param['reference'] = $req['billno'];                           // 第三方订单ID
        $param['userId'] = '';                                   // 第三方账号ID

        return $param;
    }
}