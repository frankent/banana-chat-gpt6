<?php
namespace App\Jobs;
use App\Models\Attachment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
class PurgeExpiredUploads implements ShouldQueue {
 use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;
 public function handle():void {Attachment::withoutGlobalScopes()->where('status','pending')->where('expires_at','<',now())->chunkById(100,function($items){foreach($items as $a){Storage::disk()->delete($a->storage_key);$a->delete();}});}
}
