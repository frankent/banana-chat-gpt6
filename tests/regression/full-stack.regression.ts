import {expect,test,type Page} from '@playwright/test';
import {mkdir} from 'node:fs/promises';
import path from 'node:path';

const apiBase=process.env.PLAYWRIGHT_API_BASE_URL??'http://127.0.0.1:8010';
const captureRoot=path.resolve('artifacts/regression/screenshots');

async function capture(page:Page,name:string,project:string){
 await mkdir(captureRoot,{recursive:true});
 await page.screenshot({path:path.join(captureRoot,`${project}-${name}.png`),fullPage:true});
}

test('backend health endpoint reports ready dependencies',async({page,request},testInfo)=>{
 const response=await request.get(`${apiBase}/api/v1/health`);
 expect(response.ok()).toBeTruthy();
 const health=await response.json();
 expect(health.data.status).toBe('ok');

 await page.goto(`${apiBase}/api/v1/health`);
 await expect(page.locator('body')).toContainText('"status":"ok"');
 await capture(page,'01-backend-health',testInfo.project.name);
});

test('frontend login, room history, message and attachment flow',async({page},testInfo)=>{
 await page.goto('/');
 await expect(page.getByRole('heading',{name:/Welcome back/})).toBeVisible();
 await capture(page,'02-frontend-login',testInfo.project.name);

 await page.getByLabel('Username',{exact:true}).fill('tony');
 await page.getByLabel('Password',{exact:true}).fill('BananaChat2026!');
 await page.getByRole('button',{name:'Let’s get you connected'}).click();
 await expect(page.getByText('Banana Studio',{exact:true}).first()).toBeVisible();
 await capture(page,'03-frontend-room-list',testInfo.project.name);

 await page.getByRole('button',{name:/general/}).click();
 await expect(page).toHaveURL(/\/rooms\//);
 await expect(page.getByText('Something good is coming together ✨',{exact:true})).toBeVisible();

 const message=`Playwright regression ${testInfo.project.name}`;
 await page.getByPlaceholder(/Write a message/).fill(message);
 await page.getByRole('button',{name:'Send'}).click();
 await expect(page.locator('article').filter({hasText:message}).last()).toBeVisible();
 await expect(page.getByPlaceholder(/Write a message/)).toBeEnabled();

 const png=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAgAAAAICAIAAABLbSncAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAFElEQVQImWP8esqdARtgwio6aCUAmGkCFix6ppgAAAAASUVORK5CYII=','base64');
 await page.locator('input[type=file]').setInputFiles({name:`banana-${testInfo.project.name}.png`,mimeType:'image/png',buffer:png});
 await expect(page.getByText('Ready',{exact:true})).toBeVisible({timeout:20_000});
 await page.getByRole('button',{name:'Send'}).click();
 await expect(page.getByRole('img',{name:`banana-${testInfo.project.name}.png`}).last()).toBeVisible({timeout:15_000});
 await capture(page,'04-frontend-chat',testInfo.project.name);
});

test('admin login and management resources are available',async({page},testInfo)=>{
 await page.goto(`${apiBase}/admin/login`);
 await expect(page.getByText('Banana Chat Admin')).toBeVisible();
 await capture(page,'05-admin-login',testInfo.project.name);

 await page.getByRole('textbox',{name:/Username/}).fill('tony');
 await page.getByRole('textbox',{name:/Password/}).fill('BananaChat2026!');
 await page.getByRole('button',{name:/sign in/i}).click();
 await expect(page).toHaveURL(/\/admin\/?$/);
 await expect(page.getByText('Dashboard',{exact:true}).first()).toBeVisible();
 await capture(page,'06-admin-dashboard',testInfo.project.name);

 await page.getByRole('link',{name:'Users',exact:true}).click();
 await expect(page).toHaveURL(/\/admin\/users/);
 await expect(page.getByRole('row',{name:/tony Tony active/i})).toBeVisible();
 await capture(page,'07-admin-users',testInfo.project.name);
});
