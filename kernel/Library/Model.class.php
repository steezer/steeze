<?php
namespace Library;
use Service\Database\Manager as DatabaseService;
use Service\Database\Driver as DatabaseDriver;
use ArrayAccess;
use Exception;

/**
 * Model模型类 实现了ORM和ActiveRecords模式
 * @package Library
 * @method Model strict(boolean $isUse=false) 用于设置数据写入和查询是否严格检查是否存在字段。默认情况下不合法数据字段自动删除，如果设置了严格检查则会抛出异常
 * @method Model order(string|array $opt) 对操作的结果排序
 * @method Model alias(string $name) 用于设置当前数据表的别名，便于使用其他的连贯操作例如join方法
 * @method Model having(string $opt) 配合group方法完成从分组的结果中筛选
 * @method Model group(string $opt) 结合合计函数，根据一个或多个列对结果集进行分组，多个字段以","分割
 * @method Model lock(boolean $isUse=false) 用于数据库的锁机制
 * @method int count($field='*') 计算字段数量
 * @method int sum($field='*') 计算字段总和
 * @method int min($field='*') 计算字段最小值
 * @method int max($field='*') 计算字段最大值
 * @method int avg($field='*') 计算字段平均值
 * @method Model distinct(boolean $isUse=false) 用于返回唯一不同的值
 * @method Model auto(array $rules) 自动表单处理，填充因子定义格式：array('field','填充内容','填充条件','附加规则',[额外参数])
 * @method Model filter(callable $func) 安全过滤函数，对写入的数据进行过滤
 * @method Model validate(array $rules) 使用当前校验规则进行数据校验
 * @method Model result(callable|string $opt) 对返回的数据进行处理，可传入回调函数，也可以传入"json"或"xml"字符串（返回json或xml格式）
 * @method Model token(boolean $isClose=false) 用于临时关闭令牌验证
 * @method Model index(string $field) 对查询数据集进行按照字段索引，如果$field参数是以","分割的字符串，则以前一个字段值为索引，后一个字段值为值
 * @method Model force(string|array $index) 索引分析，可在操作链中指定需要强制使用的索引
 * @method mixed getBy[field](mixed $value) 根据[field]字段的值$value，获取一条记录
 * @method mixed getFieldBy[field](mixed $value, string $fdName, $spea=null) 根据[field]字段的值$value，获取字段名称为$fdName的值, $spea 字段数据间隔符号 NULL返回数组
 * @method array selectBy[field](mixed $value) 根据[field]字段的值$value，获取多条记录
 */
class Model implements ArrayAccess{
	// 操作状态
    const MODEL_DELETE=0; // 删除模型
	const MODEL_INSERT=1; // 插入模型数据
	const MODEL_UPDATE=2; // 更新模型数据
	const MODEL_BOTH=3; // 包含插入和更新两种方式
    const MODEL_CHANGE=4; // 包含删除、插入和更新的模型数据改变
	const MUST_VALIDATE=1; // 必须验证
	const EXISTS_VALIDATE=0; // 表单存在字段则验证
	const VALUE_VALIDATE=2; // 表单值不为空则验证
	
    /**
     * 当前数据库操作对象
     *
     * @var DatabaseDriver
     */
	protected $db=null;
    
	// 数据库对象池
	private $_db=[];
	// 主键名称
	protected $pk='id';
	// 主键是否自动增长
	protected $autoinc=false;
	// 数据表前缀
	protected $tablePrefix=null;
	// 模型名称
	protected $name='';
	// 数据库名称
	protected $dbName='';
	// 数据库配置
	protected $connection='';
	// 数据表名（不包含表前缀）
	protected $tableName='';
	// 实际数据表名（包含表前缀）
	protected $trueTableName='';
	// 最近错误信息
	protected $error='';
	// 字段信息
	protected $fields=[];
	// 数据信息
	protected $data=[];
	// 查询表达式参数
	protected $options=[];
    
    /**
     * 自动验证定义
     * array(field,rule,message,condition,type,when,params)
     *
     * @var array
     */
	protected $_validate=[]; // 自动验证定义
    
    /**
     * 自动填充内容
     * array('field','填充内容','填充条件','附加规则',[额外参数])
     *
     * @var array
     */
	protected $_auto=[]; // 自动完成定义
    
    /**
     * 字段映射定义
     *
     * @var array
     */
	protected $_map=[];
    
    /**
     * 命名范围定义
     *
     * @var array
     */
	protected $_scope=[];
	
	// 是否自动检测数据表字段信息
	protected $autoCheckFields=true;
	// 字段值是否去掉反斜杠
	protected $stripSlashes=false;
	// 是否批处理验证
	protected $patchValidate=false;
    // 是否自动提交
    protected $autoCommit=true;
    
	// 链操作方法列表
	private $methods=array('strict','order','alias','having','group','lock','distinct','auto','filter','validate','result','token','index','force');

	/**
	 * 架构函数 取得DB类的实例对象 字段检查
	 * 
	 * @param string $name 模型名称
	 * @param string $tablePrefix 表前缀
	 * @param mixed $connection 数据库连接信息
	 */
	public function __construct($name='',$tablePrefix='',$connection=''){
		// 模型初始化
		$this->_initialize();
		// 获取模型名称
		if(!empty($name)){
			if(strpos($name, '.')){ // 支持 数据库名.模型名的 定义
				list($this->dbName, $this->name)=explode('.', $name);
			}else{
				$this->name=$name;
			}
		}elseif(empty($this->name)){
			$this->name=$this->getModelName();
		}

		$connection=empty($this->connection) ? $connection : $this->connection;
		
		// 支持读取配置参数
		
		if(is_string($connection)){
			$databases=C('database.*');
			if(!empty($databases)){
				if(false === strpos($connection, '/')){
					$connName=empty($connection) ? C('db_conn','default') : $connection;
                    $conn=isset($databases[$connName]) ? $connName:'default';
					$connection=$databases[$conn];
				}else{
					$connName=C('db_conn','default');
                    $conn=isset($databases[$connName]) ? $connName:'default';
					$connection=array_merge(
							$databases[$conn],
                            DatabaseService::parseDsn($connection)
						);
				}
			}
		}

		// 设置表前缀
		if(is_null($tablePrefix)){ // 前缀为null表示没有前缀
			$this->tablePrefix='';
		}elseif('' != $tablePrefix){
			$this->tablePrefix=$tablePrefix;
		}elseif(!isset($this->tablePrefix)){
			$this->tablePrefix=is_array($connection) && isset($connection['prefix']) ? $connection['prefix'] : '';
		}
		
		//同步数据库表名前缀及数据库名称
		if(is_array($connection)){
			$connection['prefix']=$this->tablePrefix;
            if(empty($this->dbName) && isset($connection['name'])){
                $this->dbName=$connection['name'];
            }
		}

		// 数据库初始化操作
		// 获取数据库操作对象
		// 当前模型有独立的数据库连接信息
		$this->db(0, $connection, true);
	}

	/**
	 * 自动检测数据表信息
	 * 
	 * @return void
	 */
	protected function _checkTableInfo(){
		// 如果不是Model类 自动记录数据表信息
		// 只在第一次执行记录
		if(empty($this->fields)){
			// 如果数据表字段没有定义则自动获取
			if(C('db_fields_cache', !APP_DEBUG)){
                $name=$this->getTableName(true, true);
				$fields=F('_fields/'.strtolower($name));
				if($fields){
					$this->fields=$fields;
					if(!empty($fields['_pk'])){
						$this->pk=$fields['_pk'];
					}
					return;
				}
			}
			// 每次都会读取数据表信息
			$this->flush();
		}
	}

