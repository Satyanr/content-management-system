import test from 'node:test';
import assert from 'node:assert/strict';
import { canvasPoint, initialBox, moveBox, resizeBox, objectFit, requestToken } from '../../resources/js/studio-geometry.js';
import { studioInteractions } from '../../resources/js/studio-interactions.js';

const clone = value => JSON.parse(JSON.stringify(value));
const baseZone = { id: 3, name: 'Main', x: 100, y: 100, width: 400, height: 200, keep_aspect_ratio: true, is_visible: true, is_locked: false, z_index: 1, fit_mode: 'contain', items: [] };
const snapshot = (pageId = 1) => ({ pageId, canEdit: true, canMedia: true, width: 1920, height: 1080, extentX: 500, extentY: 300, zones: [clone(baseZone)] });
function fixture() {
    const listeners = new Map();
    globalThis.window = {
        addEventListener(name, callback) { listeners.set(name, callback); },
        removeEventListener(name, callback) { if (listeners.get(name) === callback) listeners.delete(name); },
        confirm() { return true; },
    };
    const state = studioInteractions();
    Object.assign(state, { width: 1920, height: 1080, scale: 0.5, preview: false, tab: 'library', showProperties: true,
        applyCanvas(w,h) { this.width=w; this.height=h; },
        $wire: {}, $refs: { canvas: { getBoundingClientRect: () => ({ left: 40, top: 50 }) } },
    });
    state.syncEditor(snapshot());
    return { state, listeners };
}
const pointer = (x=0,y=0) => ({ button: 0, clientX:x, clientY:y, pointerId:7, preventDefault() {}, stopPropagation() {} });

test('drop points use original output pixels at every zoom including Fit below 25%', () => {
    for (const scale of [0.12,0.25,0.5,1,2]) {
        const rect = { left: 37, top: -121 };
        const point = canvasPoint(rect.left+320*scale, rect.top+180*scale, rect, scale);
        assert.ok(Math.abs(point.x-320)<0.000001);
        assert.ok(Math.abs(point.y-180)<0.000001);
        assert.deepEqual(initialBox(point, 800, 400, 1920, 1080), {x:320,y:180,width:768,height:384});
    }
    const box = initialBox({x:5000,y:4000}, 800, 400, 1920, 1080);
    assert.equal(box.x+box.width,1920);
    assert.equal(box.y+box.height,1080);
});

test('all eight resize handles maintain ratio and bounds and free resize changes ratio', () => {
    const box = {x:100,y:100,width:400,height:200}, canvas={width:1920,height:1080};
    for (const handle of ['nw','n','ne','e','se','s','sw','w']) {
        for (const delta of [-10000,-30,30,10000]) {
            const next=resizeBox(box,handle,delta,delta,canvas,true);
            assert.ok(next.width>=1 && next.height>=1);
            assert.ok(Math.abs(next.width / next.height-2)<0.03);
            assert.ok(next.x>=0 && next.y>=0 && next.x+next.width<=1920 && next.y+next.height<=1080);
        }
    }
    assert.deepEqual(resizeBox(box,'se',100,50,canvas,true), {x:100,y:100,width:500,height:250});
    assert.deepEqual(resizeBox(box,'se',100,100,canvas,false), {x:100,y:100,width:500,height:300});
    assert.deepEqual(resizeBox(box,'nw',-50,-25,canvas,true), {x:50,y:75,width:450,height:225});
});

test('movement snaps to grid and edges while preventing overflow', () => {
    const box={x:100,y:100,width:400,height:200}, canvas={width:1920,height:1080};
    assert.deepEqual(moveBox(box,13,8,canvas,10), {...box,x:110,y:110});
    assert.equal(moveBox(box,1417,0,canvas,0,6).x,1520);
    assert.equal(moveBox(box,-98,0,canvas,0,6).x,0);
    const next=moveBox(box,10000,10000,canvas);
    assert.equal(next.x+next.width,1920); assert.equal(next.y+next.height,1080);
});

test('drag sends zero requests during pointer moves and one request at interaction end', async () => {
    const {state,listeners}=fixture(), calls=[];
    state.$wire.updateWindow=async (...args) => { calls.push(args); return true; };
    state.startWindow(pointer(),3);
    for (let i=1;i<=20;i++) state.pointerMove(pointer(i,i));
    assert.equal(calls.length,0);
    assert.equal(state.zoneForm.x,140);
    await state.finishPointer(false);
    assert.equal(calls.length,1);
    assert.deepEqual(calls[0], [1,3,{x:140,y:140,width:400,height:200}]);
    assert.equal(listeners.size,0);
});

test('resize persists original pixel size independently from zoom', async () => {
    for (const scale of [0.12,0.25,0.5,1,2]) {
        const {state}=fixture(), calls=[]; state.scale=scale;
        state.$wire.updateWindow=async (...args) => { calls.push(args); return true; };
        state.startWindow(pointer(),3,'se'); state.pointerMove(pointer(100*scale,50*scale));
        await state.finishPointer(false);
        assert.deepEqual(calls[0][2], {x:100,y:100,width:500,height:250});
    }
});

