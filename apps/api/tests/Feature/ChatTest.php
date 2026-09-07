<?php
namespace Tests\Feature;

use App\Models\{User, Workspace, WorkspaceMember, Room, RoomMember, Message, Attachment};
use App\Jobs\{ProcessAttachment,PurgeExpiredUploads};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Hash, Event, Http, Storage, Queue};
use Illuminate\Support\Str;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;
    private function account(string $name = 'tony', bool $fresh = true): User
    {
        return User::create(['username'=>$name, 'display_name'=>ucfirst($name), 'password_hash'=>Hash::make('BananaChat2026!'), 'must_change_password'=>!$fresh]);
    }
    private function workspace(User ...$users): Workspace
    {
        $ws = Workspace::create(['name'=>'Banana Studio', 'slug'=>strtolower(Str::random(12))]);
        foreach ($users as $i=>$u) WorkspaceMember::create(['workspace_id'=>$ws->id,'user_id'=>$u->id,'role'=>$i===0?'owner':'member','joined_at'=>now()]);
        return $ws;
    }
    private function login(User $u): array
    {
        return $this->postJson('/api/v1/auth/login',['username'=>strtoupper($u->username),'password'=>'BananaChat2026!','device'=>['platform'=>'web','name'=>'Test browser','app_version'=>'1.0.0']])->assertOk()->json('data');
    }
    private function asUser(User $u, Workspace $ws): static
    {
        $auth=$this->login($u);
        return $this->withHeaders(['Authorization'=>'Bearer '.$auth['access_token'],'X-Workspace-Id'=>$ws->id]);
    }
    private function group(User $u, Workspace $ws, array $members=[]): string
    {
        return $this->asUser($u,$ws)->postJson('/api/v1/rooms',['type'=>'group','name'=>'Design room','member_ids'=>$members])->assertCreated()->json('data.room.id');
    }
    private function png(): string
    {
        $image=imagecreatetruecolor(8,8);$yellow=imagecolorallocate($image,245,202,71);imagefill($image,0,0,$yellow);ob_start();imagepng($image);$bytes=ob_get_clean();imagedestroy($image);return $bytes;
    }
    public function test_TC_AUTH_001_002_login_normalizes_username_and_issues_hashed_tokens(): void
    {
        $u=$this->account();$this->workspace($u);$auth=$this->login($u);
        $this->assertNotEmpty($auth['refresh_token']);$this->assertCount(1,$auth['workspaces']);
        $this->assertDatabaseHas('audit_logs',['action'=>'auth.login','actor_id'=>$u->id]);
        $this->assertDatabaseMissing('personal_access_tokens',['token'=>$auth['access_token']]);
    }
    public function test_TC_AUTH_006_forced_password_change_blocks_rooms(): void
    {
        $u=$this->account('newuser',false);$w=$this->workspace($u);$this->asUser($u,$w);
        $this->getJson('/api/v1/me')->assertOk();
        $this->getJson('/api/v1/rooms')->assertForbidden()->assertJsonPath('error.code','AUTH_PASSWORD_CHANGE_REQUIRED');
    }
    public function test_TC_AUTH_009_010_refresh_reuse_revokes_entire_family(): void
    {
        $u=$this->account();$auth=$this->login($u);
        $new=$this->postJson('/api/v1/auth/refresh',['refresh_token'=>$auth['refresh_token']])->assertOk()->json('data');
        $this->postJson('/api/v1/auth/refresh',['refresh_token'=>$auth['refresh_token']])->assertUnauthorized()->assertJsonPath('error.code','AUTH_REFRESH_REUSED');
        $this->withToken($new['access_token'])->getJson('/api/v1/me')->assertUnauthorized();
    }
    public function test_TC_AUTH_013_logout_revokes_access_immediately(): void
    {
        $u=$this->account();$w=$this->workspace($u);$this->asUser($u,$w)->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }
    public function test_TC_AUTH_017_change_password_keeps_current_session(): void
    {
        $u=$this->account('newuser',false);$w=$this->workspace($u);$this->asUser($u,$w);
        $this->postJson('/api/v1/auth/change-password',['current_password'=>'BananaChat2026!','new_password'=>'MyNewBanana2026!'])->assertNoContent();
        $this->getJson('/api/v1/rooms')->assertOk();
        $this->assertTrue(Hash::check('MyNewBanana2026!',$u->fresh()->password_hash));
    }
    public function test_TC_WS_008_header_prevents_cross_workspace_access(): void
    {
        $u=$this->account();$a=$this->workspace($u);$b=$this->workspace($u);$id=$this->group($u,$a);
        $this->withHeader('X-Workspace-Id',$b->id)->getJson('/api/v1/rooms/'.$id)->assertNotFound();
    }
    public function test_TC_ROOM_001_002_003_dm_is_unique_and_cannot_target_self(): void
    {
        $u=$this->account();$v=$this->account('mali');$ws=$this->workspace($u,$v);$this->asUser($u,$ws);
        $id=$this->postJson('/api/v1/rooms',['type'=>'dm','user_id'=>$v->id])->assertCreated()->json('data.room.id');
        $this->postJson('/api/v1/rooms',['type'=>'dm','user_id'=>$v->id])->assertOk()->assertJsonPath('data.room.id',$id);
        $this->postJson('/api/v1/rooms',['type'=>'dm','user_id'=>$u->id])->assertUnprocessable()->assertJsonPath('error.code','ROOM_DM_SELF');
        $this->deleteJson('/api/v1/rooms/'.$id)->assertUnprocessable();
    }
    public function test_TC_ROOM_007_group_has_owner_and_initial_system_message(): void
    {
        $u=$this->account();$w=$this->workspace($u);$id=$this->group($u,$w);
        $this->assertDatabaseHas('rooms',['id'=>$id,'owner_id'=>$u->id,'last_seq'=>1]);
        $this->getJson('/api/v1/rooms/'.$id.'/messages')->assertJsonPath('data.0.type','system');
    }
    public function test_TC_ROOM_027_030_owner_must_transfer_before_leaving(): void
    {
        $u=$this->account();$v=$this->account('mali');$w=$this->workspace($u,$v);$id=$this->group($u,$w,[$v->id]);
        $this->postJson("/api/v1/rooms/$id/leave")->assertUnprocessable()->assertJsonPath('error.code','ROOM_OWNER_CANNOT_LEAVE');
        $this->patchJson("/api/v1/rooms/$id/members/{$v->id}",['role'=>'owner'])->assertOk();
        $this->postJson("/api/v1/rooms/$id/leave")->assertNoContent();
    }
    public function test_TC_MSG_001_007_sending_is_ordered_and_idempotent(): void
    {
        $u=$this->account();$w=$this->workspace($u);$id=$this->group($u,$w);$payload=['body'=>'Hello **team**','client_message_id'=>(string)Str::uuid()];
        $message=$this->postJson("/api/v1/rooms/$id/messages",$payload)->assertCreated()->json('data.message');
        $this->assertEquals(2,$message['seq']);
        $this->postJson("/api/v1/rooms/$id/messages",$payload)->assertOk()->assertJsonPath('data.message.id',$message['id']);
        $this->assertDatabaseCount('messages',2);
    }
    public function test_TC_MSG_004_005_rejects_empty_and_oversized_text(): void
    {
        $u=$this->account();$w=$this->workspace($u);$id=$this->group($u,$w);
        foreach (['   '=>'MSG_EMPTY',str_repeat('ก',4001)=>'MSG_TOO_LONG'] as $body=>$code)
            $this->postJson("/api/v1/rooms/$id/messages",['body'=>$body,'client_message_id'=>(string)Str::uuid()])->assertUnprocessable()->assertJsonPath('error.code',$code);
    }
    public function test_TC_MSG_029_037_edit_history_and_delete_tombstone(): void
    {
        $u=$this->account();$w=$this->workspace($u);$id=$this->group($u,$w);
        $m=$this->postJson("/api/v1/rooms/$id/messages",['body'=>'First','client_message_id'=>(string)Str::uuid()])->json('data.message.id');
        $this->patchJson('/api/v1/messages/'.$m,['body'=>'Second'])->assertOk()->assertJsonPath('data.message.edit_count',1);
        $this->assertDatabaseHas('message_edits',['message_id'=>$m,'previous_body'=>'First']);
        $this->deleteJson('/api/v1/messages/'.$m)->assertNoContent();
        $this->getJson("/api/v1/rooms/$id/messages")->assertJsonPath('data.1.body',null)->assertJsonPath('data.1.seq',2);
    }
    public function test_TC_MEDIA_001_007_008_upload_complete_process_and_send_attachment(): void
    {
        Storage::fake('local');Queue::fake();$u=$this->account();$w=$this->workspace($u);$room=$this->group($u,$w);$bytes=$this->png();
        $upload=$this->postJson('/api/v1/uploads',['kind'=>'image','filename'=>'banana.png','mime_type'=>'image/png','size_bytes'=>strlen($bytes)])->assertCreated()->json('data');
        $this->assertDatabaseHas('attachments',['id'=>$upload['attachment_id'],'status'=>'pending','uploader_id'=>$u->id]);
        $this->call('PUT',$upload['put_url'],[],[],[],['CONTENT_TYPE'=>'image/png'],$bytes)->assertNoContent();
        $this->postJson('/api/v1/uploads/'.$upload['attachment_id'].'/complete')->assertOk()->assertJsonPath('data.attachment.status','uploaded');
        Queue::assertPushed(ProcessAttachment::class,fn($job)=>$job->attachmentId===$upload['attachment_id']);
        (new ProcessAttachment($upload['attachment_id'],$w->id))->handle();
        $this->assertDatabaseHas('attachments',['id'=>$upload['attachment_id'],'status'=>'ready','width'=>8,'height'=>8]);
        $this->postJson('/api/v1/uploads/'.$upload['attachment_id'].'/complete')->assertOk()->assertJsonPath('data.attachment.status','ready');
        $message=$this->postJson("/api/v1/rooms/$room/messages",['client_message_id'=>(string)Str::uuid(),'attachment_ids'=>[$upload['attachment_id']]])->assertCreated()->json('data.message');
        $this->assertSame('image',$message['type']);$this->assertNull($message['body']);$this->assertSame('banana.png',$message['attachments'][0]['original_name']);
    }
    public function test_TC_MEDIA_002_003_005_006_limits_blocked_types_and_content_are_enforced(): void
    {
        Storage::fake('local');Queue::fake();$u=$this->account();$w=$this->workspace($u);$this->asUser($u,$w);
        $this->postJson('/api/v1/uploads',['kind'=>'image','filename'=>'huge.png','mime_type'=>'image/png','size_bytes'=>20971521])->assertUnprocessable()->assertJsonPath('error.code','MEDIA_TOO_LARGE')->assertJsonPath('error.details.max_bytes',20971520);
        $this->postJson('/api/v1/uploads',['kind'=>'file','filename'=>'invoice.pdf.exe','mime_type'=>'application/octet-stream','size_bytes'=>20])->assertUnprocessable()->assertJsonPath('error.code','MEDIA_TYPE_BLOCKED');
        $upload=$this->postJson('/api/v1/uploads',['kind'=>'image','filename'=>'fake.png','mime_type'=>'image/png','size_bytes'=>5])->assertCreated()->json('data');
        $this->call('PUT',$upload['put_url'],[],[],[],['CONTENT_TYPE'=>'image/png'],'hello')->assertNoContent();
        $this->postJson('/api/v1/uploads/'.$upload['attachment_id'].'/complete')->assertUnprocessable()->assertJsonPath('error.code','MEDIA_MIME_MISMATCH');
        Storage::disk('local')->assertMissing(Attachment::withoutGlobalScopes()->find($upload['attachment_id'])->storage_key);
    }
    public function test_TC_MEDIA_009_011_upload_is_private_and_expired_uploads_are_purged(): void
    {
        Storage::fake('local');$u=$this->account();$v=$this->account('mali');$w=$this->workspace($u,$v);$this->asUser($u,$w);
        $upload=$this->postJson('/api/v1/uploads',['kind'=>'file','filename'=>'notes.txt','mime_type'=>'text/plain','size_bytes'=>5])->assertCreated()->json('data');
        $this->call('PUT',$upload['put_url'],[],[],[],['CONTENT_TYPE'=>'text/plain'],'hello')->assertNoContent();
        $this->asUser($v,$w)->postJson('/api/v1/uploads/'.$upload['attachment_id'].'/complete')->assertNotFound();
        Attachment::withoutGlobalScopes()->whereKey($upload['attachment_id'])->update(['expires_at'=>now()->subMinute()]);(new PurgeExpiredUploads)->handle();
        $this->assertSoftDeleted('attachments',['id'=>$upload['attachment_id']]);
    }
    public function test_TC_SRCH_001_003_011_search_finds_thai_text_and_respects_room_membership(): void
    {
        $u=$this->account();$v=$this->account('mali');$w=$this->workspace($u,$v);$visible=$this->group($u,$w);$hidden=$this->group($v,$w);
        $this->asUser($u,$w)->postJson("/api/v1/rooms/$visible/messages",['body'=>'สวัสดี ทีมกล้วย','client_message_id'=>(string)Str::uuid()])->assertCreated();
        $this->asUser($v,$w)->postJson("/api/v1/rooms/$hidden/messages",['body'=>'สวัสดี ข้อมูลลับ','client_message_id'=>(string)Str::uuid()])->assertCreated();
        $this->asUser($u,$w)->getJson('/api/v1/search/messages?q='.urlencode('สวัสดี'))->assertOk()->assertJsonCount(1,'data')->assertJsonPath('data.0.message.body','สวัสดี ทีมกล้วย');
        $this->getJson('/api/v1/search/messages?q=x')->assertUnprocessable();
    }
    public function test_TC_READ_001_003_004_read_cursor_never_decreases_or_exceeds_last_seq(): void
    {
        $u=$this->account();$w=$this->workspace($u);$id=$this->group($u,$w);
        $this->postJson("/api/v1/rooms/$id/read",['seq'=>999])->assertOk()->assertJsonPath('data.last_read_seq',1);
        $this->postJson("/api/v1/rooms/$id/read",['seq'=>0])->assertOk()->assertJsonPath('data.last_read_seq',1);
    }
    public function test_TC_PERM_001_nonmember_cannot_read_room_even_as_workspace_owner(): void
    {
        $u=$this->account();$v=$this->account('mali');$w=$this->workspace($u,$v);$id=$this->group($v,$w);
        $this->asUser($u,$w)->getJson('/api/v1/rooms/'.$id)->assertNotFound();
    }
    public function test_TC_AI_001_007_014_consent_and_generation_flow(): void
    {
        Http::fake(['https://ai.example/v1/chat/completions'=>Http::response(['model'=>'banana-1','choices'=>[['message'=>['content'=>'A bright idea.'],'finish_reason'=>'stop']],'usage'=>['prompt_tokens'=>12,'completion_tokens'=>4]])]);
        $u=$this->account();$w=$this->workspace($u);
        \App\Models\AiProvider::create(['name'=>'Example AI','base_url'=>'https://ai.example/v1','api_key_encrypted'=>'secret','api_key_last4'=>'cret','model'=>'banana-1','is_enabled'=>true,'is_default'=>true]);
        $this->asUser($u,$w)->getJson('/api/v1/ai/status')->assertOk()->assertJsonPath('data.configured',true);
        $conversation=$this->postJson('/api/v1/ai/conversations',[])->assertCreated()->json('data.conversation.id');
        $payload=['client_message_id'=>(string)Str::uuid(),'content'=>'Help me think.'];
        $this->postJson("/api/v1/ai/conversations/$conversation/messages",$payload)->assertForbidden()->assertJsonPath('error.code','AI_CONSENT_REQUIRED');
        $this->postJson('/api/v1/ai/consent')->assertNoContent();
        $this->postJson("/api/v1/ai/conversations/$conversation/messages",$payload)->assertStatus(202);
        $this->getJson("/api/v1/ai/conversations/$conversation/messages")->assertOk()->assertJsonCount(2,'data')->assertJsonPath('data.1.content','A bright idea.')->assertJsonPath('data.1.status','completed');
        Http::assertSentCount(1);
    }
    public function test_TC_AI_051_memory_is_private_per_user(): void
    {
        $u=$this->account();$v=$this->account('mali');$w=$this->workspace($u,$v);$this->asUser($u,$w);
        $id=$this->postJson('/api/v1/ai/memories',['content'=>'I prefer concise replies.','category'=>'preference'])->assertCreated()->json('data.memory.id');
        $this->asUser($v,$w)->getJson('/api/v1/ai/memories')->assertOk()->assertJsonCount(0,'data');
        $this->deleteJson('/api/v1/ai/memories/'.$id)->assertNotFound();
    }
    public function test_TC_MSG_048_NOTI_001_mentions_create_private_notifications(): void
    {
        $u=$this->account();$v=$this->account('mali');$w=$this->workspace($u,$v);$room=$this->group($u,$w,[$v->id]);
        $this->asUser($u,$w)->postJson("/api/v1/rooms/$room/messages",['body'=>'hello @Mali','client_message_id'=>(string)Str::uuid()])->assertCreated()->assertJsonPath('data.message.mentions.0',$v->id);
        $this->asUser($v,$w)->getJson('/api/v1/me/mentions')->assertOk()->assertJsonCount(1,'data');
        $notification=$this->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('meta.unread_count',1)->json('data.0.id');
        $this->postJson("/api/v1/notifications/$notification/read")->assertNoContent();
        $this->getJson('/api/v1/notifications')->assertJsonPath('meta.unread_count',0);
    }
    public function test_TC_NOTI_018_settings_device_presence_and_version_gate(): void
    {
        $u=$this->account();$w=$this->workspace($u);$this->asUser($u,$w);
        $this->patchJson('/api/v1/notification-settings',['sound'=>false,'dnd_days'=>[1,2]])->assertOk()->assertJsonPath('data.sound',0);
        $this->postJson('/api/v1/devices',['push_token'=>'test-token','push_provider'=>'webpush','platform'=>'web','device_name'=>'Chromium','app_version'=>'1.0.0'])->assertCreated();
        $this->postJson('/api/v1/presence',['state'=>'online'])->assertNoContent();
        \App\Models\AppSetting::create(['key'=>'app.minimum_version.android','value'=>'2.0.0','updated_at'=>now()]);
        $this->withHeaders(['X-App-Platform'=>'android','X-App-Version'=>'1.0.0'])->getJson('/api/v1/rooms')->assertStatus(426)->assertJsonPath('error.code','APP_UPDATE_REQUIRED');
    }
    public function test_TC_AI_076_103_regenerate_search_and_share_to_room(): void
    {
        Http::fake(['https://ai.example/v1/chat/completions'=>Http::sequence()->push(['model'=>'banana-1','choices'=>[['message'=>['content'=>'First answer'],'finish_reason'=>'stop']]])->push(['model'=>'banana-1','choices'=>[['message'=>['content'=>'Better answer'],'finish_reason'=>'stop']]])]);
        $u=$this->account();$w=$this->workspace($u);$room=$this->group($u,$w);$u->update(['ai_consented_at'=>now()]);
        \App\Models\AiProvider::create(['name'=>'Example AI','base_url'=>'https://ai.example/v1','api_key_encrypted'=>'secret','api_key_last4'=>'cret','model'=>'banana-1','is_enabled'=>true,'is_default'=>true]);
        $this->asUser($u,$w);$conversation=$this->postJson('/api/v1/ai/conversations',[])->json('data.conversation.id');
        $assistant=$this->postJson("/api/v1/ai/conversations/$conversation/messages",['client_message_id'=>(string)Str::uuid(),'content'=>'Find a better plan'])->assertStatus(202)->json('data.assistant_message.id');
        $replacement=$this->postJson("/api/v1/ai/messages/$assistant/regenerate")->assertStatus(202)->json('data.assistant_message.id');
        $this->getJson('/api/v1/ai/search?q='.urlencode('Better answer'))->assertOk()->assertJsonPath('data.0.id',$replacement);
        $this->postJson("/api/v1/ai/messages/$replacement/share",['room_id'=>$room])->assertCreated()->assertJsonPath('data.message.body','Better answer');
    }
}
