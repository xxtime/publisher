<?php
/**
 * 酷派
 * User: lihe
 * Date: 2017/7/17
 * Time: 下午6:31
 */
namespace Xt\Publisher\Providers;

use Xt\Publisher\DefaultException;

class Coolpad extends ProviderAbstract{

    //coolpad的登陆方式跟所有渠道的登陆方式不同, 需要两次请求 一次换取access token 一次换取用户信息
    //access token  需要传到token字段中
    public function verifyToken($token = '', $option = []){

        //TODO 只能使用一次，有效期300秒，相同的参数, 请求一次就失效.
        $accessToken = $option['custom'];

        //此url是用token 换取 用户信息的url
        $accessUrl = 'https://openapi.coolyun.com/oauth2/token?';

        $accessParam = [
            'grant_type' => 'authorization_code',
            'client_id'  => $this->app_id,
            'redirect_uri' => $this->app_key,
            'client_secret' => $this->app_key,
            'code'      => $accessToken
        ];

        $query = http_build_query($accessParam);

        //获取access token 用access token 再去请求sdk服务端 换取用户详细信息
        $response = file_get_contents($accessUrl.$query);
        //$response = "{\"access_token\":\"4.8f28f6388a467dc424e370e2e3f35961.8a2a954530c5cfb8c299fc866cc129e6.1500887596854\",\"refresh_token\":\"4.24215a1aafb504083e53e604d4cddddc\",\"openid\":\"87136948\",\"expires_in\":\"7776000\"}";
        //把json数据转成数组
        $response = json_decode($response, true);
        
        //如果没有返回openid 说明验证没过
        if (empty($response['access_token']) && empty($response['openid'])){
            throw new DefaultException($response);
        }

        //此Url是通过access token 换取 userInfo
        $playUrl = "https://openapi.coolyun.com/oauth2/api/get_user_info?";

        $playParam = [
            'access_token' => $response['access_token'],
            'oauth_consumer_key' => $this->app_id,
            'openid'      => $response['openid']
        ];

        $playerResponse = file_get_contents($playUrl . http_build_query($playParam));

        $playerResponse = json_decode($playerResponse, true);

        if ($playerResponse['rtn_code'] != 0){
            throw new DefaultException($playerResponse);
        }

        $playerResponse['access_token'] = $response['access_token'];

        $playerResponse['open_id'] = $response['openid'];

        return [
            'uid'   => $response['openid'],
            'username'  => $playerResponse['nickname'],
            'original'  => $playerResponse
        ];

    }

    public function notify(){
        $transdata = json_decode($_REQUEST['transdata'], true);
        if (empty($transdata)) {
            throw new DefaultException('error order');
        }

        // 平台参数
        $param['amount'] = round($transdata['money'] / 100, 2);                              // 总价.单位: 分
        $param['transaction'] = $transdata['exorderno'];                              // 订单id
        $param['currency'] = 'CNY';                                                         // 货币类型
        $param['reference'] = $transdata['transid'];                           // 第三方订单ID
        $param['userId'] = '';                                                  // 第三方账号ID

        $key1 =  base64_decode($this->option['payment_key']);
        $key2 = substr($key1,40,strlen($key1)-40);
        $key3 = base64_decode($key2);

        //获得私钥
        list ( $private_key, $mod_key ) = explode ( "+", $key3 );

        $sign_md5 = $this->decrypt($_REQUEST['sign'], $private_key, $mod_key);
        $msg_md5 = md5($_REQUEST['transdata']);

        if(strcmp($msg_md5,$sign_md5) != 0){
            throw new DefaultException('sign error');
        }

        return $param;
    }


    public function success()
    {
        exit('success');
    }

    /**
     * 解密方法
     * @param $string 需要解密的密文字符
     * @param $d
     * @param $n
     * @return String
     */
    private function decrypt($string, $d, $n){
        $keylen = 64;
        //解决某些机器验签时好时坏的bug
        //BCMath 里面的函数 有的机器php.ini设置不起作用
        //要在RSAUtil的方法decrypt 加bcscale(0);这样一行代码才行
        //要不有的机器计算的时候会有小数点 就会失败
        bcscale(0);

        $bln = $keylen * 2 - 1;
        $bitlen = ceil($bln / 8);
        $arr = explode(' ', $string);
        $data = '';
        foreach($arr as $v){
            $v = $this->hex2dec($v);
            $v = bcpowmod($v, $d, $n);
            $data .= $this->int2byte($v);
        }
        return trim($data);
    }

    private function hex2dec($num){
        $char = '0123456789abcdef';
        $num = strtolower($num);
        $len = strlen($num);
        $sum = '0';
        for($i = $len - 1, $k = 0; $i >= 0; $i--, $k++){
            $index = strpos($char, $num[$i]);
            $sum = bcadd($sum, bcmul($index, bcpow('16', $k)));
        }
        return $sum;
    }

    private function int2byte($num){
        $arr = array();
        $bit = '';
        while(bccomp($num, '0') > 0){
            $asc = bcmod($num, '256');
            $bit = chr($asc) . $bit;
            $num = bcdiv($num, '256');
        }
        return $bit;
    }

    private function http_curl_post($url, $data, $Authorization = '', $timeout = 10)
    {

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
        if (!empty($Authorization)) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, array('Authorization: ' . $Authorization));
        }
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        $content = curl_exec($curl);
        curl_close($curl);

        return $content;
    }
}