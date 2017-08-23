<?php
/**
 * Created by PhpStorm.
 * User: lihe
 * Date: 2017/8/18
 * Time: 下午2:51
 */
namespace Xt\Publisher\Providers;

use Xt\Publisher\DefaultException;

class Meitu extends ProviderAbstract{

    public function verifyToken($token = '', $option = [])
    {
        $url = 'http://www.91wan.com/api/mobile/sdk_oauth.php?';

        $flag = $this->productFlag($this->app_id, $option['uid'], $token);

        $param = [
            'appid' => $this->app_id,
            'uid' => $option['uid'],
            'state' => $token,
            'flag'  => $flag
        ];
        $param = http_build_query($param);
        
        $url = $url . $param;
        $response = file_get_contents($url);
        $result = json_decode($response, true);

        if ($result['ret'] != 100){
            throw new DefaultException($result);
        }

        return [
            'uid' => $result['uid'],
            'username' => '',
            'original' => (array)$result
        ];
    }

    public function notify()
    {
        $sign = $_REQUEST['flag'];

        $this->check_sign($sign);

        return [
            'transaction' => $_REQUEST['ext'],
            'reference'   => $_REQUEST['orderid'],
            'amount'      => $_REQUEST['money'],          //平台是以元为单位
            'currency'    => 'CNY',
            'userId'      => $_REQUEST['uid']
        ];

    }

    public function success(){
        echo '1'; exit;
    }

    //生成flag的值
    private function productFlag($app_id, $uid, $token){
        $md5_str = $app_id .  $uid .  $token .  $this->option['login_key'];
        return md5($md5_str);
    }

    //验证sign的合法性
    private function check_sign($sign){
        $req = $_REQUEST;

        $param = [
            'uid' => $req['uid'],
            'money' => $req['money'],
            'time' => $req['time'],
            'sid'  => $req['sid'],
            'orderid' => $req['orderid'],
            'ext' => $req['ext'],
            'pay_key' => $this->option['payment_key']
        ];

        $sign_str = '';
        foreach ($param as $info){
            $sign_str .=  $info;
        }
        
        if ($sign != md5($sign_str)){
            throw  new  DefaultException('sign error');
        }
    }
}