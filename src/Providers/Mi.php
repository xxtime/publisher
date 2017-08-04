<?php
/**
 * Created by PhpStorm.
 * User: lihe
 * Date: 2017/6/7
 * Time: 下午6:45
 */
namespace Xt\Publisher\Providers;

use Xt\Publisher\DefaultException;

class Mi extends ProviderAbstract
{

    public function verifyToken($token = '', $option = [])
    {
        //错误数组
        $error = [
            '1515' => 'appId 错误',
            '1516' => 'uid 错误',
            '1520' => 'session 错误',
            '1525' => 'signature 错误'
        ];

        // 小米验证的参数
        $params = array(
            'appId'   => $this->app_id,
            'session' => $token,
            'uid'     => $option['uid']
        );

        $params = array_filter($params);                                                                              // 去除空数据
        ksort($params);                                                                                               // 按照字段排序
        $text = http_build_query($params);
        $signature = hash_hmac("sha1", $text, $this->option['SecretKey'],
            false);                                                        // 转换成 URL 格式
        $params['signature'] = urlencode($signature);

        $url = "http://mis.migc.xiaomi.com/api/biz/service/verifySession.do?" . http_build_query($params);
        $response = file_get_contents($url);
        $result = json_decode($response, true);
        if ($result['errcode'] != 200) {
            $result['errcode'] = $error[$result['errcode']];
            throw new DefaultException(json_encode($result));
        }

        return [
            'uid'      => $option['uid'],
            'username' => '',
            'original' => (array)$result
        ];
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
    //appId=2882303761517239138&cpOrderId=9786bffc-996d-4553-aa33-f7e92c0b29d5&orderConsumeType=10&orderId=21140990160359583390&orderStatus=TRADE_SUCCESS&payFee=1&payTime=2014-09-05%2015:20:27&productCode=com.demo_1&productCount=1&productName=%E9%93%B6%E5%AD%901%E4%B8%A4&uid=100010&signature=1388720d978021c20aa885d9b3e1b70cec751496
    public function notify()
    {
        $orderStatus = $_REQUEST['orderStatus'];
        if ($orderStatus != 'TRADE_SUCCESS') {
            throw new DefaultException('errcode:3515');
        }

        $app_id = $this->app_id;
        if ($app_id != $_REQUEST['appId']) {
            throw new DefaultException('errcode:1515');
        }

        $sign = $_REQUEST['signature'];              // 签名

        $this->check_sign($sign);

        return [
            'transaction' => $_REQUEST['cpOrderId'],
            'reference'   => $_REQUEST['orderId'],
            'amount'      => rand($_REQUEST['payFee'] / 100, 2),
            'currency'    => '',
            'userId'      => $_REQUEST['uid']
        ];

    }

    //appId=2882303761517239138&cpOrderId=9786bffc-996d-4553-aa33-f7e92c0b29d5&orderConsumeType=10&orderId=21140990160359583390&orderStatus=TRADE_SUCCESS&payFee=1&payTime=2014-09-05%2015:20:27&productCode=com.demo_1&productCount=1&productName=%E9%93%B6%E5%AD%901%E4%B8%A4&uid=100010&signature=1388720d978021c20aa885d9b3e1b70cec751496
    public function check_sign($sign)
    {
        $req = $_REQUEST;
        unset($req['signature'], $req['_url']);
        $req = array_filter($req);
        ksort($req);

        $str = '';
        foreach ($req as $key => $value) {
            //$params_sign[$key] = urlencode( $value );
            $str .= $key . '=' . $value . '&';
        }

        $str = trim($str, '&');
        $secret = $this->option['SecretKey'];

        // hmac-sha1带密钥(secret)的哈希算法
        $signature = hash_hmac("sha1", $str, $secret, false);
        if (strtolower($sign) != $signature) {
            throw new DefaultException('sign error');
        }
    }

    public function success()
    {
        $result = [
            'errcode' => 200
        ];

        exit(json_encode($result));
    }
}