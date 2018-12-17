<?php
namespace App\Home\Middleware;

use Library\Middleware;

class CharsetConvert extends Middleware{
    
	public function handle(\Closure $next,$request,$response){
		return $next($request,$response);
	}
}
