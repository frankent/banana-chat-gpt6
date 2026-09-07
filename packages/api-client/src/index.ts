import type {Tokens} from '@banana/shared';
export class ApiError extends Error {
 constructor(public code:string, public status:number, public details:Record<string,unknown>={}, message=code){super(message);this.name='ApiError';}
}
export interface TokenStorage {get():Promise<string|null>;set(token:string|null):Promise<void>;}
export class TokenManager {
 accessToken:string|null=null;
 sessionId:string|null=null;
 private flight:Promise<string>|null=null;
 constructor(private storage:TokenStorage, private refreshRequest:(token:string)=>Promise<Tokens>,private onLogout:()=>void){}
 async set(tokens:Tokens){this.accessToken=tokens.access_token;this.sessionId=tokens.session_id;await this.storage.set(tokens.refresh_token);}
 async clear(){this.accessToken=null;this.sessionId=null;await this.storage.set(null);this.onLogout();}
 refresh():Promise<string>{
  if(this.flight)return this.flight;
  this.flight=(async()=>{const token=await this.storage.get();if(!token)throw new ApiError('AUTH_TOKEN_INVALID',401);const result=await this.refreshRequest(token);await this.set(result);return result.access_token;})().catch(async error=>{if(error instanceof ApiError&&[401,403].includes(error.status))await this.clear();throw error;}).finally(()=>{this.flight=null;});
  return this.flight;
 }
}
export class ApiClient {
 workspaceId:string|null=null;
 app:{platform:'web'|'ios'|'android';version:string}|null=null;
 tokens:TokenManager;
 constructor(public baseUrl:string,storage:TokenStorage,onLogout:()=>void=()=>{},private transport:typeof fetch=(input,init)=>fetch(input,init)){
  this.tokens=new TokenManager(storage,token=>this.request<Tokens>('/auth/refresh',{method:'POST',body:JSON.stringify({refresh_token:token})},false),onLogout);
 }
 async raw<T>(path:string, options:RequestInit={},auth=true):Promise<T>{
  const send=()=>this.transport(this.baseUrl+path,{...options,headers:{'Accept':'application/json','Content-Type':'application/json',...(this.workspaceId?{'X-Workspace-Id':this.workspaceId}:{}),...(this.app?{'X-App-Platform':this.app.platform,'X-App-Version':this.app.version}:{}),...(auth&&this.tokens.accessToken?{'Authorization':`Bearer ${this.tokens.accessToken}`}:{ }),...options.headers}});
  let response=await send();
  if(response.status===401&&auth){await this.tokens.refresh();response=await send();}
  if(response.status===204)return undefined as T;
  const payload=await response.json();if(!response.ok)throw new ApiError(payload.error?.code??'INTERNAL_ERROR',response.status,payload.error?.details??{},payload.error?.message);
  return payload as T;
 }
 async request<T>(path:string,options:RequestInit={},auth=true):Promise<T>{const result=await this.raw<{data:T}|undefined>(path,options,auth);return result?.data as T;}
 get<T>(path:string){return this.request<T>(path);}
 post<T>(path:string,body:unknown={}){return this.request<T>(path,{method:'POST',body:JSON.stringify(body)});}
 patch<T>(path:string,body:unknown){return this.request<T>(path,{method:'PATCH',body:JSON.stringify(body)});}
 delete(path:string){return this.request<void>(path,{method:'DELETE'});}
}
