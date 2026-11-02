import test from 'node:test';
import assert from 'node:assert/strict';
import { PlaybackEngine, orderedManifest, itemDuration, windowAt, trackSegments } from '../../resources/js/studio-playback.js';
import { PlaybackRenderer, MetadataPool } from '../../resources/js/studio-playback-renderer.js';
import { studioTimeline, formatTime } from '../../resources/js/studio-timeline.js';

const image=(id,duration=10)=>({id,type:'image',title:'Image '+id,src:'/image/'+id,sort_order:id,duration,available:true,knownDuration:null});
const video=(id,duration=null,knownDuration=4)=>({id,type:'video',title:'Video '+id,src:'/video/'+id,sort_order:id,duration,available:true,knownDuration});
const zone=(id,items,extra={})=>({id,name:'Window '+id,x:100,y:50,width:800,height:400,z_index:id,visible:true,fit_mode:'contain',items,...extra});
const manifest=()=>({id:1,width:1920,height:1080,background:'#000',pages:[
    {id:2,sort_order:2,name:'Second',duration:5,background:'#222',zones:[zone(3,[image(4,2)])]},
    {id:1,sort_order:1,name:'First',duration:15,background:'#111',zones:[zone(1,[image(1,3),video(2,null,4)]),zone(2,[image(3,5)])]},
]});
function clockFixture(input=manifest()){
    let now=0,next=0;const frames=new Map();
    const engine=new PlaybackEngine(input,{now:()=>now,raf:fn=>{frames.set(++next,fn);return next;},cancel:id=>frames.delete(id)});
    return {engine,frames,at(seconds){now=seconds*1000;return engine.step();}};
}

test('Pages total duration and order are fixed, while Windows run concurrently in item sort order',()=>{
    const {engine,at}=clockFixture();
    assert.equal(engine.manifest.totalDuration,20);assert.deepEqual(engine.manifest.pages.map(p=>p.id),[1,2]);
    engine.play();const s=at(3.5);
    assert.equal(s.windows[0].item.id,2);assert.equal(s.windows[0].localTime,0.5);
    assert.equal(s.windows[1].item.id,3);assert.equal(s.windows[1].localTime,3.5);
    engine.destroy();
});

test('Window sequences loop within the Page and change Page independently of unfinished videos',()=>{
    const {engine,at}=clockFixture();engine.play();
    const cycle=at(8);assert.equal(cycle.windows[0].item.id,1);assert.equal(cycle.windows[0].cycle,1);assert.equal(cycle.windows[0].localTime,1);
    const next=at(15);assert.equal(next.page.id,2);assert.equal(next.pageTime,0);assert.equal(next.windows[0].item.id,4);
    const loop=at(21);assert.equal(loop.page.id,1);assert.equal(loop.time,1);
    engine.destroy();
});

test('Loop OFF ends exactly at total and Play restarts; next/previous and Stop are functional',()=>{
    const {engine,at}=clockFixture();engine.setLoop(false);engine.play();
    const ended=at(25);assert.equal(ended.state,'Ended');assert.equal(ended.time,20);assert.equal(ended.page.id,2);
    engine.play();assert.equal(engine.time,0);engine.nextPage();assert.equal(engine.time,15);
    engine.nextPage(-1);assert.equal(engine.time,0);engine.nextPage();engine.nextPage();assert.equal(engine.time,20);
    engine.stop();assert.equal(engine.state,'Ready');assert.equal(engine.time,0);engine.destroy();
});

test('Pause resume uses one monotonic clock without resetting position or frame accumulation',()=>{
    const {engine,at,frames}=clockFixture();engine.play();at(2.25);engine.pause();
    assert.equal(engine.time,2.25);assert.equal(frames.size,0);at(100);assert.equal(engine.time,2.25);
    engine.play();assert.equal(frames.size,1);assert.equal(at(102).time,4.25);engine.destroy();assert.equal(frames.size,0);
});

