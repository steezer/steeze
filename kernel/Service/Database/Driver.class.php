<?php
namespace Service\Database;

use PDO;
use PDOException;
use Exception;

/**
 * 数据库实现基类
 * 
 * @package Database
 * @method array getFields($tableName) 获取字段
 * @method array getTables($dbName='') 取得数据库的表信息
 */
abstract class Driver {
    /**
     * PDO操作实例
     *
     * @var \PDOStatement
     */
    protected $PDOStatement = null;
    // 当前操作所属的模型名
    protected $model = '_steeze_';
    // 当前SQL指令
    protected $queryStr = '';
    protected $modelSql = array();
    // 最后插入ID
    protected $lastInsID = null;
    // 返回或者影响记录数
    protected $numRows = 0;
    // 事务指令数
    protected $transTimes = 0;
    // 错误信息
    protected $error = '';
    // 数据库连接ID 支持多个连接
    protected $linkID = array();
    // 当前连接ID
    protected $_linkID = null;
    // 前端连接NUM
    protected $_linkNum = 0;
    // 数据库连接参数配置
    protected $config = array(
        'type' => '', // 数据库类型
        'hostname' => '127.0.0.1', // 服务器地址
        'database' => '', // 数据库名
        'username' => '', // 用户名
        'password' => '', // 密码
        'hostport' => '', // 端口     
        'dsn' => '', // 数据源配置
        'params' => array(), // 数据库连接参数        
        'charset' => 'utf8', // 数据库编码默认采用utf8  
        'prefix' => '',   // 数据库表前缀
        'debug' => false, // 数据库调试模式
        'deploy' => 0, // 数据库部署方式:0 集中式(单一服务器),1 分布式(主从服务器)
        'rw_separate' => false, // 数据库读写是否分离 主从式有效
        'master_num' => 1, // 读写分离后 主服务器数量
        'slave_no' => '', // 指定从服务器序号
        'db_like_fields' => '', // 模糊查询字段
        'auto_reconnect' => true, // 离线后是否重新连接
    );
    // 数据库表达式
    protected $exp = array(
                    'eq'=>'=','neq'=>'<>','gt'=>'>','egt'=>'>=','lt'=>'<','elt'=>'<=',
                    'notlike'=>'NOT LIKE','like'=>'LIKE',
                    'in'=>'IN','notin'=>'NOT IN','not in'=>'NOT IN',
                    'between'=>'BETWEEN','not between'=>'NOT BETWEEN','notbetween'=>'NOT BETWEEN'
                );
    // 查询表达式
    protected $selectSql = 'SELECT%DISTINCT% %FIELD% FROM %TABLE%%FORCE%%JOIN%%WHERE%%GROUP%%HAVING%%ORDER%%LIMIT% %UNION%%LOCK%%COMMENT%';
    // 查询次数
    protected $queryTimes = 0;
    // 执行次数
    protected $executeTimes = 0;
    // PDO连接参数
    protected $options = array(
        PDO::ATTR_CASE => PDO::CASE_NATURAL,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
        PDO::ATTR_STRINGIFY_FETCHES => false,
	);
    protected $bind = array(); // 参数绑定

    /**
     * 架构函数 读取数据库配置信息
     * 
     * @param array $config 数据库配置数组
     */
    public function __construct($config=''){
        if(!empty($config)) {
            $this->config = array_merge($this->config, (array)$config);
            if(is_array($this->config['params'])){
                $this->options = $this->config['params'] + $this->options;
            }
        }
    }

    /**
     * 连接数据库方法
     * 
     * @param array $config
     * @param int $linkNum
     * @param bool|array $autoConnection
     */
    public function connect($config='', $linkNum=0, $autoConnection=false) {
    	$this->_linkNum=$linkNum;
    	if ( !isset($this->linkID[$linkNum]) ) {
            if(empty($config)){
                $config = $this->config;
            }
            try{
                if(empty($config['dsn'])) {
                    $config['dsn'] = $this->parseDsn($config);
                }
                if(version_compare(PHP_VERSION,'5.3.6','<=')){
                    // 禁用模拟预处理语句
                    $this->options[PDO::ATTR_EMULATE_PREPARES] = false;
                }
                
                //为sqlite数据库创建目录 sqlite:
                if($config['type']=='sqlite'){
                    if(strpos($config['dsn'], 'sqlite:')!==0){
                        $config['dsn']='sqlite:'.$config['dsn'];
                    }
                    $dsn=substr($config['dsn'],7);
                    strtolower($dsn) != ':memory:' && 
                        !is_dir($dirname=dirname($dsn)) &&
                            mkdir($dirname, 0777, true);
                }
                
                	//建立数据库连接对象
                $this->linkID[$linkNum] = new PDO( $config['dsn'], $config['username'], $config['password'],$this->options);
				
                //连接对象后期处理
				if($config['type']=='mysql'){
					$this->linkID[$linkNum]->exec('SET sql_mode=\'\'');
				}
			}catch (PDOException $e) {
                if($autoConnection){
                    trace($e->getMessage(),'','ERR');
                    return $this->connect($autoConnection,$linkNum);
                }elseif($config['debug']){
                    throw new Exception($e);
                }
            }
        }
        return $this->linkID[$linkNum];
    }

