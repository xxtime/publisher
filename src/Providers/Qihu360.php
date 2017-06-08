<?php
/**
 * Created by PhpStorm.
 * User: lihe
 * Date: 2017/6/7
 * Time: 下午12:01
 */
namespace Xt\Publisher\Providers;

use Phalcon\Config;
use Symfony\Component\Yaml\Yaml;
use Xt\Publisher\DefaultException;

class Qihu360 extends ProviderAbstract
{

    public function verifyToken($token = '', $option = [])
    {
        $fields = 'id,name,avatar,sex,area';
        $url = "https://open.mgame.360.cn/user/me.json?access_token={$token}&fields={$fields}";
        $response = file_get_contents($url);
        $result = json_decode($response, true);
        if (isset($result['id'])) {
            return [
                'uid'      => $result['id'],
                'username' => $result['name'],
                'original' => $result
            ];
        }

        throw new DefaultException($response);
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
        $gateway_flag = $this->request->get('gateway_flag');
        if ($gateway_flag != 'success') {
            throw  new  DefaultException('error order');
        }

        $param['product_sn'] = $this->request->get('product_id');                //应用自定义的商品id
        $param['amount'] = $this->request->get('amount');                        // 总价，单位(分)
        $param['order_id'] = $this->request->get('app_order_id');                // 订单id
        $param['user_id'] = $this->request->get('app_uid');                      // 应用分配给用户的id
        $param['role_id'] = $this->request->get('app_ext1');                     // 角色id
        $param['server_id'] = $this->request->get('app_ext2');                   // 服务器id

        // 其他参数
        $param['gateway_status'] = $this->request->get('gateway_flag'); // 如果支付返回成功,返回success 应用需要确认是 success 才给用户加钱
        $param['partner_user_id'] = $this->request->get('user_id');     // 360账号id
        $param['partner_order_id'] = $this->request->get('order_id');   // 360订单号

        $param['app_key'] = $this->request->get('app_key');         // 应用app_key
        $param['sign_type'] = $this->request->get('sign_type');     // 定值md5
        $sign_return = $this->request->get('sign_return');          // 应用回传给订单核实接口 的参数 不加入签名校验计算
        $sign = $this->request->get('sign');                        // 签名

        //检查签名
        $this->check_sign($param, $sign);

        //返回数据
        return [
            'transactionId'        => $param['order_id'],
            'transactionReference' => $param['partner_order_id'],
            'amount'               => round($param['amount'] / 100, 2),
            'currency'             => '',
            'userId'               => $param['partner_user_id']
        ];
    }

    private function check_sign($data, $sign)
    {
        //按照
        foreach ($data as $k => $v) {
            if (empty($v)) {
                unset($data[$k]);
            }
        }
        $data = ksort($data);
        $sign_str = implode('#', $data);
        $cfg = new Config(Yaml::parse(file_get_contents(APP_DIR . '/config/publisher.yml')));
        $qihu_cfg = $cfg->qihu360;
        $secretkey = $qihu_cfg->secret_key;
        $sign_str = $sign_str . '#' . $secretkey;

        if (strtolower($sign) != strtolower(md5($sign_str))) {
            throw new DefaultException('sign error');
        }
    }

    public function success()
    {
        $data =  [
            'status'   => 'ok',
            'delivery' => 'success',
            'msg'      => 'success'
        ];

        exit(json_encode($data));
    }
}