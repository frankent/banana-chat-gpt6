<?php

namespace Database\Seeders;

use App\Domain\Message\MessageWriter;
use App\Domain\Workspace\WorkspaceContext;
use App\Models\{AppSetting,Room,RoomMember,User,Workspace,WorkspaceMember};
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $password=Hash::make('BananaChat2026!');
        $tony=User::firstOrCreate(['username'=>'tony'],['display_name'=>'Tony','password_hash'=>$password,'must_change_password'=>false,'is_system_admin'=>true,'locale'=>'en','timezone'=>'Asia/Bangkok']);
        $mali=User::firstOrCreate(['username'=>'mali'],['display_name'=>'Mali','password_hash'=>$password,'must_change_password'=>false,'locale'=>'th','timezone'=>'Asia/Bangkok']);
        $beam=User::firstOrCreate(['username'=>'beam'],['display_name'=>'Beam','password_hash'=>$password,'must_change_password'=>false,'locale'=>'th','timezone'=>'Asia/Bangkok']);
        $workspace=Workspace::firstOrCreate(['slug'=>'banana-studio'],['name'=>'Banana Studio','settings'=>['ai_monthly_token_budget'=>null]]);
        foreach([[$tony,'owner'],[$mali,'admin'],[$beam,'member']] as [$user,$role])WorkspaceMember::firstOrCreate(['workspace_id'=>$workspace->id,'user_id'=>$user->id],['role'=>$role,'status'=>'active','joined_at'=>now()]);
        app(WorkspaceContext::class)->id=$workspace->id;
        $room=Room::firstOrCreate(['workspace_id'=>$workspace->id,'type'=>'group','name'=>'general'],['description'=>'A shared place for news, ideas, and cheerful hellos.','created_by'=>$tony->id,'owner_id'=>$tony->id,'member_count'=>3,'settings'=>['who_can_add_members'=>'everyone','who_can_edit_info'=>'everyone'],'last_message_at'=>now()]);
        foreach([[$tony,'owner'],[$mali,'admin'],[$beam,'member']] as [$user,$role])RoomMember::firstOrCreate(['room_id'=>$room->id,'user_id'=>$user->id],['workspace_id'=>$workspace->id,'role'=>$role,'joined_at'=>now(),'added_by'=>$tony->id]);
        if($room->last_seq===0){$writer=app(MessageWriter::class);$writer->system($room,'member_added',['target_ids'=>[$tony->id,$mali->id,$beam->id]]);$writer->append($room,$mali->id,'Something good is coming together ✨','00000000-0000-4000-8000-000000000001');$writer->append($room,$tony->id,"Love where this is going. Let’s do it!",'00000000-0000-4000-8000-000000000002');}
        foreach(\App\Support\Settings::DEFAULTS as $key=>$value)if($value!==null)AppSetting::updateOrCreate(['key'=>$key],['value'=>$value,'updated_by'=>$tony->id,'updated_at'=>now()]);
        app(WorkspaceContext::class)->id=null;
    }
}
