import type {Message, Room, RealtimeEvent, AiMessage} from '@banana/shared';
export class MessageStore {
 private messages=new Map<string,Message>();private held=new Map<number,Message>();private lastSeq=0;
 constructor(private fill:(after:number)=>void=()=>{}){}
 seed(messages:Message[]){for(const m of messages)this.messages.set(m.id,m);this.lastSeq=Math.max(this.lastSeq,...messages.map(m=>m.seq),0);this.drain();}
 apply(message:Message){
  if(this.messages.has(message.id)||message.seq<=this.lastSeq){this.messages.set(message.id,message);return;}
  if(this.lastSeq>0&&message.seq>this.lastSeq+1){this.held.set(message.seq,message);this.fill(this.lastSeq);return;}
  this.messages.set(message.id,message);this.lastSeq=message.seq;this.drain();
 }
 private drain(){while(this.held.has(this.lastSeq+1)){const m=this.held.get(this.lastSeq+1)!;this.held.delete(m.seq);this.messages.set(m.id,m);this.lastSeq=m.seq;}for(const seq of this.held.keys())if(seq<=this.lastSeq){const m=this.held.get(seq)!;this.messages.set(m.id,m);this.held.delete(seq);}}
 remove(id:string,deletedAt:string,reason:string){const m=this.messages.get(id);if(m)this.messages.set(id,{...m,body:null,attachments:[],deleted_at:deletedAt,delete_reason:reason});}
 values(){return [...this.messages.values()].sort((a,b)=>a.seq-b.seq);}
 get cursor(){return this.lastSeq;}
}
export function workspaceUnread(rooms:Room[],now=Date.now()){return rooms.filter(r=>r.unread_count>0&&r.notification.mode!=='none'&&(!r.notification.muted_until||Date.parse(r.notification.muted_until)<=now)).length;}
export function reconnectDelay(attempt:number,random=Math.random){return Math.min(30000,1000*2**attempt)+Math.floor(random()*250);}
export class EventRouter {
 private handlers=new Map<string,Set<(event:RealtimeEvent)=>void>>();
 on(name:string,handler:(event:RealtimeEvent)=>void){const set=this.handlers.get(name)??new Set();set.add(handler);this.handlers.set(name,set);return ()=>{set.delete(handler);};}
 route(event:RealtimeEvent){for(const h of this.handlers.get(event.event)??[])h(event);for(const h of this.handlers.get('*')??[])h(event);}
}
export class ReadThrottle {
 private timer:ReturnType<typeof setTimeout>|null=null;private maximum=0;
 constructor(private send:(seq:number)=>void,private delay=1000){}
 mark(seq:number){this.maximum=Math.max(seq,this.maximum);if(!this.timer)this.timer=setTimeout(()=>{this.send(this.maximum);this.timer=null;},this.delay);}
 dispose(){if(this.timer)clearTimeout(this.timer);this.timer=null;}
}
export class AiStreamStore {
 private states=new Map<string,{message:AiMessage,index:number,held:Map<number,string>}>();
 constructor(private refetch:(id:string)=>void=()=>{}){}
 seed(message:AiMessage,index=0){this.states.set(message.id,{message,index,held:new Map()});}
 delta(id:string,index:number,delta:string){const s=this.states.get(id);if(!s||['completed','cancelled','failed'].includes(s.message.status)||index<=s.index)return;if(index>s.index+1){s.held.set(index,delta);this.refetch(id);return;}s.message={...s.message,status:'streaming',content:(s.message.content??'')+delta};s.index=index;while(s.held.has(s.index+1)){const next=s.index+1;const text=s.held.get(next)!;s.held.delete(next);this.delta(id,next,text);}}
 complete(message:AiMessage){this.seed(message);}
 get(id:string){return this.states.get(id)?.message;}
}
export interface CacheAdapter {read<T>(key:string):Promise<T|null>;write<T>(key:string,value:T):Promise<void>;clear():Promise<void>;}
export const cacheKey=(user:string,workspace:string,resource:string)=>`${user}:${workspace}:${resource}`;
export type OutboxState='pending'|'sending'|'failed';
export interface OutboxItem<T=unknown>{id:string;roomId:string;createdAt:number;payload:T;state:OutboxState;attempts:number;error?:string;}
export class Outbox<T=unknown>{
 private items:OutboxItem<T>[]=[];private flushing=false;
 constructor(private cache:CacheAdapter,private key:string,private sender:(item:OutboxItem<T>)=>Promise<void>){}
 async load(){this.items=(await this.cache.read<OutboxItem<T>[]>(this.key)??[]).map(x=>({...x,state:x.state==='sending'?'pending':x.state}));return this.values();}
 async enqueue(item:Omit<OutboxItem<T>,'state'|'attempts'>){this.items.push({...item,state:'pending',attempts:0});await this.persist();return this.values();}
 async flush(){if(this.flushing)return;this.flushing=true;try{for(const item of [...this.items].sort((a,b)=>a.createdAt-b.createdAt)){if(item.state==='failed')continue;item.state='sending';item.attempts++;await this.persist();try{await this.sender(item);this.items=this.items.filter(x=>x.id!==item.id);await this.persist();}catch(error){item.state='failed';item.error=error instanceof Error?error.message:'SEND_FAILED';await this.persist();break;}}}finally{this.flushing=false;}}
 async retry(id:string){const item=this.items.find(x=>x.id===id);if(item){item.state='pending';delete item.error;await this.persist();}await this.flush();}
 async remove(id:string){this.items=this.items.filter(x=>x.id!==id);await this.persist();}
 values(){return [...this.items].sort((a,b)=>a.createdAt-b.createdAt);}
 private persist(){return this.cache.write(this.key,this.items);}
}
export class AttachmentUrlRefresher {
 private inflight=new Map<string,Promise<unknown>>();
 constructor(private refresh:(id:string)=>Promise<unknown>){}
 get(id:string){let request=this.inflight.get(id);if(!request){request=this.refresh(id).finally(()=>this.inflight.delete(id));this.inflight.set(id,request);}return request;}
}
export class PresenceStore {
 private users=new Map<string,{online:boolean;lastSeenAt:string|null}>();
 update(userId:string,online:boolean,lastSeenAt:string|null=null){this.users.set(userId,{online,lastSeenAt});}
 get(userId:string){return this.users.get(userId)??{online:false,lastSeenAt:null};}
}
export class TypingStore {
 private timers=new Map<string,ReturnType<typeof setTimeout>>();private users=new Map<string,Set<string>>();
 mark(roomId:string,userId:string,ttl=5000){const key=roomId+':'+userId;const set=this.users.get(roomId)??new Set<string>();set.add(userId);this.users.set(roomId,set);const old=this.timers.get(key);if(old)clearTimeout(old);this.timers.set(key,setTimeout(()=>{set.delete(userId);this.timers.delete(key);},ttl));}
 get(roomId:string){return [...(this.users.get(roomId)??[])];}
 clear(){for(const timer of this.timers.values())clearTimeout(timer);this.timers.clear();this.users.clear();}
}
