<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Filament\Models\Contracts\{FilamentUser,HasName};
use Filament\Panel;
class User extends Authenticatable implements FilamentUser, HasName {
    use HasUlids;
    protected $guarded=[];
    protected $hidden=['password_hash','remember_token','failed_login_count','locked_until'];
    protected function casts():array{return ['is_system_admin'=>'boolean','must_change_password'=>'boolean','ai_memory_enabled'=>'boolean','locked_until'=>'datetime','failed_login_at'=>'datetime','ai_consented_at'=>'datetime'];}
    public function getAuthPasswordName(){return 'password_hash';}
    public function getFilamentName():string{return $this->display_name;}
    public function canAccessPanel(Panel $panel):bool{return $this->is_system_admin && $this->status==='active' && !$this->must_change_password;}
    public function memberships(){return $this->hasMany(WorkspaceMember::class);}
}
