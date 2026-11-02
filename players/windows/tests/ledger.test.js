import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdtemp, rm, appendFile, readFile } from 'node:fs/promises';
import os from 'node:os';import path from 'node:path';
import { PlaybackLedger } from '../src/services/playback/ledger.js';
const item={id:1,mediaId:1,counterEpoch:'epoch-a',completedCount:0,maxPlays:100};
const context={revision:'a'.repeat(64),playlist_id:1,page_id:1,zone_id:1,expected_duration_ms:1000};
async function fixture(t){const root=await mkdtemp(path.join(os.tmpdir(),'cms-ledger-'));t.after(()=>rm(root,{recursive:true,force:true}));let time=0;return {root,set:value=>time=value,options:{monotonic:()=>time,utc:()=>Date.UTC(2026,10,3)+time}};}
test('checkpoint 3 fifty of one hundred survive restart resync and ack; epochs isolate reset',async t=>{
 const f=await fixture(t),ledger=new PlaybackLedger(f.root,f.options);await ledger.init();await ledger.seed('device-a',[item]);
 for(let n=0;n<50;n++){f.set(n*1000);const id=await ledger.begin('device-a',item,context);f.set((n+1)*1000);await ledger.finish(id,{status:'completed',reason:'duration_complete',actual_played_ms:1000});await ledger.finish(id,{status:'completed',reason:'retry',actual_played_ms:1000});}
 assert.equal(ledger.count('device-a',item),50);const restored=new PlaybackLedger(f.root,f.options);await restored.init();await restored.seed('device-a',[item]);assert.equal(restored.count('device-a',item),50);
 assert.equal(restored.count('device-b',item),0);await restored.acknowledge(restored.pending().map(e=>e.event_id));assert.equal(restored.pending().length,0);assert.equal(restored.count('device-a',item),50);
 assert.equal(restored.count('device-a',{...item,counterEpoch:'epoch-b'}),0);
 assert.equal(await restored.begin('device-a',{...item,maxPlays:1},context),null);
 assert.ok(await restored.begin('device-a',{...item,maxPlays:0},context));
});
test('checkpoint 3 crash tail and partial recovery never increment completed or replay twice',async t=>{
 const f=await fixture(t),ledger=new PlaybackLedger(f.root,f.options);await ledger.init();const id=await ledger.begin('a',item,context);f.set(400);await ledger.checkpoint(id,400);await appendFile(ledger.file,'{"type":"end"');
 const reboot=new PlaybackLedger(f.root,f.options);await reboot.init();assert.equal(reboot.count('a',item),0);assert.equal(reboot.pending()[0].actual_played_ms,400);assert.equal(reboot.pending()[0].status,'interrupted');
 const second=new PlaybackLedger(f.root,f.options);await second.init();assert.equal(second.pending().length,1);
 const failed=await second.begin('a',item,context);await second.finish(failed,{status:'failed',reason:'decode_error',actual_played_ms:0});assert.equal(second.count('a',item),0);
});
test('checkpoint 3 simultaneous sessions cannot reserve more than max plays and durable queue backpressures',async t=>{
 const f=await fixture(t),ledger=new PlaybackLedger(f.root,{...f.options,maxPending:2});await ledger.init();const one={...item,maxPlays:1};
 const results=await Promise.all([ledger.begin('a',one,context),ledger.begin('a',one,context)]);assert.equal(results.filter(Boolean).length,1);f.set(1000);await ledger.finish(results.find(Boolean),{status:'completed',reason:'duration_complete',actual_played_ms:1000});assert.equal(await ledger.begin('a',one,context),null);
 await ledger.begin('b',one,context);await assert.rejects(ledger.begin('c',one,context),/playback_queue_full/);
});