	/**
	 * 获取字段信息并缓存
	 */
	public function flush(){
		// 缓存不存在则查询数据表信息
		$this->db->setModel($this->name);
		$fields=$this->db->getFields($this->getTableName());
		if(!$fields){ // 无法获取字段信息
			return false;
		}
		$this->fields=array_keys($fields);
		unset($this->fields['_pk']);
		foreach($fields as $key=>$val){
			// 记录字段类型
			$type[$key]=$val['type'];
			if($val['primary']){
				// 增加复合主键支持
				if(isset($this->fields['_pk']) && $this->fields['_pk'] != null){
					if(is_string($this->fields['_pk'])){
						$this->pk=array(
								$this->fields['_pk']
						);
						$this->fields['_pk']=$this->pk;
					}
					$this->pk[]=$key;
					$this->fields['_pk'][]=$key;
				}else{
					$this->pk=$key;
					$this->fields['_pk']=$key;
				}
				if($val['autoinc'])
					$this->autoinc=true;
			}
		}
		// 记录字段类型信息
		$this->fields['_type']=$type;
		
		// 2008-3-7 增加缓存开关控制
		if(C('db_fields_cache', !APP_DEBUG)){
			// 永久缓存数据表信息
			$name=$this->getTableName(true, true);
			F('_fields/'.strtolower($name), $this->fields);
		}
	}

	/**
	 * 设置数据对象的值
	 * 
	 * @param string $name 名称
	 * @param mixed $value 值
	 */
	public function __set($name,$value){
		// 设置数据对象属性
		$this->data[$name]=$value;
	}

	/**
	 * 获取数据对象的值
	 * 
	 * @param string $name 名称
	 * @return mixed
	 */
	public function __get($name){
		return isset($this->data[$name]) ? $this->data[$name] : null;
	}

	/**
	 * 检测数据对象的值
	 * 
	 * @param string $name 名称
	 * @return boolean
	 */
	public function __isset($name){
		return isset($this->data[$name]);
	}

	/**
	 * 销毁数据对象的值
	 * 
	 * @param string $name 名称
	 */
	public function __unset($name){
		unset($this->data[$name]);
	}

	/**
	 * 利用__call方法实现一些特殊的Model方法
	 * 
	 * @param string $method 方法名称
	 * @param array $args 调用参数
	 * @return mixed
	 */
	public function __call($method,$args){
		if(in_array(strtolower($method), $this->methods, true)){
			// 连贯操作的实现
			$this->options[strtolower($method)]=$args[0];
			return $this;
		}elseif(in_array(strtolower($method), array('count','sum','min','max','avg'), true)){
			// 统计查询的实现
			$method=strtolower($method);
			$field=isset($args[0]) ? $args[0] : '*';
			$value=$this->getField(strtoupper($method) . '(' . $field . ') AS tp_' . $method);
			return is_numeric($value) ? doubleval($value) : $value;
		}elseif(strtolower(substr($method, 0, 5)) == 'getby'){
			// 根据某个字段获取记录
			$field=parse_name(substr($method, 5));
			$where[$field]=$args[0];
			return $this->where($where)->find();
		}elseif(strtolower(substr($method, 0, 10)) == 'getfieldby'){
			// 根据某个字段获取记录的某个值
            $name=parse_name(substr($method, 10));
            $spliter=isset($args[2]) ? $args[2] : null;
			$where[$name]=$args[0];
			return $this->where($where)->getField($args[1], $spliter);
		}elseif(strtolower(substr($method, 0, 8)) == 'selectby'){
			// 根据某个字段获取记录
			$field=parse_name(substr($method, 8));
			$where[$field]=$args[0];
			return $this->where($where)->select();
		}elseif(isset($this->_scope[$method])){ // 命名范围的单独调用支持
			return $this->scope($method, $args[0]);
		}else{
			throw new Exception(__CLASS__ . ':' . $method . L('method not exist'), -401);
			return null;
		}
	}

	// 回调方法 初始化模型
	protected function _initialize(){
	}

	/**
	 * 对保存到数据库的数据进行处理
	 * 
	 * @param mixed $data 要操作的数据
	 * @return boolean
	 */
	protected function _facade($data){
		
		// 检查数据字段合法性
		if(!empty($this->fields)){
			if(!empty($this->options['field'])){
				$fields=$this->options['field'];
				unset($this->options['field']);
				if(is_string($fields)){
					$fields=explode(',', $fields);
				}
			}else{
				$fields=$this->fields;
			}
			foreach($data as $key=>$val){
				if(!in_array($key, (array)$fields, true)){
					if(!empty($this->options['strict'])){
					    throw new Exception(L('data type invalid') . ':[' . $key . '=>' . $val . ']', -501);
					}
					unset($data[$key]);
				}elseif(is_scalar($val)){
					// 字段类型检查 和 强制转换
					$this->_parseType($data, $key);
				}
			}
		}
		
		// 安全过滤
		if(!empty($this->options['filter'])){
			$data=array_map($this->options['filter'], $data);
			unset($this->options['filter']);
		}
		$this->_before_write($data);
		return $data;
	}

	// 写入数据前的回调方法 包括新增和更新
	protected function _before_write(&$data){
	}
    
    // 数据发生改变后的回调方法，包括新增、更新和删除
    protected function _after_change($data, $options, $action){
    }

	/**
	 * 新增数据
	 * 
	 * @param mixed $data 数据
	 * @param array $options 表达式
	 * @param boolean $replace 是否replace
	 * @return mixed
	 */
	public function add($data='',$options=[],$replace=false){
		if(empty($data)){
			// 没有传递数据，获取当前数据对象的值
			if(!empty($this->data)){
				$data=$this->data;
				// 重置数据
				$this->data=[];
			}else{
				$this->error=L('data type invalid');
				return false;
			}
		}
		// 数据处理
		$data=$this->_facade($data);
		// 分析表达式
		$options=$this->_parseOptions($options);
		if(false === $this->_before_insert($data, $options)){
			return false;
		}
		// 写入数据到数据库
		$result=$this->db->insert($data, $options, $replace);
		if(false !== $result && is_numeric($result)){
			$pk=$this->getPk();
			// 增加复合主键支持
			if(is_array($pk))
				return $result;
			$insertId=$this->getLastInsID();
			if($insertId){
				// 自增主键返回插入ID
				$data[$pk]=$insertId;
				if(false === $this->_after_insert($data, $options)){
					return false;
				}
                $this->autoCommit && 
                    $this->_after_change($data, $options, self::MODEL_INSERT);
				return $insertId;
			}
			if(false === $this->_after_insert($data, $options)){
				return false;
			}
            $this->autoCommit && 
                $this->_after_change($data, $options, self::MODEL_INSERT);
		}
		return $result;
	}

	// 插入数据前的回调方法
	protected function _before_insert(&$data,$options){
	}

	// 插入成功后的回调方法
	protected function _after_insert($data,$options){
	}

	public function addAll($dataList,$options=[],$replace=false){
		if(empty($dataList)){
			$this->error=L('data type invalid');
			return false;
		}
		// 数据处理
		foreach($dataList as $key=>$data){
			$dataList[$key]=$this->_facade($data);
		}
		// 分析表达式
		$options=$this->_parseOptions($options);
		// 写入数据到数据库
		$result=$this->db->insertAll($dataList, $options, $replace);
		if(false !== $result){
			$insertId=$this->getLastInsID();
			if($insertId){
                $this->autoCommit && 
                    $this->_after_change($dataList, $options, self::MODEL_INSERT);
				return $insertId;
			}
		}
		return $result;
	}

	/**
	 * 通过Select方式添加记录
	 * 
	 * @param string $fields 要插入的数据表字段名
	 * @param string $table 要插入的数据表名
	 * @param array $options 表达式
	 * @return boolean
	 */
	public function selectAdd($fields='',$table='',$options=[]){
		// 分析表达式
		$options=$this->_parseOptions($options);
		// 写入数据到数据库
        $result=$this->db->selectInsert($fields ? $fields : $options['field'], $table ? $table : $this->getTableName(), $options);
		if(false === $result){
			// 数据库插入操作失败
			$this->error=L('operation wrong');
			return false;
		}else{
			// 插入成功
			return $result;
		}
	}

