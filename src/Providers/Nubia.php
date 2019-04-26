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
        $url = 'https://niugamecenter.nubia.com/VerifyAccount/CheckLogined';
        $data = [
            'uid' => $option['uid'], // 努比亚账号id
            'data_timestamp' => strval(time()),
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

    // 创建订单
    public function tradeBuild($parameter = [])
    {
        $data['app_id'] = $this->app_id;
        $data['uid'] = $parameter['raw']['uid'];
        //$data['game_id'] = $parameter['raw']['game_id'];  // 不加签名可以不传递
        $data['cp_order_id'] = $parameter['transaction'];
        $data['amount'] = $parameter['amount'];
        $data['product_name'] = $parameter['product_name'];
        $data['product_des'] = $parameter['product_des'];
        $data['number'] = $parameter['raw']['number'];
        $data['data_timestamp'] = strval(time());
        $data['cp_order_sign'] = $this->Sign($data, $this->app_id, $this->option['secrect_key']);
        return [
            'reference' => '',
            'raw' => $data,
        ];
    }


    public function notify()
    {
        $resquest = $_REQUEST;
        if ($_REQUEST['pay_suceess'] != 1) {
            throw new DefaultException('pay error');
        }

        $data = [
            'order_no' => $resquest['order_no'],
            'data_timestamp' => $resquest['data_timestamp'],
            'pay_success' => $resquest['pay_success'],
            'app_id' => $resquest['app_id'],
            'uid' => $resquest['uid'],
            'amount' => $resquest['amount'],
            'product_name' => $resquest['product_name'],
            'prodcut_des' => $resquest['prodcut_des'],
            'number' => $resquest['number']
        ];

        if ($this->Sign($data, $this->app_id, $this->option['secrect_key']) != $resquest['sign']) {
            throw  new DefaultException('sign error');
        }

        return [
            'transaction' => $resquest['order_no'],
            'reference' => $_REQUEST['order_serial'],
            'amount' => $_REQUEST['amount'],          //平台是以元为单位
            'currency' => 'CNY',
            'userId' => $_REQUEST['uid']
        ];
    }

    private function http_curl_post($url, $data, $extend = array())
    {

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_NOBODY, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
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
    public function Sign($data, $app_id, $app_secret)
    {
        $result = '';
        ksort($data);
        foreach ($data as $key => $value) {
            $result .= '&'. $key . '=' . urldecode($value);
        }
        $result .= ':' . $app_id . ':' . $app_secret;
        return md5(trim($result, '&'));
    }
}