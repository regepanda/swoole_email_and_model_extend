<?php
/**
 * Created by PhpStorm.
 * User: regepanda
 * Date: 2017/9/4
 * Time: 9:37
 */

namespace App\Library;

/**
 * 顺序表基本操作
 *
 * 包括
 * 1.顺序表的初始化 __construct()
 * 2.清空顺序表 __destruct()
 * 3.判断顺序表是否为空 isEmpty()
 * 4.返回顺序表的长度 getLength()
 * 5.根据下标返回顺序表中的某个元素 getElement()
 * 6.返回顺序表中某个元素的位置 getElementPosition()
 * 7.返回顺序表中某个元素的直接前驱元素 getElementPredecessorr()
 * 8.返回某个元素的直接后继元素 getElementSubsequence()
 * 9.指定下标位置返回元素 getElemForPos()
 * 10.根据下标或者元素值删除顺序表中的某个元素 getDeleteElement()
 * 11.根据元素位置删除顺序表中的某个元素 getDeleteEleForPos()
 * 12.在指定位置插入一个新的结点 getInsertElement()
 */
class LinearTable
{
    /**
     * 顺序表
     *
     * @var array
     */
    public $oll;

    /**
     * 顺序表初始化
     *
     * @param mixed $oll
     */
    public function __construct($oll = [])
    {
        $this->oll = $oll;
    }

    /**
     * 清空顺序表
     */
    public function __destruct()
    {
        // TODO: Implement __destruct() method.
        foreach ($this->oll as $key => $value) {
            unset($this->oll[$key]);
        }
    }

