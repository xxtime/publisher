<?php

namespace Xt\Publisher\Providers;


class Demo extends ProviderAbstract
{

    public function verifyToken($token = '', $option = [])
    {
        // do something

        return [
            'uid'      => 123456,                                   // 用户ID
            'username' => 'Danielle',                               // 用户名
            'original' => [],                                       // 原始信息
        ];
    }


    public function notify()
    {
        // do something

        return [
            'transactionId'        => '20170526024456001467000368', // 平台订单ID;   重要参数
            'transactionReference' => '1234567890ABCD',             // 发行商订单ID; 必选参数
            'amount'               => 4.99,                         // 充值金额
            'currency'             => 'CNY',                        // 货币类型
            'userId'               => '3001-2001234',               // 终端用户ID
        ];
    }


    public function success()
    {
        exit('success');
    }


    /**
     * @param array $parameter
     *    $parameter = [
     *        'transaction'  => '',
     *        'amount'       => '',
     *        'currency'     => '',
     *        'product_id'   => '',
     *        'product_name' => '',
     *    ];
     * @return array
     */
    public function tradeBuild($parameter = [])
    {
        return [
            'transactionReference' => '',   // 发行商订单号
            'raw'                  => ''    // 原始返回数组
        ];
    }

}