	/**
	 * 保存数据
	 * 
	 * @param mixed $data 数据
	 * @param array $options 表达式
	 * @return boolean
	 */
	public function save($data='',$options=[]){
		if(empty($data)){
			// 没有传递数据，获取当前数据对象的值
			if(!empty($this->data)){
				$data=$this->data;
				// 重置数据
				$this->data=[];
			}else{
				$this->error=L('data type invalid');
				return false;
			}
		}
		// 数据处理
		$data=$this->_facade($data);
		if(empty($data)){
			// 没有数据则不执行
			$this->error=L('data type invalid');
			return false;
		}
		// 分析表达式
		$options=$this->_parseOptions($options);
		$pk=$this->getPk();
		if(!isset($options['where'])){
			// 如果存在主键数据 则自动作为更新条件
			if(is_string($pk) && isset($data[$pk])){
				$where[$pk]=$data[$pk];
				unset($data[$pk]);
			}elseif(is_array($pk)){
				// 增加复合主键支持
				foreach($pk as $field){
					if(isset($data[$field])){
						$where[$field]=$data[$field];
					}else{
						// 如果缺少复合主键数据则不执行
						$this->error=L('operation wrong');
						return false;
					}
					unset($data[$field]);
				}
			}
			if(!isset($where)){
				// 如果没有任何更新条件则不执行
				$this->error=L('operation wrong');
				return false;
			}else{
				$options['where']=$where;
			}
		}
		
		if(is_array($options['where']) && isset($options['where'][$pk])){
			$pkValue=$options['where'][$pk];
		}
		if(false === $this->_before_update($data, $options)){
			return false;
		}
		$result=$this->db->update($data, $options);
		if(false !== $result && is_numeric($result)){
			if(isset($pkValue)){
				$data[$pk]=$pkValue;
			}
			$this->_after_update($data, $options);
            $this->autoCommit && 
                $this->_after_change($data, $options, self::MODEL_UPDATE);
		}
		return $result;
	}

	// 更新数据前的回调方法
	protected function _before_update(&$data,$options){
	}

	// 更新成功后的回调方法
	protected function _after_update($data,$options){
	}

	/**
	 * 删除数据
	 * 
	 * @param mixed $options 表达式
	 * @return mixed
	 */
	public function delete($options=[]){
		$pk=$this->getPk();
		if(empty($options) && empty($this->options['where'])){
			// 如果删除条件为空 则删除当前数据对象所对应的记录
			if(!empty($this->data) && isset($this->data[$pk]))
				return $this->delete($this->data[$pk]);
			else
				return false;
		}
		if(is_numeric($options) || is_string($options)){
			// 根据主键删除记录
			if(strpos($options, ',')){
				$where[$pk]=array(
						'IN',
						$options
				);
			}else{
				$where[$pk]=$options;
			}
			$options=[];
			$options['where']=$where;
		}
		// 根据复合主键删除记录
		if(is_array($options) && (count($options) > 0) && is_array($pk)){
			$count=0;
			foreach(array_keys($options) as $key){
				if(is_int($key))
					$count++;
			}
			if($count == count($pk)){
				$i=0;
				foreach($pk as $field){
					$where[$field]=$options[$i];
					unset($options[$i++]);
				}
				$options['where']=$where;
			}else{
				return false;
			}
		}
		// 分析表达式
		$options=$this->_parseOptions($options);
		if(empty($options['where'])){
			// 如果条件为空 不进行删除操作 除非设置 1=1
			return false;
		}
		if(is_array($options['where']) && isset($options['where'][$pk])){
			$pkValue=$options['where'][$pk];
		}
		
		if(false === $this->_before_delete($options)){
			return false;
		}
		$result=$this->db->delete($options);
		if(false !== $result && is_numeric($result)){
			$data=[];
			if(isset($pkValue))
				$data[$pk]=$pkValue;
			$this->_after_delete($data, $options);
            $this->autoCommit && 
                $this->_after_change($data, $options, self::MODEL_DELETE);
		}
		// 返回删除记录个数
		return $result;
	}

	// 删除数据前的回调方法
	protected function _before_delete($options){
	}

	// 删除成功后的回调方法
	protected function _after_delete($data,$options){
	}

	/**
	 * 查询数据集
	 * 
	 * @param array $options 表达式参数
	 * @return mixed
	 */
	public function select($options=[]){
		$pk=$this->getPk();
		if(is_string($options) || is_numeric($options)){
			// 根据主键查询
			if(strpos($options, ',')){
				$where[$pk]=array(
						'IN',
						$options
				);
			}else{
				$where[$pk]=$options;
			}
			$options=[];
			$options['where']=$where;
		}elseif(is_array($options) && (count($options) > 0) && is_array($pk)){
			// 根据复合主键查询
			$count=0;
			foreach(array_keys($options) as $key){
				if(is_int($key))
					$count++;
			}
			if($count == count($pk)){
				$i=0;
				foreach($pk as $field){
					$where[$field]=$options[$i];
					unset($options[$i++]);
				}
				$options['where']=$where;
			}else{
				return false;
			}
		}elseif(false === $options){ // 用于子查询 不查询只返回SQL
			$options['fetch_sql']=true;
		}
		// 分析表达式
		$options=$this->_parseOptions($options);
		// 判断查询缓存
		if(isset($options['cache'])){
			$cache=$options['cache'];
			$key=is_string($cache['key']) ? $cache['key'] : md5(serialize($options));
			$data=S($key, '', $cache);
			if(false !== $data){
				return $data;
			}
		}
		$resultSet=$this->db->select($options);
		if(false === $resultSet){
			return false;
		}
		if(!empty($resultSet)){ // 有查询结果
			if(is_string($resultSet)){
				return $resultSet;
			}
			
			$resultSet=array_map(array(
					$this,
					'_read_data'
			), $resultSet);
			$this->_after_select($resultSet, $options);
		}
		
		if(isset($cache)){
			S($key, $resultSet, $cache);
		}
		return $resultSet;
	}

	// 查询成功后的回调方法
	protected function _after_select(&$resultSet,$options){
	}

	/**
	 * 生成查询SQL 可用于子查询
	 * 
	 * @return string
	 */
	public function buildSql(){
		return '( ' . $this->fetchSql(true)->select() . ' )';
	}

	/**
	 * 分析表达式
	 * 
	 * @param array $options 表达式参数
	 * @return array
	 */
	protected function _parseOptions($options=[]){
		if(is_array($options))
			$options=array_merge($this->options, $options);
		
		if(!isset($options['table'])){
			// 自动获取表名
			$options['table']=$this->getTableName();
			$fields=$this->fields;
		}else{
			// 指定数据表 则重新获取字段列表 但不支持类型检测
			$fields=$this->getDbFields();
		}
		
		// 数据表别名
		if(!empty($options['alias'])){
			$options['table'].=' ' . $options['alias'];
		}
		// 记录操作的模型名称
		$options['model']=$this->name;
		
		// 字段类型验证
		if(isset($options['where']) && is_array($options['where']) && !empty($fields) && !isset($options['join'])){
			// 对数组查询条件进行字段类型检查
			foreach($options['where'] as $key=>$val){
				$key=trim($key);
				if(in_array($key, $fields, true)){
					if(is_scalar($val)){
						$this->_parseType($options['where'], $key);
					}
				}elseif(!is_numeric($key) && '_' != substr($key, 0, 1) && false === strpos($key, '.') && false === strpos($key, '(') && false === strpos($key, '|') && false === strpos($key, '&')){
					if(!empty($this->options['strict'])){
						throw new Exception(L('error query express') . ':[' . $key . '=>' . $val . ']');
					}
					unset($options['where'][$key]);
				}
			}
		}
		// 查询过后清空sql表达式组装 避免影响下次查询
		$this->options=[];
		// 表达式过滤
		$this->_options_filter($options);
		return $options;
	}

