<?php
namespace Service\Cache\Drivers;
use Exception;
use Service\Cache\Manager as Cache;

/**
 * Memcache缓存驱动
 * 
 * @package Cache
 * @subpackage Drivers
 */
class Memcache extends Cache {

    /**
     * 架构函数
     * @param array $options 缓存参数
     * @access public
     */
    function __construct($options=array()) {
        if ( !extension_loaded('memcache') ) {
            throw new Exception(L('_NOT_SUPPORT_').':memcache');
        }

        $options = array_merge(array (
        		'host'        =>  C('memcache_host',env('memcache_host','127.0.0.1')),
        		'port'        =>  C('memcache_port',env('memcache_port',11211)),
        		'timeout'     =>  C('memcache_timeout',env('memcache_timeout',false)),
        		'persistent'    => boolval(C('memcache_persistent',env('memcache_persistent',false))),
        		'expire'        => intval(C('data_cache_time',env('data_cache_time',60))),
        		'prefix'        => C('data_cache_prefix',env('data_cache_prefix','')),
        		'length'        => intval(C('data_cache_length',env('data_cache_length',0))),
        ),$options);

        $this->options      =   $options;           
        $func               =   $options['persistent'] ? 'pconnect' : 'connect';
        $this->handler      =   new \Memcache;
        $options['timeout'] === false ?
            $this->handler->$func($options['host'], $options['port']) :
            $this->handler->$func($options['host'], $options['port'], $options['timeout']);
    }

    /**
     * 读取缓存
     * @access public
     * @param string $name 缓存变量名
     * @return mixed
     */
    public function get($name) {
        return $this->handler->get($this->options['prefix'].$name);
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
        if($this->handler->set($name, $value, 0, $expire)) {
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
    public function rm($name, $ttl = false) {
        $name   =   $this->options['prefix'].$name;
        return $ttl === false ?
            $this->handler->delete($name) :
            $this->handler->delete($name, $ttl);
    }

    /**
     * 清除缓存
     * @access public
     * @return boolean
     */
    public function clear() {
        return $this->handler->flush();
    }
}
