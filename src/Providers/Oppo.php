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
        $url = 'https://iopen.game.oppomobile.com/sdkopen/user/fileIdInfo';
        $request_serverUrl = $url . "?fileId=" . urlencode($option['uid']) . "&token=" . urlencode($token);
        $time = microtime(true);
        $dataParams['oauthConsumerKey'] = $this->app_key;
        $dataParams['oauthToken'] = urlencode($token);
        $dataParams['oauthSignatureMethod'] = "HMAC-SHA1";
        $dataParams['oauthTimestamp'] = intval($time * 1000);
        $dataParams['oauthNonce'] = intval($time) + rand(0, 9);
        $dataParams['oauthVersion'] = "1.0";
        $requestString = $this->_assemblyParameters($dataParams);
        $oauthSignature = $this->option['secret_key'] . "&";
        $sign = $this->_signatureNew($oauthSignature, $requestString);  //生成签名
        $result = $this->OauthPostExecuteNew($sign, $requestString, $request_serverUrl);  //请求结果
        $result = json_decode($result, true);            //结果也是一个json格式字符串

        //如果有异常 抛出异常
        if ($result['resultCode'] != 200) {
            throw new DefaultException($result['resultMsg']);
        }

        // TODO: Implement verifyToken() method.
        return array('uid' => $result['ssoid'], 'username' => $result['userName'], 'original' => $result);
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

    private function OauthPostExecuteNew($sign, $requestString, $request_serverUrl)
    {
        $opt = array(
            "http" => array(
                "method" => "GET",
                'header' => array("param:" . $requestString, "oauthsignature:" . $sign),
            )
        );
        $res = file_get_contents($request_serverUrl, null, stream_context_create($opt));
        return $res;
    }

    /**
     * 使用HMAC-SHA1算法生成签名
     */
    private function _signatureNew($oauthSignature, $requestString)
    {
        return urlencode(base64_encode(hash_hmac('sha1', $requestString, $oauthSignature, true)));
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
        $param['amount'] = round($_REQUEST['price'] / 100, 2);                              // 总价.单位: 分
        $param['transaction'] = $_REQUEST['partnerOrder'];                              // 订单id
        $param['currency'] = 'CNY';                                                         // 货币类型
        $param['reference'] = $_REQUEST['notifyId'];                           // 第三方订单ID
        $param['userId'] = '';                                   // 第三方账号ID

        // 检查签名
        $this->check_sign($_REQUEST['sign']);

        return $param;
    }

    // 检查签名
    public function check_sign($sign = '')
    {
        $req = $_REQUEST;
        $sign = str_replace(' ', '+', $sign);
        $str = "notifyId={$req['notifyId']}&partnerOrder={$req['partnerOrder']}&productName={$req['productName']}&productDesc={$req['productDesc']}&price={$req['price']}&count={$req['count']}&attach={$req['attach']}";

        if ($this->verify_sign($str, $sign) != 1) {
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
        $publickey = $this->option['public_key'];

        $pem = chunk_split($publickey, 64, "\n");
        $pem = "-----BEGIN PUBLIC KEY-----\n" . $pem . "-----END PUBLIC KEY-----\n";
        $public_key_id = openssl_pkey_get_public($pem);
        $signature = base64_decode($sign);
        return openssl_verify($data, $signature, $public_key_id);                     //成功返回1,0失败，-1错误,其他看手册
    }
}