<?php
/**
 * Created by PhpStorm.
 * User: lihe
 * Date: 2017/6/7
 * Time: 上午10:39
 */
namespace Xt\Publisher\Providers;

use Xt\Publisher\DefaultException;

class Amigo extends ProviderAbstract{
    //金立登陆验证
    public function verifyToken($token = '', $option = [])
    {
        $out = preg_replace_callback(
            "(\\\\x([0-9a-f]{2}))i",
            function ($a) {
                return chr(hexdec($a[1]));
            },
            $token
        );

        $url = 'https://id.gionee.com/account/verify.do';
        $apiKey = $this->app_key;
        $secretKey = $this->option['secret_key'];
        $host = "id.gionee.com";
        $port = "443";
        $uri = "/account/verify.do";
        $method = "POST";

        $ts =  time();
        $nonce = strtoupper(substr(uniqid(),0,8)) ;
        $signature_str = $ts."\n".$nonce."\n".$method."\n".$uri."\n".$host."\n".$port."\n"."\n";
        $signature = base64_encode(hash_hmac('sha1',$signature_str,$secretKey,true));
        $Authorization = "MAC id=\"{$apiKey}\",ts=\"{$ts}\",nonce=\"{$nonce}\",mac=\"{$signature}\"";

        $result = json_decode($this -> http_curl_post( $url, $out, $Authorization ));

        //如果有异常 抛出异常
        if (!empty($result->r)){
            throw new DefaultException($result->err);
        }

        // TODO: Implement verifyToken() method.
        return array('uid' => $option['uid'], 'username' => '', 'original' => $result);
    }

    private function http_curl_post( $url, $data, $Authorization = '', $timeout = 10 )
    {

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
        if( !empty( $Authorization ) )
        {
            curl_setopt($curl, CURLOPT_HTTPHEADER,array('Authorization: '.$Authorization));
        }
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        $content = curl_exec($curl);
        curl_close($curl);

        return $content;
    }

    /**
     *  return [
    'transactionId'        => '20170526024456001467000368', // 平台订单ID;   重要参数
    'transactionReference' => '1234567890',                 // 发行商订单ID; 必选参数
    'amount'               => 4.99,                         // 充值金额
    'currency'             => 'CNY',                        // 货币类型
    'userId'               => '3001-2001234',               // 终端用户ID
    ];
     */
    public function notify(){

        $apiKey = $this -> app_key;
        if( $apiKey != $_REQUEST['api_key'] )
        {
            throw new DefaultException('fail');
        }

        // 平台参数
        $param['amount'] =$_REQUEST['deal_price'];           // 总价               二选一(product_sn|amount)
        $param['transactionId'] = $_REQUEST['out_order_no'];       // 订单id             可选

        // 自定义参数                           // 支付方式
        $param['currency'] = 'CNY';                            // 货币类型

        // 第三方参数【可选,暂未使用】
        $param['transactionReference'] =$_REQUEST['partner_order_id'];   // 第三方订单ID
        $param['userId'] = $_REQUEST['user_id'];             // 第三方账号ID

        // 检查签名
        $this->check_sign($param['sign']);

        return $param;
    }

    // 检查签名
    public function check_sign($sign = '')
    {
        $req = $_REQUEST;
        unset($req['_url'], $req['plat'], $req['sign'], $req['zone'], $req['gameid'] );
        ksort($req);

        if( !empty( $req ) )
        {
            // 验签
            if( !$this -> rsa_verify( $req, $sign ) )
            {
                throw new DefaultException('sign error');
            }
        }
        else
        {
            throw new DefaultException('fail');
        }
    }

    /**
     * des: 金立自定义-支付回调-验签方式
     * @param $data
     * @param $sign
     * @return int
     */
    public function rsa_verify( $data, $sign )
    {
        $public_key = "-----BEGIN PUBLIC KEY-----\n" .
            chunk_split($this->option['public_key'], 64, "\n") .
            '-----END PUBLIC KEY-----';

        $pubKeyId = openssl_pkey_get_public( $public_key );

        $httpStr = is_array( $data ) ? http_build_query( $data ) : $data;
        $signature =base64_decode( $sign );

        return openssl_verify( $httpStr, $signature, $pubKeyId );
    }

    public function success()
    {
        exit('success');
    }
}