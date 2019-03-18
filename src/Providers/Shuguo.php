<?php
/**
 * Created by PhpStorm.
 * User: chencheng
 * Date: 2019/3/15
 * Time: 下午13:42
 */
namespace Xt\Publisher\Providers;

use Xt\Publisher\DefaultException;

class shuguo extends ProviderAbstract
{

    public function verifyToken($token = '', $option = [])
    {

        $url = "http://www.hnshuguo.com:8080/u8server/user/verifyAccount?";
        $param = [
            'userID'  => $option['uid'],
            'token'  => $token
        ];
        $str="";
        foreach ($param as $key=>$value)
        {
            $str.=$key."=".$value;
        }
        $param['sign'] = md5($str.$this->option['AppSecret']);
        unset($param['appkey']);

        $url .=  http_build_query($param);
        $response = file_get_contents($url);
        $result = json_decode($response, true);
        if ($result['state'] == 1) {
            return [
                'uid'      => $option['uid'],
                'username' => '',
                'original' => (array)$result
            ];
        }
        //如果验证失败就抛出异常
        throw new DefaultException($response);
    }

    /**
     *  return [
     * 'transactionId'        => '20170526024456001467000368', // 平台订单ID;   重要参数  网站的订单ID
     * 'transactionReference' => '1234567890',                 // 发行商订单ID; 必选参数  渠道订单ID
     * 'amount'               => 4.99,                         // 充值金额
     * 'currency'             => 'CNY',                        // 货币类型
     * 'userId'               => '3001-2001234',               // 终端用户ID
     * ];
     */
    public function notify()
    {
        $oriContent = file_get_contents('php://input');
        if (!isset($oriContent)){
            throw new DefaultException('fail');
        }
        $result = json_decode($oriContent,true);
        $data = $result['data'];
        $state = $result['state'];
        $params['amount'] = round($data['money'], 2);
        $params['transaction'] = $data['cpOrderID'];
        $params['currency'] = 'CNY';
        $params['reference'] = $data['orderID'];
        $params['userId'] = '';
        $this->check_sign($data,$state);
        return $params;
    }

    public function success()
    {
        exit('SUCCESS');
    }

    private function check_sign($data,$state)
    {
        if($state!=1)
        {
            throw new DefaultException('state error');
        }
        unset($data['cpOrderID']);
        unset($data['signType']);
        unset($data['sign']);
        ksort($data);
        $signStr = http_build_query($data);
        $signStr = $signStr."&".$this->option['AppSecret'];
        if(!$data['sign'] || $data['sign'] != md5($signStr))
        {
            throw new DefaultException('sign error');
        }
    }
}
