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
        $url = $option['custom'];

        $param = [
            'time' => time(),
            'appkey' => $this->app_key,
            'cptoken' => $token,
            'sign'  => md5($this->app_key . $token . $this->option['sercret_key']),
            'deviceId' => $option['custom']
        ];

        $response = $this->http_curl_post($url, http_build_query($param), '', 10);

        //如果response 不等于一 抛出异常
        if ($response !== 1){
            throw new DefaultException($response);
        }

        //对response 进行解密
        $response = base64_decode($response);
        //对response 的 uid 进行3DES 解密
        $uid = $this->decrypt($response['uid']);

        return array('uid' => $uid, 'username' => '', 'original' => (array)$response);
    }

    public function notify(){
        $data = $_REQUEST['data'];
        if (empty($data)) {
            throw new DefaultException('error order');
        }

        $result = $this->decrypt($data);

        // 平台参数
        $param['amount'] = $result['payAmount'];                              // 总价.单位: 分
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

    private function http_curl_post($url, $data, $Authorization = '', $timeout = 10)
    {

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
        if (!empty($Authorization)) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, array('Authorization: ' . $Authorization));
        }
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        $content = curl_exec($curl);
        curl_close($curl);

        return $content;
    }

    private function decrypt($value){
        $td = mcrypt_module_open ( MCRYPT_3DES, '', MCRYPT_MODE_ECB, '' );
        mcrypt_generic_init ( $td, $this->option['sercret_key']);
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
}