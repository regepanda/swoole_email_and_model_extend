<?php
/**
 * User: keith.wang
 * Date: 16-10-11
 * V 1.031
 * git: https://github.com/wjt2338006/Laravel_ModelExtend.git
 */

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Class ModelExtend
 * 主要用来快速的进行各类查询，包括连表，同步，以及常用数据层操作
 * 如果不希望替换原有的模型，很多函数允许你直接传入连接和表进行操作，非常灵活
 * 注意ModelExtend不是线程安全的，请不要在Swoole等环境下使用。
 * @package App\Libraries\Tour
 */
class ModelExtend
{

    /**
     * 定义模型数据库连接
     * @var
     */
    static protected $connection;

    /**
     * 定义表
     * @var
     */
    static protected $table;

    /**
     * 定义主键名
     * @var
     */
    static protected $primaryKey;

    /**
     * 用来备份当前model配置的
     * @var
     */
    static protected $dumpData;

    /**
     * 实例化时传入主键值
     * @var
     */
    protected $id;

    /**
     * 单条数据，以数组形式访问
     * @var
     */
    protected $data;


    /**
     * 映射表
     * @var
     */
    static protected $syncMap;

    /**
     * 映射条件
     * @var void
     */
    protected $syncCondition;

    /**
     * 在每一个映射同步完后执行的语句
     * @var void
     */
    protected $afterSyncExec;

    /**
     * 是否开启异步
     * @var void
     */
    static public $async = false;

    /**
     * 连表计数器,内部使用
     * @var int
     */
    static protected $linkcal = 0;

    /**
     * json方式查询
     * @var string
     */
    static protected $valSymbol = ":";

    static protected $needMore = true;


    const AddOperationSymbol = "add";
    const UpdateOperationSymbol = "update";
    const DelOperationSymbol = "del";

    /*
        $queryLimit
        |-sort = 排序字段
        |-desc = 是否倒序true/false
        |-id = 按照某个id查询     //ud
        |-start = 查询开始条目
        |-num = 查询多少条
        |-select = ["xx as new","count(*) a snum"]需要哪些字段，不填是所有
        |-paginate = 是否使用laravel默认分页机制，需要使用填入每页条数，返回数据中会有page这个参数，是laravel返回的分页对象
        |-where = [] //ud
            |- ["or","field","=","value"] //第一个and或者or是无效的（单个 and和 or是没有意义的）
            |- ["and","field,"=","value]
            |- ...
        |-whereIn = ["id",[1,2,3]]  //组合使用whereIn是加载where末尾，如果最后一个条件是or，那么很可能不是你要的效果，最好不要混用 //ud
        |-link = []
            |-["name","selfFiled","connection.table.field1"["queryLimit"]] 如果name为空，那么数据将会嵌入当条，重复的会覆盖
            |- ...
        |-or/and =[] //仿照MongoDB or and 复杂where条件可能需要
        |-resultConvert = function(&$dataArray){}
        |-pk = 手动设定主键，id字段将按照这个字段查询，仅在使用id的时候有效
        |-deleteEmpty =["name1","name2"...]那些如果为空删除
        |-first = true 只查询单条数据
        |-custom = function($queryLimit,$query)

     */

