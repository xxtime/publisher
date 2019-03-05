<?php
/**
 * Created by PhpStorm.
 * User: lihe
 * Date: 2019/2/22
 * Time: 12:07 PM
 */

namespace Xt\Publisher\Providers;

use Xt\Publisher\DefaultException;

class Huawei2 extends ProviderAbstract
{
    private $url;
    // 用户等级|ts时间
    // url: http://v3-account.com/publisher/huawei2?app_id=100100&token=xxzzzz&custom=3|122331111&uid=11111
    public function verifyToken($token = '', $option = [])
    {
        $this->url = "https://gss-cn.game.hicloud.com/gameservice/api/gbClientApi";

        $playerInfo = explode("|", $option['custom']);

        $params['method'] = 'external.hms.gs.checkPlayerSign';
        $params['appId'] = $this->app_id;
        $params['cpId'] = $this->option['cp_id'];
        $params['playerId'] = $option['uid'];
        $params['playerLevel'] = $playerInfo[0];
        $params['ts'] = $playerInfo[1];
        $params['playerSSign'] = $token;
        $private_key = "-----BEGIN PRIVATE KEY-----\n" .
            chunk_split($this->option['private_key'], 64, "\n") .
            '-----END PRIVATE KEY-----';
        // 生成cp端签名
        $params['cpSign'] = $this->sign($params, $private_key);
        // 签名验证
        $response = $this->call($params);
        $result = json_decode($response, true);

        if (!$this->verify($result)) {
            throw new DefaultException("verify failed!");
        }

        return [
            'uid'      => $option['uid'],
            'username' => '',
            'original' => $option
        ];
    }

    /**
     * 验证渠道返回的签名是否正确
     * @param $response
     * @return bool
     */
    private function verify($response) {
        if($response->rtnCode == 0) {
            $rtnSign = base64_decode($response->rtnSign);
            unset($response->rtnSign);
            $fields = [];
            foreach ($response as $key => $value) {
                $fields[] = $key . "=" . rawurlencode($value);
            }
            usort($fields, "strcmp");
            $sbs = implode("&", $fields);
            return openssl_verify($sbs, $rtnSign, $this->option['public_key'], OPENSSL_ALGO_SHA256) == 1;
        }
        return true;
    }

    public function notify()
    {
        // 订单未成功则不处理
        $oriContent = file_get_contents('php://input');

        if (!isset($oriContent)){
            throw new DefaultException('fail');
        }

        parse_str($oriContent, $data);

        $params['amount'] = round($data['amount'], 2);
        $params['transaction'] = $data['requestId'];
        $params['currency'] = 'CNY';
        $params['reference'] = $data['orderId'];
        $params['userId'] = '';

        $this->checkSign($data['sign'], $data);

        return $params;
    }

    // 生成cp_sign
    private function sign($params, $privateKey)
    {
        ksort($params);
        $query = '';
        foreach ($params as $key => $value) {
            $query .= $key . '='. rawurldecode($value) . '&';
        }
        $query = trim($query, '&');
        openssl_sign($query, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        return base64_encode($signature);
    }

    /**
     * 发送网络请求验证签名
     * @param $param
     */
    public function call($params)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);

        $curl_result = curl_exec($ch);
        curl_close($ch);

        return $curl_result;
    }

    /**
     * 检测签名
     * @param $params
     */
    public function checkSign($sign = '', $reqs){
        $data = $reqs;
        unset($data['sign']);
        ksort($data);

        $public_key = "-----BEGIN PUBLIC KEY-----\n" .
            chunk_split($this->option['public_key'], 64, "\n") .
            '-----END PUBLIC KEY-----';

        $pubKeyId = openssl_pkey_get_public($public_key);
        $httpStr = '';
        foreach ($data as $key => $value){
            $httpStr .= $key . '=' . $value . '&';
        }
        $httpStr = rtrim($httpStr, '&');
        $sign = str_replace(' ', '+', $sign);
        $signature = base64_decode($sign);

        if (!openssl_verify($httpStr, $signature, $pubKeyId, OPENSSL_ALGO_SHA1)) {
            throw new DefaultException('sign error');
        }
    }
}