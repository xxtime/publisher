<?php
/**
 * Created by PhpStorm.
 * User: lkl
 * Date: 2017/8/18
 * Time: 18:12
 */
namespace Xt\Publisher\Providers;

use Xt\Publisher\DefaultException;

class Yiwan extends ProviderAbstract
{

    public function verifyToken($token = '', $option = [])
    {
        $openid = $option['openid'];
        $sign = md5($openid . '|' . $token . '|' . $this->app_key);

        //如果有异常 抛出异常
        if ($option['sign'] != $sign) {
            throw new DefaultException('sign error');
        }

        return array('uid' => $option['openid'], 'username' => '', 'original' => $option);
    }

    public function notify()
    {
        $req = $_REQUEST;

        $data = array(
            'serverid' => $req['serverid'],
            'custominfo' => $req['custominfo'],
            'openid' => $req['openid'],
            'ordernum' => $req['ordernum'],
            'status' => $req['status'],
            'paytype' => $req['paytype'],
            'amount' => $req['amount'],
            'errdesc' => $req['errdesc'],
            'paytime' => $req['paytime'],
        );

        $sign = $req['sign'];

        $str1 = '';
        foreach ($data as $k => $v) {
            $str1 .= "$v|";
        }

        $mysign = md5($str1 . $this->app_key);

        if ($sign != $mysign) {
            throw new DefaultException('sign error');
        }

        // 平台参数
        $param['amount'] = round($req['amount'] / 100, 2);                      // 总价.单位: 分
        $param['transaction'] = $req['custominfo'];                              // 订单id
        $param['currency'] = 'CNY';                                                         // 货币类型
        $param['reference'] = $req['ordernum'];                           // 第三方订单ID
        $param['userId'] = '';                                   // 第三方账号ID

        return $param;
    }

    public function success()
    {
        exit('1');
    }
}