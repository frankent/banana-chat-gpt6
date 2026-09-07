import {expect,test} from '@playwright/test';

test('a seeded member can open the general room',async({page})=>{
 await page.goto('/');
 await page.getByLabel('Username',{exact:true}).fill('tony');
 await page.getByLabel('Password',{exact:true}).fill('BananaChat2026!');
 await page.getByRole('button',{name:'Let’s get you connected'}).click();

 await expect(page.getByText('Banana Studio',{exact:true}).first()).toBeVisible();
 await page.getByRole('button',{name:/general/}).click();
 await expect(page).toHaveURL(/\/rooms\//);
 await expect(page.getByText('Something good is coming together ✨',{exact:true})).toBeVisible();
 await expect(page.getByPlaceholder(/Write a message/)).toBeVisible();

 const png=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAgAAAAICAIAAABLbSncAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAFElEQVQImWP8esqdARtgwio6aCUAmGkCFix6ppgAAAAASUVORK5CYII=','base64');
 await page.locator('input[type=file]').setInputFiles({name:'banana-e2e.png',mimeType:'image/png',buffer:png});
 await expect(page.getByText('Ready',{exact:true})).toBeVisible({timeout:15000});
 await page.getByRole('button',{name:'Send'}).click();
 await expect(page.getByRole('img',{name:'banana-e2e.png'}).last()).toBeVisible({timeout:10000});
});