    /**
     * 解析pdo连接的dsn信息
     * 
     * @param array $config 连接信息
     * @return string
     */
    protected function parseDsn($config){}

    /**
     * 释放查询结果
     */
    public function free() {
        $this->PDOStatement = null;
    }
    
    /**
     * 过滤字符串并在两边加入引号
     *
     * @param string $val
     * @return string
     */
    public function addQuot($val){
        return '\''.$this->escapeString($val).'\''; 
    }

    /**
     * 执行查询 返回数据集
     * 
     * @param string $str  sql指令
     * @param boolean $fetchSql  不执行只是获取SQL
     * @return mixed
     */
    public function query($str,$fetchSql=false, &$options=array()) {
        $this->initConnect(false);
        if (!$this->_linkID){
             return false;
        }
        $this->queryStr = $str;
        if(!empty($this->bind)){
            $this->queryStr = strtr(
                    $this->queryStr,
                    array_map(
                        array($this, 'addQuot'), 
                        $this->bind
                    )
                );
        }
        if($fetchSql){
            return $this->queryStr;
        }
        //释放前次的查询结果
        if ( !empty($this->PDOStatement) ){
             $this->free();
        }
        $this->queryTimes++;

        // 调试开始
        $this->debug(true);
        $this->PDOStatement = $this->_linkID->prepare($str);
        if(false === $this->PDOStatement){
            $this->error();
            return false;
        }
        foreach ($this->bind as $key => $val) {
            if(is_array($val)){
                $this->PDOStatement->bindValue($key, $val[0], $val[1]);
            }else{
                $this->PDOStatement->bindValue($key, $val);
            }
        }
        $this->bind = array();
        
        // 长时间连接断开后自动重连
        try{
        	$result = $this->PDOStatement->execute();
        }catch (PDOException $e){
            if(
                $this->config['auto_reconnect'] && 
                $e->getCode()=='HY000' && 
                stripos($e->getMessage(), 'MySQL server has gone away')
            ){
                $this->free();
                $this->close();
                unset($this->linkID[$this->_linkNum]);
                return $this->query($str,$fetchSql, $options);
            }
        }
        
        // 调试结束
        $this->debug(false);
        if (!isset($result) || false === $result ) {
            $this->error();
            return false;
        } else {
            return $this->getResult($str, $options);
        }
    }

    /**
     * 执行语句
     * 
     * @param string $str  sql指令
     * @param boolean $fetchSql  不执行只是获取SQL
     * @return mixed
     */
    public function execute($str,$fetchSql=false) {
        $this->initConnect(true);
        if ( !$this->_linkID ){
             return false;
        }
        $this->queryStr = $str;
        if(!empty($this->bind)){
            $this->queryStr = strtr(
                $this->queryStr, 
                array_map(
                    array($this, 'addQuot'),
                    $this->bind
                )
            );
        }
        if($fetchSql){
            return $this->queryStr;
        }
        //释放前次的查询结果
        if ( !empty($this->PDOStatement) ){
            $this->free();
        }
        $this->executeTimes++;
        // 记录开始执行时间
        $this->debug(true);
        $this->PDOStatement =   $this->_linkID->prepare($str);
        if(false === $this->PDOStatement) {
            $this->error();
            return false;
        }
        foreach ($this->bind as $key => $val) {
            if(is_array($val)){
                $this->PDOStatement->bindValue($key, $val[0], $val[1]);
            }else{
                $this->PDOStatement->bindValue($key, $val);
            }
        }
        $this->bind =   array();
        
        // 长时间连接断开后自动重连
		try{
			$result=$this->PDOStatement->execute();
		}catch(PDOException $e){
            $this->error();
			if(
                $this->config['auto_reconnect'] && 
                $e->getCode() == 'HY000' && 
                stripos($e->getMessage(), 'MySQL server has gone away')
            ){
				$this->free();
				$this->close();
				unset($this->linkID[$this->_linkNum]);
				return $this->execute($str, $fetchSql);
			}
		}
        
        $this->debug(false);
        if ( isset($result) && false === $result) {
            $this->error();
            return false;
        } else {
            $this->numRows = $this->PDOStatement->rowCount();
            if(preg_match("/^\s*(INSERT\s+INTO|REPLACE\s+INTO)\s+/i", $str)) {
                $this->lastInsID = $this->_linkID->lastInsertId();
            }
            return $this->numRows;
        }
    }

    /**
     * 启动事务
     * 
     * @return boolean
     */
    public function startTrans() {
        $this->initConnect(true);
        if ( !$this->_linkID ) return false;
        //数据rollback 支持
        if ($this->transTimes == 0) {
            $this->_linkID->beginTransaction();
        }
        $this->transTimes++;
        return true;
    }

    /**
     * 用于非自动提交状态下面的查询提交
     * 
     * @return boolean
     */
    public function commit() {
        if ($this->transTimes > 0) {
            $result = $this->_linkID->commit();
            $this->transTimes = 0;
            if($result){
                return true;
            }
            $this->error();
        }
        return false;
    }

