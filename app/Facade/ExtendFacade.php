<?php
/**
 * Created by PhpStorm.
 * User: regepanda
 * Date: 2017/4/26
 * Time: 15:46
 */

namespace App\Facade;

use Illuminate\Support\Facades\Facade;

class ExtendFacade extends Facade
{
    public static function getFacadeAccessor()
    {
        return 'extend';
    }
}