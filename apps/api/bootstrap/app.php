<?php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\{Exceptions,Middleware};
use Illuminate\Validation\ValidationException;
use App\Support\ApiError;
return Application::configure(basePath:dirname(__DIR__))
 ->withRouting(web:__DIR__.'/../routes/web.php',api:__DIR__.'/../routes/api.php',apiPrefix:'api/v1',commands:__DIR__.'/../routes/console.php',health:'/up')
 ->withMiddleware(function(Middleware $m){
    $m->appendToGroup('api',\App\Http\Middleware\ApiContext::class);
    $m->alias(['token'=>\App\Http\Middleware\AuthenticateToken::class,'fresh'=>\App\Http\Middleware\FreshPassword::class,'workspace'=>\App\Http\Middleware\SetWorkspace::class,'app.version'=>\App\Http\Middleware\RequireAppVersion::class]);
 })
 ->withExceptions(function(Exceptions $e){
    $e->shouldRenderJsonWhen(fn($r,$e)=>$r->is('api/*')||$r->expectsJson());
    $e->render(function(\Throwable $e,$r){
        if(!$r->is('api/*'))return null;
        if($e instanceof ApiError)return $e->render($r);
        $status=$e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface?$e->getStatusCode():500;
        $code=$status===404?'NOT_FOUND':($status===403?'ROOM_FORBIDDEN':'INTERNAL_ERROR');$details=[];
        if($e instanceof ValidationException){$status=422;$code='VALIDATION_FAILED';$details=['fields'=>$e->errors()];}
        if($status===429)$code='RATE_LIMITED';
        return (new ApiError($code,$status,$details))->render($r);
    });
 })->create();
