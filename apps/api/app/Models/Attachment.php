<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
class Attachment extends WorkspaceModel {
use \Illuminate\Database\Eloquent\SoftDeletes; protected function casts():array{return ['derived'=>'array','expires_at'=>'datetime','size_bytes'=>'integer'];}
public function messages(){return $this->belongsToMany(Message::class,'message_attachments')->withPivot('position');}
public function uploader(){return $this->belongsTo(User::class,'uploader_id');}
}