test('Seek locates Page, item and local time, preserving pause and rapid seek destinations',()=>{
    const {engine}=clockFixture();
    engine.seek(4.25);let s=engine.snapshot();assert.equal(s.state,'Paused');assert.equal(s.windows[0].item.id,2);assert.equal(s.windows[0].localTime,1.25);
    engine.seek(17);s=engine.snapshot();assert.equal(s.page.id,2);assert.equal(s.pageTime,2);assert.equal(s.windows[0].localTime,0);
    for(const t of [2,12,19,3.25])engine.seek(t);
    assert.equal(engine.snapshot().windows[0].localTime,0.25);assert.equal(engine.time,3.25);engine.pause();assert.equal(engine.time,3.25);engine.destroy();
});

test('Unknown Auto metadata never receives a fabricated duration and errors do not hang other Windows',()=>{
    const unknown=video(9,null,null),input=manifest();input.pages[1].zones[0].items=[unknown,image(10,2)];
    const {engine,at}=clockFixture(input);engine.play();
    let s=at(1);assert.equal(s.windows[0].state,'ready');assert.equal(s.windows[0].item.id,10);assert.equal(s.windows[1].state,'ready');
    assert.equal(itemDuration(unknown),null);
    engine.setMetadata(unknown.src,{status:'ready',duration:6.25});assert.equal(engine.snapshot().windows[0].item.id,9);
    engine.setMetadata(unknown.src,{status:'error'});s=engine.snapshot();assert.equal(s.windows[0].item.id,10);
    assert.equal(at(16).page.id,2);engine.destroy();
});

test('Manual durations override intrinsic duration and hidden/empty Windows never block the clock',()=>{
    assert.equal(itemDuration(video(2,12,4)),12);assert.equal(itemDuration(video(2,2,4)),2);assert.equal(itemDuration(image(2,null)),10);
    assert.equal(windowAt(zone(1,[image(2)],{visible:false}),5).state,'empty');assert.equal(windowAt(zone(1,[]),5).state,'empty');
    const {engine,at}=clockFixture();engine.play();assert.equal(at(36).page.id,2);engine.destroy();
});

test('Timeline blocks repeat, truncate at Page end, reflect changed duration, and virtualize long tracks',()=>{
    const p=orderedManifest(manifest()).pages[0],z=p.zones[0];
    let result=trackSegments(z,p,{},0,20);assert.deepEqual(result.segments.map(s=>s.start),[0,3,7,10,14]);assert.equal(result.segments.at(-1).duration,1);
    z.items[0].duration=5;result=trackSegments(z,p,{},0,20);assert.deepEqual(result.segments.map(s=>s.start),[0,5,9,14]);
    z.items=[video(5,null,null)];assert.equal(trackSegments(z,p,{},0,20).pending,true);
    z.items=[image(5,1)];p.duration=86400;assert.ok(trackSegments(z,p,{},80000,80100).segments.length<=101);
});

class Node {
    constructor(tag='div'){this.tag=tag;this.style={};this.children=[];this.listeners=new Map();this.readyState=1;this.duration=4;this._time=0;this.seeking=false;this.paused=true;this.playCalls=0;this.loadCalls=0;this.muted=true;}
    appendChild(node){node.parent=this;this.children.push(node);}
    replaceChildren(...nodes){this.children=[];for(const node of nodes)this.appendChild(node);}
    remove(){if(this.parent)this.parent.children=this.parent.children.filter(n=>n!==this);}
    addEventListener(name,fn){if(!this.listeners.has(name))this.listeners.set(name,new Set());this.listeners.get(name).add(fn);}
    removeEventListener(name,fn){this.listeners.get(name)?.delete(fn);}
    fire(name){if(name==='seeked')this.seeking=false;for(const fn of [...(this.listeners.get(name)||[])])fn();}
    pause(){this.paused=true;}
    play(){this.playCalls++;if(this.blocked)return Promise.reject(Object.assign(new Error('blocked'),{name:'NotAllowedError'}));this.paused=false;return Promise.resolve();}
    load(){this.loadCalls++;}
    removeAttribute(name){delete this[name];}
    set currentTime(value){this._time=value;if(this.asyncSeek)this.seeking=true;}
    get currentTime(){return this._time;}
}
function renderFixture(input=manifest()){
    const {engine,at}=clockFixture(input),container=new Node(),nodes=[];const errors=[],blocked=[];
    const renderer=new PlaybackRenderer(container,{createElement:tag=>{const n=new Node(tag);nodes.push(n);return n;},onError:src=>errors.push(src),onBlocked:()=>blocked.push(true)});
    const options={width:1920,height:1080,muted:true,volume:0.7,audioVersion:1};
    const draw=()=>renderer.render(engine.snapshot(),options);
    return {engine,at,renderer,container,nodes,errors,blocked,options,draw};
}

