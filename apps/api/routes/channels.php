<?php
use Illuminate\Support\Facades\Broadcast;
use App\Models\{Room,RoomMember,WorkspaceMember};
Broadcast::channel('user.{id}',fn($user,$id)=>$user->id===$id);
Broadcast::channel('workspace.{id}',fn($user,$id)=>WorkspaceMember::where('workspace_id',$id)->where('user_id',$user->id)->where('status','active')->whereHas('workspace',fn($q)=>$q->where('status','active'))->exists());
Broadcast::channel('room.{id}',function($user,$id){$room=Room::withoutGlobalScope('workspace')->find($id);return $room && WorkspaceMember::where('workspace_id',$room->workspace_id)->where('user_id',$user->id)->where('status','active')->whereHas('workspace',fn($q)=>$q->where('status','active'))->exists() && RoomMember::withoutGlobalScope('workspace')->where('room_id',$id)->where('user_id',$user->id)->whereNull('left_at')->exists();});
