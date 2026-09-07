<?php
namespace App\Listeners;
use App\Events\ChatEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\{Http,Log};
class PublishChatEventToEmqx implements ShouldQueue {
 public bool $afterCommit=true;public int $tries=5;public array $backoff=[1,5,15,30,60];
 public function handle(ChatEvent $event):void {
    $id=config('services.emqx.app_id');$secret=config('services.emqx.app_secret');$host=config('services.emqx.rest_endpoint');if(!$id||!$secret||!$host)return;
    try {
        $payload=$event->broadcastWith();Http::withBasicAuth($id,$secret)->acceptJson()->timeout(5)->retry(2,200)->post('https://'.$host.'/api/v5/publish',['topic'=>'banana/'.$event->channel,'qos'=>1,'retain'=>false,'payload'=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)])->throw();
    } catch (\Throwable $exception) {
        Log::warning('Optional EMQX event mirror failed.',['event'=>$event->name,'channel'=>$event->channel,'exception'=>$exception::class]);
    }
 }
}
