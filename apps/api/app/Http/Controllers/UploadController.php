<?php
namespace App\Http\Controllers;
use App\Jobs\ProcessAttachment;
use App\Models\Attachment;
use App\Support\{ApiError,Resources,Settings};
use Illuminate\Http\Request;
use Illuminate\Support\{Str,Facades\Storage,Facades\URL};
class UploadController extends Controller {
 private function rules(string $kind,string $name,string $mime,int $size):void {
    $kind=$kind==='avatar'?'image':$kind;$ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
    if(in_array($ext,Settings::get('upload.file.blocked_extensions',[]),true))throw new ApiError('MEDIA_TYPE_BLOCKED');
    $allowed=$kind==='image'?Settings::get('upload.image.allowed_mimes',[]):($kind==='video'?Settings::get('upload.video.allowed_mimes',[]):null);
    if($allowed!==null&&!in_array(strtolower($mime),$allowed,true))throw new ApiError('MEDIA_MIME_MISMATCH');
    $max=Settings::get('upload.'.($kind==='image'?'image':($kind==='video'?'video':'file')).'.max_bytes');
    if($size>$max)throw new ApiError('MEDIA_TOO_LARGE',422,['max_bytes'=>$max]);
 }
 public function store(Request $r){
    $v=$r->validate(['kind'=>'required|in:image,video,file,avatar','filename'=>'required|string|max:255','mime_type'=>'required|string|max:127','size_bytes'=>'required|integer|min:1','sha256'=>'nullable|regex:/^[a-f0-9]{64}$/i']);$this->rules($v['kind'],$v['filename'],$v['mime_type'],$v['size_bytes']);
    $used=Attachment::query()->whereIn('status',['uploaded','processing','ready'])->sum('size_bytes');$quota=Settings::get('upload.workspace_quota_bytes');if($used+$v['size_bytes']>$quota)throw new ApiError('MEDIA_QUOTA_EXCEEDED',422,['quota_bytes'=>$quota]);
    $id=(string)Str::ulid();$ext=strtolower(pathinfo($v['filename'],PATHINFO_EXTENSION));$key='workspaces/'.$r->attributes->get('workspace_membership')->workspace_id.'/uploads/'.$id.'/original'.($ext?'.'.$ext:'');
    $a=Attachment::create(['id'=>$id,'workspace_id'=>$r->attributes->get('workspace_membership')->workspace_id,'uploader_id'=>$r->user()->id,'kind'=>$v['kind'],'status'=>'pending','original_name'=>$v['filename'],'mime_type'=>strtolower($v['mime_type']),'size_bytes'=>$v['size_bytes'],'storage_key'=>$key,'checksum_sha256'=>$v['sha256']??null,'expires_at'=>now()->addHour()]);
    $expires=now()->addMinutes(15);if(config('filesystems.default')==='s3'){$signed=Storage::disk()->temporaryUploadUrl($key,$expires,['ContentType'=>$a->mime_type]);$url=$signed['url'];$headers=$signed['headers'];}else{$url=URL::temporarySignedRoute('uploads.content',$expires,['id'=>$a->id],false);$headers=['Content-Type'=>$a->mime_type];}
    return response()->json(['data'=>['attachment_id'=>$a->id,'put_url'=>$url,'headers'=>$headers,'expires_at'=>$expires->toIso8601String()]],201);
 }
 public function content(Request $r,string $id){
    abort_unless($r->hasValidRelativeSignature(),403);$a=Attachment::withoutGlobalScopes()->whereKey($id)->where('status','pending')->firstOrFail();if($a->expires_at->isPast())throw new ApiError('MEDIA_UPLOAD_EXPIRED');
    $stream=$r->getContent(true);Storage::disk()->put($a->storage_key,$stream);if(is_resource($stream))fclose($stream);return response()->noContent();
 }
 public function complete(Request $r,string $id){
    $a=Attachment::query()->whereKey($id)->where('uploader_id',$r->user()->id)->firstOrFail();if(in_array($a->status,['uploaded','processing','ready']))return response()->json(['data'=>['attachment'=>Resources::attachment($a)]]);if($a->status!=='pending')throw new ApiError('MEDIA_UPLOAD_INVALID');if($a->expires_at->isPast())throw new ApiError('MEDIA_UPLOAD_EXPIRED');
    $disk=Storage::disk();if(!$disk->exists($a->storage_key))throw new ApiError('MEDIA_UPLOAD_MISSING');$actualSize=$disk->size($a->storage_key);if($actualSize!==$a->size_bytes){$disk->delete($a->storage_key);$a->update(['status'=>'failed']);throw new ApiError('MEDIA_SIZE_MISMATCH',422,['expected_bytes'=>$a->size_bytes,'actual_bytes'=>$actualSize]);}
    $stream=$disk->readStream($a->storage_key);$head=fread($stream,8192);fclose($stream);$actual=(new \finfo(FILEINFO_MIME_TYPE))->buffer($head)?:'application/octet-stream';$kind=$a->kind==='avatar'?'image':$a->kind;$matches=$kind==='image'?str_starts_with($actual,'image/'):($kind==='video'?str_starts_with($actual,'video/')||$actual==='application/octet-stream':true);if(!$matches){$disk->delete($a->storage_key);$a->update(['status'=>'failed']);throw new ApiError('MEDIA_MIME_MISMATCH');}
    $a->update(['status'=>'uploaded','mime_type'=>$actual,'expires_at'=>null]);ProcessAttachment::dispatch($a->id,$a->workspace_id);return response()->json(['data'=>['attachment'=>Resources::attachment($a)]]);
 }
 public function show(Request $r,string $id){
    $a=Attachment::query()->findOrFail($id);$allowed=$a->uploader_id===$r->user()->id||$a->messages()->whereHas('room.members',fn($q)=>$q->where('user_id',$r->user()->id)->whereNull('left_at'))->exists();if(!$allowed)abort(404);return response()->json(['data'=>['attachment'=>Resources::attachment($a)]]);
 }
 public function download(Request $r,string $id){
    abort_unless($r->hasValidRelativeSignature(),403);$a=Attachment::withoutGlobalScopes()->whereKey($id)->where('status','ready')->firstOrFail();$variant=$r->string('variant','original')->toString();$key=$variant==='original'?$a->storage_key:($a->derived[$variant]??null);abort_unless($key,404);$inline=$a->kind!=='file'&&$a->mime_type!=='image/svg+xml';return Storage::disk()->response($key,$a->original_name,['Content-Type'=>$variant==='original'?$a->mime_type:'image/webp'],$inline?'inline':'attachment');
 }
}
