<?php
/**
 * Created by PhpStorm.
 * User: lihe
 * Date: 2017/6/7
 * Time: 上午10:39
 */
namespace Xt\Publisher\Providers;

use Xt\Publisher\DefaultException;

class  Baidu extends ProviderAbstract
{
    private $_uid;

    private $_cpOrder;

    private $_orderMoney;


    //百度登陆验证
    public function verifyToken($token = '', $option = [])
    {
        $url = 'http://querysdkapi.baidu.com/query/cploginstatequery?';

        //sign = MD5(AppID+AccessToken+SecretKey)
        $data = [
            'AppID'       => $this->app_id,
            'AccessToken' => $token,
            'SecretKey'   => $this->option['secret_key']
        ];
        $sign = md5(implode('', $data));

        $param = [
            'AppID'       => $this->app_id,
            'AccessToken' => $token,
            'Sign'        => $sign
        ];

        $param = http_build_query($param);

        $url = $url . $param;
        $response = file_get_contents($url);
        $result = json_decode($response, true);
        //如果有异常 抛出异常
        if ($result['ResultCode'] != 1) {
            throw new DefaultException($response);
        }

        $content = json_decode(base64_decode($result['Content']), true);


        // TODO: Implement verifyToken() method.
        return array('uid' => $option['uid'], 'username' => '', 'original' => $content);
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
        $data = json_decode(base64_decode($_REQUEST['Content']), true);
        if ($data['OrderStatus'] == 0) {
            throw new DefaultException('error order');
        }
        
        //查询app_id是否匹配
        $app_id = $this->app_id;
        $secretKey = $this->option['secret_key'];

        if ($app_id != $_REQUEST['AppID'] && $this->_uid != $data['UID'] && $this->_cpOrder != $_REQUEST['CooperatorOrderSerial'] && doubleval($this->_orderMoney) != $data['OrderMoeny']) {
            throw new DefaultException('91');
        }

        $sign = $_REQUEST['Sign'];

        //验证签名
        $this->check_sign($data, $sign, $secretKey);

        //数组组装
        // 用户实际付款额=订单金额-优惠券金额, 为防止优惠券额度大于付款额度的处理待补充
        $amount = ($data['OrderMoney'] > $data['VoucherMoney']) ? $data['OrderMoney'] - $data['VoucherMoney'] : $data['OrderMoney'];
        $transactionReference = $_REQUEST['CooperatorOrderSerial'];
        $transactionId = $_REQUEST['OrderSerial'];
        $userId = $data['UID'];
        $currency = '';

        return [
            'transaction' => $transactionReference,
            'reference'   => $transactionId,
            'amount'      => $amount,
            'currency'    => $currency,
            'userId'      => $userId
        ];
    }

    private function check_sign($data, $sign, $secretKey)
    {
        $req = $_REQUEST;

        $dataArr = [
            'AppID'                 => $req['AppID'],
            'OrderSerial'           => $req['OrderSerial'],
            'CooperatorOrderSerial' => $req['CooperatorOrderSerial'],
            'Content'               => $req['Content'],
            'SecretKey'             => $secretKey
        ];

        $signature = md5(implode('', array_values($dataArr)));

        if (strtolower($sign) != strtolower($signature)) {
            throw new DefaultException('sign error');
        }
    }

    //由于百度需要验证金额和订单号 以及uid 故需特殊处理
    public function tradeBuild($parameter){
        //获取需要验证的信息
        $this->_cpOrder = $parameter['transaction'];
        $this->_orderMoney = $parameter['amount'];
        $this->_uid = $parameter['raw']['UID'];
        return [
            'reference' => '',      // 发行商订单号
            'raw'       => $parameter       // 发行渠道返回的原始信息, 也可添加额外参数
        ];
    }

    public function success()
    {
        $AppID = $this->app_id;
        $AppKey = $this->app_key;
        $ResultCode = 1;
        $sign = md5($AppID . $ResultCode . $AppKey);

        //数据组装
        $result = [
            'AppID'      => $AppID,
            'ResultCode' => $ResultCode,
            'ResultMsg'  => 'success',
            'Sign'       => $sign,
            'Content'    => ''
        ];

        //数据返回
        exit(json_encode($result));
    }
}
