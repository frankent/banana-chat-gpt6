<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
class AiProvider extends Model {
    use HasUlids;
    protected $guarded = [];
    protected $hidden = ['api_key_encrypted'];
    protected function casts(): array { return ['api_key_encrypted'=>'encrypted','allowed_workspace_ids'=>'array','extra_headers'=>'array','capabilities'=>'array','is_default'=>'boolean','is_enabled'=>'boolean','last_test_status'=>'array']; }
    protected static function booted(): void {
        static::saving(function(self $provider){
            if($provider->isDirty('api_key_encrypted') && filled($provider->api_key_encrypted))$provider->api_key_last4=substr($provider->api_key_encrypted,-4);
            if($provider->is_default)self::whereKeyNot($provider->id)->update(['is_default'=>false]);
        });
    }
}
