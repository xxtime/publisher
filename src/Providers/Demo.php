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
            'transaction' => '20170526024456001467000368', // 平台订单ID;   重要参数
            'reference'   => '1234567890ABCD',             // 发行商订单ID; 必选参数
            'amount'      => 4.99,                         // 充值金额
            'currency'    => 'CNY',                        // 货币类型
            'userId'      => '3001-2001234',               // 终端用户ID
        ];
    }


    public function success()
    {
        exit('success');
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
        return [
            'reference' => '',      // 发行商订单号
            'raw'       => []       // 发行渠道返回的原始信息, 也可添加额外参数
        ];
    }

}