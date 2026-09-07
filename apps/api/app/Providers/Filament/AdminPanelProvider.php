<?php
namespace App\Providers\Filament;
use Filament\{Panel,PanelProvider};
use Filament\Support\Colors\Color;
class AdminPanelProvider extends PanelProvider {
 public function panel(Panel $panel):Panel{return $panel->default()->id('admin')->path('admin')->login(\App\Filament\Pages\Auth\Login::class)->authGuard('admin')->brandName('Banana Chat Admin')->colors(['primary'=>Color::Amber])->discoverResources(in:app_path('Filament/Resources'),for:'App\\Filament\\Resources')->discoverPages(in:app_path('Filament/Pages'),for:'App\\Filament\\Pages')->pages([\Filament\Pages\Dashboard::class])->middleware([\Illuminate\Cookie\Middleware\EncryptCookies::class,\Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,\Illuminate\Session\Middleware\StartSession::class,\Illuminate\Session\Middleware\AuthenticateSession::class,\Illuminate\View\Middleware\ShareErrorsFromSession::class,\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,\Illuminate\Routing\Middleware\SubstituteBindings::class,\Filament\Http\Middleware\DisableBladeIconComponents::class,\Filament\Http\Middleware\DispatchServingFilamentEvent::class])->authMiddleware([\Filament\Http\Middleware\Authenticate::class]);}
}
