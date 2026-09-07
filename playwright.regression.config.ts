import {defineConfig,devices} from '@playwright/test';

export default defineConfig({
 testDir:'./tests/regression',
 testMatch:'**/*.regression.ts',
 fullyParallel:false,
 workers:1,
 retries:0,
 timeout:45_000,
 outputDir:'artifacts/regression/raw',
 reporter:[
  ['list'],
  ['html',{outputFolder:'artifacts/regression/report',open:'never'}],
 ],
 use:{
  baseURL:process.env.PLAYWRIGHT_BASE_URL??'http://127.0.0.1:5174',
  screenshot:'on',
  trace:'on',
  video:'on',
  actionTimeout:15_000,
 },
 projects:[
  {name:'desktop',use:{...devices['Desktop Chrome'],viewport:{width:1440,height:960}}},
  {name:'mobile',use:{...devices['iPhone 13'],browserName:'chromium'}},
 ],
});