import { DevicePlayback } from '../src/playback/device-engine.js';
test('device sequencing counts two concurrent Windows once and waits for durable completion before skip',async t=>{
 const f=await fixture(t),ledger=new PlaybackLedger(f.root,f.options);await ledger.init();
 const one={...item,maxPlays:1,type:'image',src:'one',available:true,duration:1};
 const second={...one,id:2,src:'two'};let frame,completions=0;
 const bridge={begin:async input=>({ok:true,value:await ledger.begin('device',input.itemId===1?one:second,{...context,zone_id:input.zoneId,expected_duration_ms:input.durationMs})}),finish:async input=>{await ledger.finish(input.id,input);completions++;return {ok:true};},checkpoint:async input=>{await ledger.checkpoint(input.id,input.actual_played_ms);return {ok:true};}};
 const layout={id:1,pages:[{id:1,duration:4,zones:[{id:1,visible:true,z_index:0,items:[one]},{id:2,visible:true,z_index:1,items:[second]}]}]};
 const engine=new DevicePlayback(layout,{bridge,ready:()=>true,now:f.options.monotonic,raf:fn=>{frame=fn;return 1;},cancel(){}});engine.play();
 for(let i=0;i<12;i++){f.set(i*250);await engine.step();}assert.equal(completions,2);assert.equal(ledger.count('device',one),1);assert.equal(ledger.count('device',second),1);assert.equal([...engine.tracks.values()].every(t=>t.item===null),true);await engine.destroy();
});

test('manual video interval counts once while partial page and failed media do not consume quota',async t=>{
 const f=await fixture(t),ledger=new PlaybackLedger(f.root,f.options);await ledger.init();const v={...item,id:9,type:'video',available:true,src:'video',duration:3,maxPlays:0};
 const bridge={begin:async input=>({ok:true,value:await ledger.begin('d',v,{...context,expected_duration_ms:input.durationMs})}),finish:async input=>({ok:true,value:await ledger.finish(input.id,input)}),checkpoint:async input=>({ok:true,value:await ledger.checkpoint(input.id,input.actual_played_ms)})};
 const layout=duration=>({pages:[{id:1,duration,zones:[{id:1,z_index:0,visible:true,items:[v]}]}]});
 const options={bridge,ready:()=>true,now:f.options.monotonic,raf:()=>1,cancel(){}};
 const engine=new DevicePlayback(layout(10),options);engine.play();for(let n=0;n<=5;n++){f.set(n*1000);await engine.step();}assert.equal(ledger.count('d',v),1);await engine.destroy();
 const partial=new DevicePlayback(layout(2),options);partial.play();for(let n=20;n<=28;n++){f.set(n*250);await partial.step();}await partial.destroy();assert.equal(ledger.count('d',v),1);assert.ok(ledger.pending().some(e=>e.status==='interrupted'&&e.actual_played_ms>0));
 const failed=new DevicePlayback(layout(5),{...options,ready:()=> 'error'});failed.play();f.set(9000);await failed.step();f.set(10000);await failed.step();await failed.destroy();assert.equal(ledger.count('d',v),1);assert.ok(ledger.pending().some(e=>e.status==='failed'));
});
test('ledger failure pauses renderer rather than allowing unrecorded playback to advance',async()=>{
 let frame,snapshot;const engine=new DevicePlayback({pages:[{id:1,duration:10,zones:[{id:1,visible:true,z_index:0,items:[{id:1,type:'image',available:true,duration:1}]}]}]},{bridge:{begin:async()=>({ok:false,error:'disk_full'})},onChange:s=>snapshot=s,raf:fn=>{frame=fn;return 1;},cancel(){},now:()=>0});engine.play();frame();await engine.queue;assert.equal(engine.state,'Paused');assert.equal(snapshot.error,'ledger_unavailable');await engine.destroy();
});

test('journal compaction retains counts and pending events and ignores abandoned compact file',async t=>{
 const f=await fixture(t),ledger=new PlaybackLedger(f.root,f.options);await ledger.init();const id=await ledger.begin('a',item,context);f.set(1000);await ledger.finish(id,{status:'completed',reason:'duration_complete',actual_played_ms:1000});
 await ledger.append({type:'tick',id:'already-finished',data:{padding:'x'.repeat(8*1024*1024)}});await appendFile(ledger.file+'.compact','incomplete compact replacement');
 const recovered=new PlaybackLedger(f.root,f.options);await recovered.init();assert.equal(recovered.count('a',item),1);assert.equal(recovered.pending().length,1);assert.ok((await readFile(ledger.file)).length<10000);
});

