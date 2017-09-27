<?php
/**
 * Created by PhpStorm.
 * User: regepanda
 * Date: 2017/9/27
 * Time: 15:02
 */

namespace App\Container;

use Closure;

class Container
{
    public $binds;

    public $instances;

    public function bind($abstract, $concrete)
    {
        if ($concrete instanceof Closure) {
            $this->binds[$abstract] = $concrete;
        } else {
            $this->instances[$abstract] = $concrete;
        }
    }

    public function make($abstract, $parameters = [])
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        array_unshift($parameters, $this);

        return call_user_func_array($this->binds[$abstract], $parameters);
    }
}