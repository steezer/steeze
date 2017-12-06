<?php
namespace Service\Database\Drivers;

use Service\Database\Driver;

/**
 * Sqlite数据库驱动
 */
class Sqlite extends Driver {

    /**
     * 解析pdo连接的dsn信息
     * @access public
     * @param array $config 连接信息
     * @return string
     */
    protected function parseDsn($config){
        $dsn  =   'sqlite:'.$config['database'];
        return $dsn;
    }

    /**
     * 取得数据表的字段信息
     * @access public
     * @return array
     */
    public function getFields($tableName) {
        list($tableName) = explode(' ', $tableName);
        $result =   $this->query('PRAGMA table_info( '.$tableName.' )');
        $info   =   array();
        if($result){
            foreach ($result as $key => $val) {
            		if(isset($val['field']) || isset($val['name'])){
	            		$name=(isset($val['field'])?$val['field']:$val['name']);
	            		$default=isset($val['default']) ? $val['default'] : $val['dflt_value'];
	            		$primary=isset($val['dey']) ? (strtolower($val['dey']) == 'pri') : ($val['pk']=='1');
	            		$notnull=(isset($val['null']) ? ($val['null'] === '') : ($val['notnull']=='1')) ;
	            		$info[$name] = array(
	            			'name'    => $name,
	                    'type'    => $val['type'],
	            			'notnull' => (bool) $notnull, // not null is empty, null is yes
	            			'default' => $default,
	            			'primary' => $primary,
	            			'autoinc' => isset($val['extra']) && (strtolower($val['extra']) == 'auto_increment'),
	                );
	            	}
            }
        }
        return $info;
    }

    /**
     * 取得数据库的表信息
     * @access public
     * @return array
     */
    public function getTables($dbName='') {
        $result =   $this->query("SELECT name FROM sqlite_master WHERE type='table' "
             . "UNION ALL SELECT name FROM sqlite_temp_master "
             . "WHERE type='table' ORDER BY name");
        $info   =   array();
        foreach ($result as $key => $val) {
            $info[$key] = current($val);
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
        return str_ireplace("'", "''", $str);
    }

    /**
     * limit
     * @access public
     * @return string
     */
    public function parseLimit($limit) {
        $limitStr    = '';
        if(!empty($limit)) {
            $limit  =   explode(',',$limit);
            if(count($limit)>1) {
                $limitStr .= ' LIMIT '.$limit[1].' OFFSET '.$limit[0].' ';
            }else{
                $limitStr .= ' LIMIT '.$limit[0].' ';
            }
        }
        return $limitStr;
    }
}