	// 表达式过滤回调方法
	protected function _options_filter(&$options){
	}

	/**
	 * 数据类型检测
	 * 
	 * @param mixed $data 数据
	 * @param string $key 字段名
	 * @return void
	 */
	protected function _parseType(&$data,$key){
		if(!isset($this->options['bind'][':' . $key]) && isset($this->fields['_type'][$key])){
			$fieldType=strtolower($this->fields['_type'][$key]);
			if(false !== strpos($fieldType, 'enum')){
				// 支持ENUM类型优先检测
			}elseif(false === strpos($fieldType, 'bigint') && false !== strpos($fieldType, 'int')){
				$data[$key]=intval($data[$key]);
			}elseif(false !== strpos($fieldType, 'float') || false !== strpos($fieldType, 'double')){
				$data[$key]=floatval($data[$key]);
			}elseif(false !== strpos($fieldType, 'bool')){
				$data[$key]=(bool)$data[$key];
			}
		}
	}

	/**
	 * 数据读取后的处理
	 * 
	 * @param array $data 当前数据
	 * @return array
	 */
	protected function _read_data($data){
		// 检查字段映射
		if(!empty($this->_map) && C('READ_DATA_MAP')){
			foreach($this->_map as $key=>$val){
				if(isset($data[$val])){
					$data[$key]=$data[$val];
					unset($data[$val]);
				}
			}
		}
		return $data;
	}

	/**
	 * 查询数据
	 * 
	 * @param mixed $options 表达式参数
	 * @return mixed
	 */
	public function find($options=[]){
		if(is_numeric($options) || is_string($options)){
			$where[$this->getPk()]=$options;
			$options=[];
			$options['where']=$where;
		}
		// 根据复合主键查找记录
		$pk=$this->getPk();
		if(is_array($options) && (count($options) > 0) && is_array($pk)){
			// 根据复合主键查询
			$count=0;
			foreach(array_keys($options) as $key){
				if(is_int($key))
					$count++;
			}
			if($count == count($pk)){
				$i=0;
				foreach($pk as $field){
					$where[$field]=$options[$i];
					unset($options[$i++]);
				}
				$options['where']=$where;
			}else{
				return false;
			}
		}
		// 总是查找一条记录
		$options['limit']=1;
		// 分析表达式
		$options=$this->_parseOptions($options);
		// 判断查询缓存
		if(isset($options['cache'])){
			$cache=$options['cache'];
			$key=is_string($cache['key']) ? $cache['key'] : md5(serialize($options));
			$data=S($key, '', $cache);
			if(false !== $data){
				$this->data=$data;
				return $data;
			}
		}
		$resultSet=$this->db->select($options);
		if(false === $resultSet){
			return false;
		}
		if(empty($resultSet)){ // 查询结果为空
			return null;
		}
		if(is_string($resultSet)){
			return $resultSet;
		}
		
		// 读取数据后的处理
		$data=$this->_read_data($resultSet[0]);
		$this->_after_find($data, $options);
        
		$this->data=$data;
		if(isset($cache)){
			S($key, $data, $cache);
		}
		return $this->data;
	}

	// 查询成功的回调方法
	protected function _after_find(&$result,$options){
	}

	/**
	 * 处理字段映射
	 * 
	 * @param array $data 当前数据
	 * @param integer $type 类型 0 写入 1 读取
	 * @return array
	 */
	public function parseFieldsMap($data,$type=1){
		// 检查字段映射
		if(!empty($this->_map)){
			foreach($this->_map as $key=>$val){
				if($type == 1){ // 读取
					if(isset($data[$val])){
						$data[$key]=$data[$val];
						unset($data[$val]);
					}
				}else{
					if(isset($data[$key])){
						$data[$val]=$data[$key];
						unset($data[$key]);
					}
				}
			}
		}
		return $data;
	}

	/**
	 * 设置记录的某个字段值 支持使用数据库字段和方法
	 * 
	 * @param string|array $field 字段名
	 * @param string $value 字段值
	 * @return boolean
	 */
	public function setField($field,$value=''){
		if(is_array($field)){
			$data=$field;
		}else{
			$data[$field]=$value;
		}
		return $this->save($data);
	}

	/**
	 * 字段值增长
	 * 
	 * @param string $field 字段名
	 * @param integer $step 增长值
	 * @param integer $lazyTime 延时时间(s)
	 * @return boolean
	 */
	public function setInc($field,$step=1,$lazyTime=0){
		if($lazyTime > 0){ // 延迟写入
			$condition=$this->options['where'];
			$guid=md5($this->name . '_' . $field . '_' . serialize($condition));
			$step=$this->lazyWrite($guid, $step, $lazyTime);
			if(false === $step)
				return true; // 等待下次写入
		}
		return $this->setField($field, array(
				'exp',
				$field . '+' . $step
		));
	}

	/**
	 * 字段值减少
	 * 
	 * @param string $field 字段名
	 * @param integer $step 减少值
	 * @param integer $lazyTime 延时时间(s)
	 * @return boolean
	 */
	public function setDec($field,$step=1,$lazyTime=0){
		if($lazyTime > 0){ // 延迟写入
			$condition=$this->options['where'];
			$guid=md5($this->name . '_' . $field . '_' . serialize($condition));
			$step=$this->lazyWrite($guid, $step, $lazyTime);
			if(false === $step)
				return true; // 等待下次写入
		}
		return $this->setField($field, array(
				'exp',
				$field . '-' . $step
		));
	}

	/**
	 * 延时更新检查 返回false表示需要延时 否则返回实际写入的数值
	 * 
	 * @param string $guid 写入标识
	 * @param integer $step 写入步进值
	 * @param integer $lazyTime 延时时间(s)
	 * @return false|integer
	 */
	protected function lazyWrite($guid,$step,$lazyTime){
		$now_time=time();
		if(false !== ($value=S($guid))){ // 存在缓存写入数据
			if($now_time > S($guid . '_time') + $lazyTime){
				// 延时更新时间到了，删除缓存数据 并实际写入数据库
				S($guid, NULL);
				S($guid . '_time', NULL);
				return $value + $step;
			}else{
				// 追加数据到缓存
				S($guid, $value + $step);
				return false;
			}
		}else{ // 没有缓存数据
			S($guid, $step);
			// 计时开始
			S($guid . '_time', $now_time);
			return false;
		}
	}

	/**
	 * 获取一条记录的某个字段值
	 * 
	 * @param string $field 字段名
	 * @param string $spea 字段数据间隔符号 NULL返回数组
	 * @return mixed
	 */
	public function getField($field,$sepa=null){
		$options['field']=$field;
		$options=$this->_parseOptions($options);
		// 判断查询缓存
		if(isset($options['cache'])){
			$cache=$options['cache'];
			$key=is_string($cache['key']) ? $cache['key'] : md5($sepa . serialize($options));
			$data=S($key, '', $cache);
			if(false !== $data){
				return $data;
			}
		}
		$field=trim($field);
		if(strpos($field, ',') && false !== $sepa){ // 多字段
			if(!isset($options['limit'])){
				$options['limit']=is_numeric($sepa) ? $sepa : '';
			}
			$resultSet=$this->db->select($options);
			if(!empty($resultSet)){
				if(is_string($resultSet)){
					return $resultSet;
				}
				$_field=explode(',', $field);
				$field=array_keys($resultSet[0]);
				$key1=array_shift($field);
				$key2=array_shift($field);
				$cols=[];
				$isArray=count($_field)>2 || $_field[1]=='*';
				foreach($resultSet as $result){
					$name=$result[$key1];
					if(!$isArray){
						$cols[$name]=$result[$key2];
					}else{
						$cols[$name]=is_string($sepa) ? implode($sepa, array_slice($result, 1)) : $result;
					}
				}
				if(isset($cache)){
					S($key, $cols, $cache);
				}
				return $cols;
			}
		}else{ // 查找一条记录
		       // 返回数据个数
			if(true !== $sepa){ // 当sepa指定为true的时候 返回所有数据
				$options['limit']=is_numeric($sepa) ? $sepa : 1;
			}
			$result=$this->db->select($options);
			if(!empty($result)){
				if(is_string($result)){
					return $result;
				}
				if(true !== $sepa && 1 == $options['limit']){
					$data=reset($result[0]);
					if(isset($cache)){
						S($key, $data, $cache);
					}
					return $data;
				}
				
				// [2016-07-19 17:44]修复获取类似distinct id或count(id) as field的问题
				if(strpos($field, ' ')){
					$field=substr($field, strrpos($field, ' ') + 1);
				}
				
				foreach($result as $val){
					$array[]=$val[$field];
				}
				if(isset($cache)){
					S($key, $array, $cache);
				}
				return $array;
			}
		}
		return null;
	}

