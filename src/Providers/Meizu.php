<?php
/**
 * Created by PhpStorm.
 * User: lihe
 * Date: 2017/6/7
 * Time: 上午10:39
 */
namespace Xt\Publisher\Providers;

use Phalcon\Config;
use Symfony\Component\Yaml\Yaml;
use Xt\Publisher\DefaultException;

class Meizu extends ProviderAbstract{
    //魅族登陆验证
    public function verifyToken($token = '', $option = [])
    {
        $cfg = new Config(Yaml::parse(file_get_contents(APP_DIR . '/config/publisher.yml')));
        $meizu_cfg = $cfg->meizu;
        $url = 'https://api.game.meizu.com/game/security/checksession?';

        $ts = time();
        $sign = md5('app_id='.$meizu_cfg->app_id.'&session_id='.$token.'&ts='.$ts.'&uid='.$option['uid']);

        $param = [
            'app_id'   => $meizu_cfg->app_id,
            'session_id' => $token,
            'uid' => $option['uid'],
            'ts' => $ts,
            'sign_type' => 'md5',
            'sign' => $sign,
        ];

        $param = http_build_query($param);

        $url = $url.$param;
        $response = file_get_contents($url);
        $result = json_decode($response, true);

        //如果有异常 抛出异常
        if ($result['code'] != 200){
            throw new DefaultException($result['message']);
        }

        // TODO: Implement verifyToken() method.
        return array('uid' => $result['value']['uid'], 'username' => '', 'original' => $result['value']);
    }

    /**
     *  return [
    'transactionId'        => '20170526024456001467000368', // 平台订单ID;   重要参数
    'transactionReference' => '1234567890',                 // 发行商订单ID; 必选参数
    'amount'               => 4.99,                         // 充值金额
    'currency'             => 'CNY',                        // 货币类型
    'userId'               => '3001-2001234',               // 终端用户ID
    ];
     */
    public function notify(){
        // 订单未成功则不处理
        $trade_status = $this->request->get( 'trade_status' );
        if( $trade_status != '3' )
        {
            throw new DefaultException('fail');
        }

        // 平台参数
        $param['amount'] = $this->request->get( 'total_price' );            // 总价               二选一(product_sn|amount)

        $param['transactionId'] = $this->request->get( 'cp_order_id' );          // 订单id             可选

        // 自定义参数
        $param['currency'] = 'CNY';                                        // 货币类型

        // 第三方参数【可选,暂未使用】
        $param['transactionReference'] = $this->request->get( 'order_id' );     // 第三方订单ID
        $param['userId'] = $this->request->get( 'uid' );           // 第三方账号ID

        // 检查签名
        $this -> check_sign( $param['sign'] );

        return $param;
    }

    // 检查签名
    public function check_sign( $sign = '' )
    {
        $cfg = new Config(Yaml::parse(file_get_contents(APP_DIR . '/config/publisher.yml')));
        $meizu_cfg = $cfg->meizu;

        $req = $this->request->get();
        $data = array(
            'app_id'            => $req['app_id'],
            'buy_amount'        => $req['buy_amount'],
            'cp_order_id'       => $req['cp_order_id'],
            'create_time'       => $req['create_time'],
            'notify_id'         => $req['notify_id'],
            'notify_time'       => $req['notify_time'],
            'order_id'          => $req['order_id'],
            'partner_id'        => $req['partner_id'],
            'pay_time'          => $req['pay_time'],
            'pay_type'          => $req['pay_type'],
            'product_id'        => $req['product_id'],
            'product_per_price' => $req['product_per_price'],
            'product_unit'      => $req['product_unit'],
            'total_price'       => $req['total_price'],
            'trade_status'      => $req['trade_status'],
            'uid'               => $req['uid'],
            'user_info'         => $req['user_info']
        );

        $str = '';
        foreach( $data as $k => $v )
        {
            $str .= "$k=$v&";
        }
        $str = trim( $str, '&' );
        $str = $str . ':' . $meizu_cfg->secret_key;

        if( strtolower( $sign ) != md5( $str ) )
        {
            throw new DefaultException('sign error');
        }
    }


    public function success()
    {
        echo json_encode(array('code'=>200,'message'=>'','value'=>'','redirect'=>''));
        exit;
    }
}