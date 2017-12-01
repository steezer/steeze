<?php
namespace App\Home\Middleware;

class CharsetConvert{
	public function handle(\Closure $next,$request){
		return $next($request);
	}
}