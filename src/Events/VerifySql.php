<?php

declare(strict_types=1);
/*
 * PHP version 7.1
 *
 * @copyright Copyright (c) 2012-2017 EELLY Inc. (https://www.eelly.com)
 * @link      https://api.eelly.com
 * @license   衣联网版权所有
 */
namespace Eelly\DevTools\Events;

use Phalcon\Di\Injectable;
/**
 * mySql验证类
 */
class VerifySql extends Injectable
{
    /**
     * sql 规则
     */
    private $sqlRule = [
        'likeVerify'    => ['/WHERE(.*)(\s+)LIKE(\s+)\'%([^%]*)%\'/i', 'SQL查询条件中LIKE不能同时使用左右%号：'],
        'allVerify'     => ['/SELECT(\s+)(((\`(\w+)\`)|(\w+))\.)?\*/i', 'SQL查询禁用*符号：'],
        'randVerify'    => ['/order\s+rand/i', 'SQL查询禁用rand排序：'],
        'regularVerify' => ['/where.+\sregexp\s+(\'|")/i', 'SQL查询禁用正则：']
    ];

    /**
     * sql连接池对象
     * 
     * @param object $connection
     */
    private $connection;

    public function __construct($connection) 
    {
        $this->connection = $connection;
    }
	
    /**
     * sql语句规则校验
     * @param string $sql
     */
    public function sqlVerify($sql)
    {
        //检验sql查询语句
        foreach($this->sqlRule as $value){
            if (preg_match($value[0], $sql)) {
                throw new \Exception($value[1] . $sql);
            }
        }
        //校验join数量
        $this->joinVerify($sql);
        //如果是select 情况下 分析sql语句
        if(preg_match('/^SELECT/i', $sql)){
            $this->explainSql($sql);
        }
    }
        
    /**
     * join数量校验
     * @param string $sql
     * @throws \Exception
     * 
     */
    public function joinVerify($sql)
    {
        // 验证*查询
        if (preg_match('/ join /i', $sql, $matchs)) {
            if (count($matchs[0]) > 5) {
                throw new \Exception('SQL联表不能超过5个：' . $sql);
            }
        }

    }
    
    /**
     * sql语句性能分析
     * @param string $sql
     * @throws \Exception
     */
    public function explainSql($sql)
    {
        $explainSql = 'explain '.$sql;
        $variables = $this->connection->getSqlVariables();
        if(!empty($variables)){
            foreach ($variables as $key => $value){
                //怀疑有BUG 所以强制判断一下
                if(strpos($key, 'APL') !== false && substr($key, 0, 1) != ":"){
                    $variables[":".$key] = $value;
                    unset($variables[$key]);
                    $key = ":".$key;
                }
                is_string($value) && $variables[$key] = "'". $value . "'";
            }
            $explainSql = str_replace(array_keys($variables), array_values($variables), $explainSql);
        }
        
        $result = $this->connection->query($explainSql);
        $result->setFetchMode(\Phalcon\Db::FETCH_ASSOC);
        $explainResult = $result->fetchAll();
        //获取注解信息
        $router = $this->di->get("router");
        $nameSpace = $router->getNamespaceName();
        $controllerName = $router->getControllerName();
        $methodName = $router->getActionName();
        $controllerClass = $nameSpace.'\\'.ucfirst($controllerName).'Logic';
        $annotations = $this->annotations->getMethod(
            $controllerClass,
            $methodName
        );
        dd($annotations);
        if($annotations->has('badSql')){
            return true;
        }
        var_dump($explainResult);die();
        if(!empty($explainResult['type']) && strtoupper($explainResult['type']) == "ALL"){
            throw new \Exception("你的SQL有点问题,是不是应该优化优化" . $sql);
        }
        //"Oauth\Logic\AuthorizationserverLogic"
        //"accessToken"
        //dd($this->di);
        //$nameSpace.'\\'.$controllerName.'Logic'
        //dd($a);
        /*while ($robot = $result->fetch()) {
            var_dump($robot);
        }*/
        //dd(123);die();
    }
}

