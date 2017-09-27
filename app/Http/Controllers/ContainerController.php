<?php
/**
 * Created by PhpStorm.
 * User: regepanda
 * Date: 2017/9/27
 * Time: 15:07
 */

namespace App\Http\Controllers;

use App\Container\Container;
use App\Container\Superman;
use App\Container\XPower;
use App\Container\UltraBomb;

class ContainerController extends Controller
{
    public function container()
    {
        // 创建一个容器（后面称作超级工厂）
        $container = new Container;

        // 向该 超级工厂添加超人的生产脚本
        $container->bind('superman', function($container, $moduleName) {
            return new Superman($container->make($moduleName));
        });

        // 向该 超级工厂添加超能力模组的生产脚本
        $container->bind('xpower', function($container) {
            return new XPower;
        });

        // 同上
        $container->bind('ultrabomb', function($container) {
            return new UltraBomb;
        });

        // 开始启动生产
        $superman_1 = $container->make('superman', ['xpower']);
        dump($superman_1);
        $superman_2 = $container->make('superman', ['ultrabomb']);
        dump($superman_2);
        $superman_3 = $container->make('superman', ['xpower']);
        dump($superman_3);
        // ...随意添加
    }
}