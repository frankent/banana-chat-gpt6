<?php
namespace App\Support;
use App\Models\{User,Message,Room,RoomMember,Attachment};
use Illuminate\Support\Facades\{DB,Storage,URL};
class Resources {
 public static function user(?User $u):?array{return $u?['id'=>$u->id,'username'=>$u->username,'display_name'=>$u->display_name,'status'=>$u->status,'avatar'=>null,'last_seen_at'=>$u->last_seen_at,'presence'=>null]:null;}
 public static function attachment(Attachment $a):array {
    $disk=Storage::disk();$expires=now()->addHour();$keys=['original'=>$a->storage_key]+($a->derived??[]);$urls=[];
    foreach($keys as $name=>$key){if(!$key)continue;$urls[$name]=config('filesystems.default')==='s3'?$disk->temporaryUrl($key,$expires):URL::temporarySignedRoute('attachments.download',$expires,['id'=>$a->id,'variant'=>$name],false);}
    return ['id'=>$a->id,'kind'=>$a->kind,'status'=>$a->status,'original_name'=>$a->original_name,'mime_type'=>$a->mime_type,'size_bytes'=>$a->size_bytes,'width'=>$a->width,'height'=>$a->height,'duration_ms'=>$a->duration_ms,'urls'=>$urls,'url_expires_at'=>$expires->toIso8601String()];
 }
 public static function message(Message $m):array {
    $reply=$m->replyTo;
    $mentions=DB::table('message_mentions')->where('message_id',$m->id)->pluck('user_id')->all();
    return ['id'=>$m->id,'workspace_id'=>$m->workspace_id,'room_id'=>$m->room_id,'seq'=>$m->seq,'type'=>$m->type,'sender'=>self::user($m->sender),'body'=>$m->body,'attachments'=>$m->deleted_at?[]:$m->attachments->map(fn($a)=>self::attachment($a))->values(), 'reply_to'=>$reply?['id'=>$reply->id,'seq'=>$reply->seq,'sender'=>self::user($reply->sender),'snippet'=>$reply->deleted_at?null:mb_substr($reply->body??'',0,100),'type'=>$reply->type]:null,'system_event'=>$m->system_event,'mentions'=>$mentions,'edited_at'=>$m->edited_at,'edit_count'=>$m->edit_count,'deleted_at'=>$m->deleted_at,'delete_reason'=>$m->delete_reason,'created_at'=>$m->created_at,'client_message_id'=>$m->client_message_id];
 }
 public static function unread(Room $room,RoomMember $m):int {
    // Exact count excludes interleaved system events; deleted user messages still count (DEC-008).
    return $room->messages()->where('seq','>',$m->last_read_seq)->where('type','!=','system')->where('sender_id','!=',$m->user_id)->count();
 }
 public static function room(Room $r,?string $uid=null):array {
    $uid??=request()->user()->id;$mine=$r->members()->where('user_id',$uid)->first();
    $other=$r->type==='dm'?$r->members()->with('user')->where('user_id','!=',$uid)->first()?->user:null;
    $notification=DB::table('room_notification_settings')->where('room_id',$r->id)->where('user_id',$uid)->first();
    $preview=$r->messages()->with('sender')->whereNull('deleted_at')->orderByDesc('seq')->first();
    return ['id'=>$r->id,'workspace_id'=>$r->workspace_id,'type'=>$r->type,'name'=>$r->name,'description'=>$r->description,'owner_id'=>$r->owner_id,'avatar'=>null,'member_count'=>$r->member_count,'other_user'=>self::user($other),'last_message'=>$preview?self::message($preview):null,'last_seq'=>$r->last_seq,'last_user_seq'=>$r->last_user_seq,'unread_count'=>$mine?self::unread($r,$mine):0,'last_read_seq'=>$mine?->last_read_seq??0,'my_role'=>$mine?->role,'notification'=>['mode'=>$notification?->mode??'all','muted_until'=>$notification?->muted_until],'hidden'=>(bool)$mine?->hidden_at,'pinned_at'=>$mine?->pinned_at,'settings'=>$r->settings??['who_can_add_members'=>'everyone','who_can_edit_info'=>'everyone'],'updated_at'=>$r->updated_at,'last_message_at'=>$r->last_message_at];
 }
 public static function page($p,callable $map){return response()->json(['data'=>collect($p->items())->map($map),'meta'=>['next_cursor'=>$p->nextCursor()?->encode(),'has_more'=>$p->hasMorePages()]]);}
}
