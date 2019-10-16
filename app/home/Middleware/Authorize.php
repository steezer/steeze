<?php
namespace App\Home\Middleware;

class Authorize{
	public function handle($next, $request, $response){
        fastlog('Authorize start');
        $result=call_user_func($next, $request, $response);
        fastlog('Authorize end');
        return $result;
	}
}
