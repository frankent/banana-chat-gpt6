<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\{Str,Facades\DB};
use App\Models\{Room,Message,Attachment};
use App\Domain\{Room\RoomAccess,Message\MessageWriter};
use App\Support\{ApiError,Resources,Settings,Audit};
use App\Events\ChatEvent;
class MessageController extends Controller {
 public function __construct(private RoomAccess $access,private MessageWriter $writer){}
 private function body(?string $body,bool $hasAttachments=false):?string{$body=trim($body??'');if($body===''&&!$hasAttachments)throw new ApiError('MSG_EMPTY');if(mb_strlen($body)>Settings::get('message.max_length'))throw new ApiError('MSG_TOO_LONG');return $body===''?null:$body;}
 public function store(Request $r,string $id){
    $v=$r->validate(['client_message_id'=>'required|uuid','body'=>'nullable|string','reply_to_message_id'=>'nullable|ulid','attachment_ids'=>'sometimes|array|max:10','attachment_ids.*'=>'ulid|distinct']);$body=$this->body($v['body']??null,!empty($v['attachment_ids']));
    [$m,$created]=DB::transaction(function()use($r,$id,$v,$body){$room=$this->access->find($id,true,true);
        $old=$room->messages()->where('sender_id',$r->user()->id)->where('client_message_id',$v['client_message_id'])->first();if($old)return [$old,false];
        if($room->type==='dm' && $room->members()->whereHas('user',fn($q)=>$q->where('status','active'))->count()<2)throw new ApiError('ROOM_FORBIDDEN',403);
        if(isset($v['reply_to_message_id'])&&!$room->messages()->whereKey($v['reply_to_message_id'])->where('type','!=','system')->exists())throw new ApiError('MSG_REPLY_INVALID');
        $ids=$v['attachment_ids']??[];$attachments=$ids?Attachment::query()->whereIn('id',$ids)->lockForUpdate()->get():collect();
        if($attachments->count()!==count($ids)||$attachments->contains(fn($a)=>$a->uploader_id!==$r->user()->id||$a->status!=='ready'||$a->messages()->exists()))throw new ApiError('MSG_ATTACHMENT_INVALID');
        $ordered=collect($ids)->map(fn($aid)=>$attachments->firstWhere('id',$aid))->all();
        return [$this->writer->append($room,$r->user()->id,$body,$v['client_message_id'],null,$v['reply_to_message_id']??null,$ordered),true];},3);
    return response()->json(['data'=>['message'=>Resources::message($m)]],$created?201:200);
 }
 public function index(Request $r,string $id){
    $r->validate(['before_seq'=>'sometimes|integer|min:1','after_seq'=>'sometimes|integer|min:0','around_seq'=>'sometimes|integer|min:1','limit'=>'sometimes|integer|min:1']);
    $room=$this->access->find($id);$q=$room->messages()->with(['sender','replyTo.sender']);$limit=min(100,$r->integer('limit',50));
    if($r->has('before_seq'))$q->where('seq','<',$r->integer('before_seq'));
    if($r->has('after_seq'))$q->where('seq','>',$r->integer('after_seq'));
    if($r->has('around_seq'))$q->whereBetween('seq',[max(1,$r->integer('around_seq')-(int)floor($limit/2)),$r->integer('around_seq')+(int)ceil($limit/2)]);
    $data=($r->has('after_seq')||$r->has('around_seq')?$q->orderBy('seq'):$q->orderByDesc('seq'))->limit($limit)->get()->sortBy('seq')->values();
    return response()->json(['data'=>$data->map(fn($m)=>Resources::message($m)),'meta'=>['has_more_before'=>$data->isNotEmpty()&&$room->messages()->where('seq','<',$data->first()->seq)->exists(),'has_more_after'=>$data->isNotEmpty()&&$room->last_seq>$data->last()->seq]]);
 }
 public function update(Request $r,string $id){
    $r->validate(['body'=>'required|string']);$body=$this->body($r->input('body'));
    $m=DB::transaction(function()use($r,$id,$body){$m=Message::findOrFail($id);$room=$this->access->find($m->room_id,true,true);$m->refresh();
        if($m->sender_id!==$r->user()->id)throw new ApiError('ROOM_FORBIDDEN',403);
        if($m->type==='system'||$m->deleted_at)throw new ApiError('MSG_NOT_EDITABLE');
        $window=Settings::get('message.edit_window_minutes');if($window>0&&$m->created_at->lt(now()->subMinutes($window)))throw new ApiError('MSG_EDIT_WINDOW_EXPIRED');
        DB::table('message_edits')->insert(['id'=>(string)Str::ulid(),'workspace_id'=>$m->workspace_id,'message_id'=>$m->id,'previous_body'=>$m->body,'edited_by'=>$r->user()->id,'edited_at'=>now()]);
        $m->update(['body'=>$body,'edited_at'=>now(),'edit_count'=>$m->edit_count+1]);$this->writer->syncMentions($m);$room->touch();ChatEvent::dispatch('room.'.$room->id,'message.updated',['message'=>Resources::message($m)],$m->workspace_id);return $m;});return response()->json(['data'=>['message'=>Resources::message($m)]]);
 }
 public function destroy(Request $r,string $id){
    DB::transaction(function()use($r,$id){$m=Message::findOrFail($id);$room=$this->access->find($m->room_id,true,true);$m->refresh();$own=$m->sender_id===$r->user()->id;$wsRole=$r->attributes->get('workspace_membership')->role;
        if(!$own&&!($room->type==='group'&&$this->access->manager($room))&&!in_array($wsRole,['owner','admin']))throw new ApiError('ROOM_FORBIDDEN',403);
        if($m->type==='system')throw new ApiError('MSG_NOT_EDITABLE');if($m->deleted_at)return;
        $m->update(['body'=>null,'deleted_at'=>now(),'deleted_by'=>$r->user()->id,'delete_reason'=>$own?'sender':'moderator']);$room->touch();
        ChatEvent::dispatch('room.'.$room->id,'message.deleted',['message_id'=>$id,'room_id'=>$room->id,'seq'=>$m->seq,'delete_reason'=>$m->delete_reason],$m->workspace_id);if(!$own)Audit::write('message.deleted_by_moderator',$id);
    });return response()->noContent();
 }
 public function read(Request $r,string $id){
    $r->validate(['seq'=>'required|integer|min:0']);$result=DB::transaction(function()use($r,$id){$room=$this->access->find($id,false,true);$m=$this->access->membership($room);$m->update(['last_read_seq'=>max($m->last_read_seq,min($r->integer('seq'),$room->last_seq)),'last_read_at'=>now()]);ChatEvent::dispatch('room.'.$id,'room.read',['room_id'=>$id,'user_id'=>$r->user()->id,'last_read_seq'=>$m->last_read_seq],$room->workspace_id);ChatEvent::dispatch('user.'.$r->user()->id,'workspace.unread_changed',['workspace_id'=>$room->workspace_id],$room->workspace_id);return ['last_read_seq'=>$m->last_read_seq,'unread_count'=>Resources::unread($room,$m)];});return response()->json(['data'=>$result]);
 }
 public function readStatus(Request $r,string $id){$r->validate(['seq'=>'required|integer|min:1']);$room=$this->access->find($id);$members=$room->members()->with('user')->where('last_read_seq','>=',$r->integer('seq'))->get()->map(fn($m)=>['user_id'=>$m->user_id,'display_name'=>$m->user->display_name,'read_at'=>$m->last_read_at]);return response()->json(['data'=>['read_by'=>$members,'count'=>$members->count()]]);}
 public function mentions(Request $r){
    $workspaceId=$r->attributes->get('workspace')->id;$limit=min(100,$r->integer('limit',30));$roomIds=DB::table('room_members')->where('user_id',$r->user()->id)->where('workspace_id',$workspaceId)->whereNull('left_at')->pluck('room_id');$messageIds=DB::table('message_mentions')->where('user_id',$r->user()->id)->where('workspace_id',$workspaceId)->pluck('message_id');$rows=Message::with(['sender','replyTo.sender','attachments'])->whereIn('id',$messageIds)->whereIn('room_id',$roomIds)->whereNull('deleted_at')->orderByDesc('created_at')->limit($limit)->get();
    return response()->json(['data'=>$rows->map(fn($m)=>Resources::message($m))]);
 }
}
