<?php
namespace App\Jobs;
use App\Domain\Ai\{AiProviderClient,ContextBuilder,TokenEstimator,ConversationMaintenance};
use App\Events\ChatEvent;
use App\Models\{AiConversation,AiMessage,AiProvider};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue,SerializesModels};
use Illuminate\Support\Facades\{Cache,DB};

class GenerateAiReply implements ShouldQueue {
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;
    public int $timeout=660;
    public function __construct(public string $messageId) {$this->onQueue('ai');}
    public function handle(AiProviderClient $client,ContextBuilder $context,TokenEstimator $tokens,ConversationMaintenance $maintenance): void {
        $assistant=AiMessage::find($this->messageId);if(!$assistant||$assistant->status!=='pending')return;
        $conversation=AiConversation::with('user')->find($assistant->conversation_id);$provider=AiProvider::where('is_enabled',true)->where('is_default',true)->first();
        if(!$conversation||!$provider){$this->failedMessage($assistant,'AI_PROVIDER_NOT_CONFIGURED');return;}
        $started=microtime(true);$assistant->update(['status'=>'streaming','started_at'=>now(),'model'=>$provider->model]);
        ChatEvent::dispatch('user.'.$assistant->user_id,'ai.message.started',['conversation_id'=>$conversation->id,'message_id'=>$assistant->id],$assistant->workspace_id);
        try {
            $result=$client->complete($provider,$context->build($provider,$conversation));$chunks=preg_split('/(?<=\s)/u',$result['content'],-1,PREG_SPLIT_NO_EMPTY);$buffer='';$index=0;
            foreach($chunks as $chunk){if(Cache::pull('ai:cancel:'.$assistant->id)){ $assistant->update(['content'=>$buffer,'status'=>'cancelled','finish_reason'=>'cancelled','completed_at'=>now()]);ChatEvent::dispatch('user.'.$assistant->user_id,'ai.message.completed',['message'=>$assistant->fresh()],$assistant->workspace_id);return; }$buffer.=$chunk;if(mb_strlen($chunk)>=40||++$index%3===0)ChatEvent::dispatch('user.'.$assistant->user_id,'ai.message.delta',['conversation_id'=>$conversation->id,'message_id'=>$assistant->id,'index'=>$index,'delta'=>$chunk],$assistant->workspace_id);}
            $prompt=$result['tokens_prompt']?:$tokens->estimate(json_encode($context->build($provider,$conversation)));
            $completion=$result['tokens_completion']?:$tokens->estimate($buffer);
            DB::transaction(function()use($assistant,$conversation,$result,$buffer,$prompt,$completion,$started){
                $assistant->update(['content'=>$buffer,'status'=>'completed','finish_reason'=>$result['finish_reason'],'model'=>$result['model'],'tokens_prompt'=>$prompt,'tokens_completion'=>$completion,'tokens_source'=>($result['tokens_prompt']||$result['tokens_completion'])?'provider':'estimated','latency_total_ms'=>(int)((microtime(true)-$started)*1000),'completed_at'=>now()]);
                $conversation->update(['message_count'=>$conversation->message_count+1,'total_tokens_in'=>$conversation->total_tokens_in+$prompt,'total_tokens_out'=>$conversation->total_tokens_out+$completion,'last_message_at'=>now()]);
                $key=['user_id'=>$assistant->user_id,'workspace_id'=>$assistant->workspace_id,'date'=>now()->setTimezone($conversation->user->timezone)->toDateString()];
                $usage=DB::table('ai_usage_daily')->where($key)->first();
                if($usage)DB::table('ai_usage_daily')->where($key)->update(['messages'=>$usage->messages+1,'tokens_in'=>$usage->tokens_in+$prompt,'tokens_out'=>$usage->tokens_out+$completion]);
                else DB::table('ai_usage_daily')->insert($key+['messages'=>1,'tokens_in'=>$prompt,'tokens_out'=>$completion,'tokens_memory'=>0,'failed'=>0]);
            });
            ChatEvent::dispatch('user.'.$assistant->user_id,'ai.message.completed',['message'=>$assistant->fresh()],$assistant->workspace_id);
            $maintenance->run($provider,$conversation);
        } catch(\App\Support\ApiError $e){$this->failedMessage($assistant,$e->errorCode);}
    }
    private function failedMessage(AiMessage $message,string $code):void {$message->update(['status'=>'failed','error_code'=>$code,'completed_at'=>now()]);ChatEvent::dispatch('user.'.$message->user_id,'ai.message.failed',['conversation_id'=>$message->conversation_id,'message_id'=>$message->id,'error_code'=>$code],$message->workspace_id);}
}