    /**
     * 事务回滚
     * 
     * @return boolean
     */
    public function rollback() {
        if ($this->transTimes > 0) {
            $result = $this->_linkID->rollback();
            $this->transTimes = 0;
            if(!$result){
                $this->error();
                return false;
            }
        }
        return true;
    }

    /**
     * 获得所有的查询数据
     * @access private
     * @return array
     */
    private function getResult($sql='', &$options=array()) {
        //返回数据集
		try{
            $isIndex=isset($options['index']);
            $isResult=isset($options['result']);
            $param=array();
            
            // 如果无索引和结果处理全部获取后返回
            if(!$isIndex && !$isResult){
                $result = $this->PDOStatement->fetchAll(PDO::FETCH_ASSOC);
            }else{
                $result=array();
                $statement=$this->PDOStatement;
                $indexNum=0;
                $param[]=$indexNum;
                while(($row=$statement->fetch(PDO::FETCH_ASSOC))!==false){
                    // 处理结果集
                    if($isResult){
                        array_pop($param);
                        $param[]=$indexNum;
                        $row=$this->setResult($row, $options['result'], $param);
                        if($row===true){
                            continue;
                        }elseif ($row===false) {
                            break;
                        }
                    }
                    $indexNum++;
                    // 处理索引
                    if($isIndex){
                        $index=explode(',', $options['index']);
                        $_key=$row[$index[0]];
                        if(isset($index[1]) && isset($row[$index[1]])){
                            $result[$_key]=$row[$index[1]];
                        }else{
                            $result[$_key]=$row;
                        }
                    }else{
                        $result[]=$row;
                    }
                }
                $statement=null;
            }
            $this->numRows = count( $result );
            return $result;
		}catch(Exception $e){
			$this->numRows = $this->PDOStatement->rowCount();
            if($sql&&preg_match("/^\s*(INSERT\s+INTO|REPLACE\s+INTO)\s+/i", $sql)) {
                $this->lastInsID = $this->_linkID->lastInsertId();
            }
            return $this->numRows;
		}
    }
    
    /**
     * 设置处理结果
     *
     * @param array $data
     * @param mixed $type
     * @param array $param
     */
    protected function setResult($data, $type=null, $param=array()){
		if(!empty($type)){
			if(is_callable($type)){
                array_unshift($param, $data);
				return call_user_func_array($type, $param);
			}
			switch(strtolower($type)){
				case 'json':
					return json_encode($data);
				case 'xml':
					return xml_encode($data);
			}
		}
		return $data;
	}

    /**
     * 获得查询次数
     * 
     * @param boolean $execute 是否包含所有查询
     * @return integer
     */
    public function getQueryTimes($execute=false){
        return $execute?$this->queryTimes+$this->executeTimes:$this->queryTimes;
    }

    /**
     * 获得执行次数
     * 
     * @return integer
     */
    public function getExecuteTimes(){
        return $this->executeTimes;
    }

    /**
     * 关闭数据库
     */
    public function close() {
        $this->_linkID = null;
    }

    /**
     * 数据库错误信息
     * 并显示当前的SQL语句
     * 
     * @return string
     */
    public function error() {
        if($this->PDOStatement) {
            $error = $this->PDOStatement->errorInfo();
            $this->error = $error[1].':'.$error[2];
        }else{
            $this->error = '';
        }
        if('' != $this->queryStr){
            $this->error .= "\n [ SQL语句 ] : ".$this->queryStr;
        }
        // 记录错误日志
        trace($this->error,'','ERR');
        if($this->config['debug']) {// 开启数据库调试模式
            throw new Exception($this->error);
        }else{
            return $this->error;
        }
    }

    /**
     * 设置锁机制
     * 
     * @return string
     */
    protected function parseLock($lock=false) {
        return $lock ? ' FOR UPDATE ' : '';
    }

    /**
     * set分析
     * 
     * @param array $data
     * @return string
     */
    protected function parseSet($data) {
        $set=array();
        foreach ($data as $key=>$val){
            if(is_array($val) && 'exp' == $val[0]){
                $set[] = $this->parseKey($key).'='.$val[1];
            }elseif(is_null($val)){
                $set[] = $this->parseKey($key).'=NULL';
            }elseif(is_scalar($val)) {// 过滤非标量数据
                if(0===strpos($val,':') && in_array($val,array_keys($this->bind)) ){
                    $set[] = $this->parseKey($key).'='.$this->escapeString($val);
                }else{
                    $name = count($this->bind);
                    $set[] = $this->parseKey($key).'=:'.$name;
                    $this->bindParam($name,$val);
                }
            }
        }
        return ' SET '.implode(',',$set);
    }

    /**
     * 参数绑定
     * 
     * @param string $name 绑定参数名
     * @param mixed $value 绑定值
     * @return void
     */
    protected function bindParam($name,$value){
        $this->bind[':'.$name] = $value;
    }

    /**
     * 字段名分析
     * 
     * @param string $key
     * @return string
     */
    protected function parseKey(&$key) {
        return $key;
    }
    