	/**
	 * 创建数据对象 但不保存到数据库
	 * 
	 * @param mixed $data 创建数据
	 * @param string $type 状态
	 * @return mixed
	 */
	public function create($data, $type=''){
		// 如果没有传值默认取POST数据
		if(is_object($data)){
			$data=get_object_vars($data);
		}
		// 验证数据
		if(empty($data) || !is_array($data)){
			$this->error=L('data type invalid');
			return false;
		}
		
		// 状态
		$type=$type ? $type : (!empty($data[$this->getPk()]) ? self::MODEL_UPDATE : self::MODEL_INSERT);
		
		// 检查字段映射
		if(!empty($this->_map)){
			foreach($this->_map as $key=>$val){
				if(isset($data[$key])){
					$data[$val]=$data[$key];
					unset($data[$key]);
				}
			}
		}
		
		// 检测提交字段的合法性
		if(isset($this->options['field'])){ // $this->field('field1,field2...')->create()
			$fields=$this->options['field'];
			unset($this->options['field']);
		}elseif($type == self::MODEL_INSERT && isset($this->insertFields)){
			$fields=$this->insertFields;
		}elseif($type == self::MODEL_UPDATE && isset($this->updateFields)){
			$fields=$this->updateFields;
		}
		if(isset($fields)){
			if(is_string($fields)){
				$fields=explode(',', $fields);
			}
			// 判断令牌验证字段
			if(C('TOKEN_ON'))
				$fields[]=C('TOKEN_NAME', null, '__hash__');
			foreach($data as $key=>$val){
				if(!in_array($key, (array)$fields)){
					unset($data[$key]);
				}
			}
		}
		
		// 数据自动验证
		if(!$this->autoValidation($data, $type))
			return false;
		
		// 表单令牌验证
		if(!$this->autoCheckToken($data)){
			$this->error=L('token error');
			return false;
		}
		
		// 验证完成生成数据对象
		if($this->autoCheckFields){ // 开启字段检测 则过滤非法字段数据
			$fields=$this->getDbFields();
			foreach($data as $key=>$val){
				if(!in_array($key, $fields)){
					unset($data[$key]);
				}elseif($this->stripSlashes && is_string($val)){
					$data[$key]=stripslashes($val);
				}
			}
		}
		
		// 创建完成对数据进行自动处理
		$this->autoOperation($data, $type);
		// 赋值当前数据对象
		$this->data=$data;
		// 返回创建的数据以供其他调用
		return $data;
	}

	// 自动表单令牌验证
	// TODO ajax无刷新多次提交暂不能满足
	public function autoCheckToken($data){
		// 支持使用token(false) 关闭令牌验证
		if(isset($this->options['token']) && !$this->options['token'])
			return true;
		if(C('TOKEN_ON')){
			$name=C('TOKEN_NAME', null, '__hash__');
			if(!isset($data[$name]) || !isset($_SESSION[$name])){ // 令牌数据无效
				return false;
			}
			
			// 令牌验证
			list($key, $value)=explode('_', $data[$name]);
			if(isset($_SESSION[$name][$key]) && $value && $_SESSION[$name][$key] === $value){ // 防止重复提交
				unset($_SESSION[$name][$key]); // 验证完成销毁session
				return true;
			}
			// 开启TOKEN重置
			if(C('TOKEN_RESET'))
				unset($_SESSION[$name][$key]);
			return false;
		}
		return true;
	}

	/**
	 * 使用正则验证数据
	 * 
	 * @param string $value 要验证的数据
	 * @param string $rule 验证规则
	 * @return boolean
	 */
	public function regex($value,$rule){
		$validate=array(
				'require' => '/\S+/',
				'email' => '/^\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*$/',
				'url' => '/^http(s?):\/\/(?:[A-za-z0-9-]+\.)+[A-za-z]{2,4}(:\d+)?(?:[\/\?#][\/=\?%\-&~`@[\]\':+!\.#\w]*)?$/',
				'currency' => '/^\d+(\.\d+)?$/',
				'number' => '/^\d+$/',
				'zip' => '/^\d{6}$/',
				'integer' => '/^[-\+]?\d+$/',
				'double' => '/^[-\+]?\d+(\.\d+)?$/',
				'english' => '/^[A-Za-z]+$/',
				'tel'=> '/^\+?\d[\d\-]+$/',
				'mobile'=> '/^\+?\d\d+$/',
		);
        // 检查是否有内置的正则表达式
        $rule=strtolower($rule);
		if(isset($validate[$rule])){
            $rule=$validate[$rule];
        }
        
        //如果值为数组，只检查是否空
        if(is_array($value)){
            if($rule=='require'){
                return !empty($value);
            }
            return false;
        }
		return preg_match($rule, $value) === 1;
	}

	/**
	 * 自动表单处理
	 * 
	 * @param array $data 创建数据
	 * @param string $type 创建类型
	 * @return mixed
	 */
	private function autoOperation(&$data,$type){
		if(!empty($this->options['auto'])){
			$_auto=$this->options['auto'];
			unset($this->options['auto']);
		}elseif(!empty($this->_auto)){
			$_auto=$this->_auto;
		}
		// 自动填充
		if(isset($_auto)){
			foreach($_auto as $auto){
				// 填充因子定义格式
				// array('field','填充内容','填充条件','附加规则',[额外参数])
				if(empty($auto[2]))
					$auto[2]=self::MODEL_INSERT; // 默认为新增的时候自动填充
				if($type == $auto[2] || $auto[2] == self::MODEL_BOTH){
					if(empty($auto[3]))
						$auto[3]='string';
					switch(trim($auto[3])){
						case 'function': // 使用函数进行填充 字段的值作为参数
						case 'callback': // 使用回调方法
							$args=isset($auto[4]) ? (array)$auto[4] : [];
							if(isset($data[$auto[0]])){
								array_unshift($args, $data[$auto[0]]);
							}
							if('function' == $auto[3]){
								$data[$auto[0]]=call_user_func_array($auto[1], $args);
							}else{
								$data[$auto[0]]=call_user_func_array(array(
										&$this,
										$auto[1]
								), $args);
							}
							break;
						case 'field': // 用其它字段的值进行填充
							$data[$auto[0]]=$data[$auto[1]];
							break;
						case 'ignore': // 为空忽略
							if($auto[1] === $data[$auto[0]])
								unset($data[$auto[0]]);
							break;
						case 'string':
						default: // 默认作为字符串填充
							$data[$auto[0]]=$auto[1];
					}
					if(isset($data[$auto[0]]) && false === $data[$auto[0]])
						unset($data[$auto[0]]);
				}
			}
		}
		return $data;
	}

