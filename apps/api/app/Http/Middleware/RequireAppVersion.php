<?php

namespace App\Http\Middleware;

use App\Support\ApiError;
use App\Support\Settings;
use Closure;
use Illuminate\Http\Request;

class RequireAppVersion
{
    public function handle(Request $request, Closure $next)
    {
        $platform = strtolower((string) $request->header('X-App-Platform', 'web'));
        $version = $request->header('X-App-Version');
        $minimum = Settings::get("app.minimum_version.$platform");

        if ($version && $minimum && version_compare($version, (string) $minimum, '<')) {
            throw new ApiError('APP_UPDATE_REQUIRED', 426, [
                'platform' => $platform,
                'minimum_version' => $minimum,
                'store_url' => Settings::get("app.store_url.$platform"),
            ]);
        }

        return $next($request);
    }
}