    /**
     * value分析
     * 
     * @param mixed $value
     * @return mixed
     */
    protected function parseValue($value) {
        if(is_string($value)) {
            $value =  strpos($value,':') === 0 && in_array($value,array_keys($this->bind)) ? 
                        $this->escapeString($value) : '\''.$this->escapeString($value).'\'';
        }elseif(
            isset($value[0]) && 
            is_string($value[0]) && 
            strtolower($value[0]) == 'exp'
        ){
            $value =  $this->escapeString($value[1]);
        }elseif(is_array($value)) {
            $value =  array_map(array($this, 'parseValue'),$value);
        }elseif(is_bool($value)){
            $value =  $value ? '1' : '0';
        }elseif(is_null($value)){
            $value =  'null';
        }
        return $value;
    }

    /**
     * field分析
     * 
     * @param mixed $fields
     * @return string
     */
    protected function parseField($fields) {
        if(is_string($fields) && '' !== $fields) {
            $fields = explode(',',$fields);
        }
        if(is_array($fields)) {
            // 完善数组方式传字段名的支持
            // 支持 'field1'=>'field2' 这样的字段别名定义
            $array = array();
            foreach ($fields as $key=>$field){
                if(!is_numeric($key)){
                    $array[] = $this->parseKey($key).' AS '.$this->parseKey($field);
                } else {
                    $array[] = $this->parseKey($field);
                }
            }
            $fieldsStr = implode(',', $array);
        }else{
            $fieldsStr = '*';
        }
        //TODO 如果是查询全部字段，并且是join的方式，那么就把要查的表加个别名，以免字段被覆盖
        return $fieldsStr;
    }

    /**
     * table分析
     * 
     * @param mixed $table
     * @return string
     */
    protected function parseTable($tables) {
        if(is_array($tables)) {// 支持别名定义
            $array = array();
            foreach ($tables as $table=>$alias){
                if(!is_numeric($table)){
                    $array[] = $this->parseKey($table).' '.$this->parseKey($alias);
                }else{
                    $array[] = $this->parseKey($alias);
                }
            }
            $tables = $array;
        }elseif(is_string($tables)){
            $tables = (array)explode(',',$tables);
            array_walk($tables, array(&$this, 'parseKey'));
        }
        return implode(',',$tables);
    }

    /**
     * where分析
     * 
     * @param mixed $where
     * @return string
     */
    protected function parseWhere($where) {
        $whereStr = '';
        if(is_string($where)) {
            // 直接使用字符串条件
            $whereStr = $where;
        }else{
            // 使用数组表达式
            $operate = isset($where['_logic'])?strtoupper($where['_logic']):'';
            if(in_array($operate,array('AND','OR','XOR'))){
                // 定义逻辑运算规则 例如 OR XOR AND NOT
                $operate = ' '.$operate.' ';
                unset($where['_logic']);
            }else{
                // 默认进行 AND 运算
                $operate = ' AND ';
            }
            foreach ($where as $key=>$val){
                if(is_numeric($key)){
                    $key  = '_complex';
                }
                if(0===strpos($key,'_')) {
                    // 解析特殊条件表达式
                    $whereStr   .= $this->parseSteezeWhere($key,$val);
                }else{
                    // 查询字段的安全过滤
                    // if(!preg_match('/^[A-Z_\|\&\-.a-z0-9\(\)\,]+$/',trim($key))){
                    //     throw new Exception(L('_EXPRESS_ERROR_').':'.$key);
                    // }
                    // 多条件支持
                    $multi = is_array($val) &&  isset($val['_multi']);
                    $key = trim($key);
                    if(strpos($key,'|')) { // 支持 name|title|nickname 方式定义查询字段
                        $array =  explode('|',$key);
                        $str   =  array();
                        foreach ($array as $m=>$k){
                            $v =  $multi?$val[$m]:$val;
                            $str[]   = $this->parseWhereItem($this->parseKey($k),$v);
                        }
                        $whereStr .= '( '.implode(' OR ',$str).' )';
                    }elseif(strpos($key,'&')){
                        $array =  explode('&',$key);
                        $str   =  array();
                        foreach ($array as $m=>$k){
                            $v =  $multi?$val[$m]:$val;
                            $str[]   = '('.$this->parseWhereItem($this->parseKey($k),$v).')';
                        }
                        $whereStr .= '( '.implode(' AND ',$str).' )';
                    }else{
                        $whereStr .= $this->parseWhereItem($this->parseKey($key),$val);
                    }
                }
                $whereStr .= $operate;
            }
            $whereStr = substr($whereStr,0,-strlen($operate));
        }
        return empty($whereStr)?'':' WHERE '.$whereStr;
    }

