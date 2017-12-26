<?php
namespace Service\Cache\Drivers;
use Service\Cache\Manager as Cache;

/**
 * Sqlite缓存驱动
 */
class Sqlite extends Cache {

    /**
     * 架构函数
     * @param array $options 缓存参数
     * @access public
     */
    public function __construct($options=array()) {
        if ( !extension_loaded('sqlite') ) {
            E(L('_NOT_SUPPORT_').':sqlite');
        }
        
        $options = array_merge(array (
        		'db'        =>  ':memory:',
        		'table'     =>  'sharedmemory',
        		'temp'        =>  C('data_cache_path',env('data_cache_path','')),
        		'expire'        => intval(C('data_cache_time',env('data_cache_time',60))),
        		'prefix'        => C('data_cache_prefix',env('data_cache_prefix','')),
        		'length'        => intval(C('data_cache_length',env('data_cache_length',0))),
        		'persistent'    => C('data_cache_persistent',env('data_cache_persistent',false)),
        ),$options);
        
        $this->options  =   $options;      
        $func = $this->options['persistent'] ? 'sqlite_popen' : 'sqlite_open';
        $this->handler      = $func($this->options['db']);
    }

    /**
     * 读取缓存
     * @access public
     * @param string $name 缓存变量名
     * @return mixed
     */
    public function get($name) {
		$name   = $this->options['prefix'].sqlite_escape_string($name);
        $sql    = 'SELECT value FROM '.$this->options['table'].' WHERE var=\''.$name.'\' AND (expire=0 OR expire >'.time().') LIMIT 1';
        $result = sqlite_query($this->handler, $sql);
        if (sqlite_num_rows($result)) {
            $content   =  sqlite_fetch_single($result);
            if(C('data_cache_compress') && function_exists('gzcompress')) {
                //启用数据压缩
                $content   =   gzuncompress($content);
            }
            return unserialize($content);
        }
        return false;
    }

    /**
     * 写入缓存
     * @access public
     * @param string $name 缓存变量名
     * @param mixed $value  存储数据
     * @param integer $expire  有效时间（秒）
     * @return boolean
     */
    public function set($name, $value,$expire=null) {
        $name  = $this->options['prefix'].sqlite_escape_string($name);
        $value = sqlite_escape_string(serialize($value));
        if(is_null($expire)) {
            $expire  =  $this->options['expire'];
        }
        $expire	=	($expire==0)?0: (time()+$expire) ;//缓存有效期为0表示永久缓存
        if( C('data_cache_compress') && function_exists('gzcompress')) {
            //数据压缩
            $value   =   gzcompress($value,3);
        }
        $sql  = 'REPLACE INTO '.$this->options['table'].' (var, value,expire) VALUES (\''.$name.'\', \''.$value.'\', \''.$expire.'\')';
        if(sqlite_query($this->handler, $sql)){
            if($this->options['length']>0) {
                // 记录缓存队列
                $this->queue($name);
            }
            return true;
        }
        return false;
    }

    /**
     * 删除缓存
     * @access public
     * @param string $name 缓存变量名
     * @return boolean
     */
    public function rm($name) {
        $name  = $this->options['prefix'].sqlite_escape_string($name);
        $sql  = 'DELETE FROM '.$this->options['table'].' WHERE var=\''.$name.'\'';
        sqlite_query($this->handler, $sql);
        return true;
    }

    /**
     * 清除缓存
     * @access public
     * @return boolean
     */
    public function clear() {
        $sql  = 'DELETE FROM '.$this->options['table'];
        sqlite_query($this->handler, $sql);
        return ;
    }
}
