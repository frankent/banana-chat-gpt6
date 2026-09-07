import {afterEach,describe,expect,it,vi} from 'vitest';
import {ApiClient} from './index';

describe('ApiClient browser transport',()=>{
 afterEach(()=>vi.unstubAllGlobals());

 it('invokes the native fetch transport without an ApiClient receiver',async()=>{
  let receiver:unknown='not-called';
  vi.stubGlobal('fetch',function(this:unknown){
   receiver=this;
   return Promise.resolve(new Response(JSON.stringify({data:{status:'ok'}}),{
    status:200,
    headers:{'Content-Type':'application/json'},
   }));
  });
  const client=new ApiClient('https://banana.test',{get:async()=>null,set:async()=>{}});

  await expect(client.get<{status:string}>('/health')).resolves.toEqual({status:'ok'});
  expect(receiver).toBeUndefined();
 });
});
