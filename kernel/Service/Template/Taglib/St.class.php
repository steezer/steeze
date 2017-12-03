<?php
namespace Service\Template\Taglib;

class St{
	public static $dataTag='__'; // 获取数据变量名称
	
	public function parseJson($attrs,$html=''){
		$str='unset($' . self::$dataTag . ');';
		$return=str_replace('$', '', (isset($attrs['return']) && trim($attrs['return']) ? trim($attrs['return']) : 'data'));
		if(isset($attrs['url']) && !empty($attrs['url'])){
			$str.='$json = get_remote_file(\'' . $attrs['url'] . '\');';
			$str.='$' . $return . ' = json_decode($json, true);';
		}
		self::$dataTag=$return;
		return '<?php ' . $str . ' ?>'.$html;
	}
	
	public function parseGet($attrs,$html=''){
		isset($attrs['where']) && ($attrs['where']=$this->operator($attrs['where']));
		$str='unset($' . self::$dataTag . ');';
		$num=isset($attrs['num']) && intval($attrs['num']) ? intval($attrs['num']) : 20;
		$return=str_replace('$', '', (isset($attrs['return']) && trim($attrs['return']) ? trim($attrs['return']) : 'data'));
		
		$qtype=isset($attrs['sql']) ? 1 : (isset($attrs['table']) ? -1 : 0);
		$cache=isset($attrs['cache']) && intval($attrs['cache']) ? intval($attrs['cache']) : 'false';
		if($qtype){
			// 设置是否解析查询中变量，默认解析
			$isParse=isset($attrs['parse']) ? intval($attrs['parse']) : 1;
			// 根据是否解析字符串变量从而过滤字符
			$modifyArr=$qtype < 0 ? array('table','dbsource','field','where','order','group') : array('sql','dbsource');
			foreach($modifyArr as $para){
				if(isset($attrs[$para])){
					$tmpstr=($isParse ? str_replace('"', '\\"', $attrs[$para]) : str_replace('\'', '\\\'', $attrs[$para]));
					$attrs[$para]=($isParse ? '"' . $tmpstr . '"' : '\'' . $tmpstr . '\'');
				}else{
					$attrs[$para]=($para == 'field' ? '\'*\'' : '');
				}
			}
			unset($modifyArr);
			$limit=isset($attrs['limit']) ?
			trim(preg_replace('/[^\d,]/i', '', $attrs['limit']), ',') :
			(isset($attrs['start']) && intval($attrs['start']) ? (intval($attrs['start']) . ',' . $num) : $num);
			$str.='$__dbObj=M();';
			if(isset($attrs['page'])){
				$str.='$__pagesize = ' . $num . ';';
				$str.='$__pagetype = \'' . (isset($attrs['pagetype']) && !empty($attrs['pagetype']) ? $attrs['pagetype'] : 'page') . '\';';
				$str.='$__page = max(intval(isset(' . $attrs['page'] . ')?' . $attrs['page'] . ':$_GET[$__pagetype]),1);';
				$str.='$__offset = ($__page - 1) * $__pagesize;'; //
				$str.='$__setpages = ' . (isset($attrs['setpages']) && !empty($attrs['setpages']) ? $attrs['setpages'] : 8) . ';';
				$limit='$__offset,$__pagesize';
				if($qtype > 0){
					$sql=preg_replace('/^(\'|")select([^(?:from)].*?)from/i', '${1}SELECT COUNT(*) as cntnum FROM ', trim($attrs['sql']));
					$str.='$r = $__dbObj->cache('.$cache.')->query(' . $sql . ');$count = $r[0][\'cntnum\'];unset($r);';
				}else{
					$str.='$count = $__dbObj->cache('.$cache.')->table('.$attrs['table'].')'.($attrs['where'] ? '->where('.$attrs['where'].')': '').'->count();';
				}
				$str.='$pages=get_pager(array(' . (isset($attrs['pagefunc']) ? '\'pFunc\'=> \'' . addslashes(stripcslashes($attrs['pagefunc'])) . '\',' : '') . '\'total\'=> $count,\'cPage\'=> $__page,\'type\'=> $__pagetype,\'size\'=> $__pagesize),$__setpages);';
			}
			
			$qAction='';
			if($qtype > 0){
				$qAction='query(' . $this->operator($attrs['sql']) . '." limit ' . $limit . '",false);';
			}else{
				$qAction='table('.$attrs['table'].')->field('.$attrs['field'].')'
						. ($attrs['where'] ? '->where('.$attrs['where'].')' : '')
						. ($attrs['order'] ? '->order('.$attrs['order'].')' : '')
						. ($limit ? '->limit('.$limit.')' : '')
						. ($attrs['group'] ? '->group('.$attrs['group'].')' : '')
						.'->select();';
			}
			$str.='$' . $return . ' = $__dbObj->cache('.$cache.')->' . $qAction;
		}
		self::$dataTag=$return;
		return '<?php ' . $str . ' ?>'.$html;
	}
	
	
	/**
	 * 替换SQL字符串条件语句中的比较操作符
	 *
	 * @param string $str
	 * @return mixed
	 */
	private function operator($sqlstr){
		$search=array(' eq ',' neq ',' gt ',' egt ',' lt ',' elt ',' heq ',' nheq ');
		$replace=array(' == ',' != ',' > ',' >= ',' < ',' <= ',' === ',' !== ');
		return str_replace($search, $replace, $sqlstr);
	}
}
