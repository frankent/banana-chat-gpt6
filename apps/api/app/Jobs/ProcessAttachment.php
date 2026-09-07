<?php
namespace App\Jobs;
use App\Events\ChatEvent;
use App\Models\Attachment;
use App\Support\Resources;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
class ProcessAttachment implements ShouldQueue {
 use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;
 public int $tries=3;
 public function __construct(public string $attachmentId,public string $workspaceId){}
 public function handle():void {
    $a=Attachment::withoutGlobalScopes()->find($this->attachmentId);if(!$a||!in_array($a->status,['uploaded','processing']))return;$a->update(['status'=>'processing']);
    try{$derived=[];$disk=Storage::disk();if(in_array($a->kind,['image','avatar'])){$bytes=$disk->get($a->storage_key);$image=@imagecreatefromstring($bytes);if(!$image)throw new \RuntimeException('Invalid image');$a->width=imagesx($image);$a->height=imagesy($image);foreach(['thumb_sm'=>400,'thumb_md'=>1280] as $name=>$limit){$scale=min(1,$limit/max($a->width,$a->height));$w=max(1,(int)round($a->width*$scale));$h=max(1,(int)round($a->height*$scale));$thumb=imagecreatetruecolor($w,$h);imagealphablending($thumb,false);imagesavealpha($thumb,true);imagecopyresampled($thumb,$image,0,0,0,0,$w,$h,$a->width,$a->height);ob_start();imagewebp($thumb,null,$name==='thumb_sm'?80:85);$encoded=ob_get_clean();imagedestroy($thumb);$key=dirname($a->storage_key).'/'.$name.'.webp';$disk->put($key,$encoded);$derived[$name]=$key;}imagedestroy($image);}elseif($a->kind==='video'){$derived=$this->video($a);}
        $a->derived=$derived+($a->kind==='file'?['scan'=>'skipped']:[]);$a->status='ready';$a->save();ChatEvent::dispatch('user.'.$a->uploader_id,'attachment.ready',['attachment'=>Resources::attachment($a)],$a->workspace_id);
    }catch(\Throwable $e){$a->update(['status'=>'failed','derived'=>['error'=>mb_substr($e->getMessage(),0,200)]]);ChatEvent::dispatch('user.'.$a->uploader_id,'attachment.failed',['attachment'=>Resources::attachment($a)],$a->workspace_id);report($e);}
 }
 private function video(Attachment $a):array {
    $disk=Storage::disk();$input=tempnam(sys_get_temp_dir(),'banana-video-');$poster=tempnam(sys_get_temp_dir(),'banana-poster-').'.webp';file_put_contents($input,$disk->get($a->storage_key));
    $probe=shell_exec('ffprobe -v error -select_streams v:0 -show_entries stream=width,height,codec_name -show_entries format=duration -of json '.escapeshellarg($input).' 2>&1');$json=json_decode($probe??'',true);$stream=$json['streams'][0]??null;if(!$stream)throw new \RuntimeException('Unable to inspect video');$a->width=(int)($stream['width']??0);$a->height=(int)($stream['height']??0);$a->duration_ms=(int)round(((float)($json['format']['duration']??0))*1000);
    $at=$a->duration_ms<1000?'0':'1';exec('ffmpeg -y -ss '.$at.' -i '.escapeshellarg($input).' -frames:v 1 -vf '.escapeshellarg("scale='min(1280,iw)':-2").' '.escapeshellarg($poster).' 2>&1',$out,$code);if($code!==0||!is_file($poster))throw new \RuntimeException('Unable to create video poster');$key=dirname($a->storage_key).'/poster.webp';$disk->put($key,file_get_contents($poster));@unlink($input);@unlink($poster);$a->derived=['playable_web'=>strtolower($stream['codec_name']??'')==='h264'];return ['poster'=>$key,'playable_web'=>$a->derived['playable_web']];
 }
}
