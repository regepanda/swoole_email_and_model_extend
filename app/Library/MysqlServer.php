<?php
/**
 * Created by PhpStorm.
 * User: regepanda
 * Date: 2017/5/18
 * Time: 16:20
 */

namespace App\Library;

class MysqlServer
{
    public static function run()
    {
        $db = new \swoole_mysql;
        $server = array(
            'host' => '127.0.0.1',
            'user' => 'root',
            'password' => 'root',
            'database' => 'mysql',
            'chatset' => 'utf8', //指定字符集
        );

        $res = [];
        $db->connect($server, function ($db, $r) {
            if ($r === false){
                var_dump($db->connect_errno, $db->connect_error);
                die;
            }
            $sql = 'show databases;';
            $db->query($sql, function(\swoole_mysql $db, $r) {
                global $s;
                if ($r === false){
                    var_dump($db->error, $db->errno);
                }
                elseif ($r === true ){
                    var_dump($db->affected_rows, $db->insert_id);
                }
                dump($r);
                $db->close();
                return $r;
            });
        });
    }
}