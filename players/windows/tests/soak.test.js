import test from 'node:test';
import assert from 'node:assert/strict';
import { summarizeJournal, videoStalled } from '../scripts/soak-metrics.js';

test('soak journal summary honors snapshots, completions, acknowledgements and incomplete tail', () => {
    const session={id:'s',key:'device/7/epoch',playlist_zone_item_id:7,zone_id:3};
    const lines=[{type:'snapshot',state:{counters:{'device/7/epoch':2},pending:{},open:{}}},{type:'begin',session},{type:'end',id:'s',event:{event_id:'e',status:'completed'}},{type:'begin',session:{...session,id:'active'}},{type:'ack',ids:['e']}];
    const result=summarizeJournal(lines.map(JSON.stringify).join('\n')+'\n{"type":');
    assert.deepEqual(result,{available:true,pending:0,activeItems:[{itemId:7,zoneId:3}],completed:[{itemId:'7',count:3}]});
});

test('soak stall indicator requires unchanged frames and clock while video is playing',()=>{
    const video={frames:12,time:1,paused:false,seeking:false};
    assert.equal(videoStalled(video,{...video}),true);
    assert.equal(videoStalled(video,{...video,frames:13}),false);
    assert.equal(videoStalled(video,{...video,time:1.1}),false);
    assert.equal(videoStalled(video,{...video,seeking:true}),false);
    assert.equal(videoStalled(null,video),false);
});
