<?php

namespace App\Jobs;

use App\Models\{Message,User};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue,SerializesModels};
use Illuminate\Support\Facades\{DB,Http};

class SendMessageNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries=3;
    public function __construct(public string $messageId, public string $userId) { $this->onQueue('push')->afterCommit(); }

    public function handle(): void
    {
        $message=Message::withoutGlobalScopes()->with(['sender','room'])->find($this->messageId);$user=User::find($this->userId);
        if(!$message||!$user||$message->sender_id===$user->id)return;
        $roomSetting=DB::table('room_notification_settings')->where('user_id',$user->id)->where('room_id',$message->room_id)->first();
        $mentioned=DB::table('message_mentions')->where('message_id',$message->id)->where('user_id',$user->id)->exists();
        if($roomSetting?->mode==='none'||($roomSetting?->mode==='mentions'&&!$mentioned)||($roomSetting?->muted_until&&now()->lt($roomSetting->muted_until)))return;
        $preference=DB::table('user_notification_settings')->where('user_id',$user->id)->first();
        if($preference?->dnd_start&&$preference?->dnd_end){$local=now()->setTimezone($user->timezone);$days=json_decode($preference->dnd_days??'[]',true);if((!$days||in_array($local->dayOfWeek,$days,true))&&$local->format('H:i')>=$preference->dnd_start&&$local->format('H:i')<=$preference->dnd_end)return;}
        $preview=($preference?->preview_in_push??true)?($message->body?:ucfirst($message->type)):'New message';
        $payload=['to'=>null,'title'=>$message->sender?->display_name??'Banana Chat','body'=>mb_substr($preview,0,180),'data'=>['type'=>$mentioned?'mention':'message','workspace_id'=>$message->workspace_id,'room_id'=>$message->room_id,'message_id'=>$message->id,'seq'=>$message->seq]];
        foreach(DB::table('devices')->where('user_id',$user->id)->whereNull('push_disabled_at')->where('push_provider','expo')->whereNotNull('push_token')->get() as $device){$payload['to']=$device->push_token;try{Http::timeout(10)->post('https://exp.host/--/api/v2/push/send',$payload)->throw();DB::table('devices')->where('id',$device->id)->update(['push_failed_count'=>0]);}catch(\Throwable){$failures=$device->push_failed_count+1;DB::table('devices')->where('id',$device->id)->update(['push_failed_count'=>$failures,'push_disabled_at'=>$failures>=5?now():null]);}}
    }
}