    /**
     * 高级查询构造，参数见文档
     * @param $queryLimit
     * @param null $query 如果需要自定义；连接和表，这里可以传入
     * @param bool $forbidTotal  如果不进行分页查总数，请设成true，否则数据量大的情况下会非常慢(全表扫描count(*))
     * @return array 结果数组 ["status":true,"message":"","data":[],"total":10,["page":object]]
     * @throws \Exception
     */
    static public function select($queryLimit, $query = null,$forbidTotal=false)
    {


        if (empty($query))
        {
            $query = static::getQuery();
            $originQuery = clone $query;
        }
        else
        {
            if (is_string($query))
            {
                $conData = self::compileConnectionString($query);
                $query = static::getBuilder($conData["con"])->table($conData["table"]);
                $queryLimit["pk"] = $conData["field"];
                $originQuery = clone $query;
            }
        }


        //排序
        if (!empty($queryLimit["sort"]))  //自定义字段排序
        {
            if (!empty($queryLimit["desc"]) && true == $queryLimit["desc"])
            {
                $query->orderBy($queryLimit["sort"], "desc");
            }
            else
            {
                $query->orderBy($queryLimit["sort"]);
            }

        }
        else    //默认使用按id排序
        {
            if (!empty($queryLimit["desc"]) && true == $queryLimit["desc"])
            {
                if (!empty($queryLimit["pk"]))
                {
                    $query->orderBy($queryLimit["pk"], "desc");
                }
                else
                {
                    $query->orderBy(static::$primaryKey, "desc");
                }

            }
        }

        if (!empty($queryLimit["custom"]) && is_callable($queryLimit["custom"]))
        {
            $queryLimit["custom"]($queryLimit, $query);
        }

        //按主键id查找某条记录
        if (isset($queryLimit["id"])) // 有时候id为0，蛋疼
        {
            static::selectId($queryLimit, $query);
        }
        //设定where条件
        if (!empty($queryLimit["where"]))
        {
            static::selectWhere($queryLimit["where"], $query);
        }
        //设定whereIn条件
        if (!empty($queryLimit["whereIn"]))
        {
            static::selectWhereIn($queryLimit["whereIn"], $query);
        }

        if (!empty($queryLimit["or"]))
        {
            static::selectWhereOrAnd("or", $queryLimit["or"], $query);
        }
        if (!empty($queryLimit["and"]))
        {
            static::selectWhereOrAnd("and", $queryLimit["and"], $query);
        }
        //json查询扩展
        static::handleJsonQuery($queryLimit, $query);

        //自定义方法
        static::selectExtra($queryLimit, $query);

        $returnData = [];
        //计算出符合条件的查询总条数,除开num和limit
        $numQuery = clone $query;//克隆出来不用原来的对象

        $con = null;
        if (isset($conData["con"]))
        {
            $con = $conData["con"];
        }

        if(!$forbidTotal)
        {
            $returnData["total"] = $numQuery->select(static::getBuilder($con)->raw('count(*) as num'))->first()->num;
        }


        //根据开始和每页条数筛选结果
        if (!empty($queryLimit["start"]))
        {
            $query->skip($queryLimit["start"]);
        }
        if (!empty($queryLimit["num"]))
        {

            $query = $query->take($queryLimit["num"]);
        }

        //筛选个别字段
        if (!empty($queryLimit["select"]))
        {
            if (!is_array($queryLimit["select"]))
            {
                throw new \Exception("select语句，条件必须是一个数组");
            }

            $select = "";
            foreach ($queryLimit["select"] as $k => $v)
            {
                if ($k > 0)
                {
                    $select .= ",";
                }
                $select .= $v;

            }
            $select .= " ";

            //如果是空的
            if (empty($queryLimit["select"]))
            {
                $select = "*";
            }
            $query->select(static::getBuilder($con)->raw($select));
        }

        //dump($query->toSql());//todo dump

        //是否使用laravel默认的分页机制,并处理结果
        $data = [];
        if (!empty($queryLimit["paginate"]))
        {
            $returnData["page"] = $query->paginate($queryLimit["paginate"]);
        }
        if (empty($queryLimit["first"]))
        {
            $data = $query->get();
            foreach ($data as $k => $v)
            {
                $data[$k] = (array)$v;
            }
        }
        else
        {
            $data = [];
            $data[] = (array)($query->first());
        }

        //执行连表
        if (!empty($queryLimit["link"]) && !empty($data))
        {
            static::$linkcal++;
            static::linkTable($data, $queryLimit["link"]);
            static::$linkcal--;
        }


        $canUseData = [];
        foreach ($data as $k => &$singleData)
        {
            //对接收到的数据进行处理
            if (!empty($queryLimit["resultConvert"]) && is_callable($queryLimit["resultConvert"]))
            {
                $queryLimit["resultConvert"]($singleData);
                if (empty($singleData))
                {
                    continue;
                }
            }

            //删除没有指定字段的项目
            if (!empty($queryLimit["deleteEmpty"]))
            {
                Helper::isArray($queryLimit["deleteEmpty"], "deleteEmpty必须是一个数组");
                $isDel = false;
                foreach ($queryLimit["deleteEmpty"] as $v)
                {
                    if (empty($singleData[$v]))
                    {
                        $isDel = true;
                        break;
                    }
                }
                if ($isDel)
                {
                    continue;
                }
            }

            //查询后数据过滤
            if (static::$linkcal == 0)
            {
                static::selectFilter($singleData); //过滤数据
            }
            $canUseData[] = $singleData;
        }
        if (empty($queryLimit["first"]) || $queryLimit["first"] != true)
        {
            $data = $canUseData;
        }
        else
        {
            empty($canUseData[0]) ? $data = [] : $data = $canUseData[0];
        }


        //返回结果
        $returnData["status"] = 200;
        $returnData["message"] = "成功获取到数据";
        $returnData["data"] = $data;


        if (sizeof($data) == 0 && !empty($queryLimit["start"]) && $queryLimit["start"] > 0)
        {
            $queryLimit["start"] = 0;
            $returnData = static::select($queryLimit, $originQuery);
            $returnData["resetStart"] = true;
        }
        else
        {
            $returnData["resetStart"] = false;

        }
        return $returnData;

    }


    /**
     * 模型实例化,传入id
     * ModelExtend constructor.
     * @param $id
     */
//    public function __construct($id = 0, $allowEmpty = false)
//    {
//        $this->id = $id;
//        try
//        {
//            $this->syncFromDataBase();
//        } catch (\PDOException $e)
//        {
//            if (!$allowEmpty)
//            {
//                throw $e;
//            }
//            else
//            {
//                Log::info("一条数据被允许作为空存在" . static::$connection . "|" . static::$table . "| id=" . $id);
//            }
//        }
//    }


    /**
     * 添加数据
     * @param $data //需要插入的数据
     * @return static //返回当前模型实例
     * @throws \Exception
     */
    public static function add($data)
    {
        $query = static::getQuery();
        static::addExtra($data, $query);

        $id = $query->insertGetId($data);
        if ($id == 0)
        {
            throw new \Exception("数据插入失败");
        }
        return new static($id);
    }

    /**
     * add函数的一个包装，为了和以前的laravel模型保持一致
     * @param $data
     * @return ModelExtend
     */
    public static function create($data)
    {
        return static::add($data);
    }

    /**
     * add 和 update函数的封装 ，如果一个数据不存在（按照主键判定），则创建，否则修改
     * @param $data
     * @return ModelExtend
     * @throws \Exception
     */
    public static function createOrUpdate($data)
    {
        if (empty($data[static::$primaryKey]))
        {
            throw new \Exception("createOrUpdate 缺少主键，无法定位数据");
        }
        $query["id"] = $data[static::$primaryKey];
        $result = static::select($query)["data"];
        if (sizeof($result) > 0)
        {
            $model = new static($result[0][static::$primaryKey]);
            $model->update($data);
        }
        else
        {
            $model = static::add($data);
        }
    }


    /**
     * 删除方法，只会删除当前数据
     */
    public function delete()
    {
        $query = static::getQuery();
        $this->deleteExtra($query);

        $r = $this->locationData($query)->delete();
    }


    /**
     * 更新数据，更新后模型数据不是最新的
     * @param $data //需要更新的数据，如果为空，那么不会有动作
     */
    public function update($data)
    {
        if (empty($data))
        {
            return;
        }

        $query = static::getQuery();
        $this->updateExtra($data, $query);

        $r = $this->locationData($query)->update($data);

    }


