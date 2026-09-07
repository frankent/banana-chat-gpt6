<?php
namespace App\Support;
use App\Models\AuditLog;
class Audit {
    public static function write(string $action,?string $target=null,array $context=[],?string $actor=null):void {
        AuditLog::create(['workspace_id'=>app(\App\Domain\Workspace\WorkspaceContext::class)->id,'actor_id'=>$actor??request()->user()?->id,'actor_type'=>auth('admin')->check()?'admin':'user','action'=>$action,'target_type'=>explode('.',$action)[0],'target_id'=>$target,'context'=>$context,'ip'=>request()->ip(),'created_at'=>now()]);
    }
}
