<?php
/**
 * Created by PhpStorm.
 * User: lihe
 * Date: 2017/8/8
 * Time: 上午11:55
 */
namespace Xt\Publisher\Providers;

use Xt\Publisher\DefaultException;

class Sdk4399 extends ProviderAbstract{

    public function verifyToken($token = '', $option = [])
    {
        $url = 'http://m.4399api.com/openapi/oauth-check.html?';

        $param = [
            'state' => $token,
            'uid' => $option['uid']
        ];

        $response = file_get_contents($url . http_build_query($param));

        $response = json_decode($response, true);

        if ($response['code'] != 100){
            throw  new  DefaultException('error order');
        }

        return [
            'uid' =>  $response['result']['uid'],
            'username' => '',
            'original' => $response
        ];

    }

    public function notify()
    {
        $oriContent = file_get_contents('php://input');

        $param = [
            'orderid' => $oriContent['orderid'],
            'p_type'  => $oriContent['p_type'],
            'uid'     => $oriContent['uid'],
            'money'   => $oriContent['money'],
            'gamemoney' => $oriContent['gamemoney'],
            'secrect' => $this->option['secrect_key'],
            'mark'    => $oriContent['mark'],
            'time'    => $oriContent['time'],
        ];

        $sign = $oriContent['sign'];
        
        $this->check_sign($param, $sign);

        return [
            'transaction' => $param['mark'],
            'reference'   => $param['orderId'],
            'amount'      => rand($param['amount'] / 100, 2),
            'currency'    => '',
            'userId'      => $param['uid'],
        ];
    }

    private function check_sign($data,$sign){
        //删除生成sign不需要的数据 并不需要升序排序 按照文档中字段排序
        unset($data['p_type']);

        $sign_str = '';

        foreach ($data as $key =>  $value){
            $sign_str .= $value;
        }

        if (md5($sign_str) != $sign){
            throw new DefaultException('sign error');
        }
    }
}