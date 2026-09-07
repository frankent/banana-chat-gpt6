<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Hash};
use App\Models\{User,ChatSession};
use App\Domain\Auth\{Sessions,PasswordPolicy};
use App\Support\{ApiError,Audit,Settings};
class AuthController extends Controller {
 public function login(Request $r,Sessions $sessions){
    $v=$r->validate(['username'=>'required|string|max:100','password'=>'required|string|max:128','device'=>'required|array','device.platform'=>'required|in:web,ios,android','device.name'=>'required|string|max:100','device.app_version'=>'required|string|max:20']);
    $result=DB::transaction(function()use($v,$sessions){
        $u=User::where('username',strtolower(trim($v['username'])))->lockForUpdate()->first();
        if($u?->locked_until?->isFuture())return new ApiError('AUTH_LOCKED',423,['retry_after_seconds'=>max(1,(int)now()->diffInSeconds($u->locked_until))]);
        // Fixed valid Argon2 hash prevents the unknown-user path skipping password work.
        $dummy=config('chat.dummy_password_hash');
        if(!Hash::check($v['password'],$u?->password_hash??$dummy)){
            if($u){$count=$u->failed_login_at && $u->failed_login_at->gt(now()->subMinutes(15))?$u->failed_login_count+1:1;$u->update(['failed_login_count'=>$count,'failed_login_at'=>$count===1?now():$u->failed_login_at,'locked_until'=>$count>=Settings::get('auth.lockout.threshold')?now()->addMinutes(Settings::get('auth.lockout.minutes')):null]);if($u->locked_until)return new ApiError('AUTH_LOCKED',423,['retry_after_seconds'=>900]);}
            return new ApiError('AUTH_INVALID_CREDENTIALS',401);
        }
        if($u->status!=='active')return new ApiError('AUTH_ACCOUNT_DISABLED',403);
        $u->update(['failed_login_count'=>0,'locked_until'=>null,'failed_login_at'=>null]);
        if(Hash::needsRehash($u->password_hash))$u->update(['password_hash'=>Hash::make($v['password'])]);
        $tokens=$sessions->create($u,$v['device']);Audit::write('auth.login',$u->id,[],$u->id);
        return $tokens+['user'=>$u,'workspaces'=>app(WorkspaceController::class)->forUser($u),'must_change_password'=>$u->must_change_password];
    });
    if($result instanceof ApiError)throw $result;return response()->json(['data'=>$result]);
 }
 public function refresh(Request $r,Sessions $sessions){
    $r->validate(['refresh_token'=>'required|string|max:256']);$hash=hash('sha256',$r->input('refresh_token'));
    $result=DB::transaction(function()use($hash,$sessions){
        $history=DB::table('refresh_token_history')->where('token_hash',$hash)->first();
        $s=$history?ChatSession::whereKey($history->session_id)->lockForUpdate()->first():ChatSession::where('refresh_token_hash',$hash)->lockForUpdate()->first();
        // Recheck after acquiring the session lock: another refresh may have just rotated it.
        if(!$history)$history=DB::table('refresh_token_history')->where('token_hash',$hash)->first();
        if(!$s && $history)$s=ChatSession::whereKey($history->session_id)->lockForUpdate()->first();
        if($history){if($s)$sessions->revoke($s,'admin');Audit::write('auth.refresh_reuse_detected',$s?->id,[],$s?->user_id);return new ApiError('AUTH_REFRESH_REUSED',401);}
        if(!$s||$s->revoked_at||$s->expires_at->isPast())return new ApiError('AUTH_REFRESH_EXPIRED',401);
        if($s->user->status!=='active')return new ApiError('AUTH_ACCOUNT_DISABLED',403);
        DB::table('refresh_token_history')->insert(['token_hash'=>$hash,'session_id'=>$s->id,'expires_at'=>$s->expires_at]);
        DB::table('personal_access_tokens')->where('session_id',$s->id)->delete();return $sessions->issue($s);
    });
    if($result instanceof ApiError)throw $result;return response()->json(['data'=>$result]);
 }
 public function logout(Request $r,Sessions $sessions){$sessions->revoke($r->attributes->get('chat_session'),'logout');return response()->noContent();}
 public function logoutAll(Request $r,Sessions $sessions){foreach(ChatSession::where('user_id',$r->user()->id)->whereNull('revoked_at')->get() as $s)$sessions->revoke($s,'logout');return response()->noContent();}
 public function sessions(Request $r){return response()->json(['data'=>ChatSession::with('device')->where('user_id',$r->user()->id)->whereNull('revoked_at')->where('expires_at','>',now())->get()->map(fn($s)=>['id'=>$s->id,'device'=>$s->device,'ip'=>$s->ip,'last_used_at'=>$s->last_used_at,'is_current'=>$s->id===$r->attributes->get('chat_session')->id])]);}
 public function revoke(Request $r,string $id,Sessions $sessions){$s=ChatSession::where('user_id',$r->user()->id)->findOrFail($id);$sessions->revoke($s,'logout');return response()->noContent();}
 public function password(Request $r,Sessions $sessions){
    $v=$r->validate(['current_password'=>'required|string','new_password'=>'required|string|max:128']);$u=$r->user();
    if(!Hash::check($v['current_password'],$u->password_hash))throw new ApiError('AUTH_CURRENT_PASSWORD_WRONG');
    if($v['current_password']===$v['new_password'])throw new ApiError('AUTH_PASSWORD_REUSED');PasswordPolicy::validate($v['new_password'],$u->username);
    DB::transaction(function()use($u,$v,$r,$sessions){$u->update(['password_hash'=>Hash::make($v['new_password']),'must_change_password'=>false,'password_changed_at'=>now()]);foreach(ChatSession::where('user_id',$u->id)->where('id','!=',$r->attributes->get('chat_session')->id)->whereNull('revoked_at')->get() as $s)$sessions->revoke($s,'password_change');Audit::write('auth.password_changed',$u->id);});return response()->noContent();
 }
}
