import { open, readFile, mkdir, rename, stat, truncate } from 'node:fs/promises';
import path from 'node:path';
import { randomUUID } from 'node:crypto';
// A single serialized, fsync'd transaction per boundary. This directory is outside media GC.
export class PlaybackLedger {
    constructor(root,{monotonic=()=>performance.now(),utc=()=>Date.now(),maxPending=100000}={}){Object.assign(this,{root,monotonic,utc,maxPending});this.file=path.join(root,'playback.journal');this.state={counters:{},pending:{},open:{}};this.queue=Promise.resolve();this.anchors=new Map();this.compactAt=8*1024*1024;}
    key(device,item,epoch){return `${device}/${item}/${epoch}`;}
    async init(){
        await mkdir(this.root,{recursive:true});let bytes;try{bytes=await readFile(this.file);}catch(e){if(e.code!=='ENOENT')throw e;bytes=Buffer.alloc(0);}
        const complete=bytes.lastIndexOf(10)+1;if(complete<bytes.length)await truncate(this.file,complete);
        for(const line of bytes.subarray(0,complete).toString('utf8').split('\n').filter(Boolean)){const record=JSON.parse(line);if(record.type==='snapshot')this.compactAt=Buffer.byteLength(line)+1+8*1024*1024;this.apply(record);}
        for(const session of Object.values(this.state.open))await this.finish(session.id,{status:'interrupted',reason:'crash_recovery',actual_played_ms:session.actual_played_ms??0},true);
    }
    apply(record){
        if(record.type==='snapshot'){this.state=record.state;return;}
        if(record.type==='seed')for(const [key,value] of Object.entries(record.counts))this.state.counters[key]=Math.max(this.state.counters[key]??0,value);
        if(record.type==='begin')this.state.open[record.session.id]=record.session;
        if(record.type==='tick' && this.state.open[record.id])Object.assign(this.state.open[record.id],record.data);
        if(record.type==='end' && this.state.open[record.id]){
            const session=this.state.open[record.id];delete this.state.open[record.id];this.state.pending[record.event.event_id]=record.event;
            if(record.event.status==='completed')this.state.counters[session.key]=(this.state.counters[session.key]??0)+1;
        }
        if(record.type==='ack')for(const id of record.ids)delete this.state.pending[id];
    }
    run(task){const next=this.queue.then(task);this.queue=next.catch(()=>{});return next;}
    async append(record){const file=await open(this.file,'a',0o600);try{await file.writeFile(JSON.stringify(record)+'\n');await file.sync();}finally{await file.close();}this.apply(record);
        if((await stat(this.file)).size>this.compactAt){const temp=this.file+'.compact',f=await open(temp,'w',0o600);try{await f.writeFile(JSON.stringify({type:'snapshot',state:this.state})+'\n');await f.sync();}finally{await f.close();}await rename(temp,this.file);this.compactAt=(await stat(this.file)).size+8*1024*1024;}
    }
    seed(device,items){return this.run(async()=>{const counts={};for(const item of items)counts[this.key(device,item.id,item.counterEpoch)]=item.completedCount??0;await this.append({type:'seed',counts});});}
    count(device,item){return this.state.counters[this.key(device,item.id,item.counterEpoch)]??0;}
    begin(device,item,context){return this.run(async()=>{
        if(Object.keys(this.state.pending).length+Object.keys(this.state.open).length>=this.maxPending)throw Error('playback_queue_full');
        const key=this.key(device,item.id,item.counterEpoch);
        const reserved=Object.values(this.state.open).filter(s=>s.key===key).length;
        if(item.maxPlays>0 && (this.state.counters[key]??0)+reserved>=item.maxPlays)return null;
        const id=randomUUID(),session={id,key,device_public_id:device,revision:context.revision,playlist_id:context.playlist_id,page_id:context.page_id,zone_id:context.zone_id,playlist_zone_item_id:item.id,media_asset_id:item.mediaId,schedule_id:context.schedule_id??null,counter_epoch:item.counterEpoch,expected_duration_ms:context.expected_duration_ms,start_time_utc:new Date(this.utc()).toISOString(),actual_played_ms:0};
        await this.append({type:'begin',session});this.anchors.set(id,this.monotonic());return id;
    });}
    checkpoint(id,actual){return this.run(async()=>{const session=Object.hasOwn(this.state.open,id)?this.state.open[id]:null;if(!session)return;
        const elapsed=this.monotonic()-(this.anchors.get(id)??this.monotonic());
        if(!Number.isSafeInteger(actual)||actual<session.actual_played_ms||actual>elapsed+1000)throw Error('playback_duration_invalid');
        await this.append({type:'tick',id,data:{actual_played_ms:actual,last_time_utc:new Date(Date.parse(session.start_time_utc)+Math.max(0,elapsed)).toISOString()}});
    });}
    finish(id,{status,reason,actual_played_ms},recovery=false){return this.run(async()=>{
        const session=Object.hasOwn(this.state.open,id)?this.state.open[id]:null;if(!session)return null;
        const elapsed=recovery?session.actual_played_ms:this.monotonic()-(this.anchors.get(id)??this.monotonic());
        if(!/^[a-z_]{1,80}$/.test(reason??'') || !['completed','interrupted','failed'].includes(status)||!Number.isSafeInteger(actual_played_ms)||actual_played_ms<session.actual_played_ms||actual_played_ms>elapsed+1000 || (session.expected_duration_ms!==null && actual_played_ms>session.expected_duration_ms+2000))throw Error('playback_duration_invalid');
        if(status==='completed' && (session.expected_duration_ms===null || actual_played_ms<session.expected_duration_ms))throw Error('playback_incomplete');
        const {key,id:sessionId,last_time_utc,...payload}=session;
        const event={...payload,event_id:randomUUID(),playback_session_id:sessionId,end_time_utc:recovery?(last_time_utc??session.start_time_utc):new Date(Date.parse(session.start_time_utc)+Math.max(0,elapsed)).toISOString(),actual_played_ms,status,reason};
        await this.append({type:'end',id,event});this.anchors.delete(id);return event;
    });}
    pending(limit=50,device=null){return Object.values(this.state.pending).filter(e=>!device || e.device_public_id===device).slice(0,limit);}
    acknowledge(ids){return this.run(()=>this.append({type:'ack',ids:ids.filter(id=>Object.hasOwn(this.state.pending,id))}));}
}
