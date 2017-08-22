<?php
/**
 * Created by PhpStorm.
 * User: lkl
 * Date: 2017/8/21
 * Time: 18:35
 */
namespace Xt\Publisher\Providers;

use Xt\Publisher\DefaultException;

class Iqy extends ProviderAbstract
{
    //由于没有账号验证 故直接返回
    public function verifyToken($token = '', $option = [])
    {
        return [
            'uid'      => $option['uid'],
            'username' => '',
            'original' => $option['uid']
        ];
    }

    public function notify()
    {
        $req = $_REQUEST;
        $data = array(
            'user_id'  => $req['user_id'],
            'role_id'  => $req['role_id'],
            'order_id' => $req['order_id'],
            'money'    => $req['money'],
            'time'     => $req['time'],
        );

        $userData = urldecode($req['userData']);
        $sign = $req['sign'];

        $str1 = '';
        foreach ($data as $k => $v) {
            $str1 .= "$v";
        }
        $mysign = md5($str1 . $this->app_key);

        if ($sign != $mysign) {
            throw new DefaultException('sign error');
        }

        // 平台参数
        $param['amount'] = $req['money'];                              // 总价.单位: 分
        $param['transaction'] = $userData;                              // 订单id
        $param['currency'] = 'CNY';                                                         // 货币类型
        $param['reference'] = $req['order_id'];                           // 第三方订单ID
        $param['userId'] = '';                                   // 第三方账号ID

        return $param;
    }

    public function success()
    {
        $data = [
            'status'   => '0',
            'message' => 'success'
        ];

        exit(json_encode($data));
    }
}