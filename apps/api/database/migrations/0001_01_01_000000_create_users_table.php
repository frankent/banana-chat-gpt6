<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('users', function (Blueprint $t) {
            $t->ulid('id')->primary();$t->string('username',32)->unique();$t->string('display_name',80);$t->text('password_hash');
            $t->string('status')->default('active')->index();$t->boolean('must_change_password')->default(true);
            $t->timestampTz('password_changed_at')->nullable();$t->unsignedInteger('failed_login_count')->default(0);$t->timestampTz('failed_login_at')->nullable();$t->timestampTz('locked_until')->nullable();$t->timestampTz('last_seen_at')->nullable();
            $t->string('locale',5)->default('en');$t->string('timezone',64)->default('Asia/Bangkok');$t->boolean('is_system_admin')->default(false);$t->string('auth_provider')->default('local');$t->ulid('created_by')->nullable();$t->ulid('avatar_attachment_id')->nullable();
            $t->boolean('ai_memory_enabled')->default(true);$t->timestampTz('ai_consented_at')->nullable();$t->rememberToken();$t->timestampsTz();
        });
        Schema::create('web_sessions', function(Blueprint $t){$t->string('id')->primary();$t->ulid('user_id')->nullable()->index();$t->string('ip_address',45)->nullable();$t->text('user_agent')->nullable();$t->longText('payload');$t->integer('last_activity')->index();});
    }
    public function down(): void {Schema::dropIfExists('web_sessions');Schema::dropIfExists('users');}
};