test('Renderer seeks into videos using the clock; longer manual items wrap intrinsic time and fit/volume apply',()=>{
    const input=manifest();input.pages[1].zones=[zone(1,[video(1,15,4)],{fit_mode:'stretch'})];
    const f=renderFixture(input);f.engine.seek(10.5);f.draw();const v=f.nodes.find(n=>n.tag==='video');
    assert.equal(v.currentTime,2.5);assert.equal(v.style.objectFit,'fill');assert.equal(v.volume,0.7);assert.equal(v.paused,true);
    v.fire('loadedmetadata');assert.equal(v.currentTime,2.5); // metadata must not reset to zero
    f.renderer.destroy();f.engine.destroy();
});

test('Rapid asynchronous video seeks settle to latest time and stale source events are ignored',()=>{
    const input=manifest();input.pages[1].zones=[zone(1,[video(1,12,10),image(2,3)])];
    const f=renderFixture(input);f.engine.seek(2);f.draw();const v=f.nodes.find(n=>n.tag==='video');v.duration=10;v.asyncSeek=true;
    f.engine.seek(3);f.draw();assert.equal(v.currentTime,3);assert.equal(v.seeking,true);
    f.engine.seek(8);f.draw();assert.equal(v.currentTime,3);v.fire('seeked');assert.equal(v.currentTime,8);v.fire('seeked');
    const late=[...(v.listeners.get('loadedmetadata')||[])];
    f.engine.seek(13);f.draw();const before=v.currentTime;for(const fn of late)fn();
    assert.equal(v.currentTime,before);assert.equal(v.src,undefined);assert.equal(v.paused,true);
    f.renderer.destroy();f.engine.destroy();
});

test('Page switch cleans preview videos and does not reuse editor DOM or change manifest geometry',()=>{
    const f=renderFixture();f.engine.seek(4);f.draw();const v=f.nodes.find(n=>n.tag==='video');const before=JSON.stringify(f.engine.manifest);
    f.engine.seek(16);f.draw();assert.equal(v.paused,true);assert.equal(v.src,undefined);
    for(const callbacks of v.listeners.values())assert.equal(callbacks.size,0);
    assert.equal(JSON.stringify(f.engine.manifest),before);assert.equal(f.container.style.backgroundColor,'#222');
    f.renderer.destroy();assert.equal(f.container.children.length,0);f.engine.destroy();
});

test('Autoplay refusal exposes recovery and is not retried every frame',async()=>{
    const input=manifest();input.pages[1].zones=[zone(1,[video(1,12,10)])];
    const f=renderFixture(input);f.engine.play();f.draw();const v=f.nodes.find(n=>n.tag==='video');v.blocked=true;v.paused=true;v.playCalls=0;
    f.options.audioVersion++;f.draw();await Promise.resolve();assert.equal(f.blocked.length,1);
    const attempts=v.playCalls;f.draw();f.draw();assert.equal(v.playCalls,attempts);
    v.blocked=false;f.options.audioVersion++;f.draw();assert.equal(v.paused,false);f.renderer.destroy();f.engine.destroy();
});

test('Metadata pool uses valid duration, bounds loading time, deduplicates sources and cleans listeners',()=>{
    const nodes=[],results=[],timers=new Map();let id=0;
    const input=orderedManifest(manifest());input.pages[0].zones.push(zone(4,[video(2,null,4)]));
    const pool=new MetadataPool({createVideo:()=>{const n=new Node('video');nodes.push(n);return n;},setTimer:fn=>{timers.set(++id,fn);return id;},clearTimer:id=>timers.delete(id),onResult:(src,info)=>results.push([src,info])});
    pool.load(input);assert.equal(nodes.length,1);nodes[0].duration=6.5;nodes[0].fire('loadedmetadata');assert.equal(results.at(-1)[1].duration,6.5);assert.equal(timers.size,0);
    pool.destroy();assert.equal(nodes[0].src,undefined);for(const callbacks of nodes[0].listeners.values())assert.equal(callbacks.size,0);
    const badInput=orderedManifest(manifest());badInput.pages[0].zones[0].items=[video(99,null,null)];
    const bad=new MetadataPool({createVideo:()=>new Node('video'),setTimer:fn=>{timers.set(++id,fn);return id;},clearTimer:id=>timers.delete(id),onResult:(src,info)=>results.push([src,info])});
    bad.load(badInput);assert.equal(results.at(-1)[1].status,'loading');[...timers.values()][0]();assert.equal(results.at(-1)[1].status,'error');bad.destroy();
});

