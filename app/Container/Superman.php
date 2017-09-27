<?php
/**
 * Created by PhpStorm.
 * User: regepanda
 * Date: 2017/9/27
 * Time: 15:05
 */

namespace App\Container;


class Superman
{
    protected $module;

    public function __construct(SuperModuleInterface $module)
    {
        $this->module = $module;
    }
}