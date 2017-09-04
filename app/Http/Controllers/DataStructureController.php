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
        $oll = new SingleLinkedList();
        $arr = [1, 2, 3, 4, 5, 6];
//        $oll->getHeadCreateSLL($arr);
        $oll->getTailCreateSLL($arr);
        dump($oll->mNext);
    }
}