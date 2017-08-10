<?php
/**
 * Created by PhpStorm.
 * User: lihe
 * Date: 2017/6/7
 * Time: 下午2:41
 */
namespace Xt\Publisher\Providers;

use Xt\Publisher\DefaultException;

class Vivo extends ProviderAbstract
{

    public function verifyToken($token = '', $option = [])
    {
        $retcode = [
            '20000' => '请求参数错误',
            '20002' => 'authtoken过期或失效',
            '10000' => '服务器异常'
        ];

        $url = "https://usrsys.vivo.com.cn/sdk/user/auth.do?authtoken={$token}";
        $respones = file_get_contents($url);
        $result = json_decode($respones, true);
        if ($result['retcode'] != 0) {
            $result['retcode'] = $retcode[$result['retcode']];
            throw new DefaultException(json_encode($result));
        }
        return [
            'uid'      => $result['data']['openid'],
            'username' => '',
            'original' => (array)$result
        ];
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

        $info = explode('&', $request);

        //截取code
        $respCode = explode('=', $info['2']);

        if ($respCode['1'] != 200) {
            throw new DefaultException('error order');
        }

        //获取uid
        $uid = explode('=', $info['0']);

        //获取appId
        $appId = explode('=', $info['4']);

        //获取cpOrderNumber
        $cpOrderNumber = explode('=', $info['6']);

        //获取extInfo
        $extInfo = explode('=', $info['11']);

        //获取orderAmount
        $orderAmount = explode('=', $info['9']);

        //获取cpId
        $cpId = explode('=', $info['7']);

        //获取orderNumber
        $orderNumber = explode('=', $info['10']);

        //获取payTime
        $payTime = explode('=', $info['5']);

        //获取respMsg
        $respMsg = explode('=', $info['12']);

        //获取tradeStatus
        $tradeStatus = explode('=', $info['3']);

        //获取tradeType
        $tradeType = explode('=', $info['1']);

        //获取signature
        $signature = explode('=', $info['13']);

        $param['appId'] = $appId['1'];
        $param['cpId'] = $cpId['1'];
        $param['cpOrderNumber'] = $cpOrderNumber['1'];
        $param['extInfo'] = $extInfo['1'];
        $param['orderAmount'] = $orderAmount['1'];
        $param['orderNumber'] = $orderNumber['1'];
        $param['payTime'] = $payTime['1'];
        $param['respCode'] = $respCode['1'];
        $param['respMsg'] = urldecode($respMsg['1']);
        $param['tradeStatus'] = $tradeStatus['1'];
        $param['tradeType'] = $tradeType['1'];
        $param['uid'] = $uid['1'];
        $sign = $signature['1'];

        $this->check_sign($param, $sign);

        return [
            'transaction' => $param['cpOrderNumber'],
            'reference'   => $param['orderNumber'],
            'amount'      => round($param['orderAmount'] / 100, 2),
            'currency'    => '',
            'userId'      => $param['uid']
        ];
    }


    /**
     * 订单创建接口
     * @param $parameter
     * @return array
     * @throws DefaultException
     */
    public function tradeBuild($parameter)
    {
        $url = 'https://pay.vivo.com.cn/vcoin/trade';
        $query = [
            'appId'         => $this->option['app_id'],
            'version'       => '1.0.0',
            'cpId'          => $this->option['cp_id'],
            'cpOrderNumber' => $parameter['transaction'],
            'notifyUrl'     => $this->option['notify_url'],
            'orderTime'     => (new \DateTime('now', new \DateTimeZone('Asia/Shanghai')))->format('YmdHis'),
            'orderAmount'   => intval($parameter['amount'] * 100),
            'orderTitle'    => $parameter['product_name'],
            'orderDesc'     => $parameter['product_name'],
            'extInfo'       => $parameter['transaction']
        ];

        ksort($query);

        $queryStr = '';
        foreach($query as $k => $v){
            $queryStr .= $k . '=' . $v . '&';
        }

        $queryStr = trim($queryStr , '&');

        $query['signature'] = strtolower(md5($queryStr . '&' . strtolower(md5($this->option['app_key'])))); // TODO :: cp_key
        $query['signMethod'] = 'MD5';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($query));
        curl_setopt($ch, CURLOPT_NOBODY, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $curl_result = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($curl_result, true);
        if ($result['respCode'] != 200) {
            throw new DefaultException($result);
        }

        return [
            'reference' => $result['orderNumber'],
            'raw'       => $result
        ];
    }


    private function check_sign($data, $sign)
    {
        $signStr = '';
        foreach($data as $k => $v){
            $signStr .=  $k . '=' . $v . '&';
        }

        $signStr = trim($signStr, '&');

        $signature = strtolower(md5($signStr . '&' . strtolower(md5($this->option['app_key']))));
        
        if (strtolower($sign) != strtolower($signature)){
            throw  new DefaultException('sign error');
        }

    }


    public function success()
    {
        exit('success');
    }

}