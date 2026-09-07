import {defineConfig} from '@playwright/test';

export default defineConfig({
 testDir:'./tests/e2e',
 testMatch:'**/*.e2e.ts',
 fullyParallel:true,
 reporter:'list',
 use:{
  baseURL:process.env.PLAYWRIGHT_BASE_URL??'http://127.0.0.1:5173',
  screenshot:'only-on-failure',
  trace:'retain-on-failure',
  launchOptions:process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE?{executablePath:process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE}:{},
 },
 projects:[
  {name:'desktop',use:{viewport:{width:1440,height:960}}},
  {name:'mobile',use:{viewport:{width:390,height:844}}},
 ],
});
