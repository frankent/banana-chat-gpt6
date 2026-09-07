<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
class ChatSession extends Model {
use HasUlids; protected $guarded=[];
protected $table='sessions'; protected $hidden=['refresh_token_hash']; protected function casts():array{return ['expires_at'=>'datetime','revoked_at'=>'datetime'];} public function user(){return $this->belongsTo(User::class);} public function device(){return $this->belongsTo(Device::class);}
}
