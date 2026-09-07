<?php
namespace App\Events;
use Illuminate\Broadcasting\{PrivateChannel,InteractsWithSockets};
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
class ChatEvent implements ShouldBroadcast,ShouldDispatchAfterCommit {
 use Dispatchable,InteractsWithSockets;
 public function __construct(public string $channel,public string $name,public array $data,public ?string $workspaceId=null){}
 public function broadcastOn():array{return [new PrivateChannel($this->channel)];}
 public function broadcastAs():string{return $this->name;}
 public function broadcastWith():array{return ['event'=>$this->name,'workspace_id'=>$this->workspaceId,'data'=>$this->data,'emitted_at'=>now()->toISOString()];}
}
