<?php
/**
 * Created by PhpStorm.
 * User: lihe
 * Date: 2017/6/7
 * Time: 上午10:39
 */
namespace Xt\Publisher\Providers;

use Phalcon\Config;
use Symfony\Component\Yaml\Yaml;
use Xt\Publisher\DefaultException;

class Feiliu extends ProviderAbstract
{
    //飞流登陆验证
    public function verifyToken($token = '', $option = [])
    {
        $config = array(
            "digest_alg"       => "sha512",
            "private_key_bits" => 4096,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
        );

// Create the private and public key
        $res = openssl_pkey_new($config);

        dd($res);

        $iv_hex = '1234';

        $data = '5678';

        $loginToken = openssl_encrypt($data, 'AES-128-CBC', '9b3dee09e07ceabeb640rr685c3859c4', false,
            hex2bin(12345678));

        $data = openssl_decrypt($loginToken, 'AES-128-CBC', '9b3dee09e07ceabeb640rr685c3859c4', false,
            hex2bin('abcdefgh'));

        dd($loginToken, $data);

    }

    public function notify()
    {
        $data = json_decode($this->request->get(), true);

        if ($data['status'] !== 0) {
            throw new DefaultException('fail');
        }

        // 平台参数
        $param['amount'] = round($data['amount'] / 100, 2);                    // 总价.单位: 分
        $param['transactionId'] = $data['flOrderId'];                        // 订单id
        $param['currency'] = 'CNY';                                           // 货币类型
        $param['transactionReference'] = $data['cpOrderId'];                // 第三方订单ID
        $param['userId'] = $data['userId'];                                   // 第三方账号ID

        // 检查签名
        $this->check_sign($data['sign']);

        return $param;
    }

    public function check_sign($sign = '')
    {
        $req = json_decode($this->request->get(), true);
        unset($req['sign']);
        ksort($req);

        $str = '';
        foreach ($req as $k => $v) {
            $str .= "$k=$v&";
        }
        $str = trim($str, '&');

        if (strtolower($sign) != md5($str)) {
            throw new DefaultException('sign error');
        }
    }

    public function success()
    {
        echo json_encode(array('code' => 0, 'tips' => 'success'));
        exit;
    }
}