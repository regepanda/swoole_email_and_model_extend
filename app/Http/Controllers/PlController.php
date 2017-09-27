<?php
/**
 * Created by PhpStorm.
 * User: regepanda
 * Date: 2017/9/19
 * Time: 17:31
 */

namespace App\Http\Controllers;

use App\Library\LNode;

class PlController extends Controller
{
    public $status = true;

    public function test1()
    {
        LNode::test();
        dump($this->status);
    }
}