<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\{RateLimiter,Gate,Event};
use Illuminate\Cache\RateLimiting\Limit;
class AppServiceProvider extends ServiceProvider {
 public function register():void{
    $this->app->scoped(\App\Domain\Workspace\WorkspaceContext::class,fn()=>new \App\Domain\Workspace\WorkspaceContext);
    $this->app->bind(\App\Domain\Ai\AiProviderClient::class,\App\Domain\Ai\OpenAiCompatibleProvider::class);
 }
 public function boot():void{
    require base_path('routes/channels.php');
    Event::listen(\App\Events\ChatEvent::class,\App\Listeners\PublishChatEventToEmqx::class);
    foreach([\App\Models\User::class,\App\Models\Workspace::class,\App\Models\WorkspaceMember::class,\App\Models\AppSetting::class] as $model)$model::observe(\App\Observers\AdminObserver::class);
    RateLimiter::for('login',fn($r)=>Limit::perMinute(5)->by($r->ip()));
    RateLimiter::for('messages',fn($r)=>Limit::perMinute(60)->by($r->user()?->id??hash('sha256',$r->bearerToken()??$r->ip())));
    RateLimiter::for('uploads',fn($r)=>Limit::perMinute(20)->by($r->user()?->id??hash('sha256',$r->bearerToken()??$r->ip())));
    RateLimiter::for('ai',fn($r)=>Limit::perMinute(20)->by($r->user()?->id??hash('sha256',$r->bearerToken()??$r->ip())));
    Gate::define('viewHorizon',fn($user)=>auth('admin')->check()&&auth('admin')->user()->canAccessPanel(\Filament\Facades\Filament::getPanel('admin')));
 }
}