test('Invalid intrinsic metadata errors and failed images produce fallback without infinite loading',()=>{
    const nodes=[],results=[];const pool=new MetadataPool({createVideo:()=>{const n=new Node('video');nodes.push(n);return n;},onResult:(src,info)=>results.push(info)});
    pool.load(orderedManifest(manifest()));nodes[0].duration=Infinity;nodes[0].fire('loadedmetadata');assert.equal(results.at(-1).status,'error');pool.destroy();
    const f=renderFixture();f.draw();const img=f.nodes.find(n=>n.tag==='img');img.fire('error');assert.equal(f.errors.length,1);
    const entry=f.renderer.entries.get(1);assert.match(entry.box.children[0].textContent,/Media unavailable/);f.renderer.destroy();f.engine.destroy();
});

test('Renderer keeps video identity across clock renders and applies audio without touching editor elements',()=>{
    const f=renderFixture();f.engine.seek(4);f.draw();const videoNode=f.nodes.find(n=>n.tag==='video');
    for(const t of [4.1,4.2,4.3]){f.engine.seek(t);f.draw();}
    assert.equal(f.nodes.filter(n=>n.tag==='video').length,1);
    f.options.muted=false;f.options.volume=0.25;f.draw();assert.equal(videoNode.muted,false);assert.equal(videoNode.volume,0.25);
    f.renderer.destroy();f.engine.destroy();
});

test('Duration labels and display clocks never pretend pending metadata is a known length',()=>{
    const ui=studioTimeline();assert.equal(ui.durationLabel(video(1,null,null)),'Auto / Loading metadata');
    ui.metadata={'/video/1':{status:'error'}};assert.equal(ui.durationLabel(video(1,null,null)),'Auto / Unknown');
    assert.equal(formatTime(3661),'1:01:01');assert.equal(formatTime(15.8),'00:15');
});


test('Preview adapter reads a manifest once, plays locally, preserves editor drafts, and cleans on close',async(t)=>{
    const listeners=new Map();
    globalThis.window={addEventListener(n,fn){listeners.set(n,fn);},removeEventListener(n,fn){if(listeners.get(n)===fn)listeners.delete(n);}};
    globalThis.document={createElement:tag=>new Node(tag),fullscreenEnabled:false};
    globalThis.ResizeObserver=class{observe(){}disconnect(){}};
    globalThis.requestAnimationFrame=()=>1;globalThis.cancelAnimationFrame=()=>{};
    const ui=studioTimeline();t.after(()=>ui.destroyPlayback());let reads=0,focused=false;
    Object.assign(ui,{busy:false,gesture:null,preview:false,pageId:1,name:'Unsaved Playlist',zoneForm:{name:'Unsaved Window'},dirty:true,zoneDirty:true,
        $wire:{getPlaybackManifest:async()=>{reads++;return manifest();}},
        $nextTick:fn=>fn(),perform:async action=>action(),
        $refs:{previewPlayback:new Node(),inlinePlayback:new Node(),previewArea:{clientWidth:1000,clientHeight:600},closePreview:{focus(){}},previewTrigger:{focus(){focused=true;}}}
    });
    await ui.openPreview();assert.equal(reads,1);assert.equal(ui.preview,true);assert.equal(ui.playback.state,'Paused');
    assert.ok(ui.playbackManifest.width*ui.previewScale<=984);
    await ui.playPlayback();assert.equal(ui.playback.state,'Playing');ui.seekPlayback(4.5);ui.pausePlayback();
    assert.ok(Math.abs(ui.playback.time-4.5)<0.1);assert.equal(ui.playback.state,'Paused');assert.equal(reads,1);
    assert.equal(ui.name,'Unsaved Playlist');assert.equal(ui.zoneForm.name,'Unsaved Window');assert.equal(ui.dirty,true);assert.equal(ui.zoneDirty,true);
    ui.closePreview();assert.equal(ui.preview,false);assert.equal(focused,true);assert.equal(ui.$refs.previewPlayback.children.length,0);
    ui.destroyPlayback();
});

