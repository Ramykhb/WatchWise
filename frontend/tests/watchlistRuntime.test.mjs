import {test} from 'node:test'
import assert from 'node:assert/strict'
import {toQueueItem, compareRuntime, queuedRuntime} from '../src/pages/Watchlist/watchlistData.ts'
const item=(id,type='movie')=>({id,tmdb_id:id,media_type:type,status:'to_watch',created_at:'2026-11-21T10:00:00Z',watched_at:null})
const movie=(runtime)=>({slug:'1',title:'Film',meta:'2020',runtimeMinutes:runtime})
test('runtime sorting uses distinct real durations and leaves unknown values last',()=>{
 const entries=[toQueueItem(item(1),()=>movie(150)),toQueueItem(item(2),()=>movie(null)),toQueueItem(item(3),()=>movie(85))]
 assert.deepEqual(entries.sort(compareRuntime).map(x=>x.id),[3,1,2])
 assert.deepEqual(queuedRuntime(entries),{minutes:235,unknown:1})
})
test('missing and invalid durations are unknown rather than fabricated',()=>{
 for(const runtime of [undefined,null,0,-1,NaN,Infinity])assert.equal(toQueueItem(item(1),()=>movie(runtime)).runtimeMinutes,null)
})
test('TV episode lengths are labelled and excluded from full-queue movie hours',()=>{
 const show=toQueueItem(item(1,'tv'),()=>movie(45))
 assert.match(show.details,/45 min\/episode/)
 assert.deepEqual(queuedRuntime([show]),{minutes:0,unknown:1})
})