test('locked windows and unsaved properties do not begin gestures or lose edits', () => {
    const {state,listeners}=fixture();
    state.zones[3].is_locked=true; state.startWindow(pointer(),3);
    assert.equal(state.gesture,null); assert.equal(listeners.size,0);
    state.zones[3].is_locked=false; state.zoneDirty=true; state.zoneForm.name='Unsaved';
    state.startWindow(pointer(),3);
    assert.equal(state.gesture,null); assert.equal(state.zoneForm.name,'Unsaved');
});

test('sync after Livewire render preserves drafts without listeners accumulating', () => {
    const {state,listeners}=fixture();
    state.selectWindow(3); state.zoneDirty=true; state.zoneForm.name='Draft';
    for (let i=0;i<10;i++) state.syncEditor(snapshot());
    assert.equal(state.zoneForm.name,'Draft'); assert.equal(listeners.size,0);
    state.zoneDirty=false; state.startWindow(pointer(),3); state.pointerMove(pointer(20,20));
    state.syncEditor(snapshot());
    assert.equal(state.zones[3].x,140); // unrelated render must not overwrite the active drag
    state.syncEditor(snapshot(2));
    assert.equal(state.gesture,null); assert.equal(state.selectedZoneId,null); assert.equal(listeners.size,0);
    assert.equal(state.pageId,2);
    state.cleanupInteractions(); assert.equal(listeners.size,0);
});

test('pointer cancellation rolls back local geometry and sends no request', async () => {
    const {state,listeners}=fixture(); let called=false;
    state.$wire.updateWindow=async()=>{called=true;return true;};
    state.startWindow(pointer(),3); state.pointerMove(pointer(20,20));
    await state.finishPointer(true);
    assert.equal(state.zones[3].x,100); assert.equal(called,false); assert.equal(listeners.size,0);
});

test('drop busy guard rejects repeated drops until the first response completes', async () => {
    const {state}=fixture(); let resolve, calls=0;
    state.$wire.dropMedia=()=>{calls++;return new Promise(r=>{resolve=r;});};
    const prepare=()=>{state.dragMedia={id:8};state.dropGhost={x:10,y:20,width:400,height:200};};
    prepare(); const pending=state.dropMedia({preventDefault(){}});
    prepare(); await state.dropMedia({preventDefault(){}});
    await Promise.resolve(); assert.equal(calls,1); resolve(true); await pending; assert.equal(state.busy,false);
});

test('canvas handles show pixel preview and persist only on release; confirmed shrink keeps windows', async () => {
    const {state}=fixture(), calls=[];
    state.extentX=1900;
    state.$wire.resizeCanvas=async (...args)=>{calls.push(args);return true;};
    state.startCanvas(pointer(),'se'); state.pointerMove(pointer(-100,-40));
    assert.equal(state.width,1720); assert.equal(state.height,1000); assert.equal(calls.length,0);
    assert.equal(state.zones[3].width,400);
    await state.finishPointer(false);
    assert.deepEqual(calls[0],[1720,1000,true]);
});

test('rejected resize cancels preview and failed requests release the busy flag', async () => {
    const {state}=fixture();
    state.extentX=1900; window.confirm=()=>false;
    state.startCanvas(pointer(),'e');state.pointerMove(pointer(-100,0));await state.finishPointer(false);
    assert.equal(state.width,1920);
    const result=await state.perform(()=>{throw new Error('Network');});
    assert.equal(result,false);assert.equal(state.busy,false);assert.ok(state.error.length>0);
});

test('fit modes map to actual image and video object-fit styles and tokens work', () => {
    assert.equal(objectFit('contain'),'contain');assert.equal(objectFit('cover'),'cover');assert.equal(objectFit('stretch'),'fill');
    const {state}=fixture();state.zones[3].fit_mode='stretch';assert.equal(state.fitMedia(3).objectFit,'fill');
    assert.match(requestToken(),/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/);
});


test('Livewire commit failure settles pending action and cleans its scoped hook', async () => {
    const {state}=fixture(); let hook, failed, unhooked=0;
    state.$wire.$id='studio-1';
    window.Livewire={hook(name,callback){assert.equal(name,'commit');hook=callback;return ()=>unhooked++;}};
    state.initializeInteractions();
    hook({component:{id:'other'},fail(){throw new Error('Unrelated component hook');}});
    const pending=state.perform(()=>new Promise(()=>{}));
    hook({component:{id:'studio-1'},fail(callback){failed=callback;}});
    failed();
    assert.equal(await pending,false);assert.equal(state.busy,false);assert.ok(state.error);
    state.destroyInteractions();assert.equal(unhooked,1);
});
