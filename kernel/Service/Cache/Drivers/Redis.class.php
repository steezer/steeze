<?php
namespace Service\Cache\Drivers;

use Service\Cache\Manager as Cache;

/**
 * Redis缓存驱动 
 * 要求安装phpredis扩展：https://github.com/nicolasff/phpredis
 * 
 * @package Cache
 * @subpackage Drivers
 */
class Redis extends Cache {
	 /**
	 * 架构函数
     * @param array $options 缓存参数
     * @access public
     */
    public function __construct($options=array()) {
        if ( !extension_loaded('redis') ) {
            throw new \Exception(L('_NOT_SUPPORT_').':redis');
        }
        $options = array_merge(array (
        		'host'          => C('redis_host',env('redis_host','127.0.0.1')),
        		'port'          => C('redis_port',env('redis_port',6379)),
        		'timeout'       => C('redis_timeout',env('redis_timeout',false)),
        		'password'      => C('redis_password',env('redis_password',null)),
        		'db'            => intval(C('redis_db',env('redis_db',0))),
        		'persistent'    => boolval(C('data_cache_persistent',env('data_cache_persistent',false))),
        		'expire'        => intval(C('data_cache_time',env('data_cache_time',60))),
        		'prefix'        => C('data_cache_prefix',env('data_cache_prefix','')),
        		'length'        => intval(C('data_cache_length',env('data_cache_length',0))),
        ),$options);

        $this->options =  $options;    
        $func = $options['persistent'] ? 'pconnect' : 'connect';
        
        $this->handler  = new \Redis;
        $options['timeout'] === false ? 
        		$this->handler->$func($options['host'], $options['port']) :
            	$this->handler->$func($options['host'], $options['port'], $options['timeout']);
        
        !is_null($options['password']) && $this->handler->auth($options['password']);
        $this->handler->select(intval($options['db']));
    }
            
    /**
     * 读取缓存
     * @access public
     * @param string $name 缓存变量名
     * @return mixed
     */
    public function get($name) {
        $value = $this->handler->get($this->options['prefix'].$name);
        $jsonData  = json_decode( $value, true );
        return ($jsonData === NULL) ? $value : $jsonData;	//检测是否为JSON数据 true 返回JSON解析数组, false返回源数据
    }

    /**
     * 写入缓存
     * @access public
     * @param string $name 缓存变量名
     * @param mixed $value  存储数据
     * @param integer $expire  有效时间（秒）
     * @return boolean
     */
    public function set($name, $value, $expire = null) {
        if(is_null($expire)) {
            $expire  =  $this->options['expire'];
        }
        $name   =   $this->options['prefix'].$name;
        //对数组/对象数据进行缓存处理，保证数据完整性
        $value  =  (is_object($value) || is_array($value)) ? json_encode($value) : $value;
        if(is_int($expire) && $expire) {
            $result = $this->handler->setex($name, $expire, $value);
        }else{
            $result = $this->handler->set($name, $value);
        }
        if($result && $this->options['length']>0) {
            // 记录缓存队列
            $this->queue($name);
        }
        return $result;
    }

    /**
     * 删除缓存
     * @access public
     * @param string $name 缓存变量名
     * @return boolean
     */
    public function rm($name) {
        return $this->handler->delete($this->options['prefix'].$name);
    }

    /**
     * 清除缓存
     * @access public
     * @return boolean
     */
    public function clear() {
        return $this->handler->flushDB();
    }

}
