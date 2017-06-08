<?php
/**
 * Created by PhpStorm.
 * User: regepanda
 * Date: 2017/4/26
 * Time: 14:49
 */

namespace App\Facade;

use Illuminate\Support\Facades\Facade;

class TestServiceConnection extends Facade
{
    public static function getFacadeAccessor()
    {
        return 'test';
    }
}