    /**
     * where子单元分析
     *
     * @param string $key
     * @param array $val
     * @return string
     */
    protected function parseWhereItem($key, $val) {
        $whereStr = '';
        if(is_array($val)) {
            if(is_string($val[0])) {
				$exp	=	strtolower($val[0]);
                if(preg_match('/^(eq|neq|gt|egt|lt|elt)$/',$exp)) { // 比较运算
                    $whereStr .= $key.' '.$this->exp[$exp].' '.$this->parseValue($val[1]);
                }elseif(preg_match('/^(notlike|like)$/',$exp)){// 模糊查找
                    if(is_array($val[1])) {
                        $likeLogic  =   isset($val[2])?strtoupper($val[2]):'OR';
                        if(in_array($likeLogic,array('AND','OR','XOR'))){
                            $like    = array();
                            foreach ($val[1] as $item){
                                $like[] = $key.' '.$this->exp[$exp].' '.$this->parseValue($item);
                            }
                            $whereStr .= '('.implode(' '.$likeLogic.' ',$like).')';                          
                        }
                    }else{
                        $whereStr .= $key.' '.$this->exp[$exp].' '.$this->parseValue($val[1]);
                    }
                }elseif('bind' == $exp ){ // 使用表达式
                    $whereStr .= $key.' = :'.$val[1];
                }elseif('exp' == $exp ){ // 使用表达式
                    $whereStr .= $key.' '.$val[1];
                }elseif(preg_match('/^(notin|not in|in)$/',$exp)){ // IN 运算
                    if(isset($val[2]) && 'exp'==$val[2]) {
                        $whereStr .= $key.' '.$this->exp[$exp].' '.$val[1];
                    }else{
                        if(is_string($val[1])) {
                             $val[1] = explode(',',$val[1]);
                        }
                        $zone = implode(',',(array)$this->parseValue($val[1]));
                        $whereStr .= $key.' '.$this->exp[$exp].' ('.$zone.')';
                    }
                }elseif(preg_match('/^(notbetween|not between|between)$/',$exp)){ // BETWEEN运算
                    $data = is_string($val[1])? explode(',',$val[1]):$val[1];
                    $whereStr .=  $key.' '.$this->exp[$exp].' '.$this->parseValue($data[0]).' AND '.$this->parseValue($data[1]);
                }else{
                    throw new Exception(L('_EXPRESS_ERROR_').':'.$val[0]);
                }
            }else {
                $count = count($val);
                $rule  = isset($val[$count-1]) ? (is_array($val[$count-1]) ? strtoupper($val[$count-1][0]) : strtoupper($val[$count-1]) ) : '' ; 
                if(in_array($rule,array('AND','OR','XOR'))) {
                    $count  = $count -1;
                }else{
                    $rule   = 'AND';
                }
                for($i=0;$i<$count;$i++) {
                    $data = is_array($val[$i])?$val[$i][1]:$val[$i];
                    if('exp'==strtolower($val[$i][0])) {
                        $whereStr .= $key.' '.$data.' '.$rule.' ';
                    }else{
                        $whereStr .= $this->parseWhereItem($key,$val[$i]).' '.$rule.' ';
                    }
                }
                $whereStr = '( '.substr($whereStr,0,-4).' )';
            }
        }else {
            //对字符串类型字段采用模糊匹配
            $likeFields   =   $this->config['db_like_fields'];
            if($likeFields && preg_match('/^('.$likeFields.')$/i',$key)) {
                $whereStr .= $key.' LIKE '.$this->parseValue('%'.$val.'%');
            }else {
                $whereStr .= $key.' = '.$this->parseValue($val);
            }
        }
        return $whereStr;
    }

    /**
     * 特殊条件分析
     * 
     * @param string $key
     * @param mixed $val
     * @return string
     */
    protected function parseSteezeWhere($key,$val) {
        $whereStr = '';
        switch($key) {
            case '_string':
                // 字符串模式查询条件
                $whereStr = $val;
                break;
            case '_complex':
                // 复合查询条件
                $whereStr = substr($this->parseWhere($val),6);
                break;
            case '_query':
                // 字符串模式查询条件
                parse_str($val,$where);
                if(isset($where['_logic'])) {
                    $op = ' '.strtoupper($where['_logic']).' ';
                    unset($where['_logic']);
                }else{
                    $op = ' AND ';
                }
                $array = array();
                foreach ($where as $field=>$data){
                    $array[] = $this->parseKey($field).' = '.$this->parseValue($data);
                }
                $whereStr = implode($op, $array);
                break;
        }
        return '( '.$whereStr.' )';
    }

    /**
     * limit分析
     * 
     * @param mixed $lmit
     * @return string
     */
    protected function parseLimit($limit) {
        return !empty($limit)? ' LIMIT '.$limit.' ':'';
    }

    /**
     * join分析
     * 
     * @param mixed $join
     * @return string
     */
    protected function parseJoin($join) {
        $joinStr = '';
        if(!empty($join)) {
            $joinStr = ' '.implode(' ',$join).' ';
        }
        return $joinStr;
    }

    /**
     * order分析
     * 
     * @param mixed $order
     * @return string
     */
    protected function parseOrder($order) {
        if(is_array($order)) {
            $array = array();
            foreach ($order as $key=>$val){
                if(is_numeric($key)) {
                    $array[] = $this->parseKey($val);
                }else{
                    $array[] = $this->parseKey($key).' '.$val;
                }
            }
            $order = implode(',',$array);
        }
        return !empty($order)? ' ORDER BY '.$order:'';
    }

    /**
     * group分析
     * 
     * @param mixed $group
     * @return string
     */
    protected function parseGroup($group) {
        return !empty($group) ? ' GROUP BY '.$group:'';
    }