	/**
	 * 自动表单验证
	 * 
	 * @param array $data 创建数据
	 * @param string $type 创建类型
	 * @return boolean
	 */
	protected function autoValidation($data,$type){
		if(!empty($this->options['validate'])){
			$_validate=$this->options['validate'];
			unset($this->options['validate']);
		}elseif(!empty($this->_validate)){
			$_validate=$this->_validate;
		}
		// 属性验证
		if(isset($_validate)){ // 如果设置了数据自动验证则进行数据验证
			if($this->patchValidate){ // 重置验证错误信息
				$this->error=[];
			}
			foreach($_validate as $key=>$val){
				// 验证因子定义格式
				// array(field,rule,message,condition,type,when,params)
				// 判断是否需要执行验证
				if(empty($val[5]) || ($val[5] == self::MODEL_BOTH && $type < 3) || $val[5] == $type){
					if(0 == strpos($val[2], '{%') && strpos($val[2], '}'))
						// 支持提示信息的多语言 使用 {%语言定义} 方式
						$val[2]=L(substr($val[2], 2, -1));
					$val[3]=isset($val[3]) ? $val[3] : self::EXISTS_VALIDATE;
					$val[4]=isset($val[4]) ? $val[4] : 'regex';
					// 判断验证条件
					switch($val[3]){
						case self::MUST_VALIDATE: // 必须验证 不管表单是否有设置该字段
							if(false === $this->_validationField($data, $val))
								return false;
							break;
						case self::VALUE_VALIDATE: // 值不为空的时候才验证
							if('' != trim($data[$val[0]]))
								if(false === $this->_validationField($data, $val))
									return false;
							break;
						default: // 默认表单存在该字段就验证
							if(isset($data[$val[0]]))
								if(false === $this->_validationField($data, $val))
									return false;
					}
				}
			}
			// 批量验证的时候最后返回错误
			if(!empty($this->error))
				return false;
		}
		return true;
	}

	/**
	 * 验证表单字段 支持批量验证 如果批量验证返回错误的数组信息
	 * 
	 * @param array $data 创建数据
	 * @param array $val 验证因子
	 * @return boolean
	 */
	protected function _validationField($data,$val){
		if($this->patchValidate && isset($this->error[$val[0]]))
			return; // 当前字段已经有规则验证没有通过
		if(false === $this->_validationFieldItem($data, $val)){
			if($this->patchValidate){
				$this->error[$val[0]]=$val[2];
			}else{
				$this->error=$val[2];
				return false;
			}
		}
		return;
	}

	/**
	 * 根据验证因子验证字段
	 * 
	 * @param array $data 创建数据
	 * @param array $val 验证因子
	 * @return boolean
	 */
	protected function _validationFieldItem($data,$val){
		switch(strtolower(trim($val[4]))){
			case 'function': // 使用函数进行验证
			case 'callback': // 调用方法进行验证
				$args=isset($val[6]) ? (array)$val[6] : [];
				if(is_string($val[0]) && strpos($val[0], ','))
					$val[0]=explode(',', $val[0]);
				if(is_array($val[0])){
					// 支持多个字段验证
					foreach($val[0] as $field)
						$_data[$field]=$data[$field];
					array_unshift($args, $_data);
				}else{
					array_unshift($args, $data[$val[0]]);
				}
				if('function' == $val[4]){
					return call_user_func_array($val[1], $args);
				}else{
					return call_user_func_array(array(
							&$this,
							$val[1]
					), $args);
				}
			case 'confirm': // 验证两个字段是否相同
				return $data[$val[0]] == $data[$val[1]];
			case 'unique': // 验证某个值是否唯一
				if(is_string($val[0]) && strpos($val[0], ','))
					$val[0]=explode(',', $val[0]);
				$map=[];
				if(is_array($val[0])){
					// 支持多个字段验证
					foreach($val[0] as $field)
						$map[$field]=$data[$field];
				}else{
					$map[$val[0]]=$data[$val[0]];
				}
				$pk=$this->getPk();
				if(!empty($data[$pk]) && is_string($pk)){ // 完善编辑的时候验证唯一
					$map[$pk]=array(
							'neq',
							$data[$pk]
					);
				}
				if($this->where($map)->count())
					return false;
				return true;
			default: // 检查附加规则
				return $this->check($data[$val[0]], $val[1], $val[4]);
		}
	}

	/**
	 * 验证数据 支持 in between equal length regex expire ip_allow ip_deny
	 * 
	 * @param string $value 验证数据
	 * @param string $rule 验证表达式
	 * @param string $type 验证方式 默认为正则验证
	 * @return boolean
	 */
	public function check($value,$rule,$type='regex'){
		$type=strtolower(trim($type));
		switch($type){
			case 'in': // 验证是否在某个指定范围之内 逗号分隔字符串或者数组
			case 'notin':
				$range=is_array($rule) ? $rule : (array)explode(',', $rule);
				return $type == 'in' ? in_array($value, $range) : !in_array($value, $range);
			case 'between': // 验证是否在某个范围
			case 'notbetween': // 验证是否不在某个范围
				if(is_array($rule)){
					$min=$rule[0];
					$max=$rule[1];
				}else{
					list($min, $max)=explode(',', $rule);
				}
				return $type == 'between' ? $value >= $min && $value <= $max : $value < $min || $value > $max;
			case 'equal': // 验证是否等于某个值
			case 'notequal': // 验证是否等于某个值
				return $type == 'equal' ? $value == $rule : $value != $rule;
			case 'length': // 验证长度
				$length=mb_strlen($value, 'utf-8'); // 当前数据长度
				if(strpos($rule, ',')){ // 长度区间
					list($min, $max)=explode(',', $rule);
					return $length >= $min && $length <= $max;
				}else{ // 指定长度
					return $length == $rule;
				}
			case 'expire':
				list($start, $end)=explode(',', $rule);
				$now_time=time();
				if(!is_numeric($start))
					$start=strtotime($start);
				if(!is_numeric($end))
					$end=strtotime($end);
				return $now_time >= $start && $now_time <= $end;
			case 'ip_allow': // IP 操作许可验证
				return in_array(getIp(), (array)explode(',', $rule));
			case 'ip_deny': // IP 操作禁止验证
				return !in_array(getIp(), (array)explode(',', $rule));
			case 'regex':
			default: // 默认使用正则验证 可以使用验证类中定义的验证名称
			         // 检查附加规则
				return $this->regex($value, $rule);
		}
	}

	/**
	 * SQL查询
	 * 
	 * @param string $sql SQL指令
	 * @param mixed $parse 是否需要解析SQL
	 * @return mixed
	 */
	public function query($sql,$parse=false){
		if(!is_bool($parse) && !is_array($parse)){
			$parse=func_get_args();
			array_shift($parse);
		}
		$sql=$this->parseSql(trim($sql), $parse);
		$isQuery=stripos($sql, 'select')===0;
		
		$options=$this->_parseOptions();
		// 判断查询缓存
		if($isQuery && isset($options['cache'])){
			$cache=$options['cache'];
			$key=is_string($cache['key']) ? $cache['key'] : md5('query:'.$sql);
			$data=S($key, '', $cache);
			if(false !== $data){
				return $data;
			}
		}
		
		//查询结果
		$result=$this->db->query($sql);
		//设置缓存
		if(isset($cache)){
			S($key, $result, $cache);
		}
		return $result;
	}

	/**
	 * 执行SQL语句
	 * 
	 * @param string $sql SQL指令
	 * @param mixed $parse 是否需要解析SQL
	 * @return false | integer
	 */
	public function execute($sql,$parse=false){
		if(!is_bool($parse) && !is_array($parse)){
			$parse=func_get_args();
			array_shift($parse);
		}
		$sql=$this->parseSql($sql, $parse);
		return $this->db->execute($sql);
	}
	
	/**
	 * 过滤SQL语句中的表字段名称
	 * 
	 * @param string $sql
	 * @return string
	 */
	protected function escapeTable($sql,$isTable=true){
		if(strpos($sql, '__')!==false){
            $sql=str_replace('__PREFIX__', $this->tablePrefix, $sql);
			if($isTable){
                $sql=str_replace('__TABLE__', $this->getTableName(), $sql);
			}
			
			if(strpos($sql, '__')!==false){
				$sql=preg_replace_callback("/__([A-Z0-9_-]+)__/sU", array($this, '_escapeTableCallBack'), $sql);
			}
		}
		return $sql;
	}
    