test('Keyboard playback shortcuts honor form focus and seek without saving',()=>{
    const ui=studioTimeline(),calls=[];ui.playback={state:'Paused',time:5,total:20};
    ui.playPlayback=()=>calls.push('play');ui.pausePlayback=()=>calls.push('pause');ui.seekPlayback=t=>calls.push(t);
    const event=(code,input=false)=>({code,target:{closest:()=>input?{}:null},preventDefault(){}});
    for(const code of ['Space','Home','End','ArrowLeft','ArrowRight'])ui.playbackKey(event(code));
    assert.deepEqual(calls,['play',0,20,4,6]);
    ui.playbackKey(event('Space',true));assert.equal(calls.length,5);
    ui.playback.state='Playing';ui.playbackKey(event('Space'));assert.equal(calls.at(-1),'pause');
});


test('A video that exposes metadata but never decodes a frame times out to fallback',async()=>{
    const container=new Node(),errors=[];
    const input=manifest();input.pages[1].zones=[zone(1,[video(1,null,4)])];
    const {engine}=clockFixture(input);engine.seek(1);
    const renderer=new PlaybackRenderer(container,{timeout:1,createElement:tag=>new Node(tag),onError:src=>errors.push(src)});
    renderer.render(engine.snapshot(),{width:1920,height:1080});
    const entry=renderer.entries.get(1);entry.video.fire('loadedmetadata');
    await new Promise(resolve=>setTimeout(resolve,10));
    assert.equal(entry.failed,true);assert.equal(errors.length,1);assert.equal(entry.video.src,undefined);
    renderer.destroy();engine.destroy();
});


test('A manual video shorter than the source is released at the manual item boundary',()=>{
    const input=manifest();input.pages[1].zones=[zone(1,[video(1,2,4),image(2,10)])];
    const f=renderFixture(input);f.engine.seek(1.5);f.draw();const node=f.nodes.find(n=>n.tag==='video');assert.equal(node.currentTime,1.5);
    f.engine.seek(2.1);f.draw();assert.equal(node.paused,true);assert.equal(node.src,undefined);assert.ok(f.nodes.some(n=>n.tag==='img'));
    f.renderer.destroy();f.engine.destroy();
});

test('Automatic Page end interrupts a long video and releases its source and event listeners',()=>{
    const input=manifest();input.pages[1].zones=[zone(1,[video(1,86400,4)])];
    const f=renderFixture(input);f.engine.play();f.draw();const node=f.nodes.find(n=>n.tag==='video');
    f.at(15.1);f.draw();assert.equal(f.engine.snapshot().page.id,2);assert.equal(node.paused,true);assert.equal(node.src,undefined);
    for(const callbacks of node.listeners.values())assert.equal(callbacks.size,0);
    f.renderer.destroy();f.engine.destroy();
});

test('Default metadata timers never borrow browser-native functions onto MetadataPool',()=>{
    const originalSet=globalThis.setTimeout,originalClear=globalThis.clearTimeout;
    const results=[],nodes=[];
    globalThis.setTimeout=function(){assert.ok(this===undefined || this===globalThis,'Illegal invocation: foreign timer receiver');return 42;};
    globalThis.clearTimeout=function(){assert.ok(this===undefined || this===globalThis,'Illegal invocation: foreign clear receiver');};
    let pool;
    try {
        pool=new MetadataPool({createVideo:()=>{const node=new Node('video');nodes.push(node);return node;},onResult:(_src,info)=>results.push(info)});
        assert.doesNotThrow(()=>pool.load(orderedManifest(manifest())));
        nodes[0].duration=2.5;nodes[0].fire('loadedmetadata');assert.equal(results.at(-1).duration,2.5);
        assert.doesNotThrow(()=>pool.destroy());
    } finally { globalThis.setTimeout=originalSet;globalThis.clearTimeout=originalClear; }
});

