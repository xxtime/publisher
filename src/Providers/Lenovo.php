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

class Lenovo extends ProviderAbstract{
    //联想登陆验证
    public function verifyToken($token = '', $option = [])
    {
        $url = 'http://passport.lenovo.com/interserver/authen/1.2/getaccountid?';

        $param = [
            'lpsust' => $token,
            'realm'   => $this->app_id,
        ];

        $param = http_build_query($param);

        $url = $url.$param;

        $ch = curl_init();
        curl_setopt ($ch, CURLOPT_URL, $url);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT,10);
        $dxycontent = curl_exec($ch);

        $resultjson = $this->xml_to_json($dxycontent);
        $result = json_decode($resultjson);

        //如果有异常 抛出异常
        if (!empty($result->Code)){
            throw new DefaultException($resultjson);
        }

        // TODO: Implement verifyToken() method.
        return array('uid' => $result->AccountID, 'username' => '', 'original' => (array)$result);
    }

    public function xml_to_json($source) {
        if(is_file($source)){ //传的是文件，还是xml的string的判断
            $xml_array=simplexml_load_file($source);
        }else{
            $xml_array=simplexml_load_string($source);
        }
        $json = json_encode($xml_array); //php5，以及以上，如果是更早版本，请查看JSON.php
        return $json;
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
        $data = json_decode( $this->request->get( 'transdata' ), true );

        if( empty( $data ) )
        {
            throw new DefaultException('fail');
        }

        if( $data['result'] )
        {
            throw new DefaultException('fail');
        }

        // 平台参数
        $param['amount'] = round( $data['money'] / 100, 2 );                // 总价.单位: 分               二选一(product_sn|amount)
        $param['transactionId'] = $data['exorderno'];                            // 订单id             可选
        $param['currency'] = 'CNY';                                        // 货币类型

        // 第三方参数【可选,暂未使用】
        $param['transactionReference'] = $this->request->get('transid');            // 第三方订单ID
        $param['userId'] = $this->request->get('partner_user_id');     // 第三方账号ID

        // 检查签名
        $this -> check_sign( $param['sign'] );

        return $param;
    }

    // 检查签名
    public function check_sign( $sign = '' )
    {
        $req = $this->request->get( 'transdata' );

        $private_key = "-----BEGIN PUBLIC KEY-----\n" .
            chunk_split($this->option['private_key'], 64, "\n") .
            '-----END PUBLIC KEY-----';

        $res = openssl_get_privatekey( $private_key );
        openssl_sign( $req, $sign, $res );
        openssl_free_key( $res );
        $sign = base64_encode( $sign );

        if(!$sign){
            throw new DefaultException('sign error');
        }
    }

    public function success()
    {
        exit('success');
    }

}
