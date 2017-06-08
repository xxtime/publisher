<?php
/**
 * Created by PhpStorm.
 * User: lihe
 * Date: 2017/6/7
 * Time: 下午3:35
 */
namespace Xt\Publisher\Providers;

use Phalcon\Config;
use Symfony\Component\Yaml\Yaml;
use Xt\Publisher\DefaultException;

class downjoy extends ProviderAbstract
{

    public function verifyToken($token = '', $option = [])
    {
        
        $cfg = new Config(Yaml::parse(file_get_contents(APP_DIR . '/config/publisher.yml')));
        $down_cfg = $cfg->downjoy;

        $param = [
            'appid' => $down_cfg->app_id,
            'appkey' => $down_cfg->app_key,
            'token' => $token,
            'umid' => $option['uid']
        ];

        $sign = md5(implode('|', $param));
        unset($param['appkey']);
        $param['sig'] = $sign;

        $url ='http://ngsdk.d.cn/api/cp/checkToken?'.http_build_query($param);
        $response = file_get_contents($url);
        $result = json_decode($response, true);
        if ($result['valid'] == 1 && $result['msg_code'] == 2000){
            return [
                'uid' => $option['uid'],
                'username'  => '',
                'original'  => $response
            ];
        }
        //如果验证失败就抛出异常
        throw new DefaultException($response);
    }

    /**
     *  return [
    'transactionId'        => '20170526024456001467000368', // 平台订单ID;   重要参数  网站的订单ID
    'transactionReference' => '1234567890',                 // 发行商订单ID; 必选参数  渠道订单ID
    'amount'               => 4.99,                         // 充值金额
    'currency'             => 'CNY',                        // 货币类型
    'userId'               => '3001-2001234',               // 终端用户ID
    ];
     */
    public function notify(){
        //支付状态
        $payStatus = $this->request->get('result');
        if (!$payStatus){
            throw new DefaultException('payStatus failure');
        }

        $cfg = new Config(Yaml::parse(file_get_contents(APP_DIR . '/config/publisher.yml')));
        $downjoy_cfg = $cfg->downjoy;
        
        //验证签名
        $sign = $this->request->get('signature');

        $this->check_sign($sign, $downjoy_cfg->payment_key);

        //数据组装
        $transactionId = $this->request->get('cpOrder');
        $transactionReference = $this->request->get('order');
        $amount = $this->request->get('money');
        $currency = '';
        $userId = $this->request->get('mid');

        return[
            'transactionId' => $transactionId,
            'transactionReference'  => $transactionReference,
            'amount'    => $amount,
            'currency'  => $currency,
            'userId'    => $userId
        ];
    }

    public function success()
    {
        exit('success');
    }

    private function check_sign($sign, $key){
        $req = $this->request->get();

        //数据组装 按照特定个的顺序
        $data = [
            'order' => $req['order'],
            'money' => $req['money'],
            'mid'   => $req['mid'],
            'time'  => $req['time'],
            'result' => $req['result'],
            'cpOrder' => $req['cpOrder'],
            'ext'   => $req['ext'],
            'key'   => $key
        ];

        $str = http_build_query($data);

        if( strtolower( $sign ) != md5( $str ) )
        {
            throw new DefaultException('sign error');
        }
    }
}