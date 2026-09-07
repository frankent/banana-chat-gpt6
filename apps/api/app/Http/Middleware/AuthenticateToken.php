<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\ChatSession;
use App\Support\ApiError;
class AuthenticateToken {
    public function handle(Request $r,Closure $next){
        $token=$r->bearerToken();if(!$token)throw new ApiError('AUTH_TOKEN_INVALID',401);
        $access=DB::table('personal_access_tokens')->where('token',hash('sha256',$token))->first();
        if(!$access)throw new ApiError('AUTH_TOKEN_INVALID',401);
        if(now()->gte($access->expires_at))throw new ApiError('AUTH_TOKEN_EXPIRED',401);
        $s=ChatSession::with('user')->find($access->session_id);
        if(!$s||$s->revoked_at||$s->expires_at->isPast())throw new ApiError('AUTH_TOKEN_INVALID',401);
        if($s->user->status!=='active')throw new ApiError('AUTH_ACCOUNT_DISABLED',403);
        $r->setUserResolver(fn()=>$s->user);auth()->setUser($s->user);$r->attributes->set('chat_session',$s);
        $s->update(['last_used_at'=>now()]);return $next($r);
    }
}
