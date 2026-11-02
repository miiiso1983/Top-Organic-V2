'use strict';
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const source = fs.readFileSync(__dirname + '/../public/assets/js/ptt-receiver.js', 'utf8');
const settle = async () => { for (let i = 0; i < 5; i++) await new Promise(setImmediate); };
async function fixture() {
  const listeners = {}, timers = [], plays = [], acknowledgements = [], feeds = [];
  const button = { dataset: {user: '2'}, textContent: '', setAttribute() {}, addEventListener(n, f) { listeners[n] = f; } };
  let audio, delayedAudio;
  class Audio {
    constructor() { audio = this; }
    async play() { plays.push(this.src); }
    pause() {}
    removeAttribute() {}
  }
  const window = { addEventListener() {} };
  let blobId = 0;
  const context = { window, document: {getElementById: () => button}, Audio,
    navigator: {locks: {request: (name, options, fn) => fn({})}},
    URL: {createObjectURL: () => 'blob:' + ++blobId, revokeObjectURL() {}},
    setTimeout: (fn) => timers.push(fn),
    fetch: async (url, options) => {
      if (url.endsWith('/played')) { acknowledgements.push(url); return {ok:true}; }
      if (url.endsWith('/audio')) {
        if (delayedAudio) return delayedAudio;
        return {ok:true,blob:async()=>({})};
      }
      const data = feeds.shift() || {cursor:10,messages:[]};
      return {ok:!data.denied,status:data.denied?403:200,json:async()=>({ok:true,data})};
    }
  };
  vm.runInNewContext(source, context);
  await listeners.click(); await settle();
  return {audio,window,plays,acknowledgements,feeds,timers,button,
    deferAudio() { let resolve; delayedAudio = new Promise(r => resolve=r); return resolve; }};
}
(async()=>{
  const f=await fixture();
  f.feeds.push({cursor:12,messages:[{id:11,sender_name:'One'},{id:12,sender_name:'Two'}]});
  f.timers.shift()();await settle();
  assert.equal(f.plays.filter(x=>x.startsWith('blob:')).length,1,'play one at a time');
  assert.equal(f.acknowledgements.length,0,'do not mark played at start');
  f.audio.onended();await settle();
  assert.deepEqual(f.acknowledgements,['/ptt/messages/11/played']);
  assert.equal(f.plays.filter(x=>x.startsWith('blob:')).length,2,'advance after completion');
  f.window.PttReceiver.pause(true);
  assert.equal(f.audio.onended,null,'interrupted audio cannot acknowledge completion');
  f.feeds.push({denied:true});f.timers.shift()();await settle();
  assert.equal(f.button.textContent,'تشغيل استقبال PTT','permission loss stops receiver');
  const g=await fixture(), resolve=g.deferAudio();
  g.feeds.push({cursor:11,messages:[{id:11,sender_name:'Late'}]});g.timers.shift()();await settle();
  g.window.PttReceiver.stop();resolve({ok:true,blob:async()=>({})});await settle();
  assert.equal(g.plays.filter(x=>x.startsWith('blob:')).length,0,'late downloads cannot play after stop');
  console.log('PASS: sequential playback, completion-only acknowledgment, pause, permission revocation and stale download cancellation.');
})().catch(e=>{console.error(e);process.exitCode=1;});