    /**
     * 批量删除数据，按照queryLimit查询，删除匹配到的查询,support Link
     * @param $queryLimit //匹配查询限制
     * @param null $query //可以选择传入一个构造器，自定义连接和表
     * @param null $key //自定义连接和表以后，需要指定主键
     * @return int
     * @throws \Exception
     */
    public static function deleteMultiple($queryLimit, $query = null, $key = null)
    {

        if (empty($queryLimit))
        {
            //throw new \Exception("危险,querlimit为空,这样可能删除所有的数据");
        }
        if (empty($query))
        {
            $query = static::getQuery();
        }
        if (is_string($query))
        {
            $conData = self::compileConnectionString($query);
            $query = static::getBuilder($conData["con"])->table($conData["table"]);
            $queryLimit["pk"] = $conData["field"];
            if (empty($key))
            {
                $key = $queryLimit["pk"];
            }
        }
        if (empty($key))
        {
            if (!empty($queryLimit["pk"]))
            {
                $key = $queryLimit["pk"];
            }
            else
            {
                $key = static::$primaryKey;
            }

        }
        $r = static::select($queryLimit, $query);
        if (empty($r["data"]))
        {
            return 0;
        }
        $count = 0;
        if (empty($r["data"][0]))
        {
            $tmp = $r["data"];
            $r["data"] = [];
            $r["data"][] = $tmp;
        }
        foreach ($r["data"] as $v)
        {

            if (!empty($query))
            {
                $q = clone $query;
                $delResult = $q->where($key, $v[$key])->delete();
            }
            else
            {
                $delResult = static::getQuery()->where($key, $v[$key])->delete();
            }
            if ($delResult)
            {
                $count++;
            }

            //11-28新加入了连表删除功能
            if (!empty($queryLimit["link"]))
            {
                //如果是单条连表条件,转换成多条
                if (!is_array($queryLimit["link"][0]))
                {
                    $tmp = $queryLimit["link"];
                    $queryLimit["link"] = [];
                    $queryLimit["link"][] = $tmp;

                }
                //连表查询每一条 将匹配的删除
                foreach ($queryLimit["link"] as $link)
                {
                    $conData = static::compileConnectionString($link[2]);
                    $queryNew = static::getBuilder($conData["con"])->table($conData["table"]);

                    isset($link[3]) ? $queryLimitNew = $link[3] : $queryLimitNew = [];
                    $queryLimitNew["pk"] = $conData["field"];

                    $queryLimitNew[":" . $conData["field"]] = $v[$link[1]];
                    static::deleteMultiple($queryLimitNew, $queryNew, $conData["field"]);
                }
            }

        }
        return $count;

    }

    /**
     * 按照QueryLimit多更新，匹配数据会被更新
     * @param $queryLimit //限制
     * @param $updateData //更新数据
     * @param null $query //可以选择传入一个构造器，自定义连接和表
     * @param null $key //自定义连接和表以后，需要指定主键
     */
    public static function updateMultiple($queryLimit, $updateData, $query = null, $key = null)
    {
        //todo update support link update  notice sync Use this function
        if (empty($key))
        {
            if (!empty($queryLimit["pk"]))
            {
                $key = $queryLimit["pk"];
            }
            else
            {
                $key = static::$primaryKey;
            }

        }

        $r = static::select($queryLimit, $query);
        foreach ($r["data"] as $v)
        {
            if (!empty($query))
            {
                $q = clone $query;
                $q->where($key, $v[$key])->update($updateData);
            }
            else
            {
                static::getQuery()->where($key, $v[$key])->update($updateData);
            }
        }
    }



//    /**
//     * 按照QueryLimit多更新，匹配数据会被更新
//     * @param $queryLimit //限制
//     * @param $updateData //更新数据
//     * @param null $query //可以选择传入一个构造器，自定义连接和表
//     * @param null $key //自定义连接和表以后，需要指定主键
//     * @return int
//     * //不启用  会和原来的多更新起冲突
//     */
//    public static function updateMultiple($queryLimit, $updateData, $query = null, $key = null)
//    {
//        /**
//         * 将一条数据转为可插入的,去掉link
//         * @param $queryLimit //查询
//         * @param $updateData //单条数据
//         * @return mixed
//         */
//        $resultToCanInsert = function ($queryLimit, $updateData)
//        {
//            if (!empty($queryLimit["link"]))
//            {
//                foreach ($updateData as $k => $singleField)
//                {
//                    foreach ($queryLimit["link"] as $link)
//                    {
//                        if (!empty($link[0]))
//                        {
//                            unset($updateData[$k]);
//                        }
//                    }
//                }
//                return $updateData;
//            }
//            else
//            {
//                return $updateData;
//            }
//        };
//        $handleLinkToMulti = function ($queryLimit)
//        {
//            if (!is_array($queryLimit["link"][0]))
//            {
//                $tmp = $queryLimit["link"];
//                $queryLimit["link"] = [];
//                $queryLimit["link"][] = $tmp;
//
//            }
//            return $queryLimit;
//        };
//
//
//
//        if (empty($query))
//        {
//            $query = static::getQuery();
//        }
//
//        if (is_string($query))
//        {
//            $conData = self::compileConnectionString($query);
//            $query = static::getBuilder($conData["con"])->table($conData["table"]);
//            $queryLimit["pk"] = $conData["field"];
//            if (empty($key))
//            {
//                $key = $queryLimit["pk"];
//            }
//        }
//        if (empty($key))
//        {
//            if (!empty($queryLimit["pk"]))
//            {
//                $key = $queryLimit["pk"];
//            }
//            else
//            {
//                $key = static::$primaryKey;
//            }
//
//        }
//
//        $r=0;
//        //如果是低纬度数据,转换成高纬度的
//        if (!isset($updateData[0])||!is_array($updateData[0])){
//            $tmp = $updateData;
//            $updateData = [];
//            $updateData[] = $tmp;
//        }
//        foreach ($updateData as $singleUpdateData)
//        {
//            $insertData = $resultToCanInsert($queryLimit, $singleUpdateData);
//            if(isset($singleUpdateData[$key]))
//            {
//                $r += $query->where($key, "=", $singleUpdateData[$key])->update($insertData);
//            }
//            else
//            {
//                $singleUpdateData[$key] =  $query->insertGetId($insertData);
//                $r +=1;
//            }
//
//
//            if (!empty($queryLimit["link"]))
//            {
//                $handleLinkToMulti($queryLimit);
//                foreach ($queryLimit["link"] as $link)
//                {
//                    if (!empty($link[3]))       //获取下一层的link
//                    {
//                        $newLimit = $link[3];
//                    }
//                    else
//                    {
//                        $newLimit = [];
//                    }
//
//
//                    if (!empty($link[0]))       //获取下一层的修改数据
//                    {
//                        $newNextData = $singleUpdateData[$link[0]];
//                    }
//                    else
//                    {
//                        $newNextData = ModelExtend::select([
//                            "first" => true,
//                            "id" => $singleUpdateData[$link[1]]
//                        ], $link[2])["data"];
//                        $tmp = [];
//                        foreach( $newNextData as $k=>$v)
//                        {
//                            $tmp[$k] = $singleUpdateData[$k];
//                        }
//                        $newNextData = $tmp;
//                    }
//
//                    //加一个下一级联系数据
//                    $conData = static::compileConnectionString($link[2]);
//                    $newLimit[":".$conData["field"]] = $newNextData[$conData["field"]];
//
//                    static::updateMultiple($newLimit, $newNextData, $link[2]);
//                }
//            }
//        }
//        return $r;
//
//    }