test('unknown Auto metadata failure records null expected duration and never invents a completed play',async t=>{
 const f=await fixture(t),ledger=new PlaybackLedger(f.root,f.options);await ledger.init();const broken={...item,type:'video',duration:null,knownDuration:null,available:true,src:'broken'};
 const bridge={begin:async input=>({ok:true,value:await ledger.begin('a',broken,{...context,expected_duration_ms:input.durationMs})}),finish:async input=>({ok:true,value:await ledger.finish(input.id,input)})};
 const engine=new DevicePlayback({pages:[{id:1,duration:5,zones:[{id:1,z_index:0,visible:true,items:[broken]}]}]},{bridge,now:f.options.monotonic,raf:()=>1,cancel(){}});engine.setMetadata('broken',{status:'error'});engine.play();await engine.step();await engine.destroy();assert.equal(ledger.pending()[0].status,'failed');assert.equal(ledger.pending()[0].expected_duration_ms,null);assert.equal(ledger.count('a',broken),0);
});

test('suspend or stalled renderer gap is not invented active duration',async t=>{
 const f=await fixture(t),ledger=new PlaybackLedger(f.root,f.options);await ledger.init();const image={...item,type:'image',duration:1,available:true};const bridge={begin:async input=>({ok:true,value:await ledger.begin('a',image,{...context,expected_duration_ms:input.durationMs})}),finish:async input=>({ok:true,value:await ledger.finish(input.id,input)}),checkpoint:async()=>({ok:true})};
 const engine=new DevicePlayback({pages:[{id:1,duration:15,zones:[{id:1,z_index:0,visible:true,items:[image]}]}]},{bridge,ready:()=>true,now:f.options.monotonic,raf:()=>1,cancel(){}});engine.play();await engine.step();f.set(100);await engine.step();f.set(3600000);await engine.step();assert.equal(ledger.count('a',image),0);assert.equal(engine.tracks.get(1).played,0);await engine.destroy();
});

test('UTC realignment during an active item cannot reverse its event timestamps',async t=>{
 const f=await fixture(t);let offset=0;const ledger=new PlaybackLedger(f.root,{...f.options,utc:()=>f.options.utc()+offset});await ledger.init();const id=await ledger.begin('a',item,context);offset=-120000;f.set(1000);const event=await ledger.finish(id,{status:'completed',reason:'duration_complete',actual_played_ms:1000});assert.equal(Date.parse(event.end_time_utc)-Date.parse(event.start_time_utc),1000);
});

test('unknown and prototype-shaped session IDs never create events or counters',async t=>{
 const f=await fixture(t),ledger=new PlaybackLedger(f.root,f.options);await ledger.init();for(const id of ['__proto__','constructor','missing']){assert.equal(await ledger.finish(id,{status:'completed',reason:'duration_complete',actual_played_ms:0}),null);await ledger.checkpoint(id,0);}assert.deepEqual(ledger.state.counters,{});assert.equal(ledger.pending().length,0);
});

test('large pending snapshot compacts by new journal growth rather than rewriting every checkpoint',async t=>{
 const f=await fixture(t),ledger=new PlaybackLedger(f.root,f.options);await ledger.init();ledger.state.pending.large={event_id:'large',padding:'x'.repeat(9*1024*1024)};
 await ledger.append({type:'tick',id:'none',data:{padding:'x'.repeat(8*1024*1024)}});const size=(await readFile(ledger.file)).length;await ledger.append({type:'seed',counts:{}});assert.ok((await readFile(ledger.file)).length>size);const reopened=new PlaybackLedger(f.root,f.options);await reopened.init();assert.ok(reopened.compactAt>size+1024*1024);
});
