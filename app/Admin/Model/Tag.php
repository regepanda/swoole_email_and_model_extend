<?php
/**
 * Created by PhpStorm.
 * User: regepanda
 * Date: 2017/8/15
 * Time: 17:15
 */

namespace App\Admin\Model;

use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    protected $table = 'tags';

//    public function setOptionsAttribute($options)
//    {
//        if (is_array($options)) {
//            $this->attributes['options'] = implode(',', $options);
//        }
//    }
//
//    public function getOptionsAttribute($options)
//    {
//        if (is_string($options)) {
//            return explode(',', $options);
//        }
//
//        return $options;
//    }
}