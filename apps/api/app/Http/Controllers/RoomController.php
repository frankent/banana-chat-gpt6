<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\{Room,RoomMember,WorkspaceMember,Workspace};
use App\Domain\Room\RoomAccess;
use App\Domain\Message\MessageWriter;
use App\Support\{ApiError,Audit,Resources,Settings};
use App\Events\ChatEvent;
class RoomController extends Controller {
 public function __construct(private RoomAccess $access,private MessageWriter $writer){}
 private function validMembers(array $ids):void{
    $count=WorkspaceMember::where('workspace_id',request()->header('X-Workspace-Id'))->where('status','active')->whereIn('user_id',$ids)->whereHas('user',fn($q)=>$q->where('status','active'))->count();if($count!==count($ids))throw new ApiError('NOT_FOUND',404);
 }
 public function store(Request $r){
    $v=$r->validate(['type'=>'required|in:dm,group','user_id'=>'required_if:type,dm|ulid','name'=>'required_if:type,group|string|max:100','description'=>'nullable|string|max:500','member_ids'=>'sometimes|array|max:500','member_ids.*'=>'ulid']);
    $uid=$r->user()->id;$ids=array_values(array_unique(array_merge([$uid],$v['type']==='dm'?[$v['user_id']]:($v['member_ids']??[]))));
    if($v['type']==='dm' && $uid===$v['user_id'])throw new ApiError('ROOM_DM_SELF');$this->validMembers($ids);
    if(count($ids)>Settings::get('room.group.max_members'))throw new ApiError('ROOM_FULL');
    [$room,$created]=DB::transaction(function()use($r,$v,$uid,$ids){
        // Serializes DM creation across both participants and enforces composite uniqueness.
        Workspace::whereKey($r->header('X-Workspace-Id'))->lockForUpdate()->firstOrFail();
        $sorted=$ids;sort($sorted);$key=$v['type']==='dm'?hash('sha256',implode(':',$sorted)):null;
        if($key && ($old=Room::where('dm_key',$key)->first()))return [$old,false];
        $room=Room::create(['workspace_id'=>$r->header('X-Workspace-Id'),'type'=>$v['type'],'name'=>$v['type']==='group'?trim($v['name']):null,'description'=>$v['description']??null,'dm_key'=>$key,'created_by'=>$uid,'owner_id'=>$key?null:$uid,'member_count'=>count($ids),'settings'=>['who_can_add_members'=>'everyone','who_can_edit_info'=>'everyone'],'last_message_at'=>now()]);
        foreach($ids as $id)RoomMember::create(['workspace_id'=>$room->workspace_id,'room_id'=>$room->id,'user_id'=>$id,'role'=>!$key&&$id===$uid?'owner':'member','joined_at'=>now(),'added_by'=>$uid]);
        if(!$key)$this->writer->system($room,'member_added',['target_ids'=>$ids]);
        foreach($ids as $id)ChatEvent::dispatch('user.'.$id,'room.created',['room_summary'=>Resources::room($room,$id)],$room->workspace_id);
        Audit::write('room.created',$room->id);return [$room,true];
    },3);
    return response()->json(['data'=>['room'=>Resources::room($room)]],$created?201:200);
 }
 public function index(Request $r){
    $r->validate(['filter'=>'sometimes|in:all,unread,hidden','limit'=>'sometimes|integer|min:1|max:100']);$uid=$r->user()->id;$filter=$r->input('filter','all');
    $q=Room::whereHas('members',function($q)use($uid,$filter){$q->where('user_id',$uid);$filter==='hidden'?$q->whereNotNull('hidden_at'):$q->whereNull('hidden_at');});
    if($filter==='unread')$q->whereHas('messages',fn($m)=>$m->where('type','!=','system')->where('sender_id','!=',$uid)->whereRaw('messages.seq > (SELECT last_read_seq FROM room_members WHERE room_members.room_id=rooms.id AND room_members.user_id=?)',[$uid]));
    // Deterministic keyset ordering. Personal pins are ordered within loaded rooms by clients.
    $p=$q->orderByDesc('last_message_at')->orderByDesc('id')->cursorPaginate($r->integer('limit',50));return Resources::page($p,fn($x)=>Resources::room($x));
 }
 public function show(string $id){$room=$this->access->find($id);return response()->json(['data'=>['room'=>Resources::room($room),'my_membership'=>$this->access->membership($room)]]);}
 public function update(Request $r,string $id){
    $v=$r->validate(['name'=>'sometimes|required|string|max:100','description'=>'nullable|string|max:500','settings'=>'sometimes|array:who_can_add_members,who_can_edit_info','settings.who_can_add_members'=>'in:everyone,admins','settings.who_can_edit_info'=>'in:everyone,admins']);
    $room=DB::transaction(function()use($id,$v){$room=$this->access->find($id,true,true);$this->access->allow($room,'edit');if(isset($v['settings']))$this->access->allow($room,'settings');$old=$room->name;$room->update($v);if(isset($v['name'])&&$old!==$v['name'])$this->writer->system($room,'room_renamed',['name'=>$v['name']]);ChatEvent::dispatch('room.'.$id,'room.updated',['room'=>Resources::room($room)],$room->workspace_id);return $room;});return response()->json(['data'=>['room'=>Resources::room($room)]]);
 }
 public function destroy(string $id){DB::transaction(function()use($id){$room=$this->access->find($id,true,true);$this->access->allow($room,'delete');$this->deleteRoom($room);});return response()->noContent();}
 private function deleteRoom(Room $room):void{$room->update(['purge_after'=>now()->addDays(Settings::get('room.deleted_purge_days'))]);foreach($room->members()->get() as $m)ChatEvent::dispatch('user.'.$m->user_id,'room.deleted',['room_id'=>$room->id],$room->workspace_id);ChatEvent::dispatch('room.'.$room->id,'room.deleted',['room_id'=>$room->id],$room->workspace_id);$room->delete();Audit::write('room.deleted',$room->id);}
 public function members(Request $r,string $id){$room=$this->access->find($id);return Resources::page($room->members()->with('user')->orderBy('id')->cursorPaginate(100),fn($m)=>Resources::user($m->user)+['role'=>$m->role,'joined_at'=>$m->joined_at,'last_read_seq'=>$m->last_read_seq]);}
 public function addMembers(Request $r,string $id){
    $r->validate(['user_ids'=>'required|array|min:1|max:500','user_ids.*'=>'ulid']);$ids=array_values(array_unique($r->input('user_ids')));$this->validMembers($ids);
    $result=DB::transaction(function()use($id,$ids,$r){$room=$this->access->find($id,true,true);$this->access->allow($room,'add');$already=$room->members()->whereIn('user_id',$ids)->pluck('user_id')->all();$added=array_values(array_diff($ids,$already));if($room->member_count+count($added)>Settings::get('room.group.max_members'))throw new ApiError('ROOM_FULL');
        foreach($added as $uid)RoomMember::updateOrCreate(['room_id'=>$id,'user_id'=>$uid],['workspace_id'=>$room->workspace_id,'role'=>'member','left_at'=>null,'joined_at'=>now(),'last_read_seq'=>$room->last_seq,'added_by'=>$r->user()->id]);
        if($added){$room->update(['member_count'=>$room->member_count+count($added)]);$this->writer->system($room,'member_added',['target_ids'=>$added]);ChatEvent::dispatch('room.'.$id,'room.member_added',['room_id'=>$id,'members'=>$added,'actor_id'=>$r->user()->id],$room->workspace_id);foreach($added as $uid)ChatEvent::dispatch('user.'.$uid,'room.created',['room_summary'=>Resources::room($room,$uid)],$room->workspace_id);}return ['added'=>$added,'already'=>$already];});return response()->json(['data'=>$result]);
 }
 public function removeMember(Request $r,string $id,string $user){return $this->remove($r,$id,$user,false);}
 public function leave(Request $r,string $id){return $this->remove($r,$id,$r->user()->id,true);}
 private function remove(Request $r,string $id,string $uid,bool $leave){
    DB::transaction(function()use($r,$id,$uid,$leave){$room=$this->access->find($id,true,true);$this->access->group($room);$mine=$this->access->membership($room);$target=$room->members()->where('user_id',$uid)->firstOrFail();
        if($leave && $target->role==='owner' && $room->member_count>1)throw new ApiError('ROOM_OWNER_CANNOT_LEAVE');
        if(!$leave && (!$this->access->manager($room)||$target->role==='owner'||($target->role==='admin'&&$mine->role!=='owner')))throw new ApiError('ROOM_FORBIDDEN',403);
        $target->update(['left_at'=>now()]);$room->update(['member_count'=>$room->member_count-1]);$this->writer->system($room,$leave?'member_left':'member_removed',['target_ids'=>[$uid]]);
        foreach(['room.'.$id,'user.'.$uid] as $channel)ChatEvent::dispatch($channel,'room.member_removed',['room_id'=>$id,'user_id'=>$uid,'actor_id'=>$r->user()->id,'reason'=>$leave?'left':'removed'],$room->workspace_id);
        if($room->member_count===0)$this->deleteRoom($room);
    });return response()->noContent();
 }
 public function role(Request $r,string $id,string $user){
    $r->validate(['role'=>'required|in:owner,admin,member']);
    $target=DB::transaction(function()use($r,$id,$user){$room=$this->access->find($id,true,true);$this->access->group($room);$mine=$this->access->membership($room);$target=$room->members()->where('user_id',$user)->firstOrFail();$role=$r->input('role');
        if(!$this->access->manager($room)||$target->role==='owner'||($role==='owner'&&$mine->role!=='owner')||($target->role==='admin'&&$role==='member'&&$mine->role!=='owner'))throw new ApiError('ROOM_FORBIDDEN',403);
        if($role==='owner'){$mine->update(['role'=>'admin']);$room->update(['owner_id'=>$user]);$this->writer->system($room,'owner_transferred',['target_ids'=>[$user]]);}
        $target->update(['role'=>$role]);ChatEvent::dispatch('room.'.$id,'room.member_role_changed',['room_id'=>$id,'user_id'=>$user,'role'=>$role],$room->workspace_id);return $target;});return response()->json(['data'=>['room_member'=>$target]]);
 }
 public function personal(Request $r,string $id,string $action){
    DB::transaction(function()use($r,$id,$action){$room=$this->access->find($id,false,true);$m=$this->access->membership($room);if($action==='pin'&&!$m->pinned_at && RoomMember::where('user_id',$r->user()->id)->whereNull('left_at')->whereNotNull('pinned_at')->count()>=10)throw new ApiError('ROOM_PIN_LIMIT');$m->update([in_array($action,['pin','unpin'])?'pinned_at':'hidden_at'=>in_array($action,['pin','hide'])?now():null]);});return response()->noContent();
 }
}
