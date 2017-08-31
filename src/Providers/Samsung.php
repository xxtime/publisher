<?php
/**
 * Created by PhpStorm.
 * User: lihe
 * Date: 2017/8/8
 * Time: 下午4:44
 */
namespace Xt\Publisher\Providers;

use Xt\Publisher\DefaultException;

class Samsung extends ProviderAbstract
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
        $response = file_get_contents("php://input");

        $arr=array_map(create_function('$v', 'return explode("=", $v);'), explode('&', $response));
        foreach($arr as $value) {
            $resp[($value[0])] = urldecode($value[1]);
        }

        //解析transdata
        if(array_key_exists("transdata", $resp)) {
            $respJson = json_decode($resp["transdata"], true);
        }


        $result = $this->parseResp($response, $this->formatPubKey());

        if (!$result){
            throw new  DefaultException("sign error");
        }

        return [
            'transaction' => $respJson['cporderid'],
            'reference'   => $respJson['transid'],
            'amount'      => $respJson['money'],
            'currency'    => 'CNY',
            'userId'      => $respJson['appuserid'],         //只需要user_id server_id 不需要
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
        $appuserid = $userInfo['1'] . '#' . $userInfo['0'];

        $transdata = [
            'appid'     => $this->option['app_id'],
            'waresid'   => intval(substr($parameter['product_id'], -1)),          //产品id为 int类型
            'cporderid' => $parameter['transaction'],
            'currency'  => 'RMB',
            'appuserid' => $appuserid,
            'price'     => $parameter['amount'],
            'notifyurl' => $this->option['notify_url']
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

        //transdata={"transid":"32011501141440430237"}&sign=NJ1qphncrBZX8nLjonKk2tDIKRKc7vHNej3e/jZaXV7Gn/m1IfJv4lNDmDzy88Vd5Ui1PGMGvfXzbv8zpuc1m1i7lMvelWLGsaGghoXi0Rk7eqCe6tpZmciqj1dCojZoi0/PnuL2Cpcb/aMmgpt8LVIuebYcaFVEmvngLIQXwvE=&signtype=RSA
        //创建匿名函数
        // array_map函数 将匿名函数 作用在数组中的每个值上,并返回带有新值的数组
        $arr = array_map(create_function('$v', 'return explode("=", $v);'), explode('&', $response));

        foreach ($arr as $value) {
            $resp[($value[0])] = urldecode($value[1]);
        }

        //解析transdata
        if (array_key_exists("transdata", $resp)) {
            $respJson = json_decode($resp["transdata"], true);
        }

        if (array_key_exists("sign", $resp)) {
            //校验签名
            $pkey = $this->formatPubKey();
            $result = $this->verify($respJson, $resp["sign"], $pkey);
        }
        else {
            throw new DefaultException('order error');
        }

        if ($result != 0) {
            throw new DefaultException('varify error');
        }

        return [
            'reference' => '',                 // 发行商订单号
            'raw'       => $respJson                 // 发行渠道返回的原始信息, 也可添加额外参数
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
    private function sign($data, $priKey)
    {
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
    private function formatPriKey()
    {
        $private_key = "-----BEGIN PRIVATE KEY-----\n" .
            chunk_split($this->option['private_key'], 64, "\n") .
            '-----END PRIVATE KEY-----';
        return $private_key;
    }

    //格式化公钥
    private function formatPubKey()
    {
        $private_key =  "-----BEGIN PUBLIC KEY-----\n" .
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
    private function verify($data, $sign, $pubKey)
    {
        $sign = str_replace(' ', '+', $sign);
        //转换为openssl格式密钥
        $res = openssl_get_publickey($pubKey);

        //调用openssl内置方法验签，返回bool值
        $result = (bool)openssl_verify($data, base64_decode($sign), $res, OPENSSL_ALGO_MD5);

        //释放资源
        openssl_free_key($res);

        //返回资源是否成功
        return $result;
    }

    /**
     * 解析response报文
     * $content  收到的response报文
     * $pkey     爱贝平台公钥，用于验签
     * $respJson 返回解析后的json报文
     * return    解析成功TRUE，失败FALSE
     */
    function parseResp($content, $pkey) {
        $arr=array_map(create_function('$v', 'return explode("=", $v);'), explode('&', $content));
        foreach($arr as $value) {
            $resp[($value[0])] = urldecode($value[1]);
        }

        //解析transdata
        if(array_key_exists("transdata", $resp)) {
            $respJson = json_decode($resp["transdata"]);
        }

        //验证签名，失败应答报文没有sign，跳过验签
        if(array_key_exists("sign", $resp)) {
            //校验签名
            $pkey = $this->formatPubKey($pkey);
            return $this->verify($resp["transdata"], $resp["sign"], $pkey);
        } else if(!array_key_exists("errmsg", $respJson)) {
            throw new  DefaultException((array)$respJson);
        }
    }

}