    /**
     * 同步添加，同步添加会根据映射关系去被同步库添加数据
     * 废弃：只需要update可以完成
     * @param $data //需要填入的数据，只支持单条数据
     * @param null $async // 是否启用异步
     * @return ModelExtend //返回这条数据生成的模型
     */
//    public static function syncAdd($data, $async = null)
//    {
//        if ($async === null)
//        {
//            $async = static::$async;
//        }
//        static::$syncMap = static::loadSyncMap();
//
//        if (empty(static::$syncMap))
//        {
//            //执行本库添加
//            $model = static::add($data);
//            return $model;
//        }
//        $model = static::add($data);
//        static::loadSyncCondition($model);
//        if ($async == false)
//        {
//            $model->asyncRunAdd();
//        }
//        else
//        {
//            $model->asyncSend($model->id, $model->syncCondition, "add");
//        }
//
//        return $model;
//
//    }
//
//    /**
//     * 异步运行同步，同步的同步数据也会使用这个函数，异步运行的需要在异步端运行这个函数
//     * 添加不需要匹配条件
//     */
//    public function asyncRunAdd()
//    {
//        $sonExecList = [];
//        foreach (static::$syncMap as $k => $v)
//        {
//            //将数据匹配map取出，将数据映射上
//            $insertData = $this->mapData($k);
//
//            if ($insertData)
//            {
//                //获取需要同步数据库链接
//                $connectionData = static::compileConnectionString($k);
//                $query = static::getBuilder($connectionData["con"])
//                    ->table($connectionData["table"]);
//                $r = $query->insert($insertData);
//            }
//        }
//    }

    /**
     * 同步更新，更新会根据条件匹配被同步库，如果没有数据，则会按照映射新加入，有数据会按照映射更新
     * @param $updateData //需要更新的参数数组
     * @param null $async //是否需要启用异步同步
     * @param array $argList
     */
    public function syncUpdate($updateData, $argList = [], $async = null)
    {
        if ($async === null)
        {
            $async = static::$async;
        }
        //有时候可能需要进行一些设置操作,必须在数据还存在时候进行
        if (!$async)
        {
            static::loadSyncCondition($this, $argList);
        }


        //执行本库修改
        $this->update($updateData);
        $this->syncFromDataBase();//获得最新数据


        //遍历每个表的规则
        if ($async == false)
        {
            $this->asyncRunUpdate();
        }
        else
        {
            $this->asyncSend($this->id, $argList, static::UpdateOperationSymbol);
        }


    }

    /**
     * 异步运行同步，同步的同步数据也会使用这个函数，异步运行的需要在异步端运行这个函数
     */
    public function asyncRunUpdate()
    {


        while (sizeof($this->syncCondition) > 0)
        {
            $sonExecList = [];
            foreach ($this->syncCondition as $k => $v)
            {
                try
                {

                    //match 将数据匹配map取出，将数据映射上,得到需要插入到老数据库的东西
                    $resultData = static::matchData($k, $v);
                    //map
                    $insertData = $this->mapData($k, $resultData);

                    if ($insertData)
                    {

                        //获取需要同步数据库链接
                        $connectionData = static::compileConnectionString($k);
                        $query = static::getBuilder($connectionData["con"])
                            ->table($connectionData["table"]);

                        //被同步方有没有匹配数据
                        if (sizeof($resultData) == 0)
                        {
                            //没有该匹配数据的行为，一次纯天然的添加
                            //执行添加
                            $query->insert($insertData);
                        }
                        else
                        {
                            //注意这里会把主键注入到里面方便使用功能
                            $v["pk"] = $connectionData["field"];
                            //可能其他的表已经添加了这一条数据，我们需要原处更新
                            //执行更新
//                            $query->where($connectionData["field"],"=",$resultData[$connectionData["field"]])->update($insertData);

                            static::updateMultiple($v, $insertData, $query, $connectionData["field"]);
                        }
                    }
                    $thisObj = $this;
                    if (isset($this->afterSyncExec[$k]) && is_callable($this->afterSyncExec[$k]))
                    {
                        $sonExecList[] = function () use ($thisObj, $k)
                        {
                            $thisObj->afterSyncExec[$k]($thisObj->syncCondition, $thisObj);
                        };
                    }
                } catch (\Exception $e)
                {
                    Log::error(Helper::handleException("更新同步时条件出错 跳过执行$k ", $e, true));
                } finally
                {
                    unset($this->syncCondition[$k]);
                }
            }

            foreach ($sonExecList as $sonExec)
            {
                $sonExec();
            }
        }

    }


    /**
     * 同步删除，会根据条件匹配，匹配后删除
     * @param array $argList
     * @param null $async 异步执行
     */
    public function syncDelete($argList = [], $async = null)
    {
        if ($async === null)
        {
            $async = static::$async;
        }

        if (!$async)
        {
            static::loadSyncCondition($this, $argList);
        }

        //执行本库删除
        //$this->delete();

        if ($async == false)
        {
            $thisObj = $this;
            static::asyncRunDelete($thisObj);
        }
        else
        {
            $this->asyncSend($this->id, $argList, static::DelOperationSymbol);
        }

    }

