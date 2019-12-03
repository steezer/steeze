<?php
namespace Service\Database\Drivers;

use Service\Database\Driver;
use Exception;

/**
 * Firebird数据库驱动 
 * 
 * @package Database
 * @subpackage Drivers
 */
class Firebird extends Driver{
    protected $selectSql  =     'SELECT %LIMIT% %DISTINCT% %FIELD% FROM %TABLE%%JOIN%%WHERE%%GROUP%%HAVING%%ORDER%';

    /**
     * 解析pdo连接的dsn信息
     * @access public
     * @param array $config 连接信息
     * @return string
     */
    protected function parseDsn($config){
       $dsn  =   'firebird:dbname='.$config['hostname'].'/'.($config['hostport']?$config['hostport']:3050).':'.$config['database'];
       return $dsn;
    }
    
    /**
     * 执行语句
     * @access public
     * @param string $str  sql指令
     * @param boolean $fetchSql  不执行只是获取SQL
     * @return mixed
     */
    public function execute($str,$fetchSql=false) {
        $this->initConnect(true);
        if ( !$this->_linkID ) return false;
        $this->queryStr = $str;
        if(!empty($this->bind)){
            $this->queryStr =   strtr(
                    $this->queryStr,
                    array_map(array($this, 'addQuot'), $this->bind)
                );
        }
        if($fetchSql){
            return $this->queryStr;
        }
        //释放前次的查询结果
        if ( !empty($this->PDOStatement) ) $this->free();
        $this->executeTimes++;
        // 记录开始执行时间
        $this->debug(true);
        $this->PDOStatement =   $this->_linkID->prepare($str);
        if(false === $this->PDOStatement) {
            throw new Exception($this->error());
        }
        foreach ($this->bind as $key => $val) {
            if(is_array($val)){
                $this->PDOStatement->bindValue($key, $val[0], $val[1]);
            }else{
                $this->PDOStatement->bindValue($key, $val);
            }
        }
        $this->bind =   array();
        $result =   $this->PDOStatement->execute();
        $this->debug(false);
        if ( false === $result) {
            $this->error();
            return false;
        } else {
            $this->numRows = $this->PDOStatement->rowCount();
            return $this->numRows;
        }
    }
    
    /**
     * 取得数据表的字段信息
     * @access public
     */
    public function getFields($tableName) {
        $this->initConnect(true);
        list($tableName) = explode(' ', $tableName);
        $sql='SELECT RF.RDB$FIELD_NAME AS FIELD,RF.RDB$DEFAULT_VALUE AS DEFAULT1,RF.RDB$NULL_FLAG AS NULL1,TRIM(T.RDB$TYPE_NAME) || \'(\' || F.RDB$FIELD_LENGTH || \')\' as TYPE FROM RDB$RELATION_FIELDS RF LEFT JOIN RDB$FIELDS F ON (F.RDB$FIELD_NAME = RF.RDB$FIELD_SOURCE) LEFT JOIN RDB$TYPES T ON (T.RDB$TYPE = F.RDB$FIELD_TYPE) WHERE RDB$RELATION_NAME=UPPER(\''.$tableName.'\') AND T.RDB$FIELD_NAME = \'RDB$FIELD_TYPE\' ORDER By RDB$FIELD_POSITION';
        $result = $this->query($sql);
        $info   =   array();
        if($result){
            foreach($result as $key => $val){
                $info[trim($val['field'])] = array(
                    'name'    => trim($val['field']),
                    'type'    => $val['type'],
                    'notnull' => (bool) ($val['null1'] ==1), // 1表示不为Null
                    'default' => $val['default1'],
                    'primary' => false,
                    'autoinc' => false,
                );
            }
        }
        //获取主键
        $sql='select b.rdb$field_name as field_name from rdb$relation_constraints a join rdb$index_segments b on a.rdb$index_name=b.rdb$index_name where a.rdb$constraint_type=\'PRIMARY KEY\' and a.rdb$relation_name=UPPER(\''.$tableName.'\')';
        $rs_temp = $this->query($sql);
        foreach($rs_temp as $row) {
            $info[trim($row['field_name'])]['primary']= true;
        }
        return $info;
    }
    
    /**
     * 取得数据库的表信息
     * @access public
     */
    public function getTables($dbName='') {
        $sql='SELECT DISTINCT RDB$RELATION_NAME FROM RDB$RELATION_FIELDS WHERE RDB$SYSTEM_FLAG=0';
        $result   =  $this->query($sql);
        $info   =   array();
        foreach ($result as $key => $val) {
            $info[$key] = trim(current($val));
        }
        return $info;
    }
    
    /**
     * SQL指令安全过滤
     * @access public
     * @param string $str  SQL指令
     * @return string
     */
    public function escapeString($str) {
        return str_replace("'", "''", $str);
    }

    /**
     * limit
     * @access public
     * @param string $limit 表达式
     * @return string
     */
    public function parseLimit($limit) {
        $limitStr    = '';
        if(!empty($limit)) {
            $limit  =   explode(',',$limit);
            if(count($limit)>1) {
                 $limitStr = ' FIRST '.$limit[1].' SKIP '.$limit[0].' ';
            }else{
              $limitStr = ' FIRST '.$limit[0].' ';
            }
        }
        return $limitStr;
    }
}
