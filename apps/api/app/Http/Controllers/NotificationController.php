<?php

namespace App\Http\Controllers;

use App\Events\ChatEvent;
use App\Models\{Device,Room,RoomMember};
use App\Support\ApiError;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Cache,DB};

class NotificationController extends Controller
{
    public function settings(Request $request)
    {
        $settings = DB::table('user_notification_settings')->where('user_id', $request->user()->id)->first();
        return response()->json(['data' => $settings ?: [
            'user_id' => $request->user()->id, 'dnd_start' => null, 'dnd_end' => null,
            'dnd_days' => [], 'sound' => true, 'preview_in_push' => true,
        ]]);
    }

    public function updateSettings(Request $request)
    {
        $values = $request->validate([
            'dnd_start' => ['nullable','date_format:H:i'], 'dnd_end' => ['nullable','date_format:H:i'],
            'dnd_days' => ['sometimes','array'], 'dnd_days.*' => ['integer','between:0,6'],
            'sound' => ['sometimes','boolean'], 'preview_in_push' => ['sometimes','boolean'],
        ]);
        $current = (array) DB::table('user_notification_settings')->where('user_id', $request->user()->id)->first();
        $data = array_merge(['dnd_start'=>null,'dnd_end'=>null,'dnd_days'=>[],'sound'=>true,'preview_in_push'=>true], $current, $values);
        if (is_array($data['dnd_days'])) $data['dnd_days'] = json_encode(array_values(array_unique($data['dnd_days'])));
        DB::table('user_notification_settings')->updateOrInsert(['user_id'=>$request->user()->id], $data);
        return $this->settings($request);
    }

    public function room(Request $request, string $id)
    {
        $room = Room::findOrFail($id);
        if (!RoomMember::where('room_id',$id)->where('user_id',$request->user()->id)->whereNull('left_at')->exists()) throw new ApiError('NOT_FOUND',404);
        $values = $request->validate(['mode'=>['sometimes','in:all,mentions,none'],'muted_until'=>['nullable','date']]);
        DB::table('room_notification_settings')->updateOrInsert(
            ['user_id'=>$request->user()->id,'room_id'=>$id],
            ['workspace_id'=>$room->workspace_id,'mode'=>$values['mode']??'all','muted_until'=>$values['muted_until']??null]
        );
        return response()->json(['data'=>DB::table('room_notification_settings')->where('user_id',$request->user()->id)->where('room_id',$id)->first()]);
    }

    public function registerDevice(Request $request)
    {
        $values=$request->validate(['push_token'=>'required|string|max:4096','push_provider'=>'required|in:fcm,apns,expo,webpush','platform'=>'required|in:web,ios,android','device_name'=>'required|string|max:100','app_version'=>'required|string|max:20','locale'=>'sometimes|in:en,th']);
        $device=Device::updateOrCreate(['user_id'=>$request->user()->id,'push_token'=>$values['push_token']],$values+['last_active_at'=>now(),'push_disabled_at'=>null,'push_failed_count'=>0]);
        return response()->json(['data'=>['device'=>$device]],201);
    }

    public function index(Request $request)
    {
        $rows=DB::table('notifications')->where('user_id',$request->user()->id)->when($request->header('X-Workspace-Id'),fn($q,$id)=>$q->where('workspace_id',$id))->orderByDesc('created_at')->limit(min(100,$request->integer('limit',30)))->get()->map(function($row){$row->data=json_decode($row->data,true);return $row;});
        return response()->json(['data'=>$rows,'meta'=>['unread_count'=>DB::table('notifications')->where('user_id',$request->user()->id)->whereNull('read_at')->count()]]);
    }

    public function read(Request $request, string $id)
    {
        $updated=DB::table('notifications')->where('id',$id)->where('user_id',$request->user()->id)->update(['read_at'=>now(),'updated_at'=>now()]);
        if(!$updated&&!DB::table('notifications')->where('id',$id)->where('user_id',$request->user()->id)->exists()) throw new ApiError('NOT_FOUND',404);
        return response()->noContent();
    }

    public function readAll(Request $request)
    {
        DB::table('notifications')->where('user_id',$request->user()->id)->whereNull('read_at')->update(['read_at'=>now(),'updated_at'=>now()]);
        return response()->noContent();
    }

    public function presence(Request $request)
    {
        $values=$request->validate(['state'=>['required','in:online,away,offline']]);
        $key='presence:'.$request->user()->id;
        if($values['state']==='offline') Cache::forget($key); else Cache::put($key,['state'=>$values['state'],'at'=>now()->toIso8601String()],90);
        $request->user()->update(['last_seen_at'=>now()]);
        $workspaceId=$request->attributes->get('workspace')->id;
        ChatEvent::dispatch('workspace.'.$workspaceId,'presence.updated',['user_id'=>$request->user()->id,'state'=>$values['state'],'last_seen_at'=>now()->toIso8601String()],$workspaceId);
        return response()->noContent();
    }
}