    /**
     * 异步运行同步，同步的同步数据也会使用这个函数，异步运行的需要在异步端运行这个函数
     * 注意删除的异步是静态的,因为在异步端数据可能已经被删除了,async不支持递归条件
     * @param $thisObj // virtual object, not have data because that have been deleted maybe
     */
    public static function asyncRunDelete($thisObj)
    {

        //遍历每个表的规则
        while (sizeof($thisObj->syncCondition) > 0)
        {
            $sonExecList = [];
            foreach ($thisObj->syncCondition as $k => $v)
            {
                try
                {
                    //根据规则匹配数据
                    $resultData = static::matchData($k, $v);

                    //不再需要映射


                    //获取需要同步数据库链接
                    $connectionData = static::compileConnectionString($k);
                    $query = static::getBuilder($connectionData["con"])
                        ->table($connectionData["table"]);

                    //被同步方有没有匹配数据
                    if (sizeof($resultData) == 0)
                    {
                        Log::info("no match data, stop delete" . json_encode($v));
                        //匹配失败,没有数据会被删除
                    }
                    else
                    {
                        $v["pk"] = $connectionData["field"];
                        //将匹配的数据删除
                        static::deleteMultiple($v, $query, $connectionData["field"]);
                    }

                    if (isset($thisObj->afterSyncExec[$k]) && is_callable($thisObj->afterSyncExec[$k]))
                    {
                        $sonExecList[] = function () use ($thisObj, $k)
                        {
                            $thisObj->afterSyncExec[$k]($thisObj->syncCondition, $thisObj);
                        };
                    }
                } catch (\Exception $e)
                {
                    Log::error(Helper::handleException("Delete同步时条件出错 跳过执行$k ", $e, true));
                } finally
                {
                    unset($thisObj->syncCondition[$k]);
                }

            }

            foreach ($sonExecList as $sonExec)
            {
                $sonExec();
            }
        }
    }

    /**
     * 如果需要异步化，请从新实现这个函数，发送异步请求
     * @param int $id //当前这条数据主键
     * @param $argList
     * @param string $opr //动作 如add update del
     */
    public static function asyncSend($id, $argList, $opr)
    {

    }

    public static function asyncRecv($id, $argList, $opr)
    {
        $modelObj = null;
        if ($opr == static::DelOperationSymbol)
        {
            $modelObj = new static($id, true);
            static::loadSyncCondition($modelObj, $argList);
            static::asyncRunDelete($modelObj);
        }
        else
        {
            $modelObj = new static($id);
            static::loadSyncCondition($modelObj, $argList);
            $modelObj->asyncRunUpdate();
        }


    }


    //同步条件映射设置函数
    /*
    1.条件  通过两套条件，匹配不同的数据
    本方条件直接通过id匹配，对端条件
    |-"connection.table.主键" = $queryLimit
    |-"connection.table.主键" = $queryLimit
    ......
    条件只有运行时才知道，所以模型只设定规则
    匹配发生在数据被修改前

    条件和映射要一一对应
    下面是示范，一个模型如果需要同步，应该覆盖该方法
    在这里返回初设条件，这些条件是同类型模型通用
    */
    /**
     * @param $thisObj //会传入本方对象
     */
    public static function loadSyncCondition($thisObj, $argList = [])//todo
    {
        static::loadSyncMap();
    }


    //追加一个条件
    /**
     * 添加同步条件
     * @param $connection //必须是 连接.表.主键 且要有对应映射
     * @param $queryLimit //限制被同步方的条件
     * @param null $afterExec //在这个同步条件执行完成后会执行function($condition,$thisObj=null)
     */
    public function appendSyncCondition($connection, $queryLimit, $afterExec = null)
    {
        $this->syncCondition[$connection] = $queryLimit;//条件可序列化的部分放入syncCondition
        if (is_callable($afterExec))
        {
            $this->afterSyncExec[$connection] = $afterExec;//匿名函数不可序列化部分放入afterSyncExec
        }
    }

    /**
     * 清理同步条件
     */
    public function cleanSyncCondition()
    {
        $this->syncCondition = [];
    }

    /*
    3.映射
    映射分为可修改和不可修改的
    |-connection.table.主键
        |-field1 = selfField1
    |-connection.table.主键
        |-field1= [selfField2 ,function(&$data){}] //如果需要对数据修改，将会把本条数据传入

    条件和映射要一一对应
    下面是示范，一个模型如果需要同步，应该覆盖该方法
     */
    /**
     * 返回映射关系表
     */
    public static function loadSyncMap()
    {
        /*
        return [
            "tour.product.product_id" => ["product_some_field" => "my_table_field"],
            "tour.product.product_id" => [
                "product_some_field" => [
                    "my_table_field",
                    function (&$data)
                    {
                        return $data["my_table_field"] * 99;
                    }
                ]
            ],
        ];
        */
    }


    //辅助函数


    public static function getOriginDB()
    {
        return app("db");
    }

    /**
     * 获取本连接表的查询
     * @return mixed
     */
    public static function getQuery()
    {
        return app("db")->connection(static::$connection)->table(static::$table);
    }

    /**
     * 获取本连接的查询
     * @param $connection
     * @return mixed
     */
    public static function getBuilder($connection = null, $isFull = false)
    {
        if (empty($connection))
        {
            $connection = static::$connection;
        }
        if ($isFull)
        {
            $conData = self::compileConnectionString($connection);
            $query = static::getBuilder($conData["con"])->table($conData["table"]);
            return $query;
        }
        return app("db")->connection($connection);
    }

    /**
     * 查询数据库中的最新本条数据，更新内存中本条的数据
     * @throws \Exception
     */
    public function syncFromDataBase()
    {
        $this->data = (array)$this->locationData(static::getQuery())->first();
        if (empty($this->data))
        {
            throw new \Exception("没有这一条记录," . static::$table . " id=" . $this->id);
        }
    }

    protected function locationData($q)
    {
        return $q->where(static::$primaryKey, $this->id);

    }

    /**
     * 过滤字段
     * @param $data //过滤的数据
     * @param $fieldList //需要过滤字段
     * @param bool $isForbid true //删除这些字段，false保留这些字段
     * @param array|null $messageSet //自定义错误消息
     * @return array //过滤后的数据
     * @throws ValidationException
     * @throws \Exception
     */
    public static function filter(&$data, $fieldList, $isForbid = false,$messageSet = [])
    {
        try
        {
            $isForbid ? $result = $data : $result = [];
            $checkList = [];
            foreach ($fieldList as $k => $v)
            {

                if (!is_int($k))
                {
                    $checkList[$k] = $v;
                    $v = $k;
                }

                if (!isset($data[$v]))
                {
                    continue;
                }
                if ($isForbid)
                {
                    unset($result[$v]);
                }
                else
                {
                    $result[$v] = $data[$v];
                }


            }

            if (!empty($checkList))
            {
                $validator = Validator::make($data,$checkList,$messageSet);
                if($validator->fails())
                {
                    $messages = $validator->errors();
                    $msgList = [];
                    foreach ($messages->all() as $message) {
                        $msgList[] = $message;
                    }
                    $msgList =  json_encode($msgList);
                    $r = response()->json(["status"=>500,"message"=>$msgList]);
                    throw new ValidationException($validator,$r);

                }
            }
            $data = $result;
            return $result;
        } catch (\Exception $e)
        {
            if($e instanceof ValidationException)
            {
                throw $e;
            }
            throw new \Exception("ModelExtend::filter 无法过滤数据 " . $e->getMessage() . " " . $e->getFile() . " " . $e->getLine());
        }

    }


