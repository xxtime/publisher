<?php
/**
 * Created by PhpStorm.
 * User: lihe
 * Date: 2017/8/8
 * Time: 下午4:44
 */
namespace Xt\Publisher\Providers;

use Xt\Publisher\DefaultException;

class Sumsung extends ProviderAbstract
{
    //samsung没有登录系统 直接返回
    public function verifyToken($token = '', $option = [])
    {
        return [
            'uid'      => $option['uid'],
            'username' => '',
            'original' => $option['uid']
        ];
    }

    public function notify()
    {

    }

    public function tradeBuild($parameter = [])
    {
        $url = 'http://siapcn1.ipengtai.com:7002/payapi/order';

        //组装appuserid
        $userInfo = explode($_REQUEST['custom'], '-');
        $appuserid = $userInfo['1']. '#' . $userInfo['0'];

        $transdata = [
            'appid' => $this->option['app_id'],
            'waresid' => $parameter['product_id'],
            'cporderid' => $parameter['transaction'],
            'currency'  => 'RMB',
            'appuserid' => '',
            'price' => $parameter['amount'],
            'notifyurl' => $this->option['notifyurl']
        ];

        ksort($transdata);

        $privateKey = $this->formatPriKey();

        $sign = $this->sign($transdata, $privateKey);

        $signtype = 'RSA';
        //拼装成 {参数名":"数据" , 参数名":"数据" ....}&sign=xxxxxxx&signtype=RSA
        $post_data = urlencode(json_encode($transdata) . '&' . 'sign=' . $sign . '&' . 'signtype=' . $signtype);
        
        //发送post请求
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 4);
        curl_setopt($ch, CURLOPT_ENCODING, ""); //必须解压缩防止乱码
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        curl_close($ch);

        $response = urldecode($response);
        //transdata={"transid":"32011501141440430237"}&sign=NJ1qphncrBZX8nLjonKk2tDIKRKc7vHNej3e/jZaXV7Gn/m1IfJv4lNDmDzy88Vd5Ui1PGMGvfXzbv8zpuc1m1i7lMvelWLGsaGghoXi0Rk7eqCe6tpZmciqj1dCojZoi0/PnuL2Cpcb/aMmgpt8LVIuebYcaFVEmvngLIQXwvE=&signtype=RSA
        $res_data = explode($response, '&');

        if (empty($res_data['sign'])){
            throw  new  DefaultException('create order error');
        }

        $orderData = $res_data['0'];

        $orderId = explode($orderData, '=');

        $transid = json_decode($orderId, true);

        return [
            'reference' => $transid['transid'],      // 发行商订单号
            'raw'       => $res_data                 // 发行渠道返回的原始信息, 也可添加额外参数
        ];
    }

    /**RSA签名
     * $data待签名数据
     * $priKey商户私钥
     * 签名用商户私钥
     * 使用MD5摘要算法
     * 最后的签名，需要用base64编码
     * return Sign签名
     */
    private function sign($data, $priKey) {
        //转换为openssl密钥
        $res = openssl_get_privatekey($priKey);

        //调用openssl内置签名方法，生成签名$sign
        openssl_sign($data, $sign, $res, OPENSSL_ALGO_MD5);

        //释放资源
        openssl_free_key($res);

        //base64编码
        $sign = base64_encode($sign);
        return $sign;
    }

    //格式化私钥
    private function formatPriKey(){
        $private_key = "-----BEGIN PRIVATE KEY-----\n" .
            chunk_split($this->option['private_key'], 64, "\n") .
            '-----END PRIVATE KEY-----';
        return $private_key;
    }
}