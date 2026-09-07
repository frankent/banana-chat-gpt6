<?php
namespace App\Http\Controllers;
use App\Models\{Attachment,Message};
use App\Support\Resources;
use Illuminate\Http\Request;
class SearchController extends Controller {
 public function messages(Request $r){
    $v=$r->validate(['q'=>'required|string|min:2|max:100','room_id'=>'nullable|ulid','sender_id'=>'nullable|ulid','from'=>'nullable|date','to'=>'nullable|date|after_or_equal:from','type'=>'nullable|in:text,image,video,file','cursor'=>'nullable|string']);$term=trim($v['q']);$driver=\DB::getDriverName();
    $q=Message::query()->with(['sender','replyTo.sender','attachments','room'])->whereNull('deleted_at')->where('type','!=','system')->whereHas('room.members',fn($members)=>$members->where('user_id',$r->user()->id)->whereNull('left_at'));
    $driver==='pgsql'?$q->where(fn($x)=>$x->whereRaw('body ILIKE ?',['%'.$term.'%'])->orWhereRaw("body_search @@ plainto_tsquery('simple', ?)",[$term])):$q->where('body','like','%'.$term.'%');
    if(isset($v['room_id']))$q->where('room_id',$v['room_id']);if(isset($v['sender_id']))$q->where('sender_id',$v['sender_id']);if(isset($v['from']))$q->where('created_at','>=',$v['from']);if(isset($v['to']))$q->where('created_at','<=',$v['to']);if(isset($v['type']))$q->where('type',$v['type']);
    $page=$q->orderByDesc('created_at')->orderByDesc('id')->cursorPaginate(25);return Resources::page($page,fn($m)=>['message'=>Resources::message($m),'room'=>['id'=>$m->room->id,'name'=>$m->room->name,'type'=>$m->room->type],'highlight'=>mb_substr($m->body??'',0,240)]);
 }
 public function files(Request $r){
    $v=$r->validate(['q'=>'required|string|min:2|max:100','kind'=>'nullable|in:image,video,file,avatar','room_id'=>'nullable|ulid','cursor'=>'nullable|string']);$term=trim($v['q']);
    $q=Attachment::query()->with(['messages.sender','messages.room'])->where('status','ready')->where('original_name','like','%'.$term.'%')->whereHas('messages.room.members',fn($members)=>$members->where('user_id',$r->user()->id)->whereNull('left_at'));
    if(isset($v['kind']))$q->where('kind',$v['kind']);if(isset($v['room_id']))$q->whereHas('messages',fn($messages)=>$messages->where('room_id',$v['room_id']));
    $page=$q->orderByDesc('created_at')->orderByDesc('id')->cursorPaginate(25);return Resources::page($page,function($a){$m=$a->messages->first();return ['attachment'=>Resources::attachment($a),'message'=>$m?Resources::message($m):null,'room'=>$m?['id'=>$m->room->id,'name'=>$m->room->name,'type'=>$m->room->type]:null];});
 }
}
