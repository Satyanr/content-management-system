import { orderedManifest, itemDuration } from './vendor/studio-playback.js';
// Device-only sequencing: shared Preview keeps its independent, quota-free clock.
export class DevicePlayback {
    constructor(manifest,{bridge,renderer,onChange=()=>{},ready=()=>false,now=()=>performance.now(),raf=fn=>requestAnimationFrame(fn),cancel=id=>cancelAnimationFrame(id),context={}}={}){
        Object.assign(this,{manifest:orderedManifest(manifest),bridge,renderer,onChange,ready,now,raf,cancel,context});this.metadata={};this.tracks=new Map();this.time=0;this.previous=null;this.pageIndex=0;this.state='Ready';this.frame=null;this.dead=false;this.queue=Promise.resolve();
    }
    setMetadata(src,info){this.metadata[src]=info;}
    play(){this.state='Playing';this.previous=this.now();this.schedule();}
    schedule(){if(this.dead || this.frame!==null)return;this.frame=this.raf(()=>{this.frame=null;this.queue=this.queue.then(()=>this.step()).catch(()=>{this.state='Paused';const page=this.manifest.pages[this.pageIndex];if(!this.dead && page)this.onChange({...this.snapshot(page),error:'ledger_unavailable'});});this.queue.finally(()=>{if(this.state==='Playing')this.schedule();});});}
    async finish(track,status,reason){if(!track.session)return;const result=await this.bridge.finish({id:track.session,status,reason,actual_played_ms:Math.floor(track.played)});if(!result.ok)throw Error(result.error);track.session=null;}
    async next(track,zone,page){
        if(this.dead)return;if(track.failed.size+track.exhausted.size>=zone.items.length){track.item=null;return;}
        for(let n=0;n<zone.items.length;n++){
            const index=track.index++ % zone.items.length,item=zone.items[index],duration=itemDuration(item,this.metadata);
            if(!item.available || track.failed.has(item.id) || track.exhausted.has(item.id))continue;
            if(duration===null){if(this.metadata[item.src]?.status==='error'){
                track.failed.add(item.id);const result=await this.bridge.begin({itemId:item.id,pageId:page.id,zoneId:zone.id,durationMs:null});if(!result.ok)throw Error(result.error);if(result.value){const ended=await this.bridge.finish({id:result.value,status:'failed',reason:'metadata_error',actual_played_ms:0});if(!ended.ok)throw Error(ended.error);}
            }continue;}
            const result=await this.bridge.begin({itemId:item.id,pageId:page.id,zoneId:zone.id,durationMs:Math.round(duration*1000)});
            if(!result.ok)throw Error(result.error);if(!result.value){track.exhausted.add(item.id);continue;}
            Object.assign(track,{item,session:result.value,played:0,expected:Math.round(duration*1000),checkpoint:0,wasReady:false,cycle:track.cycle+1});return;
        }
        track.item=null;
    }
    snapshot(page){return {state:this.state,page,seekVersion:0,time:this.time/1000,windows:page.zones.filter(z=>z.visible).map(zone=>{const track=this.tracks.get(zone.id);return {zone,item:track?.item??null,state:track?.item?'ready':'empty',localTime:(track?.played??0)/1000,cycle:track?.cycle??0};})};}
    async step(){
        if(this.dead || !this.manifest.pages.length)return;const now=this.now(),gap=now-(this.previous??now),dt=gap>1000?0:Math.max(0,gap);this.previous=now;
        let page=this.manifest.pages[this.pageIndex],remaining=Math.max(0,page.duration*1000-this.time);
        for(const zone of page.zones.filter(z=>z.visible)){
            let track=this.tracks.get(zone.id);if(!track){track={index:0,item:null,session:null,played:0,cycle:0,failed:new Set(),exhausted:new Set()};this.tracks.set(zone.id,track);}
            if(track.item){
                const info=this.ready(zone.id,track.item);const failed=info==='error' || this.metadata[track.item.src]?.status==='error';
                if(failed){track.failed.add(track.item.id);await this.finish(track,'failed','decode_error');track.item=null;}
                else{
                    if(info===true && track.wasReady)track.played+=Math.min(dt,remaining,Math.max(0,track.expected-track.played));track.wasReady=info===true;
                    if(track.played>=track.expected){await this.finish(track,'completed','duration_complete');track.item=null;}
                    else if(track.played-track.checkpoint>=1000){const result=await this.bridge.checkpoint({id:track.session,actual_played_ms:Math.floor(track.played)});if(!result.ok)throw Error(result.error);track.checkpoint=track.played;}
                }
            }
            if(!track.item)await this.next(track,zone,page);
        }
        this.time+=dt;
        if(this.time>=page.duration*1000){
            for(const track of this.tracks.values())await this.finish(track,'interrupted','page_transition');
            this.tracks.clear();this.time=0;this.pageIndex=(this.pageIndex+1)%this.manifest.pages.length;page=this.manifest.pages[this.pageIndex];
        }
        if(!this.dead)this.onChange({...this.snapshot(page),quotaEmpty:page.zones.filter(z=>z.visible).every(z=>{const t=this.tracks.get(z.id);return t && t.exhausted.size>=z.items.length;})});
    }
    async destroy(){this.dead=true;if(this.frame!==null)this.cancel(this.frame);await this.queue;for(const track of this.tracks.values())await this.finish(track,'interrupted','playlist_transition');this.tracks.clear();}
}
