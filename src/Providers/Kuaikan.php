<?php
/**
 * Created by PhpStorm.
 * User: lihe
 * Date: 2017/8/18
 * Time: 下午4:19
 */
namespace Xt\Publisher\Providers;

use Xt\Publisher\DefaultException;

class Kuaikan extends ProviderAbstract{

    public function verifyToken($token = '', $option = [])
    {
        //验证open_id合法性
        $url = 'http://api.kkmh.com/v1/game/oauth/check_open_id?';

        $param = [
            'app_id' => $this->app_id,
            'open_id' => $option['custom'],
            'access_token' => $token,
        ];

        $sign = $this->productSign($param);
        $param['sign'] = urlencode($sign);

        $param_str = '';
        foreach ($param as $key=>$value){
            $param_str .= $key . '=' . $value . '&';
        }

        $param_str = trim($param_str, '&');

        $url = $url . $param_str;

        $response = file_get_contents($url);
        $result = json_decode($response, true);
        if ($result['code'] != 200){
            throw  new  DefaultException($result);
        }
        //如果合法 则获取用户信息
        $userInfo_url = 'http://api.kkmh.com/v1/game/oauth/user_info?';

        $userInfo_param = $token;

        $userInfo_url =$userInfo_url . 'access_token' . '=' . $userInfo_param;
        $res = file_get_contents($userInfo_url);
        $user_result = json_decode($res, true);
        if ($user_result['code'] != 200){
            throw new DefaultException($user_result);
        }

        return [
            'uid' => $user_result['data']['open_id'],
            'username' => $user_result['data']['nickname'],
            'original' => (array)$user_result
        ];
    }

    // $response = "{\"wares_id\":1,\"pay_status\":2,\"out_order_id\":\"1104\",\"trans_money\":0.0,\"trans_id\":\"32461612231438102462\",\"currency\":\"RMB\",\"pay_type\":402,\"trans_result\":1,\"trans_time\":1482475126000,\"open_uid\":\"1104\",\"order_id\":\"7501085669965004888881024\",\"app_id\":1}";
    public function notify()
    {
        //如果 回调没有发送成功, 需要通过查询订单接口进行查询信息
        $req = $_REQUEST;
        $trans_data = json_decode($req['trans_data'], true);
        $sign_kuaikan = $req['sign'];
        $this->check_sign($trans_data, $sign_kuaikan);

        return [
            'transaction' => $trans_data['out_order_id'],
            'reference'   => $trans_data['trans_id'],
            'amount'      => round($trans_data['trans_money'] * 100, 2),
            'currency'    => 'CNY',
            'userId'      => $trans_data['open_uid']
        ];

    }

    private function productSign($param){
        //按照参数的ASCII码 从小到大排序
        ksort($param);

        $sign_str = '';
        foreach ($param as $key => $value){
            //如果为空 不参加签名
            if ($value == ''){
                unset($param[$key]);
            }
            $sign_str .= $key . '=' . $value . '&';
        }
        
        $sign_str .= 'key' . '=' . $this->option['secrect_key'];

        return base64_encode(md5($sign_str, true));
    }

    public function tradeBuild($parameter = []){
        $data['app_id'] = "$this->app_id";
        $data['wares_id'] = intval(substr($parameter['product_id'], -1));
        $data['out_order_id'] = $parameter['transaction'];
        $data['open_uid'] = $parameter['raw']['data']['open_id'];
        $data['out_notify_url'] = $this->option['notify_url'];

        $sign = $this->productSign($data);

        $param['trans_data'] = json_encode($data);
        $param['sign'] = $sign;

        return [
            'reference' => '',      // 发行商订单号
            'raw'       => $param   // 发行渠道返回的原始信息, 也可添加额外参数
        ];
    }

    private function check_sign($param, $sign){
        $param['trans_money'] = strval($param['trans_money'].'.0');

        ksort($param);

        $sign_str = '';
        foreach ($param as $key => $value){
            //如果为空 不参加签名
            if ($value == ''){
                unset($param[$key]);
            }
            $sign_str .= $key . '=' . $value . '&';
        }

        $sign_str .= 'key' . '=' . $this->option['secrect_key'];

        if (base64_encode(md5($sign_str, true)) != $sign){
            throw new DefaultException('sign error');
        }
    }
}