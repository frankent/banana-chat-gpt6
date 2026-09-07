<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Domain\Workspace\WorkspaceContext;
class ApiContext {
    public function handle(Request $r,Closure $next){
        $r->attributes->set('request_id',(string)Str::uuid());
        app(WorkspaceContext::class)->id=null;
        try{$response=$next($r);$response->headers->set('X-Request-Id',$r->attributes->get('request_id'));return $response;}
        finally{app(WorkspaceContext::class)->id=null;}
    }
}
