import {useEffect,useState} from 'react';
import {useQueryClient} from '@tanstack/react-query';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import {EventRouter} from '@banana/chat-core';
import type {RealtimeEvent,Room} from '@banana/shared';
import {api,useSession} from './state';
export const events=new EventRouter();
export function useRealtime(rooms:Room[]){
 const {user,workspaceId}=useSession();const client=useQueryClient();const [status,setStatus]=useState('connecting');
 const roomIds=rooms.slice(0,200).map(r=>r.id).sort().join(',');
 useEffect(()=>{
  if(!user||!workspaceId)return;
  const refresh=()=>{void client.invalidateQueries({queryKey:['rooms',workspaceId]});void client.invalidateQueries({queryKey:['workspaces']});void client.invalidateQueries({queryKey:['messages',workspaceId]});void client.invalidateQueries({queryKey:['room-members',workspaceId]});void client.invalidateQueries({queryKey:['ai']});};
  if(!import.meta.env.VITE_REVERB_APP_KEY){setStatus('polling');const timer=setInterval(refresh,5000);return()=>clearInterval(timer);}
  const echo=new Echo({broadcaster:'pusher',key:import.meta.env.VITE_REVERB_APP_KEY,client:new Pusher(import.meta.env.VITE_REVERB_APP_KEY,{cluster:'mt1',wsHost:import.meta.env.VITE_REVERB_HOST??location.hostname,wsPort:Number(import.meta.env.VITE_REVERB_PORT??8080),wssPort:Number(import.meta.env.VITE_REVERB_PORT??443),forceTLS:import.meta.env.VITE_REVERB_SCHEME==='https',enabledTransports:['ws','wss'],channelAuthorization:{endpoint:api.baseUrl+'/broadcasting/auth',transport:'ajax',customHandler:async(params,callback)=>{try{const result=await api.raw<{auth:string;channel_data?:string}>('/broadcasting/auth',{method:'POST',body:JSON.stringify(params)});callback(null,result);}catch(error){callback(error as Error,null);}}}})});
  const handler=(event:RealtimeEvent)=>{events.route(event);if(event.event==='session.revoked'&&event.data.session_id===api.tokens.sessionId){void api.tokens.clear();client.clear();return;}refresh();};
  const names=['room.created','room.updated','room.deleted','room.activity','room.member_removed','room.member_added','room.member_role_changed','message.created','message.updated','message.deleted','room.read','workspace.unread_changed','workspace.member_added','workspace.member_removed','session.revoked','ai.message.delta','ai.message.completed','ai.message.failed'];
  const channels=[echo.private('user.'+user.id),echo.private('workspace.'+workspaceId),...roomIds.split(',').filter(Boolean).map(id=>echo.private('room.'+id))];
  for(const channel of channels)for(const name of names)channel.listen('.'+name,handler);
  const connection=echo.connector.pusher.connection;connection.bind('state_change',({current}:{current:string})=>{setStatus(current);if(current==='connected')refresh();});
  // REST catch-up also covers event loss and revoked subscriptions.
  const timer=setInterval(refresh,30000);
  return()=>{clearInterval(timer);echo.disconnect();};
 },[user?.id,workspaceId,roomIds,client]);
 return status;
}
