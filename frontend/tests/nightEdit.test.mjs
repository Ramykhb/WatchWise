import {test} from 'node:test'
import assert from 'node:assert/strict'
import {nightEditPayload} from '../src/pages/MovieNights/nightEdit.ts'
const fields = {title:' Film night ',date:'2030-06-20',time:'20:30',location:' Home ',meetingLink:'',note:' Note '}
test('editing details retains an unchanged schedule and clears optional fields explicitly',()=>{
 assert.deepEqual(nightEditPayload(fields,fields.date,fields.time),{title:'Film night',location:'Home',meeting_link:null,note:'Note'})
})
test('rescheduling converts browser-local date and time to an absolute timestamp',()=>{
 assert.equal(nightEditPayload(fields,'2030-06-19',fields.time,0).scheduled_at,new Date('2030-06-20T20:30').toISOString())
})
test('invalid titles, changed past times and unsafe links cannot be submitted',()=>{
 assert.throws(()=>nightEditPayload({...fields,title:' '},fields.date,fields.time),/title/)
 assert.throws(()=>nightEditPayload(fields,'2029-01-01',fields.time,Date.parse('2031-01-01')),/future/)
 assert.throws(()=>nightEditPayload({...fields,meetingLink:'javascript:alert(1)'},fields.date,fields.time),/meeting/)
})
