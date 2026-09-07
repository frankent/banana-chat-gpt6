self.addEventListener('push',event=>{
 const payload=event.data?.json?.()??{};
 const data=payload.data??payload;
 event.waitUntil(self.registration.showNotification(payload.title??'Banana Chat',{
  body:payload.body??'You have a new message',icon:'/favicon.svg',badge:'/favicon.svg',
  tag:data.collapse_key??data.room_id??data.conversation_id??'banana-chat',data,
 }));
});
self.addEventListener('notificationclick',event=>{
 event.notification.close();const data=event.notification.data??{};
 const path=data.room_id?`/rooms/${data.room_id}${data.message_id?`#message-${data.message_id}`:''}`:data.conversation_id?`/ai/${data.conversation_id}`:'/';
 event.waitUntil(clients.matchAll({type:'window',includeUncontrolled:true}).then(windows=>{const existing=windows[0];if(existing){existing.navigate(path);return existing.focus();}return clients.openWindow(path);}));
});
