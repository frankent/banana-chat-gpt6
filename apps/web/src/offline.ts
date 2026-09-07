import type {CacheAdapter} from '@banana/chat-core';

export class IndexedDbCache implements CacheAdapter {
 private database:Promise<IDBDatabase>;
 constructor(){this.database=new Promise((resolve,reject)=>{const request=indexedDB.open('banana-chat',1);request.onupgradeneeded=()=>request.result.createObjectStore('cache');request.onsuccess=()=>resolve(request.result);request.onerror=()=>reject(request.error);});}
 async read<T>(key:string):Promise<T|null>{const db=await this.database;return new Promise((resolve,reject)=>{const request=db.transaction('cache').objectStore('cache').get(key);request.onsuccess=()=>resolve((request.result as T)??null);request.onerror=()=>reject(request.error);});}
 async write<T>(key:string,value:T):Promise<void>{const db=await this.database;return new Promise((resolve,reject)=>{const request=db.transaction('cache','readwrite').objectStore('cache').put(value,key);request.onsuccess=()=>resolve();request.onerror=()=>reject(request.error);});}
 async clear():Promise<void>{const db=await this.database;return new Promise((resolve,reject)=>{const request=db.transaction('cache','readwrite').objectStore('cache').clear();request.onsuccess=()=>resolve();request.onerror=()=>reject(request.error);});}
}

export const offlineCache=typeof indexedDB==='undefined'?null:new IndexedDbCache();
