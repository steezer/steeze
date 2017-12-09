<?php

$str='a-121d-s33';
$pattern='a-{a}-{b}';

//$pattern='as{a|s}{b}';

$offset=0;
$keys=[];
$slices=[];
$startPos=$endPos=0;
$results=[];

while (true){
	if(($pos=strpos($pattern,'{',$offset))!==false){
		
		$startPos=$pos+1;
		$slice=substr($pattern,$offset,$pos-$offset);
		
		if(!$offset){
			//首次匹配
			if(!empty($slice) && strpos($str,$slice,$startPos)!==0){
				break;
			}else{
				$startPos=strlen($slice);
			}
			
		}else{
			if(empty($slice)){
				$results[$key]=substr($str,$startPos);
			}else{
				if(($endPos=strpos($str,$slice,$startPos))!==false){
					$results[$key]=substr($str,$startPos,$startPos-$endPos);
					$startPos=$endPos+strlen($slice);
				}else{
					break;
				}
			}
		}

		if(($pos=strpos($pattern,'}',$pos+1))!==false){
			$offset=$pos+1;
			$key=substr($pattern,$startPos,$pos-$startPos);
		}else{
			break;
		}
		
	}else{
		break;
	}
}

exit(var_dump($results));


while(($pos=strpos($pattern,'{',$offset))!==false){
	$startPos=$pos+1;
	$slice=substr($pattern,$offset,$pos-$offset);
	
	if(!$offset){
		if(!empty($slice) && strpos($str,$slice,$startPos)!==0){
			break;
		}else{
			$startPos=strlen($slice);
		}
	}else{
		if(empty($slice)){
			$results[$key]=substr($str,$startPos);
		}else{
			if(($endPos=strpos($str,$slice,$startPos))!==false){
				$results[$key]=substr($str,$startPos,$startPos-$endPos);
				$startPos=$endPos+strlen($slice);
			}else{
				break;
			}
		}
	}

	if(($pos=strpos($pattern,'}',$pos+1))!==false){
		$offset=$pos+1;
		$key=substr($pattern,$startPos,$pos-$startPos);
	}else{
		break;
	}
}

$slices[]=substr($pattern,$offset); 

var_dump($keys,$slices);

//as-121d-s33
//['as-','-','']

$startPos=$endPos=0;
foreach($slices as $index=> $slice){
	if(!$index){
		if(!empty($slice) && strpos($str,$slice,$startPos)!==0){
			break;
		}else{
			$startPos=strlen($slice);
		}
	}else{
		if(empty($slice)){
			$value=substr($str,$startPos);
			var_dump($value);
		}else{
			if(($endPos=strpos($str,$slice,$startPos))!==false){
				$value=substr($str,$startPos,$startPos-$endPos);
				var_dump($value);
				$startPos=$endPos+strlen($slice);
			}else{
				break;
			}
		}
	}
}


exit(0);



//替换匹配模式
preg_match_all('/\{(\w+)(?:\|[ds])?\}/', $pattern,$params);
exit(var_dump($params));
$params=$params[1];

$pattern='/^'.$pattern.'$/i';
$pattern=preg_replace('/\{\w+\}/i', '(\w+)', $pattern);

if(preg_match($pattern, $str,$matches)){
	array_shift($matches);
	var_dump(array_combine($params,$matches));
}

// $res=preg_split('/\{\w+\}/', $pattern);
// var_dump($res);
