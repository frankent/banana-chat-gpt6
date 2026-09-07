<?php

namespace App\Domain\Ai;

use App\Events\ChatEvent;
use App\Models\{AiConversation,AiProvider,AiUserMemory};
use App\Support\Settings;

class ConversationMaintenance
{
    public function __construct(private TokenEstimator $tokens) {}

    public function run(AiProvider $provider, AiConversation $conversation): void
    {
        $conversation->refresh();
        $this->rememberExplicitRequest($conversation);
        $this->compactIfNeeded($provider, $conversation);
    }

    private function rememberExplicitRequest(AiConversation $conversation): void
    {
        if (!$conversation->user->ai_memory_enabled || !Settings::get('ai.memory.enabled')) return;
        $latest = $conversation->messages()->where('role','user')->whereNull('superseded_at')->latest('seq')->first();
        if (!$latest || !preg_match('/^(?:จำไว้ว่า|remember that)\s*[:：]?\s*(.+)$/isu', trim($latest->content), $match)) return;
        $content = mb_substr(trim($match[1]), 0, 300);
        if ($content === '') return;
        $memory = AiUserMemory::firstOrCreate(
            ['user_id'=>$conversation->user_id,'content'=>$content],
            ['category'=>'other','importance'=>5,'source'=>'extracted','source_conversation_id'=>$conversation->id,'source_message_id'=>$latest->id]
        );
        $limit=(int)Settings::get('ai.memory.max_per_user',200);
        AiUserMemory::where('user_id',$conversation->user_id)->orderByDesc('importance')->orderByDesc('last_used_at')->orderByDesc('created_at')->skip($limit)->take(PHP_INT_MAX)->get()->each->delete();
        ChatEvent::dispatch('user.'.$conversation->user_id,'ai.memories.changed',['memory_id'=>$memory->id],$latest->workspace_id);
    }

    private function compactIfNeeded(AiProvider $provider, AiConversation $conversation): void
    {
        $budget=max(1,$provider->window_size-$provider->max_output_tokens-(int)ceil($provider->window_size*.02));
        $recent=$conversation->messages()->where('seq','>',$conversation->summary_up_to_seq)->whereIn('status',['completed','cancelled'])->whereNull('superseded_at')->orderBy('seq')->get();
        $cost=$recent->sum(fn($message)=>$this->tokens->estimate((string)$message->content,(float)($conversation->token_ratio?:1)));
        if($cost<$budget*(float)Settings::get('ai.compaction.trigger_ratio',.7)||$recent->count()<6)return;
        $take=max(2,(int)floor($recent->count()/2));$compacted=$recent->take($take);$lines=$compacted->map(fn($message)=>strtoupper($message->role).': '.preg_replace('/\s+/u',' ',mb_substr((string)$message->content,0,500)))->all();
        $summary=trim(($conversation->summary?"{$conversation->summary}\n":'').implode("\n",$lines));
        $maxTokens=(int)floor($budget*.2);while($this->tokens->estimate($summary)>$maxTokens&&mb_strlen($summary)>100)$summary=mb_substr($summary,(int)floor(mb_strlen($summary)*.15));
        $upTo=$compacted->last()->seq;$conversation->update(['summary'=>$summary,'summary_up_to_seq'=>$upTo,'summary_tokens'=>$this->tokens->estimate($summary)]);
        ChatEvent::dispatch('user.'.$conversation->user_id,'ai.conversation.updated',['conversation_id'=>$conversation->id,'summary_up_to_seq'=>$upTo],$compacted->last()->workspace_id);
    }
}