    /**
     * having分析
     * 
     * @param string $having
     * @return string
     */
    protected function parseHaving($having) {
        return  !empty($having) ? ' HAVING '.$having:'';
    }

    /**
     * comment分析
     * 
     * @param string $comment
     * @return string
     */
    protected function parseComment($comment) {
        return  !empty($comment) ? ' /* '.$comment.' */':'';
    }

    /**
     * distinct分析
     * 
     * @param mixed $distinct
     * @return string
     */
    protected function parseDistinct($distinct) {
        return !empty($distinct) ? ' DISTINCT ' :'';
    }

    /**
     * union分析
     * 
     * @param mixed $union
     * @return string
     */
    protected function parseUnion($union) {
        if(empty($union)) return '';
        if(isset($union['_all'])) {
            $str = 'UNION ALL ';
            unset($union['_all']);
        }else{
            $str = 'UNION ';
        }
        foreach ($union as $u){
            $sql[] = $str.(is_array($u)?$this->buildSelectSql($u):$u);
        }
        return implode(' ',$sql);
    }

    /**
     * 参数绑定分析
     * 
     * @param array $bind
     * @return array
     */
    protected function parseBind($bind){
        $this->bind = array_merge($this->bind,$bind);
    }

    /**
     * index分析，可在操作链中指定需要强制使用的索引
     * 
     * @param mixed $index
     * @return string
     */
    protected function parseForce($index) {
        if(empty($index)) return '';
        if(is_array($index)) $index = join(",", $index);
        return sprintf(" FORCE INDEX ( %s ) ", $index);
    }

    /**
     * ON DUPLICATE KEY UPDATE 分析
     * 
     * @param mixed $duplicate 
     * @return string
     */
    protected function parseDuplicate($duplicate){
        return '';
    }

    /**
     * 插入记录
     * 
     * @param mixed $data 数据
     * @param array $options 参数表达式
     * @param boolean $replace 是否replace
     * @return false | integer
     */
    public function insert($data,$options=array(),$replace=false) {
        $values = $fields = array();
        $this->model  =   $options['model'];
        $this->parseBind(!empty($options['bind'])?$options['bind']:array());
        foreach ($data as $key=>$val){
            if(is_array($val) && 'exp' == $val[0]){
                $fields[] = $this->parseKey($key);
                $values[] = $val[1];
            }elseif(is_null($val)){
                $fields[] = $this->parseKey($key);
                $values[] = 'NULL';
            }elseif(is_scalar($val)) { // 过滤非标量数据
                $fields[] = $this->parseKey($key);
                if(
                    0===strpos($val,':') && 
                    in_array($val,array_keys($this->bind))
                ){
                    $values[] = $this->parseValue($val);
                }else{
                    $name = count($this->bind);
                    $values[] = ':'.$name;
                    $this->bindParam($name,$val);
                }
            }
        }
        // 兼容数字传入方式
        $replace= (is_numeric($replace) && $replace>0)?true:$replace;
        $sql = (true===$replace?'REPLACE':'INSERT')
                .' INTO '.$this->parseTable($options['table'])
                .' ('.implode(',', $fields).') VALUES ('.implode(',', $values).')'
                .$this->parseDuplicate($replace);
        $sql .= $this->parseComment(!empty($options['comment']) ? $options['comment'] : '');
        return $this->execute($sql,!empty($options['fetch_sql']) ? true : false);
    }


    /**
     * 批量插入记录
     * 
     * @param mixed $dataSet 数据集
     * @param array $options 参数表达式
     * @param boolean $replace 是否replace
     * @return false|int
     */
    public function insertAll($dataSet, $options=array(), $replace=false) {
        $values = array();
        $this->model = $options['model'];
        if(!is_array($dataSet[0])) return false;
        $this->parseBind(!empty($options['bind'])?$options['bind']:array());
        $fields = array_map(array($this,'parseKey'),array_keys($dataSet[0]));
        foreach ($dataSet as $data){
            $value = array();
            foreach ($data as $key=>$val){
                if(is_array($val) && 'exp' == $val[0]){
                    $value[] = $val[1];
                }elseif(is_null($val)){
                    $value[] = 'NULL';
                }elseif(is_scalar($val)){
                    if(0===strpos($val,':') && in_array($val,array_keys($this->bind))){
                        $value[] = $this->parseValue($val);
                    }else{
                        $name = count($this->bind);
                        $value[] = ':'.$name;
                        $this->bindParam($name,$val);
                    }
                }
            }
            $values[] = 'SELECT '.implode(',', $value);
        }
        $sql = 'INSERT INTO '.$this->parseTable($options['table'])
                .' ('.implode(',', $fields).') '
                .implode(' UNION ALL ',$values);
        $sql .= $this->parseComment(!empty($options['comment']) ? $options['comment'] : '');
        return $this->execute($sql, !empty($options['fetch_sql']) ? true : false);
    }

