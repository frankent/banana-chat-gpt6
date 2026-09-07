<?php
use Illuminate\Support\Facades\{Route,DB,Broadcast};
use App\Http\Controllers\{AuthController,WorkspaceController,RoomController,MessageController,AiController,UploadController,SearchController,NotificationController};
Route::get('health',function(){try{DB::select('SELECT 1');return ['data'=>['status'=>'ok']];}catch(\Throwable){return response()->json(['data'=>['status'=>'degraded']],503);}});
Route::put('uploads/{id}/content',[UploadController::class,'content'])->name('uploads.content');
Route::get('attachments/{id}/download',[UploadController::class,'download'])->name('attachments.download');
Route::post('auth/login',[AuthController::class,'login'])->middleware('throttle:login');
Route::post('auth/refresh',[AuthController::class,'refresh'])->middleware('throttle:60,1');
Route::middleware(['token','app.version'])->group(function(){
 Route::get('me',[WorkspaceController::class,'me']);Route::post('auth/change-password',[AuthController::class,'password']);
 Route::middleware('fresh')->group(function(){
  Route::post('auth/logout',[AuthController::class,'logout']);Route::post('auth/logout-all',[AuthController::class,'logoutAll']);
  Route::get('me/sessions',[AuthController::class,'sessions']);Route::delete('me/sessions/{id}',[AuthController::class,'revoke']);
  Route::patch('me',[WorkspaceController::class,'profile']);Route::get('me/workspaces',[WorkspaceController::class,'workspaces']);
  Route::post('broadcasting/auth',function(\Illuminate\Http\Request $r){return Broadcast::auth($r);});
  Route::middleware('workspace')->group(function(){
   Route::get('members',[WorkspaceController::class,'members']);Route::get('workspace',[WorkspaceController::class,'show']);Route::get('sync',[WorkspaceController::class,'sync']);Route::post('me/focus',[WorkspaceController::class,'focus']);
   Route::get('rooms',[RoomController::class,'index']);Route::post('rooms',[RoomController::class,'store']);
   Route::get('rooms/{id}',[RoomController::class,'show']);Route::patch('rooms/{id}',[RoomController::class,'update']);Route::delete('rooms/{id}',[RoomController::class,'destroy']);
   Route::get('rooms/{id}/members',[RoomController::class,'members']);Route::post('rooms/{id}/members',[RoomController::class,'addMembers']);Route::delete('rooms/{id}/members/{user}',[RoomController::class,'removeMember']);Route::patch('rooms/{id}/members/{user}',[RoomController::class,'role']);Route::post('rooms/{id}/leave',[RoomController::class,'leave']);
   Route::post('rooms/{id}/{action}',[RoomController::class,'personal'])->whereIn('action',['hide','unhide','pin','unpin']);
   Route::get('rooms/{id}/messages',[MessageController::class,'index']);Route::post('rooms/{id}/messages',[MessageController::class,'store'])->middleware('throttle:messages');
   Route::patch('messages/{id}',[MessageController::class,'update']);Route::delete('messages/{id}',[MessageController::class,'destroy']);
   Route::post('rooms/{id}/read',[MessageController::class,'read']);Route::get('rooms/{id}/read-status',[MessageController::class,'readStatus']);
   Route::post('uploads',[UploadController::class,'store'])->middleware('throttle:uploads');Route::post('uploads/{id}/complete',[UploadController::class,'complete'])->middleware('throttle:uploads');Route::get('attachments/{id}',[UploadController::class,'show']);
   Route::get('search/messages',[SearchController::class,'messages']);Route::get('search/files',[SearchController::class,'files']);
   Route::get('me/mentions',[MessageController::class,'mentions']);
   Route::get('notifications',[NotificationController::class,'index']);Route::post('notifications/read-all',[NotificationController::class,'readAll']);Route::post('notifications/{id}/read',[NotificationController::class,'read']);
   Route::get('notification-settings',[NotificationController::class,'settings']);Route::patch('notification-settings',[NotificationController::class,'updateSettings']);Route::patch('rooms/{id}/notification-settings',[NotificationController::class,'room']);
   Route::post('devices',[NotificationController::class,'registerDevice']);Route::post('presence',[NotificationController::class,'presence']);
   Route::middleware('throttle:ai')->prefix('ai')->group(function(){
    Route::get('status',[AiController::class,'status']);Route::post('consent',[AiController::class,'consent']);
    Route::get('conversations',[AiController::class,'index']);Route::post('conversations',[AiController::class,'store']);Route::get('conversations/{id}',[AiController::class,'show']);Route::patch('conversations/{id}',[AiController::class,'update']);Route::delete('conversations/{id}',[AiController::class,'destroy']);
    Route::get('conversations/{id}/messages',[AiController::class,'messages']);Route::post('conversations/{id}/messages',[AiController::class,'send']);Route::get('messages/{id}',[AiController::class,'message']);Route::post('messages/{id}/cancel',[AiController::class,'cancel']);
    Route::post('messages/{id}/regenerate',[AiController::class,'regenerate']);Route::patch('messages/{id}',[AiController::class,'editMessage']);Route::post('messages/{id}/share',[AiController::class,'share']);Route::get('search',[AiController::class,'search']);
    Route::get('memories',[AiController::class,'memories']);Route::post('memories',[AiController::class,'addMemory']);Route::delete('memories/{id}',[AiController::class,'deleteMemory']);Route::post('memories/clear',[AiController::class,'clearMemories']);
   });
  });
 });
});