    /**
     * 在获得数据的时候格式化一部分数据
     * @param $data //会传入每一条数据
     */
    public static function selectFilter(&$data)
    {

    }


    /**
     * 取出本条数据
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * 取出id
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * 动态更改链接
     * @param $str
     */
    public static function setConnection($str)
    {
        static::$connection = $str;
    }

    /**
     * 动态更改表
     * @param $str
     */
    public static function setTable($str)
    {
        static::$table = $str;
    }

    /**
     * 动态的更改主键
     * @param $str
     */
    public static function setPrimaryKey($str)
    {
        static::$primaryKey = $str;
    }

    /**
     * @return mixed
     */
    public static function getConnection()
    {
        return self::$connection;
    }

    /**
     * @return mixed
     */
    public static function getTable()
    {
        return self::$table;
    }

    /**
     * @return mixed
     */
    public static function getPrimaryKey()
    {
        return self::$primaryKey;
    }

    /**
     *  备份连接
     */
    public static function saveConnection()
    {
        static::$dumpData = [static::$connection, static::$table, static::$primaryKey];
    }

    /**
     * 还原连接
     * @throws \Exception
     */
    public static function rollbackConnection()
    {
        if (empty(static::$dumpData))
        {
            throw  new \Exception("没有备份配置");
        }
        static::$connection = static::$dumpData[0];
        static::$table = static::$dumpData[1];
        static::$primaryKey = static::$dumpData[2];

    }

    /**
     * 转换天数到时间戳
     * @param $dayStr //如 1970-01-02
     * @return int
     */
    public static function timeFromDayStr($dayStr, $format = 'Y-m-d')
    {
        $zone = new \DateTimeZone(env("TIMEZONE", "Asia/Chongqing"));
        $date = \DateTime::createFromFormat($format . " H:i:s",
            $dayStr . " 00:00:00", $zone);
        if ($date == false)
        {
            $date = 0;
        }
        else
        {
            $date = $date->getTimestamp();
        }
        return $date;
    }

    /**
     * 转换具体秒到时间戳
     * @param $str //如 1970-01-02 00:00:00
     * @return int
     */
    public static function timeFromSecondStr($str, $format = 'Y-m-d H:i:s')
    {
        $zone = new \DateTimeZone(env("TIMEZONE", "Asia/Chongqing"));
        $date = \DateTime::createFromFormat($format,
            $str, $zone);
        if ($date == false)
        {
            throw  new \Exception("time conver error $str  =>  $format !");
        }
        else
        {
            $date = $date->getTimestamp();
        }
        return $date;

        return $date;
    }

    /**
     * 从时间戳转换为 秒 格式 1970-01-02 00:00:00
     * @param $time
     * @return false|string
     */
    public static function timeToSecondStr($time)
    {
        date_default_timezone_set(env("TIMEZONE", "Asia/Chongqing"));
        return date('Y-m-d H:i:s', $time);
    }

    /**
     * 从时间戳转换为 天 格式 1970-01-02
     * @param $time
     * @return false|string
     */
    public static function timeToDayStr($time)
    {
        date_default_timezone_set(env("TIMEZONE", "Asia/Chongqing"));
        return date('Y-m-d', $time);

    }


    //自定义方案,可以在继承方案中被覆盖
    /**
     * 额外查询，可以自定义一些查询类型，在排序，决定条数之前调用,在这里面一般不要再附加queryLimit的语法，有一部分不会起作用
     * @param $queryLimit //传入的筛选函数
     * @param $query //查询构造器
     */
    protected static function selectExtra(&$queryLimit, $query)
    {

    }

    /**
     * 额外添加，在添加之前调用
     * @param $data //会被添加的数据
     * @param $query //查询构造器
     */
    protected static function addExtra(&$data, $query)
    {

    }

    /**
     * 额外更新，在更新之前调用
     * @param $data
     * @param $query
     */
    protected function updateExtra(&$data, $query)
    {

    }

    /**
     * 额外删除，在删除之前调用
     * @param $query
     */
    protected function deleteExtra($query)
    {

    }


    //同步内部调用
    /**
     * 建立匹配
     * @param $willMatch //需要匹配的链接，配置中的名字
     * @param $queryLimit //匹配限制，规则同select
     * @return array
     * @throws \Exception
     */
    protected static function matchData($willMatch, $queryLimit)
    {
        try
        {
            $connectionData = static::compileConnectionString($willMatch);
            $query = static::getBuilder($connectionData["con"])
                ->table($connectionData["table"]);
            $queryLimit["pk"] = $connectionData["field"];
            $queryLimit["first"] = true;
            $resultData = static::select($queryLimit, $query)["data"];
            return $resultData;
        } catch (\Exception $e)
        {
            throw new \Exception(Helper::handleException("匹配数据出错，请检查匹配条件" . $willMatch, $e, true));
        }

    }


    /**
     * 建立新老映射
     * @param $willMap //要使用的映射关系，配置中的名字
     * @return array    //返回映射后可以插入的数据 如果是使用默认逻辑 返回映射数据 自定逻辑返回false
     * @throws \Exception
     */
    protected function mapData($willMap, $matchData = [])
    {
        if (!isset(static::$syncMap[$willMap]))
        {
            Log::info(json_encode(static::$syncMap));
            throw new \Exception("没有这个映射关系 " . $willMap);
        }
        //将数据匹配map取出，将数据映射上
        $map = static::$syncMap[$willMap];
        $insertData = [];
        if (is_callable($map))
        {
            $map($matchData, $insertData, $this);
            return false;
        }
        foreach ($map as $mapK => $mapV)
        {
            try
            {
                if (is_callable($mapV) && !is_string($mapV))
                {
                    $insertData[$mapK] = $mapV($this->data, $insertData, $this);
                }
                else
                {
                    $insertData[$mapK] = $this->data[$mapV];
                }
            } catch (\Exception $e)
            {
                throw new \Exception(Helper::handleException("映射$willMap 时出错：" . $mapK, $e, true));
            }

        }
        return $insertData;
    }


