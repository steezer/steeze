<?php
namespace Service\Database;
use Exception;

/**
 * 数据库中间层实现类
 * 
 * @package Database
 */
class Manager {

    static private  $instance   =  array();     //  数据库连接实例
    static private  $_instance  =  null;   //  当前数据库连接实例

    /**
     * 取得数据库类实例
     * @static
     * @access public
     * @param mixed $config 连接配置
     * @return Object 返回数据库驱动类
     */
    static public function getInstance($config=array()) {
        $md5    =   md5(serialize($config));
        if(!isset(self::$instance[$md5])) {
            // 解析连接参数 支持数组和字符串
            $options    =   self::parseConfig($config);
            // 兼容mysqli
            if('mysqli' == $options['type']) $options['type']   =   'mysql';
            //兼任sqlite3和sqlite2
            if('sqlite3' == $options['type'] || 'sqlite2' == $options['type']) $options['type']   =   'sqlite';
            
            if(defined('INI_STEEZE')){
                $class = 'Service\\Database\\Drivers\\'.ucwords(strtolower($options['type']));
            }else{
                $class = ucwords(strtolower($options['type'])).'DbDriver';
            }
            if(class_exists($class)){
                self::$instance[$md5]   =   new $class($options);
            }else{
                // 类没有定义
            	throw new Exception(L('no database driver: {0}',$class).': ' . $class);
            }
        }
        self::$_instance    =   self::$instance[$md5];
        return self::$_instance;
    }
    
    /**
     * DSN解析
     * 格式： mysql://username:passwd@localhost:3306/DbName?param1=val1&param2=val2#utf8
     * @static
     * @access private
     * @param string $dsnStr
     * @return array
     */
    static public function parseDsn($dsnStr) {
	    	if( empty($dsnStr) ){return array();}
	    	$info = parse_url($dsnStr);
	    	if(!$info) {
	    		return array();
	    	}
	    	$converts=array(
                'scheme'=>'type',
    			'pass'=>'pwd',
    			'path'=>'name',
    			'fragment'=>'charset',
	    		'query'=>'params',
            );
	    	$dsn=array();
	    	foreach($info as $k=> $v){
	    		$dsn[(isset($converts[$k]) ? $converts[$k] : $k)] = ($k=='path' ? trim($v,'/') : $v);
	    	}
	    	
	    	if(isset($info['query'])) {
	    		parse_str($info['query'],$dsn['params']);
	    	}
	    	return $dsn;
    }

    /**
     * 数据库连接参数解析
     * @static
     * @access private
     * @param mixed $config
     * @return array
     */
    static private function parseConfig($config){
	    	$default = array(
    			'type'          =>  'mysql',
    			'username'      =>  'root',
    			'password'      =>  '',
    			'hostname'      =>  '127.0.0.1',
    			'hostport'      =>  '3306',
    			'database'      =>  'test',
    			'dsn'           =>  '',
    			'params'        =>  '',
    			'charset'       =>  'utf8',
    			'deploy'        =>  '',
    			'rw_separate'   =>  '',
    			'master_num'    =>  '',
    			'slave_no'      =>  '',
    			'debug'         =>  false,
    			'lite'          =>  '',
	    	);
        if(!empty($config)){
           $config =   is_string($config) ? self::parseDsn($config) : array_change_key_case($config);
           //将字符串参数转换为数组
           if(isset($config['params']) && is_string($config['params'])){
	           	$config['params']=trim($config['params']);
	           	if(!empty($config['params'])){
	           		parse_str(trim($config['params']),$config['params']);
	           	}
           }
           return array(
                'type'          =>  (isset($config['type']) ? $config['type'] : $default['type']),
                'username'      =>  (isset($config['user']) ? $config['user'] : $default['username']),
                'password'      =>  (isset($config['pwd']) ? $config['pwd'] : $default['password']),
                'hostname'      =>  (isset($config['host']) ? $config['host'] : $default['hostname']),
                'hostport'      =>  (isset($config['port']) ? $config['port'] : $default['hostport']),
                'database'      =>  (isset($config['name']) ? $config['name'] : $default['database']),
                'dsn'           =>  isset($config['dsn']) ? $config['dsn'] : $default['dsn'],
                'params'        =>  isset($config['params']) ? $config['params'] : $default['params'],
                'charset'       =>  (isset($config['charset']) ? $config['charset'] : $default['charset']),
                'deploy'        =>  isset($config['deploy_type']) ? $config['deploy_type'] : $default['deploy'],
                'rw_separate'   =>  isset($config['rw_separate']) ? $config['rw_separate'] : $default['rw_separate'],
                'master_num'    =>  isset($config['master_num']) ? $config['master_num'] : $default['master_num'],
                'slave_no'      =>  isset($config['slave_no']) ? $config['slave_no'] : $default['slave_no'],
                'debug'         =>  isset($config['debug']) ? $config['debug'] : $default['debug'],
                'lite'          =>  isset($config['lite']) ? $config['lite'] : $default['lite'],
           );
        }else{
        		return $default;
        }
    }

    // 调用驱动类的方法
    static public function __callStatic($method, $params){
        return call_user_func_array(array(self::$_instance, $method), $params);
    }
}
