<?php
/**
 * Created by PhpStorm.
 * User: lihe
 * Date: 2017/6/13
 * Time: 下午4:14
 */
namespace Xt\Publisher\Providers;

use Xt\Publisher\DefaultException;

class Baiwen extends ProviderAbstract
{
    //由于百文没有账号验证 故直接返回
    public function verifyToken($token = '', $option = [])
    {
        return [
            'uid'      => $option['uid'],
            'username' => '',
            'original' => $option['uid']
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
    public function notify()
    {
        $param['user_id'] = $_REQUEST['user_id'];               //平台账号ID
        $param['order_id'] = $_REQUEST['order_id'];             //订单ID  渠道的平台id
        $param['total_money'] = $_REQUEST['total_money'];       //金额
        $param['time'] = $_REQUEST['time'];                     //登入的游戏服务器id
        $sign = $_REQUEST['sign'];                              //签名

        $this->check_sign($param, $sign);

        return [
            'transactionId'        => $param['order_id'],
            'transactionReference' => '',
            'amount'               => $param['total_money'],
            'currency'             => '',
            'userId'               => $param['user_id']
        ];

    }

    private function check_sign($data, $sign)
    {
        //数组排序
        ksort($data);
        $result = '';
        foreach ($data as $k => $v) {
            $result .= $k . '=' . $v . '&';
        }
        $result = trim($result, '&');
        if (md5($result . $this->app_key) != $sign) {
            throw new DefaultException('sign error');
        }
    }

    public function success()
    {
        exit('true');
    }
}