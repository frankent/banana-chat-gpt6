import http from 'k6/http';
import {check,sleep} from 'k6';
import {randomUUID} from 'https://jslib.k6.io/k6-utils/1.6.0/index.js';

export const options={scenarios:{messages:{executor:'constant-arrival-rate',rate:Number(__ENV.RATE||17),timeUnit:'1s',duration:__ENV.DURATION||'2m',preAllocatedVUs:20,maxVUs:150}},thresholds:{http_req_failed:['rate<0.01'],'http_req_duration{endpoint:send}':['p(95)<300','p(99)<800']}};
const base=__ENV.BASE_URL||'http://127.0.0.1:8000/api/v1';

export function setup(){
 const response=http.post(`${base}/auth/login`,JSON.stringify({username:__ENV.USERNAME||'tony',password:__ENV.PASSWORD||'BananaChat2026!',device:{platform:'web',name:'k6',app_version:'0.1.0'}}),{headers:{'Content-Type':'application/json'}});
 check(response,{'login succeeds':r=>r.status===200});const auth=response.json('data');
 const rooms=http.get(`${base}/rooms?limit=1`,{headers:{Authorization:`Bearer ${auth.access_token}`,'X-Workspace-Id':auth.workspaces[0].workspace.id}}).json('data');
 return {token:auth.access_token,workspace:auth.workspaces[0].workspace.id,room:rooms[0].id};
}

export default function(data){
 const response=http.post(`${base}/rooms/${data.room}/messages`,JSON.stringify({client_message_id:randomUUID(),body:`k6 message ${__VU}/${__ITER}`}),{headers:{Authorization:`Bearer ${data.token}`,'X-Workspace-Id':data.workspace,'Content-Type':'application/json'},tags:{endpoint:'send'}});
 check(response,{'message accepted':r=>r.status===201});sleep(.01);
}
