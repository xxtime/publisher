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
        $url = 'https://ysdk.qq.com';
        $uri = '/v3/r/mpay/get_balance_m';
        $req = $_REQUEST;
        $data = array(
            'openid'               => $req['openid'],
            'openkey'                => $req['openkey'],
            'pf'                   => $req['pf'],
            'pfkey'              => $req['pfkey'],
            'zoneid'                => $req['zoneid'],
            'appid'                => $this->option['payapp_id'],
            'ts'                => time(),
        );

        ksort($data);

        $str1 = '';
        foreach ($data as $k => $v) {
            $str1 .= "$k=$v&";
        }
        $str2 = rawurlencode(trim($str1, '&'));

        $str3 = 'GET&' . rawurlencode($uri).'&' . $str2;

        $appkey = $this->app_key . '&';
        $sig = $this->getSignature($str3, $appkey);

        $url .=  '/mpay/get_balance_m?' . $str1 .'sig='. rawurlencode($sig);
        $cookie = 'session_id='.rawurlencode('hy_gameid').';session_type='.rawurlencode('wc_actoken').';org_loc='.rawurlencode('/mpay/pay_m').';';
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_COOKIE, $cookie);
        $data = curl_exec($curl);
        curl_close($curl);

        $result = json_decode($data, true);

        if ($result['ret'] != 0 ) {
            throw new DefaultException('sign error');
        }

        // 平台参数
        $param['amount'] = round($req['amount'] / 100, 2);                              // 总价.单位: 分
        $param['transaction'] = $req['orderid'] ;                              // 订单id
        $param['currency'] = 'CNY';                                                         // 货币类型
        $param['reference'] = $req['orderid'];                           // 第三方订单ID
        $param['userId'] = '';                                   // 第三方账号ID

        return $param;
    }

    public function success()
    {
        exit(json_encode(array('code'=>0, 'msg'=>'success')));
    }

    private function getSignature($str, $key) {
        $signature = "";
        if (function_exists('hash_hmac')) {
            $signature = base64_encode(hash_hmac("sha1", $str, $key, true));
        } else {
            $blocksize = 64;
            $hashfunc = 'sha1';
            if (strlen($key) > $blocksize) {
                $key = pack('H*', $hashfunc($key));
            }
            $key = str_pad($key, $blocksize, chr(0x00));
            $ipad = str_repeat(chr(0x36), $blocksize);
            $opad = str_repeat(chr(0x5c), $blocksize);
            $hmac = pack(
                'H*', $hashfunc(
                    ($key ^ $opad) . pack(
                        'H*', $hashfunc(
                            ($key ^ $ipad) . $str
                        )
                    )
                )
            );
            $signature = base64_encode($hmac);
        }
        return $signature;
    }
}