    /**
     * 为表添加前缀回调函数
     *
     * @param array $match
     */
    protected function _escapeTableCallBack($match){
        return $this->tablePrefix . strtolower($match[1]);
    }

	/**
	 * 解析SQL语句
	 * 
	 * @param string $sql SQL指令
	 * @param boolean $parse 是否需要解析SQL
	 * @return string
	 */
	protected function parseSql($sql,$parse){
		// 分析表达式
		if(true === $parse){
			$options=$this->_parseOptions();
			$sql=$this->db->parseSql($sql, $options);
		}elseif(is_array($parse)){ // SQL预处理
			$parse=array_map(array(
					$this->db,
					'escapeString'
			), (array)$parse);
			$sql=vsprintf($sql, $parse);
		}else{
			$sql=$this->escapeTable($sql);
		}
		$this->db->setModel($this->name);
		return $sql;
	}

	/**
	 * 切换当前的数据库连接
	 * 
	 * @param integer $linkNum 连接序号
	 * @param mixed $config 数据库连接信息
	 * @param boolean $force 强制重新连接
	 * @return Model
	 */
	public function db($linkNum='',$config='',$force=false){
		if('' === $linkNum && $this->db){
			return $this->db;
		}
		
		if(!isset($this->_db[$linkNum]) || $force){
			// 创建一个新的实例
			$this->_db[$linkNum]=DatabaseService::getInstance($config);
		}elseif(NULL === $config){
			$this->_db[$linkNum]->close(); // 关闭数据库连接
			unset($this->_db[$linkNum]);
			return;
		}
		
		// 切换数据库连接
		$this->db=$this->_db[$linkNum];
		$this->_after_db();
		// 字段检测
		if(!empty($this->name) && $this->autoCheckFields)
			$this->_checkTableInfo();
		return $this;
	}

	// 数据库切换后回调方法
	protected function _after_db(){
	}

	/**
	 * 得到当前的数据对象名称
	 * 
	 * @return string
	 */
	public function getModelName(){
		if(
            empty($this->name) && 
            (
                defined('INI_STEEZE') ? 
                    is_subclass_of($this, '\Library\Model') : 
                    is_subclass_of($this, 'Model') 
            )
        ){
			$len=strlen(C('DEFAULT_M_LAYER'));
			$name=$len ? substr(get_class($this), 0, -$len) : get_class($this);
			if($pos=strrpos($name, '\\')){ // 有命名空间
				$this->name=substr($name, $pos + 1);
			}else{
				$this->name=$name;
			}
		}
		return $this->name;
	}

	/**
	 * 得到完整的数据表名
	 * 
     * @param bool $isTablePrefix 是否带表前缀
     * @param bool $isDbName 是否带数据库名称
	 * @return string
	 */
	public function getTableName($isTablePrefix=true, $isDbName=false){
		if(empty($this->trueTableName)){
            $prefix=$this->tablePrefix;
			if(!empty($this->tableName)){
				$table=$this->tableName;
			}else{
				$name=parse_name($this->escapeTable($this->name,false));
				$table=$prefix!=='' && strpos($name,$prefix)===0 ? substr($name, strlen($prefix)) : $name;
			}
            $tableName=strtolower($prefix.$table); //不带数据库前缀的表名
            $this->trueTableName=!empty($this->dbName) && $isDbName ? $this->dbName . '.' . $tableName : $tableName;
            return $isTablePrefix ? $this->trueTableName : $table;
		}
        if(!$isTablePrefix){
            //不带数据库前缀的表名
            $tableName=!empty($this->dbName) && strpos($this->trueTableName, $this->dbName.'.')===0 ? 
                    substr($this->trueTableName,strlen($this->dbName)+1) : $this->trueTableName;
            return $this->tablePrefix==='' ? $tableName : substr($tableName, strlen($this->tablePrefix));
        }
        return $this->trueTableName;
	}

	/**
	 * 启动事务
	 */
	public function startTrans(){
        $this->autoCommit=false;
		$this->db->commit();
		$this->db->startTrans();
	}

	/**
	 * 提交事务
	 * 
     * @param array $data
     * @param array $options
	 * @return boolean
	 */
	public function commit($data=[], $options=[]){
        $this->autoCommit=true;
		$result=$this->db->commit();
        if($result){
            $this->_after_change($data, $options, self::MODEL_CHANGE);
        }
        return $result;
	}

	/**
	 * 事务回滚
	 * 
	 * @return boolean
	 */
	public function rollback(){
        $this->autoCommit=false;
		return $this->db->rollback();
	}

	/**
	 * 返回模型的错误信息
	 * 
	 * @return string
	 */
	public function getError(){
		return $this->error;
	}

	/**
	 * 返回数据库的错误信息
	 * 
	 * @return string
	 */
	public function getDbError(){
		return $this->db->getError();
	}

	/**
	 * 返回最后插入的ID
	 * 
	 * @return string
	 */
	public function getLastInsID(){
		return $this->db->getLastInsID();
	}

	/**
	 * 返回最后执行的sql语句
	 * 
	 * @return string
	 */
	public function getLastSql(){
		return $this->db->getLastSql($this->name);
	}

	// 鉴于getLastSql比较常用 增加_sql 别名
	public function _sql(){
		return $this->getLastSql();
	}

	/**
	 * 获取主键名称
	 * 
	 * @return string
	 */
	public function getPk(){
		return $this->pk;
	}

	/**
	 * 获取数据表字段信息
	 * 
	 * @return array
	 */
	public function getDbFields(){
		if(isset($this->options['table'])){ // 动态指定表名
			if(is_array($this->options['table'])){
				$table=key($this->options['table']);
			}else{
				$table=$this->options['table'];
				if(strpos($table, ')')){
					// 子查询
					return false;
				}
			}
			$fields=$this->db->getFields($table);
			return $fields ? array_keys($fields) : false;
		}
		if($this->fields){
			$fields=$this->fields;
			unset($fields['_type'], $fields['_pk']);
			return $fields;
		}
		return false;
	}
	
	/**
	 * 获取数据表字段详细信息
	 *
	 * @return array
	 */
	public function getDbFieldInfos(){
		$tableName=!empty($this->options['table']) ? $this->options['table'] : $this->trueTableName;
		if(isset($tableName)){ // 动态指定表名
			if(is_array($tableName)){
				$table=key($tableName);
			}else{
				$table=$tableName;
				if(strpos($table, ')')){
					// 子查询
					return false;
				}
			}
			return $this->db->getFields($table);
		}
		return false;
	}
    
    /**
     * 获取当前数据库表
     *
     * @param boolean $usePrefix 是否使用表前缀，如果使用只返回具有当前表前缀的表名称
     * @return array
     */
    public function getDbTables($usePrefix=true){
        $tables=$this->db->getTables();
        if(
            $usePrefix && 
            !is_null($this->tablePrefix) && 
            $this->tablePrefix!==''
        ){
            $returnTables=[];
            $prefixLength=strlen($this->tablePrefix);
            foreach($tables as &$val){
                if(strpos($val, $this->tablePrefix)===0){
                    $returnTables[]=substr($val, $prefixLength);
                }
            }
            return $returnTables;
        }
        return $tables;
    }
	

	/**
	 * 设置数据对象值
	 * 
	 * @param mixed $data 数据
	 * @return Model
	 */
	public function data($data=''){
		if('' === $data && !empty($this->data)){
			return $this->data;
		}
		if(is_object($data)){
			$data=get_object_vars($data);
		}elseif(is_string($data)){
            $param=[];
			parse_str($data, $param);
            $data=$param;
		}elseif(!is_array($data)){
			throw new Exception(L('data type invalid'));
		}
		$this->data=$data;
		return $this;
	}

