<?php
/**
 * Created by PhpStorm.
 * User: lihe
 * Date: 2017/6/7
 * Time: 下午2:41
 */
namespace Xt\Publisher\Providers;

use Xt\Publisher\DefaultException;
use Phalcon\Config;
use Symfony\Component\Yaml\Yaml;

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
            'original' => $respones
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
        $respCode = $this->request->get('respCode');
        if ($respCode != 200) {
            throw new DefaultException('error order');
        }

        $param['appId'] = $this->request->get('appId');
        $param['cpId'] = $this->request->get('cpId');
        $param['cpOrderNumber'] = $this->request->get('cpOrderNumber');
        $param['extInfo'] = $this->request->get('extInfo');
        $param['orderAmount'] = $this->request->get('orderAmount');
        $param['orderNumber'] = $this->request->get('orderNumber');
        $param['payTime'] = $this->request->get('payTime');
        $param['respCode'] = $respCode;
        $param['respMsg'] = $this->request->get('respMsg');
        $param['tradeStatus'] = $this->request->get('tradeStatus');
        $param['tradeType'] = $this->request->get('tradeType');
        $param['uid'] = $this->request->get('uid');
        $sign = $this->request->get('signature');

        $this->check_sign($param, $sign);

        return [
            'transactionId'        => $param['cpOrderNumber'],
            'transactionReference' => $param['orderNumber'],
            'amount'               => range($param['orderAmount'] / 100, 2),
            'currency'             => '',
            'userId'               => $param['uid']
        ];
    }

    private function check_sign($data, $sign)
    {
        $cfg = new Config(Yaml::parse(file_get_contents(APP_DIR . '/config/publisher.yml')));
        $vivo_cfg = $cfg->vivo;
        $app_key = $vivo_cfg->app_key;
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