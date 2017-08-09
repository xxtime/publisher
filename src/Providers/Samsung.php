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

    //渠道 回调
    public function notify()
    {
        $request = file_get_contents("php://input");

        $request = urldecode($request);

        $data_info = explode($request, '&');

        $transData = $data_info['0'];
        $trans = explode($transData, '=');
        //获取订单信息字符串
        $tran = $trans['1'];
        //把订单信息转化成数组
        $tran = json_decode($tran, true);

        $signData = $data_info['1'];
        //获取第一个'='的位置
        $location = strpos($signData, '=');
        //截取第一个'='后面的字符串
        $sign = substr($signData, $location + 1);

        //签名验证
        $result = $this->verify($tran, $sign, $this->formatPubKey());

        if ($result){
            throw new DefaultException('verify error');
        }

        $user_id = explode($tran['appuserid'], '#');

        return [
            'transaction' => $tran['cporderid'],
            'reference'   => $tran['transid'],
            'amount'      => rand($tran['money'] / 100, 2),
            'currency'    => 'CNY',
            'userId'      => $user_id['0'],         //只需要user_id server_id 不需要
        ];
    }

    public function success()
    {
        exit('SUCCESS');
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

    //格式化公钥
    private function formatPubKey(){
        $private_key = "-----BEGIN PUBLIC KEY-----\n" .
            chunk_split($this->option['public_key'], 64, "\n") .
            '-----END PUBLIC KEY-----';
        return $private_key;
    }

    /**RSA验签
     * $data待签名数据
     * $sign需要验签的签名
     * $pubKey爱贝公钥
     * 验签用爱贝公钥，摘要算法为MD5
     * return 验签是否通过 bool值
     */
    function verify($data, $sign, $pubKey)  {
        //转换为openssl格式密钥
        $res = openssl_get_publickey($pubKey);

        //调用openssl内置方法验签，返回bool值
        $result = (bool)openssl_verify($data, base64_decode($sign), $res, OPENSSL_ALGO_MD5);

        //释放资源
        openssl_free_key($res);

        //返回资源是否成功
        return $result;
    }

}