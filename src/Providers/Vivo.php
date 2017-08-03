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
        $respCode = $_REQUEST['respCode'];
        if ($respCode != 200) {
            throw new DefaultException('error order');
        }

        $param['appId'] = $_REQUEST['appId'];
        $param['cpId'] = $_REQUEST['cpId'];
        $param['cpOrderNumber'] = $_REQUEST['cpOrderNumber'];
        $param['extInfo'] = $_REQUEST['extInfo'];
        $param['orderAmount'] = $_REQUEST['orderAmount'];
        $param['orderNumber'] = $_REQUEST['orderNumber'];
        $param['payTime'] = $_REQUEST['payTime'];
        $param['respCode'] = $respCode;
        $param['respMsg'] = $_REQUEST['respMsg'];
        $param['tradeStatus'] = $_REQUEST['tradeStatus'];
        $param['tradeType'] = $_REQUEST['tradeType'];
        $param['uid'] = $_REQUEST['uid'];
        $sign = $_REQUEST['signature'];

        $this->check_sign($param, $sign);

        return [
            'transaction' => $param['cpOrderNumber'],
            'reference'   => $param['orderNumber'],
            'amount'      => range($param['orderAmount'] / 100, 2),
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
        $app_key = $this->app_key;
        $app_key = md5($app_key);

        $sign_str = md5(http_build_query($data));

        $sign_new = $sign_str . '&' . $app_key;

        if (strtolower($sign) != strtolower($sign_new)) {
            throw new DefaultException('sign error');
        }
    }


    public function success()
    {
        exit('success');
    }

}