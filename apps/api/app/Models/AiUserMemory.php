<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
class AiUserMemory extends Model { use HasUlids; protected $guarded = []; protected function casts(): array { return ['last_used_at'=>'datetime']; } }
