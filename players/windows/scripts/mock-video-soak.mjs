import { _electron as electron } from '@playwright/test';
import { createServer } from 'node:http';
import { createHash } from 'node:crypto';
import { mkdtemp, readFile, realpath, writeFile } from 'node:fs/promises';
import path from 'node:path';
import os from 'node:os';
import { manifest, publicId, token } from '../tests/fixtures.js';
import { summarizeJournal } from './soak-metrics.js';

// Isolated mock Device, local mock API and known test media only. Never attaches to an operator Player.
const seconds = Number(process.argv.find(x => x.startsWith('--seconds='))?.split('=')[1] ?? 30);
if (!Number.isFinite(seconds) || seconds < 15 || seconds > 3600) throw Error('seconds must be between 15 and 3600');
const videoPath = process.env.CMS_TEST_VIDEO_PATH || path.resolve('../../storage/app/phase1-acceptance-bear.mp4');
const video = await readFile(videoPath), hash = createHash('sha256').update(video).digest('hex');
if (hash !== 'aa2786362e3d61646390ba23cb0639b6ae9c9f48b58a882a7622d94c8afe7fe1') throw Error('video_fixture_checksum_invalid');
const image = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aD1sAAAAASUVORK5CYII=', 'base64');
const imageHash = createHash('sha256').update(image).digest('hex'), m = manifest();
m.schemaVersion = 2; m.state = 'playing'; m.currentSchedule = null; m.fallbackPlaylistId = 1; m.commandId = 'mock-soak';
m.schedulePlan = m.schedulePlan.map(s => ({...s,state:'playing',schedule:null,playlistId:1,commandId:m.commandId,source:'primary'})); m.fallbackSelection = m.schedulePlan[0];
const playlist = m.playlists[0], page = playlist.pages[0], zone = page.zones[0]; page.duration = playlist.totalDuration = 60; zone.width = 960;
Object.assign(m.media[0], {size:video.length,type:'video',mimeType:'video/mp4',checksum:{algorithm:'sha256',value:hash}});
Object.assign(zone.items[0], {type:'video',title:'Test video',duration:null,durationMode:'auto',knownDuration:1.068118,maxPlays:0,counterEpoch:'00000000-0000-4000-8000-000000000002',completedCount:0});
m.media.push({id:2,type:'image',mimeType:'image/png',size:image.length,checksum:{algorithm:'sha256',value:imageHash},available:true,downloadUrl:'/api/device/media/2'});
page.zones.push({...structuredClone(zone),id:2,name:'Image',x:960,z_index:1,items:[{id:2,sort_order:1,mediaId:2,title:'Test image',type:'image',duration:3,durationMode:'manual',knownDuration:null,available:true,maxPlays:0,counterEpoch:'00000000-0000-4000-8000-000000000003',completedCount:0}]});
const tempRoot = await realpath(os.tmpdir()), directory = await mkdtemp(path.join(tempRoot,'cms-mock-soak-'));
if (!directory.startsWith(tempRoot+path.sep)) throw Error('temporary_path_invalid');
const received = new Set(), stats = {heartbeats:0,duplicateEvents:0}, samples=[];
const server = createServer(async (req,res) => {
    if(req.url==='/api/device/activate'){res.writeHead(201,{'Content-Type':'application/json'});res.end(JSON.stringify({device:{public_id:publicId},credential:token}));return;}
    if(req.headers.authorization!=='Bearer '+token){res.writeHead(401);res.end();return;}
    if(req.url==='/api/device/heartbeat'){stats.heartbeats++;res.writeHead(200,{'Content-Type':'application/json'});res.end('{"heartbeat_interval_seconds":60}');return;}
    if(req.url==='/api/device/manifest'){res.setHeader('X-Server-Time-Utc',new Date().toISOString());res.setHeader('ETag','W/"'+m.revision+'"');if(req.headers['if-none-match']){res.writeHead(304);res.end();return;}res.setHeader('Content-Type','application/json');res.end(JSON.stringify(m));return;}
    if(req.url==='/api/device/media/1' || req.url==='/api/device/media/2'){const bytes=req.url.endsWith('/1')?video:image,h=req.url.endsWith('/1')?hash:imageHash;res.writeHead(200,{ETag:'"'+h+'"','X-Content-Revision':m.revision,'Content-Length':bytes.length});res.end(bytes);return;}
    if(req.url==='/api/device/playback-events'){let body='';for await(const chunk of req)body+=chunk;const events=JSON.parse(body).events;for(const event of events){if(received.has(event.event_id))stats.duplicateEvents++;received.add(event.event_id);}res.writeHead(200,{'Content-Type':'application/json'});res.end(JSON.stringify({acknowledged:events.map(e=>e.event_id)}));return;}
    if(req.url==='/api/device/playback-state'){res.writeHead(200,{'Content-Type':'application/json'});res.end('{}');return;}
    res.writeHead(404);res.end();
});
const listen=port=>new Promise((resolve,reject)=>{server.once('error',reject);server.listen(port,'127.0.0.1',()=>{server.removeListener('error',reject);resolve();});});
const closeServer=()=>new Promise(resolve=>server.close(()=>resolve()));
const poll=async(fn,timeout=25000)=>{const until=Date.now()+timeout;while(Date.now()<until){if(await fn())return;await new Promise(r=>setTimeout(r,200));}throw Error('mock_soak_condition_timeout');};
const journal=async()=>{try{return summarizeJournal(await readFile(path.join(directory,'playback','playback.journal'),'utf8'));}catch{return null;}};
let application; const env={...process.env};delete env.ELECTRON_RUN_AS_NODE;
const launch=()=>electron.launch({args:['.','--smoke-test','--allow-localhost-http','--test-data-dir='+directory],cwd:process.cwd(),env});
try {
    await listen(0); const port=server.address().port;
    application=await launch();let window=await application.firstWindow();
    await window.locator('#origin').fill('http://127.0.0.1:'+port);await window.locator('#code').fill('A'.repeat(20));await window.locator('#activate').click();
    await poll(()=>window.evaluate(()=>{const v=document.querySelector('#canvas video'),img=document.querySelector('#canvas img');return !!v?.videoWidth && (v.getVideoPlaybackQuality().totalVideoFrames>0) && img?.naturalWidth>0;}));
    await poll(()=>window.evaluate(()=>{const v=document.querySelector('#canvas video')?.parentElement?.getBoundingClientRect(),i=document.querySelector('#canvas img')?.parentElement?.getBoundingClientRect();return !!v && !!i && v.width>0 && i.width>0 && Math.abs(v.right-i.left)<1;}));
    await poll(()=>window.evaluate(async()=>{const v=document.querySelector('#canvas video');if(!v || v.videoWidth!==320)return false;const before={time:v.currentTime,frames:v.getVideoPlaybackQuality().totalVideoFrames};await new Promise(r=>setTimeout(r,150));return v.isConnected && v.currentTime>before.time && v.getVideoPlaybackQuality().totalVideoFrames>before.frames;}));
    const until=Date.now()+seconds*1000;
    while(Date.now()<until){const data=await window.evaluate(()=>{const v=document.querySelector('#canvas video');return {width:v?.videoWidth??0,frames:v?.getVideoPlaybackQuality().totalVideoFrames??0,time:v?.currentTime??0,imageWidth:document.querySelector('#canvas img')?.naturalWidth??0};});const processes=await application.evaluate(({app})=>app.getAppMetrics().map(p=>({type:p.type,cpuPercent:p.cpu.percentCPUUsage>=0?p.cpu.percentCPUUsage:null,workingSetKB:p.memory.workingSetSize})));samples.push({at:new Date().toISOString(),...data,processes,ledger:await journal()});await new Promise(r=>setTimeout(r,3000));}
    const completed=(await journal())?.completed?.find(x=>x.itemId==='1')?.count??0;if(completed<2 || !stats.heartbeats)throw Error('mock_soak_video_or_heartbeat_failed');
    await closeServer();await application.close();application=await launch();window=await application.firstWindow();
    await poll(()=>window.evaluate(()=>!!document.querySelector('#canvas video')?.videoWidth));
    await poll(async()=>((await journal())?.completed?.find(x=>x.itemId==='1')?.count??0)>completed,20000);
    const offline=await journal();if(!offline?.pending)throw Error('mock_soak_offline_queue_empty');
    await listen(port);await window.evaluate(()=>window.player.reconnect());await poll(async()=>((await journal())?.pending??1)===0,35000);
    if(stats.duplicateEvents)throw Error('mock_soak_duplicate_event');
    const result={passed:true,durationSeconds:seconds,videoWidth:320,videoFramesAndTimeAdvanced:true,samples:samples.length,videoCompletionsBeforeOffline:completed,offlinePending:offline.pending,uploadedUniqueEvents:received.size,duplicateEvents:stats.duplicateEvents,heartbeats:stats.heartbeats,offlineRestart:true};
    await writeFile(path.join(directory,'soak-summary.json'),JSON.stringify({result,samples},null,2));console.log(JSON.stringify({...result,runDirectory:directory}));
} finally {await application?.close();if(server.listening)await closeServer();}
