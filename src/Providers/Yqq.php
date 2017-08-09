<?php
/**
 * Created by PhpStorm.
 * User: lihe
 * Date: 2017/8/3
 * Time: 上午11:41
 */
namespace Xt\Publisher\Providers;

use Xt\Publisher\DefaultException;

class Yqq extends ProviderAbstract
{

    public function verifyToken($token = '', $option = [])
    {
        $url = 'http://ysdktest.qq.com/auth/qq_check_token?';    //此地址是测试地址,不是正式地址

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
        $url = 'https://ysdktest.qq.com';
        $uri = '/mpay/get_balance_m';
        $req = $_REQUEST;
        $data = array(
            'openid'               => $req['openid'],
            'openkey'                => $req['openkey'],
            'pf'                   => $req['pf'],
            'pfkey'              => $req['pfkey'],
            'zoneid'                => $req['custom'],
            'appid'                => $this->app_id,
            'ts'                => time(),
            'userip'                => 'common',
            'format'                => 'json',
        );

        ksort($data);

        $str1 = '';
        foreach ($data as $k => $v) {
            $str1 .= "$k=$v&";
        }
        $str2 = urlencode(trim($str1, '&'));

        $str3 = 'GET' . urlencode($uri) . $str2;
        $appkey = $this->app_key . '&';
        $sig = hash_hmac('sha1', $str3, $appkey);
        $sig = base64_encode($sig);

        $url .= $uri . '?' . $str1 .'&sig='. urlencode($sig);
        $cookie = 'session_id ="'.urlencode('openid').'";session_type = "'.urlencode('kp_actoken').'";org_loc="'.urlencode('/mpay/get_balance_m').'"';

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, 1);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_COOKIE, $cookie);
        $data = curl_exec($curl);
        curl_close($curl);

        dd($data);

//        if ($sig != $mysig) {
//            throw new DefaultException('sign error');
//        }

        // 平台参数
        $param['amount'] = round($req['amt'] / 10, 2);                              // 总价.单位: 分
        $param['transaction'] = $req['billno'];                              // 订单id
        $param['currency'] = 'CNY';                                                         // 货币类型
        $param['reference'] = $req['billno'];                           // 第三方订单ID
        $param['userId'] = '';                                   // 第三方账号ID

        return $param;
    }

    public function success()
    {
        echo json_encode(array('ret' => 0, 'msg' => 'OK'));
        exit;
    }
}