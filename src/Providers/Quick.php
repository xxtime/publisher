<?php
/**
 * Created by PhpStorm.
 * User: lihe
 * Date: 2019/2/11
 * Time: 9:49 AM
 */
namespace Xt\Publisher\Providers;

use Xt\Publisher\DefaultException;

class Quick extends ProviderAbstract {

    // 登录验证
    public function verifyToken($token = '', $option = [])
    {
        $url = 'http://checkuser.sdk.quicksdk.net/v2/checkUserInfo?';
        $uid = $option['uid'];
        $channel_id = $option['custom']['channel_id'];
        $product_code = $this->option['product_code'];

        $param = [
            'token' => $token,
            'product_code' => $product_code,
            'uid' => $uid,
            'channel_code' => ''
        ];

        $params = '';
        foreach ($param as $value) {
            $params .= '&' . $value;
        }
        $params = trim($params, '&');

        $result = file_get_contents($url.$params);  // 通过则 "1", 否则则 "0"
        if (!$result) {
            throw  new  DefaultException($result);
        }

        return [
            'uid' => $uid,
            'channel_id' => $channel_id,
            'username' => '',
            'original' => $result
        ];
    }

    // 充值验证
    public function notify()
    {
        $nt_data = $_REQUEST['nt_data'];
        $sign = $_REQUEST['sign'];
        $md5Sing = $_REQUEST['md5Sign'];
        $cB_key = trim($this->option['callBack_key'], '\'');
        // 解密
        $nt_data_de = $this->decode($nt_data, $cB_key);
        $param = $this->decodeXML($nt_data_de);

        // 验证签名
        $this->check_sign($md5Sing, $nt_data, $sign, $cB_key);

        return [
            'transaction' => $param['game_order'],
            'reference'   => $param['order_no'],
            'amount'      => $param['amount'],      // 金额 元
            'currency'    => 'CNY',
            'userId'      => $param['channel_uid'],
            'channel_id'     => $param['channel']      // 充值渠道 ID
        ];
    }

    // XML解析
    public function decodeXML($xml) {
        $data = simplexml_load_string($xml);
        if ($data['status']) {  // 充值失败
            throw new DefaultException('Recharge Error');
        }
        // 要求客户端 extras_params 传递 server_id, user_id, role_id 参数，并且json_encode
        $gameInfo = json_decode($data['extras_params'], true);
        $param['amount'] = $data['amount']; // 成交金额单位 元
        $param['partner_order_id'] = $data['order_no'];
        $param['order_id'] = $data['game_order'];
        $param['pay_way'] = $data['channel'];      // 此处是channelID, 并不是一个channel
        $param['server_id'] = $gameInfo['server_id'];
        $param['user_id'] = $gameInfo['user_id'];
        $param['role_id'] = $gameInfo['role_id'];

        return $param;
    }

    // 检查签名
    public function check_sign($sign = '', $nt_data, $quickSign, $cB_key)
    {
        $mdSign = $this->getSign($nt_data, $quickSign, $cB_key);
        if ($sign != $mdSign) {
            throw new DefaultException('sign error');
        }
    }

    /**
     * 解密方法
     * $strEncode 密文
     * $keys 解密密钥 为游戏接入时分配的 callback_key
     */
    public function decode($strEncode, $keys) {
        if(empty($strEncode)){
            return $strEncode;
        }
        preg_match_all('(\d+)', $strEncode, $list);
        $list = $list[0];
        if (count($list) > 0) {
            $keys = self::getBytes($keys);
            for ($i = 0; $i < count($list); $i++) {
                $keyVar = $keys[$i % count($keys)];
                $data[$i] =  $list[$i] - (0xff & $keyVar);
            }
            return self::toStr($data);
        } else {
            return $strEncode;
        }
    }

    /**
     * 转化字符串
     */
    private static function toStr($bytes) {
        $str = '';
        foreach($bytes as $ch) {
            $str .= chr($ch);
        }
        return $str;
    }

    /**
     * 转成字符数据
     */
    private static function getBytes($string)
    {
        $bytes = array();
        for ($i = 0; $i < strlen($string); $i++) {
            $bytes[] = ord($string[$i]);
        }
        return $bytes;
    }

    /**
     * 计算游戏同步签名
     */
    public static function getSign($nt_data, $sign,$callbackkey){

        return md5($nt_data.$sign.$callbackkey);
    }
}