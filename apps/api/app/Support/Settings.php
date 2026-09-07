<?php
namespace App\Support;
use App\Models\AppSetting;
use Illuminate\Support\Facades\Schema;
class Settings {
    public const DEFAULTS=['message.max_length'=>4000,'message.edit_window_minutes'=>1440,'room.group.max_members'=>500,'auth.password.min_length'=>10,'auth.max_sessions_per_user'=>10,'auth.lockout.threshold'=>10,'auth.lockout.minutes'=>15,'auth.access_token_ttl_minutes'=>60,'auth.refresh_token_ttl_days'=>30,'room.deleted_purge_days'=>30,'upload.image.max_bytes'=>20971520,'upload.video.max_bytes'=>209715200,'upload.file.max_bytes'=>104857600,'upload.workspace_quota_bytes'=>10737418240,'upload.image.allowed_mimes'=>['image/jpeg','image/png','image/gif','image/webp','image/heic','image/heif'],'upload.video.allowed_mimes'=>['video/mp4','video/quicktime','video/webm'],'upload.file.blocked_extensions'=>['exe','bat','cmd','sh','ps1','msi','scr','js','jar','com','vbs'],'ai.enabled'=>true,'ai.memory.enabled'=>true,'ai.daily_message_limit_per_user'=>200,'ai.max_message_chars'=>32000,'ai.max_concurrent_per_user'=>2,'ai.admin_review_enabled'=>false,'ai.compaction.trigger_ratio'=>0.7,'ai.memory.max_per_user'=>200,'ai.memory.inject_max'=>30,'ai.memory.inject_max_tokens'=>1500,'app.minimum_version.web'=>null,'app.minimum_version.ios'=>null,'app.minimum_version.android'=>null,'app.store_url.ios'=>null,'app.store_url.android'=>null];
    public static function get(string $key,mixed $default=null):mixed {return (Schema::hasTable('app_settings')?AppSetting::find($key)?->value:null)??self::DEFAULTS[$key]??$default;}
}
