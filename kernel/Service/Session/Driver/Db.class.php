<?php
namespace Service\Session\Driver;
/**
 * 数据库方式Session驱动
 *    CREATE TABLE think_session (
 *      session_id varchar(255) NOT NULL,
 *      session_expire int(11) NOT NULL,
 *      session_data blob,
 *      UNIQUE KEY `session_id` (`session_id`)
 *    );
 */
class Db {

    /**
     * Session有效时间
     */
   protected $lifeTime      = ''; 

    /**
     * session保存的数据库名
     */
   protected $sessionTable  = '';

    /**
     * 数据库句柄
     */
   protected $hander  = array(); 

    /**
     * 打开Session 
     * @access public 
     * @param string $savePath 
     * @param mixed $sessName  
     */
    public function open($savePath, $sessName) { 
       $this->lifeTime = C('SESSION_EXPIRE')?C('SESSION_EXPIRE'):ini_get('session.gc_maxlifetime');
       $this->sessionTable  =   C('SESSION_TABLE')?C('SESSION_TABLE'):C("db_prefix")."session";
       //分布式数据库
       $host = explode(',',C('db_host'));
       $port = explode(',',C('db_port'));
       $name = explode(',',C('db_name'));
       $user = explode(',',C('db_user'));
       $pwd  = explode(',',C('db_pwd'));
       if(1 == C('db_deploy_type')){
           //读写分离
           if(C('db_rw_separate')){
               $w = floor(mt_rand(0,C('db_master_num')-1));
               if(is_numeric(C('db_slave_no'))){//指定服务器读
                   $r = C('db_slave_no');
               }else{
                   $r = floor(mt_rand(C('db_master_num'),count($host)-1));
               }
               //主数据库链接
               $hander = mysql_connect(
                   $host[$w].(isset($port[$w])?':'.$port[$w]:':'.$port[0]),
                   isset($user[$w])?$user[$w]:$user[0],
                   isset($pwd[$w])?$pwd[$w]:$pwd[0]
                   );
               $dbSel = mysql_select_db(
                   isset($name[$w])?$name[$w]:$name[0]
                   ,$hander);
               if(!$hander || !$dbSel)
                   return false;
               $this->hander[0] = $hander;
               //从数据库链接
               $hander = mysql_connect(
                   $host[$r].(isset($port[$r])?':'.$port[$r]:':'.$port[0]),
                   isset($user[$r])?$user[$r]:$user[0],
                   isset($pwd[$r])?$pwd[$r]:$pwd[0]
                   );
               $dbSel = mysql_select_db(
                   isset($name[$r])?$name[$r]:$name[0]
                   ,$hander);
               if(!$hander || !$dbSel)
                   return false;
               $this->hander[1] = $hander;
               return true;
           }
       }
       //从数据库链接
       $r = floor(mt_rand(0,count($host)-1));
       $hander = mysql_connect(
           $host[$r].(isset($port[$r])?':'.$port[$r]:':'.$port[0]),
           isset($user[$r])?$user[$r]:$user[0],
           isset($pwd[$r])?$pwd[$r]:$pwd[0]
           );
       $dbSel = mysql_select_db(
           isset($name[$r])?$name[$r]:$name[0]
           ,$hander);
       if(!$hander || !$dbSel) 
           return false; 
       $this->hander = $hander; 
       return true; 
    } 

    /**
     * 关闭Session 
     * @access public 
     */
   public function close() {
       if(is_array($this->hander)){
           $this->gc($this->lifeTime);
           return (mysql_close($this->hander[0]) && mysql_close($this->hander[1]));
       }
       $this->gc($this->lifeTime); 
       return mysql_close($this->hander); 
   } 

    /**
     * 读取Session 
     * @access public 
     * @param string $sessID 
     */
   public function read($sessID) { 
       $hander = is_array($this->hander)?$this->hander[1]:$this->hander;
       $res = mysql_query("SELECT session_data AS data FROM ".$this->sessionTable." WHERE session_id = '$sessID'   AND session_expire >".time(),$hander); 
       if($res) {
           $row = mysql_fetch_assoc($res);
           return $row['data']; 
       }
       return ""; 
   } 

    /**
     * 写入Session 
     * @access public 
     * @param string $sessID 
     * @param String $sessData  
     */
   public function write($sessID,$sessData) { 
       $hander = is_array($this->hander)?$this->hander[0]:$this->hander;
       $expire = time() + $this->lifeTime; 
       mysql_query("REPLACE INTO  ".$this->sessionTable." (  session_id, session_expire, session_data)  VALUES( '$sessID', '$expire',  '$sessData')",$hander); 
       if(mysql_affected_rows($hander)) 
           return true; 
       return false; 
   } 

    /**
     * 删除Session 
     * @access public 
     * @param string $sessID 
     */
   public function destroy($sessID) { 
       $hander = is_array($this->hander)?$this->hander[0]:$this->hander;
       mysql_query("DELETE FROM ".$this->sessionTable." WHERE session_id = '$sessID'",$hander); 
       if(mysql_affected_rows($hander)) 
           return true; 
       return false; 
   } 

    /**
     * Session 垃圾回收
     * @access public 
     * @param string $sessMaxLifeTime 
     */
   public function gc($sessMaxLifeTime) { 
       $hander = is_array($this->hander)?$this->hander[0]:$this->hander;
       mysql_query("DELETE FROM ".$this->sessionTable." WHERE session_expire < ".time(),$hander); 
       return mysql_affected_rows($hander); 
   } 

}