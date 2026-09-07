<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
class AiMessage extends Model {
    use HasUlids;
    protected $guarded = [];
    protected function casts(): array { return ['attachments'=>'array','started_at'=>'datetime','completed_at'=>'datetime','superseded_at'=>'datetime']; }
}
