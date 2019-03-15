<?php
/**
 * Created by PhpStorm.
 * User: lihe
 * Date: 2017/6/7
 * Time: 下午12:01
 */
namespace Xt\Publisher\Providers;

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
                'original' => (array)$result
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
        $gateway_flag = $_REQUEST['gateway_flag'];
        if ($gateway_flag != 'success') {
            throw  new  DefaultException('error order');
        }

        $param['product_id'] = $_REQUEST['product_id'];                //应用自定义的商品id
        $param['amount'] = $_REQUEST['amount'];                        // 总价，单位(分)
        $param['app_order_id'] = $_REQUEST['app_order_id'];                // 订单id
        $param['app_uid'] = $_REQUEST['app_uid'];                      // 应用分配给用户的id

        // 其他参数
        $param['gateway_flag'] = $gateway_flag;                       // 如果支付返回成功,返回success 应用需要确认是 success 才给用户加钱
        $param['user_id'] = $_REQUEST['user_id'];                     // 360账号id
        $param['order_id'] = $_REQUEST['order_id'];                   // 360订单号

        $param['app_key'] = $_REQUEST['app_key'];                       // 应用app_key
        $param['sign_type'] = $_REQUEST['sign_type'];                   // 定值md5
        $sign_return = $_REQUEST['sign_return'];                        // 应用回传给订单核实接口 的参数 不加入签名校验计算
        $sign = $_REQUEST['sign'];                                      // 签名
        //检查签名
        $this->check_sign($param, $sign);

        //返回数据
        return [
            'transaction' => $param['app_order_id'],
            'reference'   => $param['order_id'],
            'amount'      => round($param['amount'] / 100, 2),
            'currency'    => '',
            'userId'      => $param['user_id']
        ];
    }

    public function tradeBuild($parameter = [])
    {
        $data['app_key'] = $this->option['app_key'];
        $data['product_id'] = $parameter['product_id'];
        $data['product_name'] = $parameter['product_name'];
        $data['amount'] = intval($parameter['amount']*100);
        $data['app_uid'] = $parameter['raw']['uid'];  //应用分配给用户的id
        $data['app_uname'] = $parameter['raw']['username']; //应用内的用户名
        $data['user_id'] = $parameter['raw']['uid']; //360帐号id
        $data['sign_type'] = "md5";
        $data['app_order_id'] = $parameter['transaction'];
        $data['sign'] = $this->sign($data);
        $url = "https://mgame.360.cn/srvorder/get_token.json?";
        $args = http_build_query($data);
        $response = file_get_contents($url.$args);
        $result = json_decode($response, true);
        if (isset($result['error_code'])) {
            throw new DefaultException($result['error']);
        }
        return [
            'reference' => "",
            'raw'       => $result
        ];
    }

    private function sign($data)
    {
        foreach ($data as $k => $v) {
            if (empty($v)) {
                unset($data[$k]);
            }
        }
        ksort($data);
        $sign_str = implode("#", $data);
        $secretkey = $this->option['secret_key'];
        $sign_str = $sign_str . '#' . $secretkey;
        return $sign_str;
    }

    private function check_sign($data, $sign)
    {
        //按照
        $sign_str = $this->sign($data);
        if (strtolower($sign) != strtolower(md5($sign_str))) {
            throw new DefaultException('sign error');
        }
    }

    public function success()
    {
        $data = [
            'status'   => 'ok',
            'delivery' => 'success',
            'msg'      => 'success'
        ];

        exit(json_encode($data));
    }
}