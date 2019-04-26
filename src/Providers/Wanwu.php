<?php
/**
 * Created by PhpStorm.
 * User: zgx
 * Date: 2019/4/16
 * Time: 12:27
 */

namespace Xt\Publisher\Providers;

use Xt\Publisher\DefaultException;

class  Wanwu extends ProviderAbstract
{
    //登陆验证
    public function verifyToken($token = '', $option = [])
    {
        $url = 'http://www.9152ww.com:8080/u8server/user/verifyAccount?';

        $data = [
            'userID' => $option['uid'], //第三方账号ID
            'token' => $token,
        ];
        $data['sign'] = $this->Sign($data, $this->option['appSecret']);
        $response = $this->http_curl_post($url, $data);
        $result = json_decode($response, true);
        if ($result['state'] != 1) {
            throw new DefaultException('verity error');
        }

        return [
            'uid' => $option['uid'],
            'username' => '',
            'original' => $result
        ];
    }

    public function Sign($data, $app_secret)
    {
        $result = '';
        foreach ($data as $key => $value) {
            $result .= $key . '=' . $value;
        }
        $result .=  $app_secret;

        return md5($result);
    }

    /**
     *  return [
     * 'transactionId'        => '20170526024456001467000368', // 平台订单ID;   重要参数
     * 'transactionReference' => '1234567890',                 // 发行商订单ID; 必选参数
     * 'amount'               => 4.99,                         // 充值金额
     * 'currency'             => 'CNY',                        // 货币类型
     * 'userId'               => '3001-2001234',               // 终端用户ID
     * ];
     */
    public function notify()
    {
        $oriContent = file_get_contents('php://input');
        file_put_contents("wanwu.txt",$oriContent,FILE_APPEND);
        if (!isset($oriContent)){
            throw new DefaultException('fail');
        }

        $result = json_decode($oriContent,true);
        $data = $result['data'];
        $state = $result['state'];
        $params['amount'] = round($data['money']/100, 2);
        $params['transaction'] = $data['extension'];
        $params['currency'] = 'CNY';
        $params['reference'] = $data['orderID'];
        $params['userId'] = '';
        $this->check_sign($data,$state);
        return $params;
    }

    public function check_sign($data ,$state)
    {
        if($state!=1)
        {
            throw new DefaultException('state error');
        }
        $sign = $data['sign'];
        $sign = str_replace(' ', '+', $sign);
        $signType = $data['signType'];
        unset($data['signType']);
        unset($data['sign']);
        ksort($data);
        $signStr = http_build_query($data);
        if($signType == "rsa")
        {
            if ($this->verify_sign($signStr, $sign) != 1) {
                throw new DefaultException('sign error');
            }
        }else
        {
            $signStr = $signStr."&".$this->option['secret_key'];
            if(!$sign || $sign!= md5($signStr))
            {
                throw new DefaultException('sign error');
            }
        }

    }
    private function verify_sign($data, $sign)
    {
        $publickey = $this->option['public_key'];
        $pem = chunk_split($publickey, 64, "\n");
        $pem = "-----BEGIN PUBLIC KEY-----\n" . $pem . "-----END PUBLIC KEY-----\n";
        $public_key_id = openssl_pkey_get_public($pem);
        $signature = base64_decode($sign);
        $data .= "&".$this->option['appSecret'];
        return openssl_verify($data, $signature, $public_key_id);                    //成功返回1,0失败，-1错误,其他看手册
    }

    public function success()
    {
        exit('SUCCESS');
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
}
