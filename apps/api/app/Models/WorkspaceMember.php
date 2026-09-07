<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
class WorkspaceMember extends Model {
use HasUlids; protected $guarded=[];
public function user(){return $this->belongsTo(User::class);} public function workspace(){return $this->belongsTo(Workspace::class);}
}
