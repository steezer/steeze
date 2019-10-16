<?php
namespace App\Home\Middleware;

use Library\Middleware;

class CharsetConvert extends Middleware{
	public function handle($next, $request, $response){
        fastlog('CharsetConvert start');
		$result=call_user_func($next, $request, $response);
        fastlog('CharsetConvert start');
        return $result;
	}
}