	/**
	 * 指定当前的数据表
	 * 
	 * @param array|string $table
	 * @return Model
	 */
	public function table($table){
		$prefix=$this->tablePrefix;
		if(is_array($table)){
			$this->options['table']=$table;
		}elseif(!empty($table)){
			if(($prefix==='' || strpos($table, $prefix)!==0) && preg_match('/^[a-z]\w+$/i', $table)){
				$this->options['table']=$prefix.$table;
			}else{
				$this->options['table']=$this->escapeTable($table);
			}
		}
		return $this;
	}

	/**
	 * USING支持 用于多表删除
	 * 
	 * @param array|string $using
	 * @return Model
	 */
	public function using($using){
		if(is_array($using)){
			$this->options['using']=$using;
		}elseif(!empty($using)){
			$this->options['using']=$this->escapeTable($using);
		}
		return $this;
	}

	/**
	 * 查询SQL组装 join
	 * 
	 * @param mixed $join
	 * @param string $type JOIN类型
	 * @return Model
	 */
	public function join($join,$type='INNER'){
		if(is_array($join)){
			foreach($join as $key=>&$_join){
				$_join=$this->escapeTable($_join);
				$_join=false !== stripos($_join, 'JOIN') ? $_join : $type . ' JOIN ' . $_join;
			}
			$this->options['join']=$join;
		}elseif(!empty($join)){
			// 将__TABLE_NAME__字符串替换成带前缀的表名
			$join=$this->escapeTable($join);
			$this->options['join'][]=false !== stripos($join, 'JOIN') ? $join : $type . ' JOIN ' . $join;
		}
		return $this;
	}

	/**
	 * 查询SQL组装 union
	 * 
	 * @param mixed $union
	 * @param boolean $all
	 * @return Model
	 */
	public function union($union,$all=false){
		if(empty($union))
			return $this;
		if($all){
			$this->options['union']['_all']=true;
		}
		if(is_object($union)){
			$union=get_object_vars($union);
		}
		// 转换union表达式
		if(is_string($union)){
			$options=$this->escapeTable($union);
		}elseif(is_array($union)){
			if(isset($union[0])){
				$this->options['union']=array_merge($this->options['union'], $union);
				return $this;
			}else{
				$options=$union;
			}
		}else{
			throw new Exception(L('data type invalid'));
		}
		$this->options['union'][]=$options;
		return $this;
	}

	/**
	 * 查询缓存
	 * 
	 * @param mixed $key
	 * @param integer $expire
	 * @param string $type
	 * @return Model
	 */
	public function cache($key=true,$expire=null,$type=''){
		// 增加快捷调用方式 cache(10) 等同于 cache(true, 10)
		if(is_numeric($key) && is_null($expire)){
			$expire=$key;
			$key=true;
		}
		if(false !== $key)
			$this->options['cache']=array(
					'key' => $key,
					'expire' => $expire,
					'type' => $type
			);
		return $this;
	}

	/**
	 * 指定查询字段 支持字段排除
	 * 
	 * @param mixed $field
	 * @param boolean $except 是否排除
	 * @return Model
	 */
	public function field($field,$except=false){
		if(true === $field){ // 获取全部字段
			$fields=$this->getDbFields();
			$field=$fields ? $fields : '*';
		}elseif($except){ // 字段排除
			if(is_string($field)){
				$field=explode(',', $field);
			}
			$fields=$this->getDbFields();
			$field=$fields ? array_diff($fields, (array)$field) : $field;
		}
		$this->options['field']=$field;
		return $this;
	}

	/**
	 * 调用命名范围
	 * 
	 * @param mixed $scope 命名范围名称 支持多个 和直接定义
	 * @param array $args 参数
	 * @return Model
	 */
	public function scope($scope='',$args=NULL){
		if('' === $scope){
			if(isset($this->_scope['default'])){
				// 默认的命名范围
				$options=$this->_scope['default'];
			}else{
				return $this;
			}
		}elseif(is_string($scope)){ // 支持多个命名范围调用 用逗号分割
			$scopes=explode(',', $scope);
			$options=[];
			foreach($scopes as $name){
				if(!isset($this->_scope[$name]))
					continue;
				$options=array_merge($options, $this->_scope[$name]);
			}
			if(!empty($args) && is_array($args)){
				$options=array_merge($options, $args);
			}
		}elseif(is_array($scope)){ // 直接传入命名范围定义
			$options=$scope;
		}
		
		if(is_array($options) && !empty($options)){
			$this->options=array_merge($this->options, array_change_key_case((array)$options));
		}
		return $this;
	}

	/**
	 * 指定查询条件 支持安全过滤
	 * 
	 * @param mixed $where 条件表达式
	 * @param mixed $parse 预处理参数
	 * @return Model
	 */
	public function where($where,$parse=null){
        if(is_numeric($where)){
            $pk=$this->getPk();
            $val=$where;
            $where=[];
            $where[$pk]=$val;
        }elseif(!is_null($parse) && is_string($where)){
			if(!is_array($parse)){
				$parse=func_get_args();
				array_shift($parse);
			}
			$parse=array_map(array(
					$this->db,
					'escapeString'
			), (array)$parse);
			$where=vsprintf($where, $parse);
		}elseif(is_object($where)){
			$where=get_object_vars($where);
		}
		if(is_string($where) && '' != $where){
			$map=[];
			$map['_string']=$where;
			$where=$map;
		}
		if(isset($this->options['where'])){
			$this->options['where']=array_merge($this->options['where'], $where);
		}else{
			$this->options['where']=$where;
		}
		
		return $this;
	}

	/**
	 * 指定查询数量
	 * 
	 * @param mixed $offset 起始位置
	 * @param mixed $length 查询数量
	 * @return Model
	 */
	public function limit($offset,$length=null){
		if(is_null($length) && strpos($offset, ',')){
			list($offset, $length)=explode(',', $offset);
		}
		$this->options['limit']=intval($offset) . ($length ? ',' . intval($length) : '');
		return $this;
	}

	/**
	 * 指定分页
	 * 
	 * @param mixed $page 页数
	 * @param mixed $listRows 每页数量
	 * @return Model
	 */
	public function page($page,$listRows=null){
		if(is_null($listRows) && strpos($page, ',')){
			list($page, $listRows)=explode(',', $page);
		}
		$this->options['page']=array(
				intval($page),
				intval($listRows)
		);
		return $this;
	}

	/**
	 * 查询注释
	 * 
	 * @param string $comment 注释
	 * @return Model
	 */
	public function comment($comment){
		$this->options['comment']=$comment;
		return $this;
	}

	/**
	 * 获取执行的SQL语句
	 * 
	 * @param boolean $fetch 是否返回sql
	 * @return Model
	 */
	public function fetchSql($fetch=true){
		$this->options['fetch_sql']=$fetch;
		return $this;
	}

	/**
	 * 参数绑定
	 * 
	 * @param string $key 参数名
	 * @param mixed $value 绑定的变量及绑定参数
	 * @return Model
	 */
	public function bind($key,$value=false){
		if(is_array($key)){
			$this->options['bind']=$key;
		}else{
			$num=func_num_args();
			if($num > 2){
				$params=func_get_args();
				array_shift($params);
				$this->options['bind'][$key]=$params;
			}else{
				$this->options['bind'][$key]=$value;
			}
		}
		return $this;
	}

	/**
	 * 设置模型的属性值
	 * 
	 * @param string $name 名称
	 * @param mixed $value 值
	 * @return Model
	 */

	public function setProperty($name,$value){
		if(property_exists($this, $name))
			$this->$name=$value;
		return $this;
	}
	
	///////////////////////////////////////////////
	/////////////////数组访问接口的实现///////////////
	public function offsetExists ($offset) {
		return isset($this->data[$offset]);
	}
	public function offsetGet ($offset) {
		return $this->data[$offset];
	}
	public function offsetSet ($offset, $value) {
		$this->data[$offset]=$value;
	}
	public function offsetUnset ($offset) {
		unset($this->data[$offset]);
	}
    
}
