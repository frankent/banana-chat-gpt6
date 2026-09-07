<?php
namespace App\Domain\Auth;
use App\Models\{User,ChatSession,Device};
use App\Support\{Settings,Audit};
use App\Events\ChatEvent;
use Illuminate\Support\{Str,Facades\DB};
class Sessions {
 public function issue(ChatSession $s):array {
    $access=Str::random(64);$refresh=Str::random(96);$minutes=Settings::get('auth.access_token_ttl_minutes');
    $s->update(['refresh_token_hash'=>hash('sha256',$refresh),'expires_at'=>now()->addDays(Settings::get('auth.refresh_token_ttl_days')),'last_used_at'=>now()]);
    DB::table('personal_access_tokens')->insert(['id'=>(string)Str::ulid(),'session_id'=>$s->id,'token'=>hash('sha256',$access),'expires_at'=>now()->addMinutes($minutes),'created_at'=>now(),'updated_at'=>now()]);
    return ['access_token'=>$access,'expires_in'=>$minutes*60,'refresh_token'=>$refresh,'session_id'=>$s->id,'device_id'=>$s->device_id];
 }
 public function create(User $u,array $device):array {
    $d=Device::create(['user_id'=>$u->id,'platform'=>$device['platform'],'device_name'=>$device['name'],'app_version'=>$device['app_version'],'locale'=>$u->locale,'last_active_at'=>now()]);
    $s=ChatSession::create(['user_id'=>$u->id,'device_id'=>$d->id,'refresh_token_hash'=>hash('sha256',Str::random(64)),'ip'=>request()->ip()??'127.0.0.1','user_agent'=>request()->userAgent()??'','last_used_at'=>now(),'expires_at'=>now()->addDays(30)]);
    $max=Settings::get('auth.max_sessions_per_user');
    $old=ChatSession::where('user_id',$u->id)->whereNull('revoked_at')->orderByDesc('last_used_at')->orderByDesc('id')->get()->slice($max);
    foreach($old as $session)$this->revoke($session,'expired');
    return $this->issue($s);
 }
 public function revoke(ChatSession $s,string $reason):void {
    if($s->revoked_at)return;
    $s->update(['revoked_at'=>now(),'revoked_reason'=>$reason]);
    DB::table('personal_access_tokens')->where('session_id',$s->id)->delete();
    Device::where('id',$s->device_id)->update(['push_token'=>null]);
    ChatEvent::dispatch('user.'.$s->user_id,'session.revoked',['session_id'=>$s->id,'reason'=>$reason]);
 }
}
