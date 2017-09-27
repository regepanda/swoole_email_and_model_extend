<?php
/**
 * Created by PhpStorm.
 * User: regepanda
 * Date: 2017/9/4
 * Time: 9:37
 */

namespace App\Http\Controllers;

use App\Library\LinearTable;
use App\Library\SingleLinkedList;

class DataStructureController extends Controller
{
    public function linearTable()
    {
        $foo = 'bar';
        global $foo;
//        unset($foo);
        echo $foo;
    }
}