test('Stalled and waiting events retain a bounded timeout, and late events cannot restore failed metadata',()=>{
    const node=new Node('video'),events=[],results=[];let deadline;
    const pool=new MetadataPool({createVideo:()=>node,setTimer:fn=>(deadline=fn,1),clearTimer:()=>{},onResult:(_src,info)=>results.push(info),onDiagnostic:info=>events.push(info)});
    const input=orderedManifest(manifest());input.pages[0].zones[0].items=[video(99,null,null)];pool.load(input);
    const loaded=[...node.listeners.get('loadedmetadata')][0];
    node.fire('waiting');node.fire('stalled');assert.equal(results.at(-1).status,'loading');deadline();
    assert.equal(results.at(-1).status,'error');assert.match(results.at(-1).message,/timed out/);
    const count=results.length;loaded();assert.equal(results.length,count);assert.equal(node.src,undefined);
    assert.ok(events.some(info=>info.event==='timeout'));pool.destroy();
});

test('Only-unknown Auto becomes an explicit error, while known items in the same Window keep playing',()=>{
    const unknown=video(99,null,null),sameWindow=zone(1,[unknown,image(2,3)]);
    assert.equal(windowAt(sameWindow,1).item.id,2);
    const pending=trackSegments(sameWindow,{start:0,duration:15},{},0,15);
    assert.equal(pending.pending,true);assert.ok(pending.segments.length>0);
    const onlyUnknown=zone(2,[unknown]);assert.equal(windowAt(onlyUnknown,1).state,'loading');
    const failed=windowAt(onlyUnknown,1,{[unknown.src]:{status:'error',message:'Metadata timed out'}});
    assert.equal(failed.state,'error');assert.match(failed.message,/timed out/);assert.equal(itemDuration(unknown),null);
});

test('Media decode errors are specific and diagnostics never include source URL or credentials',()=>{
    const node=new Node('video'),diagnostics=[],results=[];node.error={code:4};
    const pool=new MetadataPool({createVideo:()=>node,onDiagnostic:info=>diagnostics.push(info),onResult:(_src,info)=>results.push(info)});
    pool.load(orderedManifest(manifest()));node.fire('error');assert.match(results.at(-1).message,/codec.*unsupported/);
    for(const entry of diagnostics){assert.equal('src' in entry,false);assert.equal('url' in entry,false);}
    assert.equal(diagnostics.at(-1).errorCode,4);pool.destroy();
});

test('Playback applies item zoom pan and crop without changing Window geometry',()=>{
    const input=manifest(),transform={scale:2,offset_x:20,offset_y:-15,fit_mode:'cover',keep_aspect_ratio:true};
    input.pages[1].zones[0].items=[{...video(1,15,4),mediaTransform:transform}];
    const geometry=structuredClone(input.pages[1].zones[0]);const f=renderFixture(input);f.engine.seek(1);f.draw();
    const node=f.nodes.find(n=>n.tag==='video');assert.equal(node.style.transform,'translate(20%, -15%) scale(2)');assert.equal(node.style.objectFit,'cover');
    assert.equal(f.renderer.entries.get(1).box.style.width,100*geometry.width/input.width+'%');
    assert.equal(input.pages[1].zones[0].width,geometry.width);f.renderer.destroy();f.engine.destroy();
});

test('Studio warns when manual or resolved Auto item exceeds its Page without inventing unknown duration',()=>{
 const ui={...studioTimeline(),metadata:{},playbackManifest:{pages:[{duration:2,zones:[{items:[{id:99,type:'video',src:'test',duration:null,knownDuration:null,available:true}]}]}]}};
 assert.equal(ui.fileExceedsPage(99),false);ui.metadata.test={status:'ready',duration:3};assert.equal(ui.fileExceedsPage(99),true);ui.metadata.test={status:'error'};assert.equal(ui.fileExceedsPage(99),false);ui.playbackManifest.pages[0].zones[0].items[0].duration=4;assert.equal(ui.fileExceedsPage(99),true);
});
