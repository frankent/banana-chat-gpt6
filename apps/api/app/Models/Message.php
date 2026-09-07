<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
class Message extends WorkspaceModel {
protected function casts():array{return ['system_event'=>'array','metadata'=>'array','seq'=>'integer','edit_count'=>'integer','edited_at'=>'datetime','deleted_at'=>'datetime'];} public function sender(){return $this->belongsTo(User::class,'sender_id');} public function room(){return $this->belongsTo(Room::class);} public function replyTo(){return $this->belongsTo(self::class,'reply_to_message_id');} public function attachments(){return $this->belongsToMany(Attachment::class,'message_attachments')->withPivot('position')->orderByPivot('position');}
}
