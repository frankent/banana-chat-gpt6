<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
class Workspace extends Model {
use HasUlids; protected $guarded=[];
protected function casts():array{return ['settings'=>'array'];} public function memberships(){return $this->hasMany(WorkspaceMember::class);} 
}
