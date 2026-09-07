<?php
namespace App\Domain\Message;
use App\Models\{Room,Message,RoomMember,Attachment};
use App\Events\ChatEvent;
use App\Support\Resources;
use Illuminate\Support\Facades\DB;
class MessageWriter {
 // Caller holds the room row lock in a transaction. FR-MSG-001/007.
 public function append(Room $room,?string $sender,?string $body,?string $clientId=null,?array $system=null,?string $reply=null,array $attachments=[]):Message {
    $seq=$room->last_seq+1;
    $kinds=collect($attachments)->pluck('kind')->unique();$type=$system?'system':($kinds->isEmpty()?'text':($kinds->every(fn($k)=>in_array($k,['image','avatar']))?'image':($kinds->count()===1&&$kinds->first()==='video'?'video':'file')));
    $m=Message::create(['workspace_id'=>$room->workspace_id,'room_id'=>$room->id,'sender_id'=>$sender,'seq'=>$seq,'type'=>$type,'body'=>$body,'client_message_id'=>$clientId,'system_event'=>$system,'reply_to_message_id'=>$reply]);
    if($attachments)$m->attachments()->attach(collect($attachments)->mapWithKeys(fn(Attachment $a,$i)=>[$a->id=>['position'=>$i]])->all());
    if($sender&&$body)$this->syncMentions($m);
    $m->load(['sender','replyTo.sender','attachments']);
    $room->update(['last_seq'=>$seq,'last_user_seq'=>$system?$room->last_user_seq:$seq,'last_message_id'=>$m->id,'last_message_at'=>now()]);
    if($sender){$room->members()->where('user_id',$sender)->update(['last_read_seq'=>$seq,'last_read_at'=>now()]);$room->members()->update(['hidden_at'=>null]);}
    ChatEvent::dispatch('room.'.$room->id,'message.created',['message'=>Resources::message($m)],$room->workspace_id);
    $preview=$body??($type==='image'?'📷 Image':($type==='video'?'🎬 Video':'📎 '.($attachments[0]->original_name??'File')));
    foreach($room->members()->get() as $member){ChatEvent::dispatch('user.'.$member->user_id,'room.activity',['room_id'=>$room->id,'last_seq'=>$seq,'last_message_preview'=>$preview,'unread_count'=>Resources::unread($room,$member)],$room->workspace_id);if($sender&&$member->user_id!==$sender)\App\Jobs\SendMessageNotification::dispatch($m->id,$member->user_id);}
    return $m;
 }
 public function syncMentions(Message $message):array {
    DB::table('message_mentions')->where('message_id',$message->id)->delete();
    if(!$message->body)return [];
    preg_match_all('/(?<![\pL\pN_.-])@([a-z0-9._-]{3,32})/iu',$message->body,$matches);
    $userIds=DB::table('users')->join('workspace_members','users.id','=','workspace_members.user_id')->where('workspace_members.workspace_id',$message->workspace_id)->where('workspace_members.status','active')->whereIn(DB::raw('lower(users.username)'),array_map('strtolower',array_unique($matches[1]??[])))->pluck('users.id')->all();
    foreach($userIds as $userId){
        DB::table('message_mentions')->insertOrIgnore(['message_id'=>$message->id,'user_id'=>$userId,'workspace_id'=>$message->workspace_id]);
        if($userId!==$message->sender_id) DB::table('notifications')->insert(['id'=>(string)\Illuminate\Support\Str::ulid(),'user_id'=>$userId,'workspace_id'=>$message->workspace_id,'type'=>'mention','data'=>json_encode(['room_id'=>$message->room_id,'message_id'=>$message->id,'seq'=>$message->seq]),'created_at'=>now(),'updated_at'=>now()]);
    }
    return $userIds;
 }
 public function system(Room $r,string $kind,array $extra=[]):Message{return $this->append($r,null,null,null,['kind'=>$kind,'actor_id'=>request()->user()?->id]+$extra);}
}
