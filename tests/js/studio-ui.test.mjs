import test from 'node:test';
import assert from 'node:assert/strict';
import { guidePreference } from '../../resources/js/cms-guide.js';
import { studioPreferences, studioUi } from '../../resources/js/studio-ui.js';
import { mediaTransformStyle } from '../../resources/js/studio-geometry.js';
const storage=()=>{const map=new Map();return {getItem:key=>map.get(key)??null,setItem:(key,value)=>map.set(key,value),removeItem:key=>map.delete(key)};};
test('welcome is once per tab session, persistent per account and onboarding version',()=>{
    const local=storage(),session=storage(),a=guidePreference(1,1,local,session);
    assert.equal(a.shouldOpen(),true);a.seen();assert.equal(a.shouldOpen(),false);
    assert.equal(guidePreference(1,1,local,storage()).shouldOpen(),true);
    a.remember(true);assert.equal(guidePreference(1,1,local,storage()).shouldOpen(),false);
    assert.equal(guidePreference(2,1,local,session).shouldOpen(),true);
    assert.equal(guidePreference(1,2,local,session).shouldOpen(),true);
    a.remember(false);assert.equal(guidePreference(1,1,local,storage()).shouldOpen(),true);
});
test('Studio panel preferences are per user and malformed state fails safely',()=>{
    const local=storage();studioPreferences(1,local).save({timelineOpen:true,advanced:true});
    assert.equal(studioPreferences(1,local).load().timelineOpen,true);assert.deepEqual(studioPreferences(2,local).load(),{});
    local.setItem('cms.studio.ui.v1.user.1','null');assert.deepEqual(studioPreferences(1,local).load(),{});
    assert.deepEqual(studioPreferences(1,{getItem:()=>{throw Error();}}).load(),{});
});
test('media styles preserve legacy fit and define bounded independent zoom pan',()=>{
    assert.equal(mediaTransformStyle(undefined,'stretch').objectFit,'fill');
    assert.deepEqual(mediaTransformStyle({scale:2,offset_x:20,offset_y:-15,fit_mode:'cover',keep_aspect_ratio:true}),{objectFit:'cover',transform:'translate(20%, -15%) scale(2)',transformOrigin:'center center'});
    assert.equal(mediaTransformStyle({scale:50,offset_x:500}).transform,'translate(100%, 0%) scale(5)');
});
test('changing selected media and presets leaves other items and Windows untouched',()=>{
    const original={scale:1,offset_x:0,offset_y:0,fit_mode:'contain',keep_aspect_ratio:true};
    const ui={...studioUi(),width:1920,height:1080,selectedZoneId:1,zones:{1:{items:[{id:1,mediaTransform:original},{id:2,mediaTransform:original}]},2:{x:500,y:0,width:100,height:100}},zoneForm:{x:0,y:0,width:800,height:600}};
    ui.syncMediaSelection();ui.mediaForm.scale=2;assert.equal(original.scale,1);assert.equal(ui.zones[2].width,100);
    ui.selectMedia(2);assert.equal(ui.mediaForm.scale,1);ui.windowPreset('left');assert.equal(ui.zoneForm.width,960);assert.equal(ui.zones[2].width,100);
});
