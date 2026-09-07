<?php
namespace App\Http\Middleware;
class FreshPassword {public function handle($r,\Closure $next){if($r->user()->must_change_password)throw new \App\Support\ApiError('AUTH_PASSWORD_CHANGE_REQUIRED',403);return $next($r);}}
