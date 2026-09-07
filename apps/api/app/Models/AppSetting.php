<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
class AppSetting extends Model {
protected $guarded=[];
protected $primaryKey='key'; public $incrementing=false; protected $keyType='string'; public $timestamps=false; protected function casts():array{return ['value'=>'json','updated_at'=>'datetime'];}
}
