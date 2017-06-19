<?php
/**
 * Created by PhpStorm.
 * User: lihe
 * Date: 2017/6/7
 * Time: 上午10:39
 */
namespace Xt\Publisher\Providers;

use Xt\Publisher\DefaultException;

class Meizu extends ProviderAbstract
{
    //魅族登陆验证
    public function verifyToken($token = '', $option = [])
    {
        $url = 'https://api.game.meizu.com/game/security/checksession';

        $ts = time();
        $sign = md5('app_id=' . $this->app_id . '&session_id=' . $token . '&ts=' . $ts . '&uid=' . $option['uid'] . ':' . $this->option['secret_key']);

        $param = [
            'app_id'     => $this->app_id,
            'session_id' => $token,
            'uid'        => $option['uid'],
            'ts'         => $ts,
            'sign_type'  => 'md5',
            'sign'       => $sign,
        ];

        $param = http_build_query($param);

        $response = file_get_contents($url, false, stream_context_create(array(
            'http' => array(
                'protocol_version' => '1.1',
                'timeout'          => 30,
                'method'           => 'POST',
                'header'           => 'Content-Type:application/x-www-form-urlencoded;',
                'user_agent'       => 'xxtime.com',
                'content'          => $param // 字符串
            )
        )));

        $result = json_decode($response, true);

        //如果有异常 抛出异常
        if ($result['code'] != 200) {
            throw new DefaultException($result['message']);
        }

        $result['uid'] = $option['uid'];
        // TODO: Implement verifyToken() method.
        return array('uid' => $option['uid'], 'username' => '', 'original' => $result);
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
        // 订单未成功则不处理
        $trade_status = $_REQUEST['trade_status'];
        if ($trade_status != '3') {
            throw new DefaultException('fail');
        }

        // 平台参数
        $param['amount'] = $_REQUEST['total_price'];                    // 总价
        $param['transaction'] = $_REQUEST['cp_order_id'];               // 订单id
        $param['currency'] = 'CNY';                                     // 货币类型
        $param['reference'] = '';                    // 第三方订单ID
        $param['userId'] = '';                            // 第三方账号ID

        // 检查签名
        $this->check_sign($_REQUEST['sign']);

        return $param;
    }

    // 检查签名
    public function check_sign($sign = '')
    {
        $req = $_REQUEST;
        $data = array(
            'app_id'            => $req['app_id'],
            'buy_amount'        => $req['buy_amount'],
            'cp_order_id'       => $req['cp_order_id'],
            'create_time'       => $req['create_time'],
            'notify_id'         => $req['notify_id'],
            'notify_time'       => $req['notify_time'],
            'order_id'          => $req['order_id'],
            'partner_id'        => $req['partner_id'],
            'pay_time'          => $req['pay_time'],
            'pay_type'          => $req['pay_type'],
            'product_id'        => $req['product_id'],
            'product_per_price' => $req['product_per_price'],
            'product_unit'      => $req['product_unit'],
            'total_price'       => $req['total_price'],
            'trade_status'      => $req['trade_status'],
            'uid'               => $req['uid'],
            'user_info'         => $req['user_info']
        );

        $str = '';
        foreach ($data as $k => $v) {
            $str .= "$k=$v&";
        }
        $str = trim($str, '&');
        $str = $str . ':' . $this->option['secret_key'];

        if (strtolower($sign) != md5($str)) {
            throw new DefaultException('sign error');
        }
    }


    public function success()
    {
        echo json_encode(array('code' => 200, 'message' => '', 'value' => '', 'redirect' => ''));
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
        $time = time();

        $data = array(
            'app_id'            => $this->app_id,
            'buy_amount'        => 1,
            'cp_order_id'       => $parameter['transaction'],
            'create_time'       => $time,
            'pay_type'          => 0,
            'product_body'      => $parameter['product_name'],
            'product_id'        => $parameter['product_id'],
            'product_per_price' => (string)$parameter['amount'],
            'product_subject'   => '购买' . (int)$parameter['amount'] . '枚金币',
            'product_unit'      => '',
            'total_price'       => (string)$parameter['amount'],
            'uid'               => $parameter['raw']['uid'],
            'user_info'         => '',
        );

        $str = '';

        foreach ($data as $k => $v) {
            $str .= "$k=$v&";

        }
        $str = trim($str, '&');
        $str = $str . ':' . $this->option['secret_key'];
        $data['sign_type'] = 'md5';
        $data['sign'] = md5($str . ':' . $this->option['secret_key']);

        return [
            'reference' => '',   // 发行商订单号
            'raw'       => $data    // 原始返回数组
        ];
    }
}