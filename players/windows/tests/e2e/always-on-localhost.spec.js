import { test, expect, _electron as electron, chromium } from '@playwright/test';
import { spawn, spawnSync } from 'node:child_process';
import { mkdtemp, rm, readFile, realpath, mkdir, rename, access, readdir, stat } from 'node:fs/promises';
import { randomBytes } from 'node:crypto';
import { createServer } from 'node:net';
import path from 'node:path';import os from 'node:os';
test('real Laravel localhost plus Windows Player offline restart quota upload and XLSX',async({},testInfo)=>{
 test.skip(process.env.CMS_REAL_ACCEPTANCE!=='1','Explicit opt-in for temporary local database fixtures and real Laravel server.');test.setTimeout(180000);
 const probe=createServer();await new Promise(resolve=>probe.listen(0,'127.0.0.1',resolve));const port=probe.address().port;await new Promise(resolve=>probe.close(resolve));
 const repo=path.resolve('../..'),tag=randomBytes(6).toString('hex'),base='http://127.0.0.1:'+port;
 const fixture=input=>{const r=spawnSync('php',['tests/Browser/always-on-fixture.php'],{cwd:repo,input:JSON.stringify({tag,...input}),encoding:'utf8',windowsHide:true});if(r.status || !r.stdout.trim().startsWith('{'))throw Error('Real acceptance fixture failed: '+r.stderr.slice(0,160));return JSON.parse(r.stdout);};
 const tempRoot=await realpath(os.tmpdir()),directory=await mkdtemp(path.join(tempRoot,'cms-real-always-'));if(!directory.startsWith(tempRoot+path.sep))throw Error('Temporary directory escaped');
 const env={...process.env};delete env.ELECTRON_RUN_AS_NODE;let server,application,browser,created=false,hadHot=false;const hot=path.join(repo,'public','hot'),hotBackup=path.join(repo,'public','hot.always-on-'+tag);const serverLog=[];
 const start=async()=>{server=spawn('php',['-S','127.0.0.1:'+port,'-t','public','server.php'],{cwd:repo,env,windowsHide:true,stdio:['ignore','pipe','pipe']});for(const stream of [server.stdout,server.stderr])stream.on('data',chunk=>{for(const line of chunk.toString().split('\n'))if(/Accepted|Closing|URI:|Address already in use|Development Server/.test(line)){serverLog.push(line.trim());if(serverLog.length>40)serverLog.shift();}});await expect.poll(async()=>fetch(base+'/up',{headers:{Connection:'close'},signal:AbortSignal.timeout(3000)}).then(async r=>{await r.arrayBuffer();return r.status;}).catch(()=>0),{timeout:15000}).toBe(200);};
 const stop=async()=>{if(server && server.exitCode===null){const done=new Promise(resolve=>server.once('exit',resolve));server.kill();await done;}server=null;};
 const launch=async()=>{application=await electron.launch({args:['.','--smoke-test','--disable-gpu','--disable-accelerated-video-decode','--allow-localhost-http','--test-data-dir='+directory],cwd:process.cwd(),env});return application.firstWindow();};
 const localCounts=async()=>{const text=await readFile(path.join(directory,'playback','playback.journal'),'utf8').catch(()=>'');const rows=text.slice(0,text.lastIndexOf('\n')+1).split('\n').filter(Boolean).map(JSON.parse),counts={};for(const row of rows)if(row.type==='end'&&row.event.status==='completed')counts[row.event.playlist_zone_item_id]=(counts[row.event.playlist_zone_item_id]??0)+1;return counts;};
 try{
  const response=await fetch('https://raw.githubusercontent.com/chromium/chromium/main/media/test/data/bear.mp4',{signal:AbortSignal.timeout(30000)});if(!response.ok)throw Error('H264 fixture unavailable');created=true;const data=fixture({action:'setup',video:Buffer.from(await response.arrayBuffer()).toString('base64')});await start();let page=await launch();
  await application.evaluate(()=>{const original=globalThis.fetch;globalThis.__cmsNetwork=[];globalThis.fetch=async(...args)=>{const url=new URL(args[0]);try{const r=await Reflect.apply(original,globalThis,args);globalThis.__cmsNetwork.push({path:url.pathname,status:r.status,length:r.headers.get('content-length'),type:r.headers.get('content-type')});return r;}catch(error){globalThis.__cmsNetwork.push({path:url.pathname,error:error.code??error.name});throw error;}};});
  await page.locator('#origin').fill(base);await page.locator('#code').fill(data.activation);await page.locator('#activate').click();
  await expect.poll(()=>page.evaluate(()=>{const v=document.querySelector('#canvas video');return v?.videoWidth??0;}),{timeout:60000}).toBe(320);
  await expect.poll(()=>page.evaluate(()=>document.querySelector('#canvas img')?.naturalWidth??0)).toBe(1);
  await expect.poll(()=>page.evaluate(()=>document.querySelector('#canvas video')?.getVideoPlaybackQuality().totalVideoFrames??0)).toBeGreaterThan(0);
  await expect.poll(async()=>(await localCounts())[data.ids.video]??0,{timeout:20000}).toBeGreaterThanOrEqual(1);
  await stop();await application.close();application=null;
  page=await launch();await expect.poll(async()=>(await localCounts())[data.ids.video]??0,{timeout:20000}).toBe(3);await expect.poll(async()=>(await localCounts())[data.ids.image]??0,{timeout:20000}).toBe(5);
  await application.close();application=null;page=await launch();await expect.poll(()=>page.locator('#setup').isHidden()).toBe(true);await new Promise(r=>setTimeout(r,1800));expect((await localCounts())[data.ids.video]).toBe(3);expect((await localCounts())[data.ids.image]).toBe(5);
  await start();await page.evaluate(()=>window.player.reconnect());
  await expect.poll(()=>{const report=fixture({action:'inspect'});return report.counts.map(x=>Number(x.plays)).sort();},{timeout:45000}).toEqual([3,5]);
  await expect.poll(()=>fixture({action:'inspect'}).applied,{timeout:15000}).toBe(true);expect(fixture({action:'inspect'}).historyRevision).toBe(true);
  await expect.poll(()=>fixture({action:'inspect'}).pending,{timeout:15000}).toBe(0);
  const journal=(await readFile(path.join(directory,'playback','playback.journal'),'utf8')).trim().split('\n').map(JSON.parse);
  const first=fixture({action:'inspect'});expect(first.events).toBe(journal.filter(row=>row.type==='end').length);await page.evaluate(()=>window.player.reconnect());await new Promise(r=>setTimeout(r,1200));expect(fixture({action:'inspect'}).events).toBe(first.events);
  try{await access(hot);await rename(hot,hotBackup);hadHot=true;}catch(e){if(e.code!=='ENOENT')throw e;}
  browser=await chromium.launch({channel:'chrome',headless:true});const admin=await browser.newPage({viewport:{width:1440,height:1100},acceptDownloads:true});await admin.goto(base+'/login');await admin.waitForFunction(()=>!!document.querySelector('[wire\\:id]')?.__livewire);await admin.locator('input[type=email]').fill(data.email);await admin.locator('input[type=password]').fill(data.password);await admin.getByRole('button',{name:/log in/i}).click();await admin.waitForURL('**/admin/dashboard');await admin.waitForLoadState('networkidle');const guide=admin.getByRole('dialog');await guide.getByRole('checkbox').check();await guide.getByRole('button',{name:'Mulai Menggunakan'}).click();await admin.goto(base+'/admin/proof-of-play');await expect(admin.getByRole('cell',{name:'Actual video',exact:true}).first()).toBeVisible();
  await mkdir(path.join(repo,'docs','screenshots'),{recursive:true});await admin.screenshot({path:path.join(repo,'docs','screenshots','proof-of-play.png'),fullPage:true});
  const download=admin.waitForEvent('download');await admin.getByRole('link',{name:'Ekspor Excel (.xlsx)'}).click();const file=await download;expect(file.suggestedFilename()).toBe('proof-of-play.xlsx');const exported=await readFile(await file.path());expect(exported.subarray(0,2).toString()).toBe('PK');
  console.log(JSON.stringify({realLaravel:true,realElectron:true,decodedH264Frame:true,parallelImage:true,laravelStopped:true,restartOffline:true,quotaCompleted:[3,5],reconnectedWithoutDuplicates:true,appliedConfirmed:true,xlsxBytes:exported.length}));
 }catch(error){
  try{if(application){const network=await application.evaluate(()=>globalThis.__cmsNetwork??[]),files=[],root=path.join(directory,'content','blobs');for(const file of await readdir(root).catch(()=>[]))files.push({partial:file.endsWith('.part'),size:(await stat(path.join(root,file))).size});console.log(JSON.stringify({network,files}));const page=await application.firstWindow();await testInfo.attach('safe-status',{body:JSON.stringify(await page.evaluate(async()=>{const s=(await window.player.status()).value;return {configured:s.configured,online:s.online,lastError:s.lastError,state:s.playback.state,revision:s.revision};})),contentType:'application/json'});}}catch{}
  console.log(JSON.stringify({serverExited:server?.exitCode,requests:serverLog}));throw error;
 }finally{if(hadHot){try{await access(hot);}catch(e){if(e.code==='ENOENT')await rename(hotBackup,hot);else throw e;}}await browser?.close();await application?.close();await stop();if(created)fixture({action:'cleanup'});await rm(directory,{recursive:true,force:true});}
});
