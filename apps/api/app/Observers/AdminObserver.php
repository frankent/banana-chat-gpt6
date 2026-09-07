<?php
namespace App\Observers;
use App\Models\{User,Workspace,WorkspaceMember,ChatSession};
use App\Support\Audit;
use App\Domain\Auth\Sessions;
use App\Domain\Admin\RemoveWorkspaceMember;
class AdminObserver {
 public function created($model):void{if(auth('admin')->check())Audit::write(strtolower(class_basename($model)).'.created',$model->id,[],auth('admin')->id());}
 public function updating($model):void{
  if($model instanceof User&&$model->isDirty('username'))$model->username=strtolower(trim($model->username));
 }
 public function updated($model):void {
  if($model instanceof User){
   if(($model->wasChanged('status')&&$model->status!=='active')||($model->wasChanged('password_hash')&&auth('admin')->check())){
    foreach(ChatSession::where('user_id',$model->id)->whereNull('revoked_at')->get() as $s)app(Sessions::class)->revoke($s,'admin');
   }
   if($model->wasChanged('status')&&$model->status==='deactivated')foreach(WorkspaceMember::where('user_id',$model->id)->where('status','active')->get() as $m)app(RemoveWorkspaceMember::class)->handle($m);
  }
  if($model instanceof WorkspaceMember && $model->wasChanged('status')&&$model->status==='removed')app(RemoveWorkspaceMember::class)->handle($model);
  if(auth('admin')->check())Audit::write(strtolower(class_basename($model)).'.updated',$model->id,['fields'=>array_values(array_diff(array_keys($model->getChanges()),['password_hash','remember_token','api_key_encrypted']))],auth('admin')->id());
 }
}
