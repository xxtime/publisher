<?php
/**
 * Created by PhpStorm.
 * User: lihe
 * Date: 2017/6/7
 * Time: 下午7:09
 */
namespace Xt\Publisher\Providers;

use Xt\Publisher\DefaultException;

class Fl extends ProviderAbstract
{

    public function verifyToken($token = '', $option = [])
    {
        //md5 验证
        $data = [
            'timestamp' => $option['custom'],
            'uid' => $option['uid'],
            'SECRETKEY' => $this->option['secret_key']
        ];
        $sign = implode('&', array_values($data));
        if ($token != md5($sign)){
            throw new DefaultException('failed');
        }
        unset($data['SECRETKEY']);

        return [
            'uid'      => $option['uid'],
            'username' => '',
            'original' => $data
        ];
    }

    public function notify()
    {
        $data = json_decode($_REQUEST, true);

        if ($data['status'] !== 0) {
            throw new DefaultException('fail');
        }

        // 平台参数
        $param['amount'] = $data['amount'];                                // 总价.单位: 分               二选一(product_sn|amount)
        $param['transactionId'] = $data['flOrderId'];                      // 订单id             可选
        $param['currency'] = 'CNY';                                        // 货币类型

        // 第三方参数【可选,暂未使用】
        $param['transactionReference'] = $data['cpOrderId'];               // 第三方订单ID
        $param['userId'] = $data['userId'];     // 第三方账号ID

        // 检查签名
        $this->check_sign($data['sign']);

        //转化单位,并保留2位小数
        $param['amount'] = round($data['amount'] / 100, 2);

        return $param;
    }

    public function check_sign($sign = '')
    {
        $req = json_decode($_REQUEST, true);
        unset($req['sign'], $req['_url']);
        ksort($req);

        $str = '';
        foreach ($req as $k => $v) {
            $str .= "$k=$v&";
        }
        $str = trim($str, '&');

        if (strtolower($sign) != md5($str)) {
            throw new DefaultException('sign error');
        }
    }

    public function success()
    {
        echo json_encode(array('code' => 0, 'tips' => 'success'));
        exit;
    }
}