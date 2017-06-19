<?php
/**
 * Created by PhpStorm.
 * User: lihe
 * Date: 2017/6/7
 * Time: 上午10:39
 */
namespace Xt\Publisher\Providers;

use Xt\Publisher\DefaultException;

class Huawei extends ProviderAbstract
{

    //华为登陆验证
    public function verifyToken($token = '', $option = [])
    {
        $token = str_replace(' ', '+', $token);
        $content = $this->app_id . $option['custom'] . $option['uid'];

        $public_key = "-----BEGIN PUBLIC KEY-----\n" .
            chunk_split($this->option['public_key'], 64, "\n") .
            '-----END PUBLIC KEY-----';

        $openssl_public_key = openssl_get_publickey($public_key);

        $ok = openssl_verify($content, base64_decode($token), $openssl_public_key, OPENSSL_ALGO_SHA256);
        openssl_free_key($openssl_public_key);

        if (!$ok) {
            throw new DefaultException('login failed');
        }
        $result['uid'] = $option['uid'];
        return array('uid' => $option['uid'], 'username' => '', 'original' => $result);
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
        // 订单未成功则不处理
        $oriContent = file_get_contents('php://input');

        if (!isset($oriContent)) {
            throw new DefaultException('fail');
        }

        parse_str($oriContent, $data);

        // 平台参数
        $param['amount'] = round($data['amount'], 2);                           // 总价.单位:分
        $param['transaction'] = $data['requestId'];                             // 订单id
        // 支付方式
        $param['currency'] = 'CNY';                                             // 货币类型
        $param['reference'] = $data['order_id'];                                // 第三方订单ID
        $param['userId'] = '';                                                  // 签名

        // 检查签名
        $this->check_sign($data['sign'], $data);

        return $param;
    }


    // 检查签名
    public function check_sign($sign = '', $reqs)
    {
        $data = $reqs;
        unset($data['_url'], $data['plat'], $data['sign'], $data['signType'], $data['zone'], $data['gameid']);
        ksort($data);

        $public_key = "-----BEGIN PUBLIC KEY-----\n" .
            chunk_split($this->option['public_key'], 64, "\n") .
            '-----END PUBLIC KEY-----';

        $pubKeyId = openssl_pkey_get_public($public_key);

        $httpStr = is_array($data) ? http_build_query($data) : $data;
        $signature = base64_decode($sign);

        if (!openssl_verify($httpStr, $signature, $pubKeyId)) {
            throw new DefaultException('sign error');
        }
    }


    public function success()
    {
        echo json_encode(array('result' => 0));
        exit;
    }

    /**
     * @param array $parameter
     *    $parameter = [
     *        'transaction'  => '', // 平台订单ID
     *        'amount'       => '', // 金额
     *        'currency'     => '', // 货币种类
     *        'product_id'   => '', // 产品ID
     *        'product_name' => '', // 产品名称
     *        'raw'          => '', // 用户登录发行渠道返回的原始数据， verifyToken 方法返回的 original字段
     *    ];
     * @return array
     */
    public function tradeBuild($parameter = [])
    {
        $data['userID'] = $parameter['raw']['uid'];
        $data['applicationID'] = $this->app_id;
        $data['amount'] = $parameter['amount'];
        $data['productName'] = $parameter['product_name'];
        $data['requestId'] = $parameter['transaction'];
        $data['productDesc'] = $parameter['product_name'];

        ksort($data);
        $str = '';
        foreach ($data as $k => $v) {
            $str .= "$k=$v&";
        }
        $str = trim($str, '&');

        // 生成签名
        $sign = $this->rsa_sign($str);
        $data['userName'] = $parameter['raw']['uid'];
        $data['sign'] = $sign;

        return [
            'reference' => '',      // 发行商订单号
            'raw'       => $data       // 发行渠道返回的原始信息, 也可添加额外参数
        ];
    }

    private function rsa_sign($str)
    {
        $private_key = "-----BEGIN PRIVATE KEY-----\n" .
            chunk_split($this->option['private_key'], 64, "\n") .
            '-----END PRIVATE KEY-----';
        $private_key_id = openssl_pkey_get_private($private_key);
        $signature = false;
        openssl_sign($str, $signature, $private_key_id);
        $sign = base64_encode($signature);
        return $sign;
    }

}