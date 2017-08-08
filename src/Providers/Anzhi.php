<?php
/**
 * 安智.
 * User: lihe
 * Date: 2017/7/17
 * Time: 下午6:28
 */
namespace Xt\Publisher\Providers;

use Xt\Publisher\DefaultException;

class Anzhi extends ProviderAbstract{

    //安智的服务端验证url 需要从客户端发来的请求中获取
    public function verifyToken($token = '', $option = []){
        $url = $_REQUEST['custom'];
        $param = [
            'time' => substr($this->udate('YmdHisu'), 0, 17),
            'appkey' => $this->app_key,
            'cptoken' => $token,
            'sign'  => md5($this->app_key . $token . $this->option['secret_key']),
            'deviceId' => $_REQUEST['deviceId']
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $param);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 4);
        curl_setopt($ch, CURLOPT_ENCODING, ""); //必须解压缩防止乱码
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($response, true);

        //如果response 不等于一 抛出异常
        if ($response['code'] !== 1){
            throw new DefaultException($response);
        }

        //对response 进行解密
        $response = json_decode(base64_decode($response['data']), true);


        //对response 的 uid 进行3DES 解密
        $uid = $this->decrypt($response['uid']);

        return array('uid' => $uid, 'username' => '', 'original' => (array)$response);
    }

    public function notify(){
        $data = $_REQUEST['data'];
        if (empty($data)) {
            throw new DefaultException('error order');
        }

        $data = str_replace(' ', '+', $data);
        $result = $this->decrypt($data);
        $result = json_decode($result ,true);

        if(empty($result)){
            throw new DefaultException('sign error');
        }

        // 平台参数
        $param['amount'] = round($result['payAmount'] / 100, 2);                              // 总价.单位: 分
        $param['transaction'] = $result['cpInfo'];                              // 订单id
        $param['currency'] = 'CNY';                                                         // 货币类型
        $param['reference'] = $result['orderId'];                           // 第三方订单ID
        $param['userId'] = $result['uid'];                                   // 第三方账号ID

        return $param;
    }

    public function success()
    {
        exit('success');
    }


    /**
     * 解密
     */
    public  function decrypt($value) {
        $td = mcrypt_module_open ( MCRYPT_3DES, '', MCRYPT_MODE_ECB, '' );
        //$iv = pack ( 'H16', $this->iv );
        //$key = pack ( 'H48', $this->key );
        mcrypt_generic_init ( $td, $this->option['secret_key'],'00000000');
        $ret = trim ( mdecrypt_generic ( $td, base64_decode ( $value ) ) );
        $ret = $this->UnPaddingPKCS7 ( $ret );
        mcrypt_generic_deinit ( $td );
        mcrypt_module_close ( $td );
        return $ret;
    }

    private  function UnPaddingPKCS7($data) {
        $padlen = ord (substr($data, (strlen( $data )-1), 1 ) );
        if ($padlen > 8 )
            return $data;

        for($i = -1*($padlen-strlen($data)); $i < strlen ( $data ); $i ++) {
            if (ord ( substr ( $data, $i, 1 ) ) != $padlen) {
                return false;
            }
        }

        return substr ( $data, 0, -1*($padlen-strlen ( $data ) ) );
    }

    private function  udate($format = 'u', $utimestamp = null) {
        if (is_null($utimestamp))
            $utimestamp = microtime(true);

        $timestamp = floor($utimestamp);
        $milliseconds = round(($utimestamp - $timestamp) * 1000000);

        return date(preg_replace('`(?<!\\\\)u`', $milliseconds, $format), $timestamp);
    }
}