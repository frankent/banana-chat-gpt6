<?php

namespace App\Jobs;

use App\Models\{AiConversation,Attachment,Room,Workspace};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue,SerializesModels};
use Illuminate\Support\Facades\{DB,Storage};

class EnforceRetention implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public function __construct() { $this->onQueue('retention'); }

    public function handle(): void
    {
        Workspace::whereNotNull('message_retention_days')->each(function(Workspace $workspace){
            $cutoff=now()->subDays($workspace->message_retention_days);
            DB::table('messages')->where('workspace_id',$workspace->id)->where('created_at','<',$cutoff)->update(['body'=>null,'deleted_at'=>DB::raw('COALESCE(deleted_at, CURRENT_TIMESTAMP)'),'delete_reason'=>'retention']);
        });
        Room::onlyTrashed()->where('purge_after','<=',now())->each(fn(Room $room)=>$room->forceDelete());
        AiConversation::onlyTrashed()->where('purge_after','<=',now())->each(fn(AiConversation $conversation)=>$conversation->forceDelete());
        Attachment::onlyTrashed()->where('deleted_at','<',now()->subDays(7))->each(function(Attachment $attachment){
            foreach(array_filter([$attachment->storage_key,...array_values($attachment->derived??[])]) as $key)Storage::disk()->delete($key);
            $attachment->forceDelete();
        });
        DB::table('notifications')->where('created_at','<',now()->subDays(90))->delete();
        DB::table('refresh_token_history')->where('expires_at','<',now())->delete();
    }
}
