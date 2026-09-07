<?php
namespace App\Http\Middleware;
use App\Models\{Workspace,WorkspaceMember};
use App\Support\ApiError;
use App\Domain\Workspace\WorkspaceContext;
class SetWorkspace {
    public function handle($r,\Closure $next){
        $id=$r->header('X-Workspace-Id');if(!$id)throw new ApiError('WS_HEADER_REQUIRED',400);
        $m=WorkspaceMember::where('workspace_id',$id)->where('user_id',$r->user()->id)->where('status','active')->first();
        if(!$m)throw new ApiError('WS_FORBIDDEN',403);
        $w=Workspace::find($id);if(!$w||$w->status!=='active')throw new ApiError('WS_ARCHIVED',403);
        app(WorkspaceContext::class)->id=$id;$r->attributes->set('workspace',$w);$r->attributes->set('workspace_membership',$m);return $next($r);
    }
}
