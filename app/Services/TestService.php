<?php
/**
 * Created by PhpStorm.
 * User: regepanda
 * Date: 2017/4/26
 * Time: 14:31
 */

namespace App\Services;

class TestService
{
    public function callMe($controller)
    {
        dd('Call Me From TestServiceProvider In '.$controller);
    }
}