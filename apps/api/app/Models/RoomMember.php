<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
class RoomMember extends WorkspaceModel {
protected function casts():array{return ['last_read_seq'=>'integer','pinned_at'=>'datetime','hidden_at'=>'datetime'];} public function user(){return $this->belongsTo(User::class);} public function room(){return $this->belongsTo(Room::class);}
}
