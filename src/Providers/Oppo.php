<?php
/**
 * Created by PhpStorm.
 * User: lihe
 * Date: 2017/6/7
 * Time: 上午10:39
 */
namespace Xt\Publisher\Providers;

use Xt\Publisher\DefaultException;

class Oppo extends ProviderAbstract
{
    //oppo登陆验证
    public function verifyToken($token = '', $option = [])
    {
        $token = str_replace(' ', '+', $token);
        $url = 'http://i.open.game.oppomobile.com/gameopen/user/fileIdInfo';
        $request_serverUrl = $url . "?fileId=" . $option['uid'] . "&token=" . $token;
        $time = microtime(true);
        $dataParams['oauthConsumerKey'] = $this->app_key;
        $dataParams['oauthToken'] = $token;
        $dataParams['oauthSignatureMethod'] = "HMAC-SHA1";
        $dataParams['oauthTimestamp'] = intval($time * 1000);
        $dataParams['oauthNonce'] = intval($time) + rand(0, 9);
        $dataParams['oauthVersion'] = "1.0";
        $requestString = $this->_assemblyParameters($dataParams);

        $oauthSignature = $this->option['secret_key'] . "&";
        $sign = $this->_signatureNew($oauthSignature, $requestString);
        $result = $this->http_curl_post($request_serverUrl);
        $result = json_decode($result, true);            //结果也是一个json格式字符串

        //如果有异常 抛出异常
        if ($result['resultCode'] != 200) {
            throw new DefaultException($result['resultMsg']);
        }

        // TODO: Implement verifyToken() method.
        return array('uid' => $result['ssoid'], 'username' => '', 'original' => $result);
    }


    /**
     * 请求的参数串组合
     */
    private function _assemblyParameters($dataParams)
    {
        $requestString = "";
        foreach ($dataParams as $key => $value) {
            $requestString = $requestString . $key . "=" . $value . "&";
        }
        return $requestString;
    }

    /**
     * 使用HMAC-SHA1算法生成签名
     */
    private function _signatureNew($oauthSignature, $requestString)
    {
        return urlencode(base64_encode(hash_hmac('sha1', $requestString, $oauthSignature, true)));
    }

    private function http_curl_post($url, $Authorization = '', $timeout = 10)
    {

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
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

    /**
     *  return [
     * 'transactionId'        => '20170526024456001467000368', // 平台订单ID;   重要参数
     * 'transactionReference' => '1234567890',                 // 发行商订单ID; 必选参数
     * 'amount'               => 4.99,                         // 充值金额
     * 'currency'             => 'CNY',                        // 货币类型
     * 'userId'               => '3001-2001234',               // 终端用户ID
     * ];
     */
    public function notify()
    {
        // 平台参数
        $param['amount'] = round($this->request->get('price') / 100,
            2);                    // 总价.单位: 分               二选一(product_sn|amount)
        $param['transactionId'] = $this->request->get('partnerOrder');                             // 订单id             可选

        // 自定义参数
        $param['currency'] = 'CNY';                            // 货币类型

        // 第三方参数【可选,暂未使用】
        $param['transactionReference'] = $this->request->get('notifyId');             // 第三方订单ID
        $param['userId'] = $this->request->get('partner_user_id');       // 第三方账号ID

        // 检查签名
        $this->check_sign($param['sign']);

        return $param;
    }

    // 检查签名
    public function check_sign($sign = '')
    {
        $req = $this->request->get();
        $data = array(
            'notifyId'     => $req['notifyId'],
            'partnerOrder' => $req['partnerOrder'],
            'productName'  => $req['productName'],
            'productDesc'  => $req['productDesc'],
            'price'        => $req['price'],
            'count'        => $req['count'],
            'attach'       => $req['attach']
        );

        $str = '';
        foreach ($data as $k => $v) {
            $str .= "$k=$v&";
        }
        $str = trim($str, '&');

        if ($this->verify_sign($str, $sign)) {
            throw new DefaultException('sign error');
        }
    }

    public function success()
    {
        echo json_encode(array('result' => 'OK', 'resultMsg' => ''));
        exit;
    }

    private function verify_sign($data, $sign)
    {
        $public_key = "-----BEGIN PUBLIC KEY-----\n" .
            chunk_split($this->option['public_key'], 64, "\n") .
            '-----END PUBLIC KEY-----';

        $public_key_id = openssl_pkey_get_public($public_key);
        $signature = base64_decode($sign);
        return openssl_verify($data, $signature, $public_key_id);                     //成功返回1,0失败，-1错误,其他看手册
    }
}