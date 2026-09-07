<?php
namespace App\Domain\Admin;
use App\Models\{WorkspaceMember,RoomMember,Room};
use App\Domain\Message\MessageWriter;
use App\Events\ChatEvent;
use App\Domain\Workspace\WorkspaceContext;
use Illuminate\Support\Facades\DB;
class RemoveWorkspaceMember {
 public function handle(WorkspaceMember $member):void {
  $context=app(WorkspaceContext::class);$previous=$context->id;$context->id=$member->workspace_id;
  try{DB::transaction(function()use($member){
   $member->updateQuietly(['status'=>'removed','removed_at'=>now()]);
   foreach(RoomMember::where('user_id',$member->user_id)->whereNull('left_at')->get() as $membership){
    $room=Room::lockForUpdate()->find($membership->room_id);if(!$room)continue;
    $membership->update(['left_at'=>now()]);$room->update(['member_count'=>max(0,$room->member_count-1)]);
    if($room->owner_id===$member->user_id){$next=$room->members()->orderByRaw("CASE WHEN role = 'admin' THEN 0 ELSE 1 END")->orderBy('joined_at')->orderBy('id')->first();if($next){$next->update(['role'=>'owner']);$room->update(['owner_id'=>$next->user_id]);}}
    app(MessageWriter::class)->system($room,'member_removed',['target_ids'=>[$member->user_id]]);
    ChatEvent::dispatch('user.'.$member->user_id,'room.member_removed',['room_id'=>$room->id,'user_id'=>$member->user_id,'reason'=>'removed'],$room->workspace_id);
    if($room->member_count===0){$room->update(['purge_after'=>now()->addDays(30)]);$room->delete();}
   }
   ChatEvent::dispatch('user.'.$member->user_id,'workspace.member_removed',['workspace_id'=>$member->workspace_id],$member->workspace_id);
  });}finally{$context->id=$previous;}
 }
}
