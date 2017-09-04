<?php
/**
 * Created by PhpStorm.
 * User: regepanda
 * Date: 2017/9/4
 * Time: 13:48
 */

namespace App\Library;


class LNode
{
    public $mElem;
    public $mNext;

    public function __construct()
    {
        $this->mElem = null;
        $this->mNext = null;
    }
}