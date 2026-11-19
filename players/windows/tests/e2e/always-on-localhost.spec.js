import { test, expect, _electron as electron, chromium } from '@playwright/test';
import { spawn, spawnSync } from 'node:child_process';
import { mkdtemp, rm, readFile, realpath, access, readdir, stat } from 'node:fs/promises';
import { randomBytes, createHash } from 'node:crypto';
import { createServer } from 'node:net';
import path from 'node:path';import os from 'node:os';
// Traces can contain the temporary login credentials and activation code.
test.use({ trace: 'off' });
test('real Laravel localhost plus Windows Player offline restart quota upload and XLSX',async({},testInfo)=>{
 test.skip(process.env.CMS_REAL_ACCEPTANCE!=='1','Explicit opt-in for temporary local database fixtures and real Laravel server.');test.setTimeout(180000);
 const probe=createServer();await new Promise(resolve=>probe.listen(0,'127.0.0.1',resolve));const port=probe.address().port;await new Promise(resolve=>probe.close(resolve));
 const repo=path.resolve('../..'),tag=randomBytes(6).toString('hex'),owner=randomBytes(32).toString('hex'),base='http://127.0.0.1:'+port;
 const db=process.env.CMS_ACCEPTANCE_DB_DATABASE,user=process.env.CMS_ACCEPTANCE_DB_USERNAME;
 if(process.env.CMS_ACCEPTANCE_DB_HOST!=='127.0.0.1' || !/^[a-zA-Z0-9_]+_acceptance_test$/.test(db??'') || !user || user.toLowerCase()==='root' || process.env.CMS_ACCEPTANCE_DB_PASSWORD===undefined)throw Error('Explicit isolated acceptance database configuration required');
 const env={...process.env,APP_ENV:'testing',DB_CONNECTION:'mysql',DB_HOST:'127.0.0.1',DB_PORT:process.env.CMS_ACCEPTANCE_DB_PORT || '3306',DB_DATABASE:db,DB_USERNAME:user,DB_PASSWORD:process.env.CMS_ACCEPTANCE_DB_PASSWORD,DB_URL:'',DB_SOCKET:'',CACHE_STORE:'array',SESSION_DRIVER:'database',QUEUE_CONNECTION:'sync',MAIL_MAILER:'array'};delete env.ELECTRON_RUN_AS_NODE;
 const playerEnv={...env};for(const key of Object.keys(playerEnv))if(key.startsWith('DB_') || key.startsWith('CMS_ACCEPTANCE_DB_'))delete playerEnv[key];
 const fixture=input=>{const r=spawnSync('php',['tests/Browser/always-on-fixture.php'],{cwd:repo,env,input:JSON.stringify({tag,owner,...input}),encoding:'utf8',windowsHide:true});if(r.status || !r.stdout.trim().startsWith('{'))throw Error('Real acceptance fixture failed; check isolated database and fixture preconditions');return JSON.parse(r.stdout);};
 const tempRoot=await realpath(os.tmpdir()),directory=await mkdtemp(path.join(tempRoot,'cms-real-always-'));if(!directory.startsWith(tempRoot+path.sep))throw Error('Temporary directory escaped');
 let server,application,browser,created=false;const hot=path.join(repo,'public','hot');const serverLog=[];
 const start=async()=>{let started=false;server=spawn('php',['-S','127.0.0.1:'+port,'-t','public','server.php'],{cwd:repo,env,windowsHide:true,stdio:['ignore','pipe','pipe']});for(const stream of [server.stdout,server.stderr])stream.on('data',chunk=>{for(const line of chunk.toString().split('\n')){if(/Development Server .* started/.test(line))started=true;if(/Address already in use|Development Server/.test(line)){serverLog.push(line.trim());if(serverLog.length>40)serverLog.shift();}}});await expect.poll(async()=>{if(!started || server.exitCode!==null)return 0;return fetch(base+'/up',{headers:{Connection:'close'},signal:AbortSignal.timeout(3000)}).then(async r=>{await r.arrayBuffer();return r.status;}).catch(()=>0);},{timeout:15000}).toBe(200);};
 const stop=async()=>{if(server && server.exitCode===null){const done=new Promise(resolve=>server.once('exit',resolve));server.kill();await done;}server=null;};
 const launch=async()=>{application=await electron.launch({executablePath:process.env.CMS_TEST_ELECTRON_EXECUTABLE || undefined,args:['.','--smoke-test','--disable-gpu','--disable-accelerated-video-decode','--allow-localhost-http','--test-data-dir='+directory],cwd:process.cwd(),env:playerEnv});return application.firstWindow();};
 const localCounts=async()=>{const text=await readFile(path.join(directory,'playback','playback.journal'),'utf8').catch(()=>'');const rows=text.slice(0,text.lastIndexOf('\n')+1).split('\n').filter(Boolean).map(JSON.parse),counts={};for(const row of rows)if(row.type==='end'&&row.event.status==='completed')counts[row.event.playlist_zone_item_id]=(counts[row.event.playlist_zone_item_id]??0)+1;return counts;};
 try{
  try{await access(hot);throw Error('public/hot exists; stop Vite and remove it through its normal shutdown before acceptance');}catch(e){if(e.code!=='ENOENT')throw e;}
  let video;if(process.env.CMS_ACCEPTANCE_VIDEO_PATH)video=await readFile(process.env.CMS_ACCEPTANCE_VIDEO_PATH);else{const response=await fetch('https://raw.githubusercontent.com/chromium/chromium/main/media/test/data/bear.mp4',{signal:AbortSignal.timeout(30000)});if(!response.ok)throw Error('H264 fixture unavailable');video=Buffer.from(await response.arrayBuffer());}
  if(createHash('sha256').update(video).digest('hex')!=='aa2786362e3d61646390ba23cb0639b6ae9c9f48b58a882a7622d94c8afe7fe1')throw Error('H264 fixture checksum mismatch');
  created=true;const data=fixture({action:'setup',video:video.toString('base64')});await start();let page=await launch();
  await application.evaluate(()=>{const original=globalThis.fetch;globalThis.__cmsNetwork=[];globalThis.fetch=async(...args)=>{const url=new URL(args[0]);try{const r=await Reflect.apply(original,globalThis,args);globalThis.__cmsNetwork.push({path:url.pathname,status:r.status,length:r.headers.get('content-length'),type:r.headers.get('content-type')});return r;}catch(error){globalThis.__cmsNetwork.push({path:url.pathname,error:error.code??error.name});throw error;}};});
  await page.locator('#origin').fill(base);await page.locator('#code').fill(data.activation);await page.locator('#activate').click();
  await expect.poll(()=>page.evaluate(()=>{const v=document.querySelector('#canvas video');return v?.videoWidth??0;}),{timeout:60000}).toBe(320);
  await expect.poll(()=>page.evaluate(()=>document.querySelector('#canvas img')?.naturalWidth??0)).toBe(1);
  const before=await page.evaluate(()=>{const v=document.querySelector('#canvas video');return {time:v.currentTime,frames:v.getVideoPlaybackQuality().totalVideoFrames};});
  await expect.poll(()=>page.evaluate(()=>{const v=document.querySelector('#canvas video');return v?.getVideoPlaybackQuality().totalVideoFrames??0;})).toBeGreaterThan(before.frames);
  await expect.poll(()=>page.evaluate(()=>document.querySelector('#canvas video')?.currentTime??0)).toBeGreaterThan(before.time);
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
  browser=await chromium.launch({channel:'chrome',headless:true});const admin=await browser.newPage({viewport:{width:1440,height:1100},acceptDownloads:true});await admin.goto(base+'/login');await admin.waitForFunction(()=>!!document.querySelector('[wire\\:id]')?.__livewire);await admin.locator('input[type=email]').fill(data.email);await admin.locator('input[type=password]').fill(data.password);await admin.getByRole('button',{name:/log in/i}).click();await admin.waitForURL('**/admin/dashboard');await admin.waitForLoadState('networkidle');const guide=admin.getByRole('dialog');await guide.getByRole('checkbox').check();await guide.getByRole('button',{name:'Mulai Menggunakan'}).click();await admin.goto(base+'/admin/proof-of-play');await expect(admin.getByRole('cell',{name:'Actual video',exact:true}).first()).toBeVisible();
  await admin.screenshot({path:path.join(directory,'proof-of-play.png'),fullPage:true});
  const download=admin.waitForEvent('download');await admin.getByRole('link',{name:'Ekspor Excel (.xlsx)'}).click();const file=await download;expect(file.suggestedFilename()).toBe('proof-of-play.xlsx');const exported=await readFile(await file.path());expect(exported.subarray(0,2).toString()).toBe('PK');
  const parsed=spawnSync('php',[path.join(repo,'tests/Browser/read-acceptance-xlsx.php'),await file.path()],{cwd:repo,env,encoding:'utf8',windowsHide:true});if(parsed.status!==0)throw Error('XLSX parsing failed');const workbook=JSON.parse(parsed.stdout);
  expect(Object.keys(workbook)).toEqual(['Summary','Detail']);expect(workbook.Detail.length-1).toBe(first.events);
  for(const type of ['video','image']){const expected=first.summary.find(r=>Number(r.media_asset_id)===data.ids[type+'_media']);const row=workbook.Summary.find(r=>r[3]==='Actual '+type);expect(row).toBeTruthy();expect(row[1]).toBe('Real Windows Acceptance');expect(row[2]).toBe('Always-On Real Playback');expect(row.slice(4,8).map(Number)).toEqual(['completed','partial','failed','played_ms'].map(k=>Number(expected[k])));}
  console.log(JSON.stringify({realLaravel:true,realElectron:true,decodedH264Frame:true,parallelImage:true,laravelStopped:true,restartOffline:true,quotaCompleted:[3,5],reconnectedWithoutDuplicates:true,appliedConfirmed:true,xlsxBytes:exported.length}));
 }catch(error){
  try{if(application){const network=await application.evaluate(()=>globalThis.__cmsNetwork??[]),files=[],root=path.join(directory,'content','blobs');for(const file of await readdir(root).catch(()=>[]))files.push({partial:file.endsWith('.part'),size:(await stat(path.join(root,file))).size});console.log(JSON.stringify({network,files}));const page=await application.firstWindow();await testInfo.attach('safe-status',{body:JSON.stringify(await page.evaluate(async()=>{const s=(await window.player.status()).value;return {configured:s.configured,online:s.online,lastError:s.lastError,state:s.playback.state,revision:s.revision};})),contentType:'application/json'});}}catch{}
  console.log(JSON.stringify({serverExited:server?.exitCode,requests:serverLog}));throw error;
 }finally{await browser?.close();await application?.close();await stop();try{if(created)fixture({action:'cleanup'});}finally{await rm(directory,{recursive:true,force:true});}}
});
