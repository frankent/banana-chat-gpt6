<?php
namespace App\Domain\Room;
use App\Models\{Room,RoomMember,User};
use App\Support\ApiError;
class RoomAccess {
 public function find(string $id,bool $write=false,bool $lock=false):Room {
    $q=Room::query();if($lock)$q->lockForUpdate();$room=$q->findOrFail($id);
    if(!$room->members()->where('user_id',request()->user()->id)->exists())throw new ApiError($write?'ROOM_NOT_MEMBER':'NOT_FOUND',$write?403:404);
    return $room;
 }
 public function membership(Room $room):RoomMember{return $room->members()->where('user_id',request()->user()->id)->firstOrFail();}
 public function group(Room $r):void{if($r->type==='dm')throw new ApiError('ROOM_DM_IMMUTABLE');}
 public function manager(Room $r):bool{return in_array($this->membership($r)->role,['owner','admin']);}
 public function allow(Room $r,string $action):void {
    $this->group($r);$mine=$this->membership($r);
    $allowed=match($action){'delete'=>$mine->role==='owner'||in_array(request()->attributes->get('workspace_membership')->role,['owner','admin']),'settings'=>$this->manager($r),'add'=>$this->manager($r)||($r->settings['who_can_add_members']??'everyone')==='everyone','edit'=>$this->manager($r)||($r->settings['who_can_edit_info']??'everyone')==='everyone',default=>false};
    if(!$allowed)throw new ApiError('ROOM_FORBIDDEN',403);
 }
}
