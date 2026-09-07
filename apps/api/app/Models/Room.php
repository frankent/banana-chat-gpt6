<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
class Room extends WorkspaceModel {
protected $attributes=['last_seq'=>0,'last_user_seq'=>0,'member_count'=>0];
use \Illuminate\Database\Eloquent\SoftDeletes; protected function casts():array{return ['settings'=>'array','last_seq'=>'integer','last_user_seq'=>'integer','member_count'=>'integer','last_message_at'=>'datetime','purge_after'=>'datetime'];} public function members(){return $this->hasMany(RoomMember::class)->whereNull('left_at');} public function messages(){return $this->hasMany(Message::class);} public function lastMessage(){return $this->belongsTo(Message::class,'last_message_id');}
}
