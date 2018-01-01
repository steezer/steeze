<?php
namespace App\Home\Middleware;

class CharsetConvert{
	public function handle(\Closure $next,$request,$response){
		return $next($request,$response);
	}
}
