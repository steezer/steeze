<?php

$str='a-121d-s33';
$pattern='a-{a}-{b}';

//$pattern='as{a|s}{b}';

$offset=0;
$keys=[];
$values=[];
$slices=[];
$startPos=$endPos=0;
$results=[];

while(true){
	if(($pos=strpos($pattern,'{',$offset))!==false){
		$startPos=$pos+1;
		$slices[]=substr($pattern,$offset,$pos-$offset);
		
		if(($pos=strpos($pattern,'}',$pos+1))!==false){
			$offset=$pos+1;
			$keys[]=substr($pattern,$startPos,$pos-$startPos);
		}else{
			break;
		}
	}else{
		$slices[]=substr($pattern,$offset); 
		break;
	}
}

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
			$values[]=substr($str,$startPos);
		}else{
			if(($endPos=strpos($str,$slice,$startPos))!==false){
				$values[]=substr($str,$startPos,$startPos-$endPos);
				$startPos=$endPos+strlen($slice);
			}else{
				break;
			}
		}
	}
}

count($keys) == count($values) &&  var_dump(array_combine($keys, $values));

exit(0);
