<?php
/**
 * Created by PhpStorm.
 * User: lihe
 * Date: 2017/6/7
 * Time: 下午5:10
 */
namespace Xt\Publisher\Providers;

use Phalcon\Config;
use Symfony\Component\Yaml\Yaml;
use Xt\Publisher\DefaultException;

class Uc extends ProviderAbstract
{
    public function verifyToken($token = '', $option = [])
    {
        //md5(sid=xxxxx + apiKey) sign拼接组成是否为“sid=sid值+apikey值”，并且需要用小写
        $sign_param = [
            'sid'    => strtolower($token),
            'apiKey' => strtolower($this->app_key)
        ];
        $sign = md5(implode('', $sign_param));

        $param = [
            'id'   => time(),
            'data' => ['sid' => $token],
            'game' => ['gameId' => $this->app_id],
            'sign' => $sign

        ];
        $response = $this->http_curl_post($url, json_encode($param));
        $result = json_decode($response, true);
        //如果遇到错误 则抛出错误
        if ($result['state']['code'] != 1) {
            throw new DefaultException($response);
        }

        return [
            'uid'      => $option['uid'],
            'username' => $result['data']['nickName'],
            'original' => $response
        ];
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
        $request = file_get_contents("php://input");

        $responseData = json_decode($request, true);

        if (empty($responseData)) {
            throw new DefaultException('error order');
        }

        //uc告知订单失败
        if ($responseData['data']['orderStatus'] == 'F') {
            throw new DefaultException('error oder');
        }

        //app_id验证
        if ($responseData['data']['gameId'] != $this->app_id) {
            throw new DefaultException('error gameId');
        }

        $param['orderId'] = $responseData['data']['orderId'];
        $param['gameId'] = $responseData['data']['gameId'];
        $param['accountId'] = $responseData['data']['accountId'];
        $param['creator'] = $responseData['data']['creator'];
        $param['payWay'] = $responseData['data']['payWay'];
        $param['amount'] = $responseData['data']['amount'];
        $param['callbackInfo'] = $responseData['data']['callbackInfo'];
        $param['orderStatus'] = $responseData['data']['orderStatus'];
        $param['failedDesc'] = $responseData['data']['failedDesc'];
        $param['cpOrderId'] = $responseData['data']['cpOrderId'];
        $sign = $responseData['sign'];

        $this->check_sign($param, $sign, $this->app_key);

        return [
            'transactionId'        => $param['cpOrderId'],
            'transactionReference' => $param['orderId'],
            'amount'               => $param['amount'],
            'currency'             => '',
            'userId'               => $param['accountId']
        ];
    }

    private function check_sign($data, $sign, $appKey)
    {
        $data_ksort = ksort($data);
        $sign_str = '';
        foreach ($data_ksort as $k => $v) {
            $sign_str .= $k . '=' . $v;
        }
        $sign_str .= 'apiKey' . '=' . $appKey;

        if ($sign != md5($sign_str)) {
            throw new DefaultException('sign error');
        }
    }

    public function success()
    {
        exit('SUCCESS');
    }
}