    /**
     * 通过Select方式插入记录
     * 
     * @param string $fields 要插入的数据表字段名
     * @param string $table 要插入的数据表名
     * @param array $option  查询数据参数
     * @return false | integer
     */
    public function selectInsert($fields, $table, $options=array()) {
        $this->model = $options['model'];
        $this->parseBind(!empty($options['bind']) ? $options['bind'] : array());
        if(is_string($fields)){
            $fields = (array)explode(',',$fields);
        }
        array_walk($fields, array($this, 'parseKey'));
        $sql = 'INSERT INTO '.$this->parseTable($table).' ('.implode(',', $fields).') ';
        $sql .= $this->buildSelectSql($options);
        return $this->execute($sql,!empty($options['fetch_sql']) ? true : false);
    }

    /**
     * 更新记录
     * 
     * @param mixed $data 数据
     * @param array $options 表达式
     * @return false | integer
     */
    public function update($data,$options) {
        $this->model = $options['model'];
        $this->parseBind(!empty($options['bind'])?$options['bind']:array());
        $table = $this->parseTable($options['table']);
        $sql = 'UPDATE ' . $table . $this->parseSet($data);
        if(strpos($table,',')){// 多表更新支持JOIN操作
            $sql .= $this->parseJoin(!empty($options['join'])?$options['join']:'');
        }
        $sql .= $this->parseWhere(!empty($options['where'])?$options['where']:'');
        if(!strpos($table,',')){
            //  单表更新支持order和lmit
            $sql .= $this->parseOrder(!empty($options['order'])?$options['order']:'')
                    .$this->parseLimit(!empty($options['limit'])?$options['limit']:'');
        }
        $sql .= $this->parseComment(!empty($options['comment'])?$options['comment']:'');
        return $this->execute($sql,!empty($options['fetch_sql']) ? true : false);
    }

    /**
     * 删除记录
     * 
     * @param array $options 表达式
     * @return false | integer
     */
    public function delete($options=array()) {
        $this->model = $options['model'];
        $this->parseBind(!empty($options['bind'])?$options['bind']:array());
        $table = $this->parseTable($options['table']);
        $sql = 'DELETE FROM '.$table;
        if(strpos($table,',')){// 多表删除支持USING和JOIN操作
            if(!empty($options['using'])){
                $sql .= ' USING '.$this->parseTable($options['using']).' ';
            }
            $sql .= $this->parseJoin(!empty($options['join'])?$options['join']:'');
        }
        $sql .= $this->parseWhere(!empty($options['where'])?$options['where']:'');
        if(!strpos($table,',')){
            // 单表删除支持order和limit
            $sql .= $this->parseOrder(!empty($options['order'])?$options['order']:'')
            .$this->parseLimit(!empty($options['limit'])?$options['limit']:'');
        }
        $sql .= $this->parseComment(!empty($options['comment'])?$options['comment']:'');
        return $this->execute($sql,!empty($options['fetch_sql']) ? true : false);
    }

    /**
     * 查找记录
     * 
     * @param array $options 表达式
     * @return mixed
     */
    public function select($options=array()) {
        $this->model = $options['model'];
        $this->parseBind(!empty($options['bind'])?$options['bind']:array());
        $sql = $this->buildSelectSql($options);
        $result = $this->query($sql,!empty($options['fetch_sql']) ? true : false, $options);
        return $result;
    }

    /**
     * 生成查询SQL
     * 
     * @param array $options 表达式
     * @return string
     */
    public function buildSelectSql($options=array()) {
        if(isset($options['page'])) {
            // 根据页数计算limit
            list($page,$listRows) = $options['page'];
            $page = $page>0 ? $page : 1;
            $listRows = $listRows>0 ? $listRows : (is_numeric($options['limit'])?$options['limit']:20);
            $offset = $listRows*($page-1);
            $options['limit'] = $offset.','.$listRows;
        }
        $sql = $this->parseSql($this->selectSql,$options);
        return $sql;
    }

    /**
     * 替换SQL语句中表达式
     * 
     * @param array $options 表达式
     * @return string
     */
    public function parseSql($sql,$options=array()){
        $sql = str_replace(
            array(
                '%TABLE%','%DISTINCT%','%FIELD%','%JOIN%',
                '%WHERE%','%GROUP%','%HAVING%','%ORDER%',
                '%LIMIT%','%UNION%','%LOCK%','%COMMENT%','%FORCE%'
            ),
            array(
                $this->parseTable($options['table']),
                $this->parseDistinct(isset($options['distinct'])?$options['distinct']:false),
                $this->parseField(!empty($options['field'])?$options['field']:'*'),
                $this->parseJoin(!empty($options['join'])?$options['join']:''),
                $this->parseWhere(!empty($options['where'])?$options['where']:''),
                $this->parseGroup(!empty($options['group'])?$options['group']:''),
                $this->parseHaving(!empty($options['having'])?$options['having']:''),
                $this->parseOrder(!empty($options['order'])?$options['order']:''),
                $this->parseLimit(!empty($options['limit'])?$options['limit']:''),
                $this->parseUnion(!empty($options['union'])?$options['union']:''),
                $this->parseLock(isset($options['lock'])?$options['lock']:false),
                $this->parseComment(!empty($options['comment'])?$options['comment']:''),
                $this->parseForce(!empty($options['force'])?$options['force']:'')
            ), $sql);
        return $sql;
    }

