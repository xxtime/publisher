<?php
/**
 * Created by PhpStorm.
 * User: lihe
 * Date: 2019/3/28
 * Time: 5:34 PM
 */
namespace Xt\Publisher\Providers;

use Whoops\Example\Exception;
use Xt\Publisher\DefaultException;

class Bilibili extends ProviderAbstract {

    public function verifyToken($token = '', $option = [])
    {
        $url = 'pnew.biligame.net/api/server/session.verify';
        //$uri = 'pserver.bilibiligame.net/api/server/session.verify';

        $param['game_id'] = $this->app_id;
        $param['merchant_id'] = $this->option['merchant_id'];
        $param['uid'] = $option['uid'];
        $param['version'] = '1';
        $param['timestamp'] = time();
        $param['access_key'] = $token;
        $param['sign'] = $this->create_sign($param, $this->option['app_key']);
        $respone = $this->call($url, $param);
        $respone = json_decode($respone, true);

        if ($respone['code'] != 0) {
            throw new DefaultException('verify failed');
        }
        return [
            'uid'      => $respone['open_id'],
            'username' => $respone['uname'],
            'original' => $respone
        ];
    }

    public function call($url, $post_data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 GameServer');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 4);
        curl_setopt($ch, CURLOPT_ENCODING, ""); //必须解压缩防止乱码
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }

    public function create_sign($data, $secret_key)
    {
        $str = '';
        ksort($data);
        foreach ($data as $value) {
            $str .= $value;
        }
        $str.= $secret_key;
        return md5($str);
    }

    public function notify()
    {
        // TODO: Implement notify() method.
    }
}
