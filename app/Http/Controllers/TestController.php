<?php
/**
 * Created by PhpStorm.
 * User: regepanda
 * Date: 2017/4/26
 * Time: 14:39
 */

namespace App\Http\Controllers;

use App\Services\SwooleClient;

class TestController extends Controller
{
    public function test()
    {
        $data["businessAdr"] = null;
        $data["userAdr"] = "1174495058@qq.com";
        $data["recvTitle"] = "swoole";
        $data["recvContent"] = "lizuoqiang";
        $data["template"] = [];

        $swooleClient = new SwooleClient();
        $swooleClient->connect();
        $send = $swooleClient->send($data);
    }
}