    /**
     * 获取最近一次查询的sql语句 
     * 
     * @param string $model  模型名
     * @return string
     */
    public function getLastSql($model='') {
        return $model?$this->modelSql[$model]:$this->queryStr;
    }

    /**
     * 获取最近插入的ID
     * 
     * @return string
     */
    public function getLastInsID() {
        return $this->lastInsID;
    }

    /**
     * 获取最近的错误信息
     * 
     * @return string
     */
    public function getError() {
        return $this->error;
    }

    /**
     * SQL指令安全过滤
     * 
     * @param string $str  SQL字符串
     * @return string
     */
    public function escapeString($str) {
        return addslashes($str);
    }

    /**
     * 设置当前操作模型
     * 
     * @param string $model  模型名
     * @return void
     */
    public function setModel($model){
        $this->model = $model;
    }

    /**
     * 数据库调试 记录当前SQL
     * 
     * @param boolean $start  调试开始标记 true 开始 false 结束
     */
    protected function debug($start) {
        if($this->config['debug']) {// 开启数据库调试模式
            if($start) {
                G('queryStartTime');
            }else{
                $this->modelSql[$this->model]   =  $this->queryStr;
                //$this->model  =   '_steeze_';
                // 记录操作结束时间
                G('queryEndTime');
                trace($this->queryStr.' [ RunTime:'.G('queryStartTime','queryEndTime').'s ]','','SQL');
            }
        }
    }

    /**
     * 初始化数据库连接
     * 
     * @param boolean $master 主服务器
     * @return void
     */
    protected function initConnect($master=true) {
        if(!empty($this->config['deploy'])){
            // 采用分布式数据库
            $this->_linkID = $this->multiConnect($master);
        }else{
            // 默认单数据库
            if ( !$this->_linkID ){
                $this->_linkID = $this->connect();
            }
        }
    }

    /**
     * 连接分布式服务器
     * 
     * @param boolean $master 主服务器
     * @return void
     */
    protected function multiConnect($master=false) {
        // 分布式数据库配置解析
        $_config['username'] = explode(',',$this->config['username']);
        $_config['password'] = explode(',',$this->config['password']);
        $_config['hostname'] = explode(',',$this->config['hostname']);
        $_config['hostport'] = explode(',',$this->config['hostport']);
        $_config['database'] = explode(',',$this->config['database']);
        $_config['dsn'] = explode(',',$this->config['dsn']);
        $_config['charset'] = explode(',',$this->config['charset']);

        $m = floor(mt_rand(0,$this->config['master_num']-1));
        // 数据库读写是否分离
        if($this->config['rw_separate']){
            // 主从式采用读写分离
            if($master){
                // 主服务器写入
                $r = $m;
            }else{
                if(is_numeric($this->config['slave_no'])) {// 指定服务器读
                    $r = $this->config['slave_no'];
                }else{
                    // 读操作连接从服务器
                    $r = floor(mt_rand($this->config['master_num'],count($_config['hostname'])-1));   // 每次随机连接的数据库
                }
            }
        }else{
            // 读写操作不区分服务器
            $r = floor(mt_rand(0,count($_config['hostname'])-1));   // 每次随机连接的数据库
        }
        
        if($m != $r ){
            $db_master = array(
                'username' => isset($_config['username'][$m])?$_config['username'][$m]:$_config['username'][0],
                'password' => isset($_config['password'][$m])?$_config['password'][$m]:$_config['password'][0],
                'hostname' => isset($_config['hostname'][$m])?$_config['hostname'][$m]:$_config['hostname'][0],
                'hostport' => isset($_config['hostport'][$m])?$_config['hostport'][$m]:$_config['hostport'][0],
                'database' => isset($_config['database'][$m])?$_config['database'][$m]:$_config['database'][0],
                'dsn'      => isset($_config['dsn'][$m])?$_config['dsn'][$m]:$_config['dsn'][0],
                'charset'  => isset($_config['charset'][$m])?$_config['charset'][$m]:$_config['charset'][0],
            );
        }
        $db_config = array(
            'username' => isset($_config['username'][$r])?$_config['username'][$r]:$_config['username'][0],
            'password' => isset($_config['password'][$r])?$_config['password'][$r]:$_config['password'][0],
            'hostname' => isset($_config['hostname'][$r])?$_config['hostname'][$r]:$_config['hostname'][0],
            'hostport' => isset($_config['hostport'][$r])?$_config['hostport'][$r]:$_config['hostport'][0],
            'database' => isset($_config['database'][$r])?$_config['database'][$r]:$_config['database'][0],
            'dsn'      => isset($_config['dsn'][$r])?$_config['dsn'][$r]:$_config['dsn'][0],
            'charset'  => isset($_config['charset'][$r])?$_config['charset'][$r]:$_config['charset'][0],
        );
        return $this->connect($db_config, $r, $r == $m ? false : $db_master);
    }

   /**
     * 析构方法
     */
    public function __destruct() {
        // 释放查询
        if ($this->PDOStatement){
            $this->free();
        }
        // 关闭连接
        $this->close();
    }
    
}
