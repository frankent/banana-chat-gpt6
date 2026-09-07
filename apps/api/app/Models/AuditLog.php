<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
class AuditLog extends Model {
use HasUlids; protected $guarded=[];
public $timestamps=false; protected function casts():array{return ['context'=>'array','created_at'=>'datetime'];}
}