    /**
     * 判断顺序表是否为空
     *
     * @return bool
     */
    public function isEmpty()
    {
        if (count($this->oll) > 0) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * 返回顺序表的长度
     *
     * @return int
     */
    public function getLenth()
    {
        return count($this->oll);
    }

    /**
     * 返回顺序表中某个元素的值
     *
     * @param $key
     *
     * @return mixed
     */
    public function getElement($key)
    {
        return $this->oll[$key];
    }

    /**
     * 返回顺序表中某个元素的位置
     *
     * @param $value
     *
     * @return int
     */
    public function getElementPosition($value)
    {
        $i = 0;
        foreach ($this->oll as $val) {
            $i++;
            if (strcmp($value, $val) === 0) {
                return $i;
            }
        }
        return -1;
    }

    /**
     * 返回顺序表中某个元素的直接前驱元素
     *
     * @param     $value //顺序表中某个元素的值
     * @param int $tag 如果$value为下标则为1,如果$value为元素值则为2
     *
     * @return array array('value'=>...)直接前驱元素值，array('key'=>...)直接前驱元素下标
     */
    public function getElementPredecessorr($value, $tag = 1)
    {
        $i = 0;
        foreach ($this->oll as $key => $val) {
            $i++;
            if ($tag == 1) {
                if (strcmp($key, $value) === 0) {
                    if ($i == 1) {
                        return false;
                    }
                    prev($this->oll);
                    prev($this->oll);
                    return array('value' => current($this->oll), 'key' => $key($this->oll));
                }
            }

            if ($tag == 2) {
                if (strcmp($val, $value) === 0) {
                    if ($i == 1) {
                        return false;
                    }
                    prev($this->oll);
                    prev($this->oll);
                    return array('value' => current($this->oll), 'key' => $key($this->oll));
                }
            }
        }
    }

    /**
     * 返回某个元素的直接后继元素
     *
     * @param mixed $value顺序表中某个元素的值
     * @param bool  $tag 如果$value为下标则为1,如果$value为元素值则为2
     *
     * @return array array('value'=>...)直接后继元素值，array('key'=>...)直接后继元素下标
     */
    public function getElementSubsequence($value, $tag = 1)
    {
        $i = 0;
        $len = count($this->oll);
        foreach ($this->oll as $key => $val) {
            $i++;
            if ($tag == 1) {
                if (strcmp($key, $value) == 0) {
                    if ($i == $len) {
                        return false;
                    }
                    return array('value' => current($this->oll), 'key' => key($this->oll));
                }
            }
            if ($tag == 2) {
                if (strcmp($val, $value) == 0) {
                    if ($i == $len) {
                        return false;
                    }
                    return array('value' => current($this->oll), 'key' => key($this->oll));
                }
            }
        }
        return false;
    }

    /**
     * 在指定位置插入一个新的结点
     *
     * @param mixed $p 新结点插入位置,从1开始
     * @param mixed $value 顺序表新结点的值
     * @param mixed $key 顺序表新结点的下标
     * @param bool  $tag 是否指定新结点的下标,1表示默认下标,2表示指定下标
     *
     * @return bool 插入成功返回true，失败返回false
     */
    public function getInsertElement($p, $value, $key = null, $tag = 1)
    {
        $p = (int)$p;
        $len = count($this->oll);
        $oll = array();
        $i = 0;

        if ($p > $len || $p < 1) {
            return false;
        }

        foreach ($this->oll as $k => $v) {
            $i++;
            if ($i == (int)$p) {
                if ($tag == 1) {
                    $oll[] = $value;
                } else {
                    if ($tag == 2) {
                        $keys = array_keys($oll);
                        $j = 0;
                        if (is_int($key)) {
                            while (in_array($key, $keys, true)) {
                                $key++;
                            }
                        } else {
                            dump($key);
                            dump($keys);
                            while (in_array($key, $keys, true)) {
                                $j++;
                                $key .= (string)$j;
                            }
                        }
                        dump($key);die;
                        $oll[$key] = $value;
                    } else {
                        return false;
                    }
                }

                $key = $k;
                $j = 0;
                $keys = array_keys($oll);
                if (is_int($key)) {
                    $oll[] = $v;
                } else {
                    while (in_array($key, $keys, true)) {
                        $j++;
                        $key .= (string)$j;
                    }
                    $oll[$key] = $v;
                }
            } else {
                if ($i > $p) {
                    $key = $k;
                    $j = 0;
                    $keys = array_keys($oll);
                    if (is_int($key)) {
                        $oll[] = $v;
                    } else {
                        while (in_array($key, $keys, true)) {
                            $j++;
                            $key .= (string)$j;
                        }
                        $oll[$key] = $v;
                    }
                } else {
                    if (is_int($k)) {
                        $oll[] = $v;
                    } else {
                        $oll[$k] = $v;
                    }
                }
            }
        }
        $this->oll = $oll;
        return true;
    }

    /**
     * 根据元素位置返回顺序表中的某个元素
     *
     * @param int $position 元素位置从1开始
     *
     * @return array  array('value'=>...)元素值，array('key'=>...)元素下标
     */
    public function getElemForPos($position)
    {
        $i = 0;
        $len = count($this->oll);
        $position = (int)$position;

        if ($position > $len || $position < 1) {
            return false;
        }

        foreach ($this->oll as $val) {
            $i++;
            if ($i == $position) {
                return array('value' => current($this->oll), 'key' => key($this->oll));
            }
        }
    }

    /**
     * 根据下标或者元素值删除顺序表中的某个元素
     *
     * @param mixed $value 元素下标或者值
     * @param int   $tag 1表示$value为下标，2表示$value为元素值
     *
     * @return bool 成功返回true,失败返回false
     */
    public function getDeleteElement($value, $tag = 1)
    {
        $len = count($this->oll);
        foreach ($this->oll as $k => $v) {
            if ($tag == 1) {
                if (strcmp($k, $value) === 0) {
                } else {
                    if (is_int($k)) {
                        $oll[] = $v;
                    } else {
                        $oll[$k] = $v;
                    }
                }
            }

            if ($tag == 2) {
                if (strcmp($v, $value) === 0) {
                } else {
                    if (is_int($k)) {
                        $oll[] = $v;
                    } else {
                        $oll[$k] = $v;
                    }
                }
            }
        }
        $this->oll = $oll;
        if (count($this->oll) == $len) {
            return false;
        }
        return true;
    }

    /**
     * 根据元素位置删除顺序表中的某个元素
     *
     * @param int $position 元素位置从1开始
     *
     * @return bool 成功返回true,失败返回false
     */
    public function getDeleteEleForPos($position)
    {
        $len = count($this->oll);
        $i = 0;
        $position = (int)$position;

        if ($position > $len || $position < 1) {
            return false;
        }

        foreach ($this->oll as $k => $v) {
            $i++;
            if ($i == $position) {
            } else {
                if (is_int($k)) {
                    $oll[] = $v;
                } else {
                    $oll[$k] = $v;
                }
            }
        }

        $this->oll = $oll;
        if (count($this->oll) == $len) {
            return false;
        }
        return true;
    }
}