<?php
namespace App\Domain\Ai;
use App\Models\{AiConversation,AiProvider,AiUserMemory};

class ContextBuilder {
    public function __construct(private TokenEstimator $tokens) {}
    public function build(AiProvider $provider, AiConversation $conversation): array {
        $budget = $provider->window_size - $provider->max_output_tokens - (int) ceil($provider->window_size * .02);
        $system = trim($provider->system_prompt ?: 'You are Banana Chat Assistant. Be accurate, useful, and say when you are unsure.');
        if ($conversation->user->ai_memory_enabled) {
            $memories = AiUserMemory::where('user_id',$conversation->user_id)->orderByDesc('importance')->orderByDesc('last_used_at')->limit((int)\App\Support\Settings::get('ai.memory.inject_max',30))->get();
            $lines = []; $used = 0;
            foreach ($memories as $memory) {
                $line = "- [{$memory->category}] {$memory->content}";
                $cost = $this->tokens->estimate($line);
                if ($used + $cost > (int)\App\Support\Settings::get('ai.memory.inject_max_tokens',1500)) break;
                $used += $cost; $lines[] = $line; $memory->update(['last_used_at'=>now()]);
            }
            if ($lines) $system .= "\n\n## What is known about the user (may be outdated)\n".implode("\n",$lines);
        }
        if ($conversation->summary) $system .= "\n\n## Earlier conversation summary\n".$conversation->summary;
        $messages = [['role'=>'system','content'=>$system]];
        $remaining = $budget - $this->tokens->estimate($system, (float)($conversation->token_ratio ?: 1));
        $recent = $conversation->messages()->where('seq','>',$conversation->summary_up_to_seq)->whereIn('status',['completed','cancelled'])->orderByDesc('seq')->get();
        $chosen = [];
        foreach ($recent as $message) {
            $cost = $this->tokens->estimate($message->content ?? '', (float)($conversation->token_ratio ?: 1));
            if ($cost > $remaining) break;
            $remaining -= $cost; $chosen[] = ['role'=>$message->role,'content'=>$message->content ?? ''];
        }
        return array_merge($messages, array_reverse($chosen));
    }
}