    //内部调用方法
    /**
     * 编译字符串连接信息到真实的链接
     * @param $connectionData
     * @return array
     * @throws \Exception
     */
    public static function compileConnectionString($connectionData)
    {
        $con = null;
        $table = null;
        $field = null;
        $argList = explode(".", $connectionData);
        if (!is_array($argList) || sizeof($argList) > 3 || sizeof($argList) < 2)
        {
            throw new \Exception("错误的连接信息 " . $connectionData . " 参数表：" . json_encode($argList));
        }
        if (sizeof($argList) == 3)
        {
            $con = $argList[0];
            $table = $argList[1];
            $field = $argList[2];
        }
        else //为2
        {
            $con = static::$connection;
            $table = $argList[0];
            $field = $argList[1];
        }
        return ["con" => $con, "table" => $table, "field" => $field];
    }

    /**
     * 将条件从原格式转换为字符串
     * @return string
     */
    public function conditionToString()
    {
        $conditionStr = json_encode($this->syncCondition);
        return $conditionStr;
    }

    /**
     * 将条件从字符串转回原格式
     * @param $conditionStr
     * @return //条件数组
     */
    public function conditionFromString($conditionStr)
    {
        return $this->syncCondition = json_decode($conditionStr, true);
    }

    /**
     * 用于静态调用的条件获取
     * @param $conditionStr
     * @return mixed
     */
    public static function conditionFromStringStatic($conditionStr)
    {
        return json_decode($conditionStr, true);
    }


    /**
     * @param $where
     * @param $query
     * @throws \Exception
     */
    protected static function selectWhere($where, $query)
    {
        if (!is_array($where))
        {
            throw new \Exception("where语句，条件必须是一个数组");
        }
        if (!isset($where[0]) || !is_array($where[0]))
        {
            $tmp = $where;
            $where = [];
            $where[] = $tmp;
        }

        foreach ($where as $v)
        {
            if ($v[0] == "or")
            {
                $query->orWhere($v[1], $v[2], $v[3]);
                continue;
            }

            if ($v[0] == "and")
            {
                $query->where($v[1], $v[2], $v[3]);
                continue;
            }

            $query->where($v[0], $v[1], $v[2]);

        }
    }
    //ok
    /*
    "or"=>
    [
        "or"=>[
            ["product_id","1"],
           ["age","=","asa'],
         ],
        "and"=>[
             ["product_id","1"],
            ["age","=","asa'],
        ],

    ]


     */
    protected static function selectWhereOrAnd($key, $orAndlimit, $query)
    {
        if (isset($orAndlimit[0]))
        {
            if (empty($orAndlimit[0]) || !is_array($orAndlimit))
            {
                throw new \Exception("错误的limit" . $orAndlimit);
            }

            foreach ($orAndlimit as $k => $limit)
            {
                if (!is_int($k))
                {
                    continue;
                }

                if (!isset($limit[0]))
                {
                    if (isset($limit["and"]))
                    {
                        $function = function ($query) use ($limit)
                        {
                            static::selectWhereOrAnd("and", $limit["and"], $query);
                        };

                    }
                    else
                    {
                        $function = function ($query) use ($limit)
                        {
                            static::selectWhereOrAnd("or", $limit["or"], $query);
                        };
                    }
                    if ($key == "and")
                    {
                        $query->where($function);
                    }
                    if ($key == "or")
                    {
                        $query->orWhere($function);
                    }

                }
                else
                {
                    $field = $limit[0];
                    if (sizeof($limit) == 2)
                    {
                        $symbol = "=";
                        $value = $limit[1];
                    }
                    else
                    {
                        $symbol = $limit[1];
                        $value = $limit[2];
                    }

                    if ($key == "or")
                    {
                        $query->orWhere($field, $symbol, $value);
                    }
                    else
                    {
                        $query->where($field, $symbol, $value);
                    }
                }

            }

        }
        if (isset($orAndlimit["or"]) || isset($orAndlimit["and"]))
        {
            if (isset($orAndlimit["or"]))
            {
                $function = function ($query) use ($orAndlimit)
                {

                    static::selectWhereOrAnd("or", $orAndlimit["or"], $query);
                };
            }
            if (isset($orAndlimit["and"]))
            {
                $function = function ($query) use ($orAndlimit)
                {
                    static::selectWhereOrAnd("and", $orAndlimit["and"], $query);

                };
            }

            if ($key == "and")
            {
                $query->where($function);
            }
            if ($key == "or")
            {
                $query->orWhere($function);
            }
        }
    }


    /**
     * @param $where
     * @param $query
     * @throws \Exception
     */
    protected static function selectWhereIn($where, $query)
    {
        if (!is_array($where))
        {
            throw new \Exception("whereIn语句，条件必须是一个数组");
        }

        $query->whereIn($where[0], $where[1]);
    }

    /**
     * @param $queryLimit
     * @param $query
     */
    protected static function selectId($queryLimit, $query)
    {
        if (isset($queryLimit["pk"]))
        {
            $query->where($queryLimit["pk"], "=", $queryLimit["id"]);
        }
        else
        {
            $query->where(static::$primaryKey, "=", $queryLimit["id"]);
        }

    }

    /**
     * 支持类似与mongoDB的json查询
     * @param $queryLimit
     * @param $query
     */
    protected static function handleJsonQuery($queryLimit, $query)
    {
        $val = [];
        foreach ($queryLimit as $k => $limit)
        {
            $sp = substr($k, 0, 1);
            if ($sp != static::$valSymbol)
            {
                continue;
            };

            $k = substr($k, 1);
            $val[$k] = $limit;

        }

        //每一个json条件之间形成and关系
        $query->where(function ($query) use ($val)
        {
            foreach ($val as $k => $v)
            {
                if (is_array($v))
                {
                    if (!empty($v))
                    {
                        if (isset($v[0]))
                        {
                            $query->whereIn($k, $v);
                        }
                        else
                        {
                            foreach ($v as $instruct => $x)
                            {
                                if ($instruct == ":like")
                                {
                                    $query->where($k, "like", $x);
                                }
                            }
                        }

                    }

                }
                else
                {
                    $query->where($k, "=", $v);
                }

            }

        });


    }

