<?php
/**
 * Created by PhpStorm.
 * User: lihe
 * Date: 2017/6/7
 * Time: 下午5:10
 */
namespace Xt\Publisher\Providers;

use Xt\Publisher\DefaultException;

class Uc extends ProviderAbstract
{


    public function verifyToken($token = '', $option = [])
    {
        $url = 'http://sdk.9game.cn/cp/account.verifySession';

        $sign = md5("sid=$token" . $this->app_key);
        $param = [
            'id'   => time(),
            'data' => ['sid' => $token],
            'game' => ['gameId' => $this->app_id],
            'sign' => $sign
        ];
        $response = $this->http_curl_post($url, json_encode($param));
        $result = json_decode($response, true);

        //如果遇到错误 则抛出错误
        if ($result['state']['code'] != 1) {
            throw new DefaultException($response);
        }

        return [
            'uid'      => $result['data']['accountId'],
            'username' => $result['data']['nickName'],
            'original' => (array)$result
        ];
    }


    private function http_curl_post($url, $data, $extend = array())
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_NOBODY, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $curl_result = curl_exec($ch);
        curl_close($ch);

        return $curl_result;
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
        $request = file_get_contents("php://input");

        $responseData = json_decode($request, true);

        if (empty($responseData)) {
            throw new DefaultException('error order');
        }

        //uc告知订单失败
        if ($responseData['data']['orderStatus'] == 'F') {
            throw new DefaultException('error oder');
        }

        //app_id验证
        if ($responseData['data']['gameId'] != $this->app_id) {
            throw new DefaultException('error gameId');
        }

        $param['orderId'] = $responseData['data']['orderId'];
        $param['gameId'] = $responseData['data']['gameId'];
        $param['accountId'] = $responseData['data']['accountId'];
        $param['creator'] = $responseData['data']['creator'];
        $param['payWay'] = $responseData['data']['payWay'];
        $param['amount'] = $responseData['data']['amount'];
        $param['callbackInfo'] = $responseData['data']['callbackInfo'];
        $param['orderStatus'] = $responseData['data']['orderStatus'];
        $param['failedDesc'] = $responseData['data']['failedDesc'];
        $param['cpOrderId'] = $responseData['data']['cpOrderId'];
        $sign = $responseData['sign'];

        $this->check_sign($param, $sign, $this->app_key);

        return [
            'transaction' => $param['cpOrderId'],
            'reference'   => $param['orderId'],
            'amount'      => $param['amount'],
            'currency'    => '',
            'userId'      => $param['accountId']
        ];
    }


    private function check_sign($data, $sign, $appKey)
    {
        $data_ksort = ksort($data);
        $sign_str = '';
        foreach ($data_ksort as $k => $v) {
            $sign_str .= $k . '=' . $v;
        }
        $sign_str .= 'apiKey' . '=' . $appKey;

        if ($sign != md5($sign_str)) {
            throw new DefaultException('sign error');
        }
    }


    public function success()
    {
        exit('SUCCESS');
    }

    /**
     * @param array $parameter
     *    $parameter = [
     *        'transaction'  => '', // 平台订单ID
     *        'amount'       => '', // 金额
     *        'currency'     => '', // 货币种类
     *        'product_id'   => '', // 产品ID
     *        'product_name' => '', // 产品名称
     *        'raw'          => '', // 用户登录发行渠道返回的原始数据， verifyToken 方法返回的 original字段
     *    ];
     * @return array
     */
    public function tradeBuild($parameter = [])
    {
        $data['AMOUNT'] = $parameter['amount'];
        $data['NOTIFY_URL'] = $this->option['notify_url'];
        $data['CP_ORDER_ID'] = $parameter['transaction'];
        $data['ACCOUNT_ID'] = $parameter['raw']['data']['accountId'];
        foreach ($data as $key => $value) {
            if (empty($value)) {
                unset($data[$key]);
            }
        }
        ksort($data);
        $sign_data = '';
        foreach ($data as $k => $v) {
            $sign_data .= $k . '=' . $v . '&';
        }
        $sign_data = trim($sign_data, '&');
        $data['SIGN_TYPE'] = 'MD5';
        $data['SIGN'] = md5($sign_data . $this->app_key);
        return [
            'reference' => '',      // 发行商订单号
            'raw'       => $data   // 发行渠道返回的原始信息, 也可添加额外参数
        ];
    }
}