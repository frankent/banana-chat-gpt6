import { z } from 'zod';
export const limits = { messageLength: 4000, groupMembers: 500, pinnedRooms: 10, aiMessageLength: 32000 } as const;
export const loginSchema = z.object({ username: z.string().trim().min(3).max(32), password: z.string().min(1).max(128) });
export const messageSchema = z.object({ client_message_id: z.string().uuid(), body: z.string().trim().min(1).max(limits.messageLength), reply_to_message_id: z.string().optional() });
export type Role = 'owner' | 'admin' | 'member';
export interface User { id: string; username: string; display_name: string; status: 'active' | 'suspended' | 'deactivated'; avatar?: {sm:string}|null; locale?: 'en'|'th'; timezone?: string; is_system_admin?: boolean; must_change_password?: boolean; ai_memory_enabled?: boolean; }
export interface Workspace { id:string; name:string; slug:string; status:string; }
export interface Membership { workspace:Workspace; role:Role; unread_rooms_count:number; }
export interface RoomMember extends User { role:Role; last_read_seq:number; joined_at:string; }
export interface Message { id:string; room_id:string; workspace_id?:string; seq:number; type:'text'|'system'|'image'|'video'|'file'; sender:User|null; body:string|null; client_message_id:string|null; created_at:string; edited_at:string|null; edit_count:number; deleted_at:string|null; delete_reason?:string|null; system_event:{kind:string; actor_id?:string; target_ids?:string[]; name?:string}|null; reply_to:{id:string; seq:number; sender:User|null; snippet:string|null; type:string}|null; attachments:Attachment[]; }
export interface Attachment { id:string; original_name:string; mime_type:string; size_bytes:number; kind:'image'|'video'|'file'|'avatar'; status:'pending'|'uploaded'|'processing'|'ready'|'failed'; width:number|null;height:number|null;duration_ms:number|null;urls:{original:string;thumb_sm?:string;thumb_md?:string;poster?:string};url_expires_at:string; }
export interface Room { id:string; workspace_id:string; type:'dm'|'group'; name:string|null; description:string|null; owner_id:string|null; other_user:User|null; member_count:number; last_message:Message|null; last_seq:number; last_read_seq:number; unread_count:number; my_role:Role; hidden:boolean; pinned_at:string|null; updated_at:string; settings:{who_can_add_members:'everyone'|'admins';who_can_edit_info:'everyone'|'admins'}; notification:{mode:'all'|'mentions'|'none';muted_until:string|null}; }
export interface Tokens {access_token:string;refresh_token:string;expires_in:number;session_id:string;device_id:string;}
export interface LoginResult extends Tokens { user:User;workspaces:Membership[];must_change_password:boolean; }
export interface Page<T> {data:T[];meta:{next_cursor?:string|null;has_more?:boolean;has_more_before?:boolean;has_more_after?:boolean};}
export interface RealtimeEvent {event:string;workspace_id:string|null;data:Record<string,unknown>;emitted_at:string;}
export interface AiConversation {id:string;title:string|null;last_seq:number;last_message_at:string|null;archived_at:string|null;summary_up_to_seq:number;}
export interface AiMessage {id:string;conversation_id:string;seq:number;role:'user'|'assistant';content:string|null;status:'pending'|'streaming'|'completed'|'failed'|'cancelled';error_code:string|null;model:string|null;created_at:string;}
export {en, th} from './locales';