    /**
     * 连表查询调用方法
     * @param $data
     * @param $links
     * @throws \Exception
     */
    protected static function linkTable(&$data, &$links)
    {
        if (empty($data)||empty($data[0]))
        {
            return;
        } // 12/12 if data is null will be repeat without limit
        if (!is_array($links))
        {
            throw new \Exception("link条件必须为一个数组");
        }

        $linkField = [];//存对面的域
        $linkLimit = [];//按照连接存限制
        $linkConnection = [];//存连接
        $linkSelect = [];//存附加select
        $linkName = []; //存子数组名
        $linkResult = [];//结果
        $linkSelf = [];//存self field


        //对于单条的link可以简写
        if (!is_array($links[0]))
        {
            $tmp = $links;
            $links = [];
            $links[] = $tmp;
        }


        //遍历每一条规则,准备阶段
        foreach ($links as $link)
        {
            try
            {
                $name = $link[0];
                $selfFiled = $link[1];
                $connectionStr = $link[2];

                //存连接
                $connectionData = static::compileConnectionString($connectionStr);
                if (isset($linkConnection[$connectionStr]))
                {
                    $connectionStr .= ":" . rand(0, 999999);
                }//多连接查询的情况,两个连接会重叠在一起
                $linkConnection[$connectionStr] = static::getBuilder($connectionData["con"])
                    ->table($connectionData["table"]);

                //存self field
                $linkSelf[$connectionStr] = $selfFiled;

                //存附加select
                $select = [];
                if (isset($link[3]))
                {
                    $select = $link[3];
                }
                $linkSelect[$connectionStr] = $select;


                //存对面的域
                $linkField[$connectionStr] = $connectionData["field"];

                //存子数组名
                $linkName[$connectionStr] = $name;

                //遍历每一条数据 存赛选数据
                if (empty($data))
                {
                    continue;
                }
                if (isset($data[0]))//多条
                {
                    foreach ($data as &$singleData)
                    {
                        $linkLimit[$connectionStr][] = $singleData[$selfFiled];
                    }
                }
                else//单条
                {
                    $linkLimit[$connectionStr][] = $data[$selfFiled];
                }
            } catch (\Exception $e)
            {
                throw new \Exception(Helper::handleException("LinkTable 错误的link参数:" . json_encode($link) . " ", $e,
                    true));
            }


        }

        //只会有 link数 个 查询 = m[ n ]
        foreach ($linkConnection as $k => $query)
        {

            //将与设定条件加入
            $query->where(function ($query) use ($k, $linkLimit, $linkField)
            {
                foreach ($linkLimit[$k] as $limitValue)
                {
                    //0303 防止空元素进入
                    if($limitValue === null)
                    {
                        continue;
                    }
                    $query->orWhere($linkField[$k], "=", $limitValue);
                }
            });
            $handleResult = function ($next, &$self, $nextK, $selfK, $needDivide = null, $first = false)
            {
                //解决每个单条组合
                $handleSingleData = function ($next, &$self, $needDivide = null, $multi = false)
                {
                    if ($needDivide == null)
                    {
                        $self = array_merge($self, $next);
                    }
                    else
                    {
                        if ($multi)
                        {
                            $self[$needDivide][] = $next;
                        }
                        else
                        {
                            $self[$needDivide] = $next;
                        }

                    }

                };

                //多条
                if (isset($self[0]))
                {
                    foreach ($self as &$singleSelf)
                    {
                        foreach ($next as &$nextSingle)
                        {
                            if ($nextSingle[$nextK] == $singleSelf[$selfK])
                            {
                                if ($first)
                                {

                                    $handleSingleData($nextSingle, $singleSelf, $needDivide, false);
                                }
                                else
                                {
                                    $handleSingleData($nextSingle, $singleSelf, $needDivide, true);
                                }

                            }

                        }
                        if ($needDivide != null && empty($singleSelf[$needDivide]))//2016-11-10修改位置,如果放在以前的位置上面,会导致当条件为该值时无法进行匹配
                        {
                            $singleSelf[$needDivide] = [];
                        }
                    }

                }
                //单条
                else
                {

                    //下一级数据条数
                    foreach ($next as &$nextSingle)
                    {
                        if ($nextSingle[$nextK] == $self[$selfK])
                        {

                            if ($first)
                            {

                                $handleSingleData($nextSingle, $self, $needDivide, false);
                            }
                            else
                            {
                                $handleSingleData($nextSingle, $self, $needDivide, true);
                            }

                        }

                    }
                    if ($needDivide != null && empty($singleSelf[$needDivide]))
                    {
                        $singleSelf[$needDivide] = [];
                    }
                }

            };

            $first = false;
            if (isset($linkSelect[$k]["first"]) && $linkSelect[$k]["first"] == true)
            {
                unset($linkSelect[$k]["first"]);
                $first = true;
            }

            if ($linkName[$k] == null)
            {
                $linkResult[$k] = static::select($linkSelect[$k], $query,true)["data"];//0308 最后一个参数为true避免计算总数
                $handleResult($linkResult[$k], $data, $linkField[$k], $linkSelf[$k], null, $first);
            }
            else
            {
                $linkResult[$k] = static::select($linkSelect[$k], $query,true)["data"]; //0308 最后一个参数为true避免计算总数
                $handleResult($linkResult[$k], $data, $linkField[$k], $linkSelf[$k], $linkName[$k], $first);

            }


        }
        //总的查询数  m[n]表示第n层的link数   m[0] * m[1] * m[2] ......


    }

    public static function getField($con = null)
    {
        if(empty($con))
        {
            $q= static::getBuilder();
        }
        else
        {
            $q = static::getBuilder($con,true);
        }

        $data = $q->select("show columns from ".static::$table);
        $result = [];
        foreach($data as $v)
        {
            $result[] = $v->Field;
        }
        return $result;
    }


}