<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class AiConversation extends Model {
    use HasUlids, SoftDeletes;
    protected $guarded = [];
    protected $attributes = ['last_seq'=>0,'message_count'=>0,'summary_up_to_seq'=>0,'summary_tokens'=>0,'total_tokens_in'=>0,'total_tokens_out'=>0];
    protected function casts(): array { return ['last_message_at'=>'datetime','archived_at'=>'datetime','purge_after'=>'datetime']; }
    public function messages() { return $this->hasMany(AiMessage::class, 'conversation_id'); }
    public function user() { return $this->belongsTo(User::class); }
}
