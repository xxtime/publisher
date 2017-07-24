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


        return [
            'uid'   => $response['openid'],
            'username'  => $playerResponse['nickname'],
            'original'  => $playerResponse
        ];

    }

    public function notify(){
        //private_key mod_key 在 payment_key 中 需要解密 提取 payment_key 是base64加密
        $pay_key = base64_decode($this->payment_key);
        $keys = explode('+', $pay_key);
        //获得私钥
        $private = $keys[0];
        $mod_key = $keys[1];
        //组装私钥格式
        $private_key = "-----BEGIN PRIVATE KEY-----\n" .
            chunk_split($private, 64, "\n") .
            '-----END PRIVATE KEY-----';

        $transdata = $_REQUEST['transdata'];
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