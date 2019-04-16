<?php
/**
 * Created by PhpStorm.
 * User: lihe
 * Date: 2019/4/15
 * Time: 5:58 PM
 */

namespace Xt\Publisher\Providers;

use Xt\Publisher\DefaultException;

class Nubia extends ProviderAbstract
{

    public function verifyToken($token = '', $option = [])
    {
        $url = 'https://niugamecenter.nubia.com/VerifyAccount/CheckLogined?';
        $data = [
            'uid' => $option['uid'], // 努比亚账号id
            'data_timestamp' => time(),
            'game_id' =>  $option['custom'],    //游戏id
            'session_id' => $token,
        ];
        $data['sign'] = $this->Sign($data, $this->app_id, $this->option['secrect_key']);
        $response = $this->http_curl_post($url, $data);
        $result = json_decode($response, true);
        if ($result['code'] != 0) {
            throw new DefaultException('verity error');
        }

        return [
            'uid' => $option['uid'],
            'username' => '',
            'original' => $result
        ];
    }

    public function notify()
    {
        // TODO: Implement notify() method.
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

    /**
     * 生成签名方法
     * @param $data
     * @param $app_id
     * @param $app_secret
     * @return string
     */
    public function Sign($data, $app_id, $app_secret) {
        ksort($data);
        $result = '';
        foreach ($data as $key => $value) {
            $result .= $key.'&'.$value;
        }
        $result .= ':' . $app_id . ':' . $app_secret;

        return md5($result);
    }
}