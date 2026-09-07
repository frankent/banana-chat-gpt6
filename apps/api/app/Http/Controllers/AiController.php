<?php
namespace App\Http\Controllers;
use App\Jobs\GenerateAiReply;
use App\Models\{AiConversation,AiMessage,AiProvider,AiUserMemory};
use App\Domain\Message\MessageWriter;
use App\Domain\Room\RoomAccess;
use App\Support\{ApiError,Settings};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Cache,DB};

class AiController extends Controller {
    public function __construct(private RoomAccess $roomAccess, private MessageWriter $messageWriter) {}
    private function provider(Request $r): AiProvider {
        if (!Settings::get('ai.enabled')) throw new ApiError('AI_DISABLED',403);
        $provider=AiProvider::where('is_enabled',true)->where('is_default',true)->first();
        if(!$provider)throw new ApiError('AI_PROVIDER_NOT_CONFIGURED',503);
        $allowed=$provider->allowed_workspace_ids;
        if(is_array($allowed)&&!in_array($r->header('X-Workspace-Id'),$allowed,true))throw new ApiError('AI_WORKSPACE_NOT_ALLOWED',403);
        return $provider;
    }
    private function conversation(Request $r,string $id):AiConversation {
        return AiConversation::where('user_id',$r->user()->id)->findOrFail($id);
    }
    public function status(Request $r) {
        $provider=AiProvider::where('is_enabled',true)->where('is_default',true)->first();$usage=DB::table('ai_usage_daily')->where('user_id',$r->user()->id)->where('date',now()->setTimezone($r->user()->timezone)->toDateString())->sum('messages');
        return response()->json(['data'=>['enabled'=>(bool)Settings::get('ai.enabled'),'configured'=>(bool)$provider,'allowed_in_workspace'=>!$provider||!is_array($provider->allowed_workspace_ids)||in_array($r->header('X-Workspace-Id'),$provider->allowed_workspace_ids,true),'provider'=>$provider?['name'=>$provider->name,'model'=>$provider->model,'window_size'=>$provider->window_size]:null,'limits'=>['daily_messages'=>$provider?->daily_message_limit_per_user??Settings::get('ai.daily_message_limit_per_user'),'max_message_chars'=>Settings::get('ai.max_message_chars')],'usage_today'=>['messages'=>(int)$usage],'memory_enabled'=>$r->user()->ai_memory_enabled,'consented'=>(bool)$r->user()->ai_consented_at,'admin_review'=>(bool)Settings::get('ai.admin_review_enabled')]]);
    }
    public function consent(Request $r){$r->user()->update(['ai_consented_at'=>now()]);return response()->noContent();}
    public function index(Request $r){$p=AiConversation::where('user_id',$r->user()->id)->when($r->boolean('archived'),fn($q)=>$q->whereNotNull('archived_at'),fn($q)=>$q->whereNull('archived_at'))->orderByDesc('last_message_at')->orderByDesc('id')->cursorPaginate(min(50,$r->integer('limit',30)));return response()->json(['data'=>$p->items(),'meta'=>['next_cursor'=>$p->nextCursor()?->encode(),'has_more'=>$p->hasMorePages()]]);}
    public function store(Request $r){$this->provider($r);$v=$r->validate(['title'=>'nullable|string|max:100']);$c=AiConversation::create(['user_id'=>$r->user()->id,'title'=>$v['title']??null,'title_source'=>isset($v['title'])?'user':null]);return response()->json(['data'=>['conversation'=>$c]],201);}
    public function show(Request $r,string $id){return response()->json(['data'=>['conversation'=>$this->conversation($r,$id)->makeHidden('summary')]]);}
    public function update(Request $r,string $id){$v=$r->validate(['title'=>'sometimes|required|string|max:100','archived'=>'sometimes|boolean']);$c=$this->conversation($r,$id);if(array_key_exists('archived',$v)){$v['archived_at']=$v['archived']?now():null;unset($v['archived']);}$c->update($v+(['title'=>$v['title']??$c->title]));return response()->json(['data'=>['conversation'=>$c->fresh()]]);}
    public function destroy(Request $r,string $id){$c=$this->conversation($r,$id);$c->update(['purge_after'=>now()->addDays(30)]);$c->delete();return response()->noContent();}
    public function messages(Request $r,string $id){$c=$this->conversation($r,$id);$q=$c->messages()->whereNull('superseded_at');if($r->filled('before_seq'))$q->where('seq','<',$r->integer('before_seq'));$data=$q->orderByDesc('seq')->limit(min(100,$r->integer('limit',50)))->get()->sortBy('seq')->values();return response()->json(['data'=>$data,'meta'=>['has_more_before'=>$data->isNotEmpty()&&$c->messages()->where('seq','<',$data->first()->seq)->exists()]]);}
    public function send(Request $r,string $id){
        $provider=$this->provider($r);if(!$r->user()->ai_consented_at)throw new ApiError('AI_CONSENT_REQUIRED',403);
        $v=$r->validate(['client_message_id'=>'required|uuid','content'=>'required|string|max:'.Settings::get('ai.max_message_chars')]);$content=trim($v['content']);if($content==='')throw new ApiError('AI_MESSAGE_TOO_LONG');$c=$this->conversation($r,$id);
        $limit=$provider->daily_message_limit_per_user??Settings::get('ai.daily_message_limit_per_user');$used=DB::table('ai_usage_daily')->where('user_id',$r->user()->id)->where('date',now()->setTimezone($r->user()->timezone)->toDateString())->sum('messages');if($used>=$limit)throw new ApiError('AI_QUOTA_EXCEEDED',429,['resets_at'=>now()->setTimezone($r->user()->timezone)->endOfDay()->toISOString()]);
        if(AiMessage::where('user_id',$r->user()->id)->whereIn('status',['pending','streaming'])->count()>=Settings::get('ai.max_concurrent_per_user'))throw new ApiError('AI_GENERATION_IN_PROGRESS',409);
        [$pair,$created]=DB::transaction(function()use($r,$c,$v,$content){$c=AiConversation::whereKey($c->id)->lockForUpdate()->first();$old=$c->messages()->where('client_message_id',$v['client_message_id'])->first();if($old){return [[$old,$c->messages()->where('parent_message_id',$old->id)->latest('seq')->first()],false];}if($c->messages()->whereIn('status',['pending','streaming'])->exists())throw new ApiError('AI_GENERATION_IN_PROGRESS',409);$user=AiMessage::create(['conversation_id'=>$c->id,'user_id'=>$r->user()->id,'workspace_id'=>$r->header('X-Workspace-Id'),'seq'=>$c->last_seq+1,'role'=>'user','content'=>$content,'status'=>'completed','client_message_id'=>$v['client_message_id'],'completed_at'=>now()]);$assistant=AiMessage::create(['conversation_id'=>$c->id,'user_id'=>$r->user()->id,'workspace_id'=>$r->header('X-Workspace-Id'),'seq'=>$c->last_seq+2,'role'=>'assistant','status'=>'pending','parent_message_id'=>$user->id]);$title=$c->title;if(!$title)$title=mb_substr(preg_replace('/\s+/u',' ',$content),0,60);$c->update(['last_seq'=>$c->last_seq+2,'message_count'=>$c->message_count+1,'last_message_at'=>now(),'title'=>$title,'title_source'=>$c->title?'user':'auto']);return [[$user,$assistant],true];});
        if($created)GenerateAiReply::dispatch($pair[1]->id);return response()->json(['data'=>['user_message'=>$pair[0],'assistant_message'=>$pair[1]]],$created?202:200);
    }
    public function cancel(Request $r,string $id){$m=AiMessage::where('user_id',$r->user()->id)->findOrFail($id);if(!in_array($m->status,['pending','streaming']))throw new ApiError('AI_NOT_GENERATING',409);Cache::put('ai:cancel:'.$m->id,true,600);return response()->json(['data'=>['message'=>$m]]);}
    public function message(Request $r,string $id){$m=AiMessage::where('user_id',$r->user()->id)->findOrFail($id);return response()->json(['data'=>['message'=>$m,'partial_content'=>$m->content,'last_index'=>0]]);}
    public function memories(Request $r){return response()->json(['data'=>AiUserMemory::where('user_id',$r->user()->id)->orderByDesc('importance')->orderByDesc('last_used_at')->get()]);}
    public function addMemory(Request $r){if(!$r->user()->ai_memory_enabled)throw new ApiError('AI_MEMORY_DISABLED',403);$v=$r->validate(['content'=>'required|string|max:300','category'=>'required|in:profile,preference,project,other']);$m=AiUserMemory::create($v+['user_id'=>$r->user()->id,'source'=>'user','importance'=>3]);return response()->json(['data'=>['memory'=>$m]],201);}
    public function deleteMemory(Request $r,string $id){AiUserMemory::where('user_id',$r->user()->id)->findOrFail($id)->delete();return response()->noContent();}
    public function clearMemories(Request $r){AiUserMemory::where('user_id',$r->user()->id)->delete();return response()->noContent();}
    public function regenerate(Request $r,string $id){
        $this->provider($r);$old=AiMessage::where('user_id',$r->user()->id)->where('role','assistant')->findOrFail($id);$c=$this->conversation($r,$old->conversation_id);
        if($c->messages()->where('role','assistant')->whereNull('superseded_at')->orderByDesc('seq')->value('id')!==$old->id)throw new ApiError('AI_REGENERATE_NOT_LATEST',422);
        $new=DB::transaction(function()use($old,$c){$locked=AiConversation::whereKey($c->id)->lockForUpdate()->first();$old->update(['superseded_at'=>now()]);$new=AiMessage::create(['conversation_id'=>$c->id,'user_id'=>$old->user_id,'workspace_id'=>$old->workspace_id,'seq'=>$locked->last_seq+1,'role'=>'assistant','status'=>'pending','parent_message_id'=>$old->parent_message_id]);$locked->update(['last_seq'=>$new->seq,'last_message_at'=>now()]);return $new;});
        GenerateAiReply::dispatch($new->id);return response()->json(['data'=>['assistant_message'=>$new]],202);
    }
    public function editMessage(Request $r,string $id){
        $this->provider($r);$values=$r->validate(['content'=>'required|string|max:'.Settings::get('ai.max_message_chars')]);$message=AiMessage::where('user_id',$r->user()->id)->where('role','user')->findOrFail($id);$content=trim($values['content']);if($content==='')throw new ApiError('AI_MESSAGE_TOO_LONG');$c=$this->conversation($r,$message->conversation_id);
        $assistant=DB::transaction(function()use($message,$c,$content){$locked=AiConversation::whereKey($c->id)->lockForUpdate()->first();$message->update(['content'=>$content]);$c->messages()->where('seq','>',$message->seq)->whereNull('superseded_at')->update(['superseded_at'=>now()]);$assistant=AiMessage::create(['conversation_id'=>$c->id,'user_id'=>$message->user_id,'workspace_id'=>$message->workspace_id,'seq'=>$locked->last_seq+1,'role'=>'assistant','status'=>'pending','parent_message_id'=>$message->id]);$locked->update(['last_seq'=>$assistant->seq,'last_message_at'=>now()]);return $assistant;});
        GenerateAiReply::dispatch($assistant->id);return response()->json(['data'=>['user_message'=>$message->fresh(),'assistant_message'=>$assistant]],202);
    }
    public function share(Request $r,string $id){
        $values=$r->validate(['room_id'=>'required|ulid']);$source=AiMessage::where('user_id',$r->user()->id)->where('role','assistant')->where('status','completed')->findOrFail($id);$room=$this->roomAccess->find($values['room_id'],true,true);$body=mb_substr((string)$source->content,0,(int)Settings::get('message.max_length'));
        $message=DB::transaction(function()use($r,$room,$body,$source){$locked=\App\Models\Room::whereKey($room->id)->lockForUpdate()->first();$message=$this->messageWriter->append($locked,$r->user()->id,$body,(string)\Illuminate\Support\Str::uuid());$message->update(['metadata'=>['source'=>['type'=>'ai','conversation_id'=>$source->conversation_id,'message_id'=>$source->id]]]);return $message;});
        return response()->json(['data'=>['message'=>\App\Support\Resources::message($message->fresh(['sender','attachments']))]],201);
    }
    public function search(Request $r){
        $values=$r->validate(['q'=>'required|string|min:2|max:100','limit'=>'sometimes|integer|min:1|max:100']);$needle='%'.str_replace(['%','_'],['\\%','\\_'],$values['q']).'%';$limit=min(100,$r->integer('limit',30));
        $messages=AiMessage::query()->join('ai_conversations','ai_messages.conversation_id','=','ai_conversations.id')->where('ai_messages.user_id',$r->user()->id)->whereNull('ai_conversations.deleted_at')->whereNull('ai_messages.superseded_at')->where(function($q)use($needle){$q->where('ai_messages.content','like',$needle)->orWhere('ai_conversations.title','like',$needle);})->select('ai_messages.*','ai_conversations.title as conversation_title')->orderByDesc('ai_messages.created_at')->limit($limit)->get();
        return response()->json(['data'=>$messages]);
    }
}
