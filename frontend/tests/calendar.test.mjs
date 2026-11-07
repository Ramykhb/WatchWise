import assert from 'node:assert/strict'
import { test } from 'node:test'
import { googleCalendarUrl, outlookCalendarUrl, toCalendarTime, watchEventIcs } from '../src/pages/MovieDetails/calendar.ts'
import { movieNightEvent } from '../src/pages/MovieNights/nightCalendar.ts'

test('Outlook preserves the title and UTC instants across midnight', () => {
  const start = new Date('2026-11-08T23:30:00+02:00')
  const end = new Date(start.getTime() + 180 * 60_000)
  const url = new URL(outlookCalendarUrl('Watch Film & Friends: Part 2', start, end))
  assert.equal(url.origin, 'https://outlook.live.com')
  assert.equal(url.searchParams.get('subject'), 'Watch Film & Friends: Part 2')
  assert.equal(url.searchParams.get('startdt'), '2026-11-08T21:30:00.000Z')
  assert.equal(url.searchParams.get('enddt'), '2026-11-09T00:30:00.000Z')
  assert.equal(url.searchParams.get('allday'), 'false')
})

test('Apple event escapes title content and folds Unicode without corrupting it', () => {
  const title = `Watch ${'映画🎬'.repeat(30)}, Part; 2\\3\r\nEND:VEVENT`
  const start = new Date('2026-11-08T18:00:00Z')
  const end = new Date('2026-11-08T20:15:00Z')
  const ics = watchEventIcs(title, start, end)
  const unfolded = ics.replace(/\r\n /g, '')
  assert.ok(ics.endsWith('\r\n'))
  for (const line of ics.split('\r\n')) assert.ok(Buffer.byteLength(line) <= 75)
  assert.ok(unfolded.includes(`SUMMARY:Watch ${'映画🎬'.repeat(30)}\\, Part\\; 2\\\\3\\nEND:VEVENT\r\n`))
  assert.ok(unfolded.includes('DTSTART:20261108T180000Z\r\nDTEND:20261108T201500Z'))
  assert.match(unfolded, /DTSTAMP:\d{8}T\d{6}Z\r\n/)
  assert.equal(ics.split('\r\nEND:VEVENT\r\n').length, 2)
  const uid = unfolded.match(/UID:([^\r]+)/)[1]
  assert.notEqual(watchEventIcs(title, start, end).match(/UID:([^\r]+)/)[1], uid)
})

test('movie night exports keep the local scheduled time, host, meeting link and location for every calendar', () => {
  const event = movieNightEvent(
    { title: 'Film & Friends', date: '2026-11-08', time: '23:30' },
    { host: { name: 'Ramy' }, link: 'https://example.com/meet?a=1&b=2' },
    'Cinema, Screen; 2',
  )
  assert.equal(event.start.getHours(), 23)
  assert.equal(event.start.getMinutes(), 30)
  assert.equal(event.end.getDate(), 9)
  assert.equal(event.end - event.start, 3 * 60 * 60 * 1000)
  const google = new URL(googleCalendarUrl(event.title, toCalendarTime(event.start), toCalendarTime(event.end), event.details))
  const outlook = new URL(outlookCalendarUrl(event.title, event.start, event.end, event.details))
  const ics = watchEventIcs(event.title, event.start, event.end, event.details).replace(/\r\n /g, '')
  assert.equal(google.searchParams.get('dates'), `${toCalendarTime(event.start)}/${toCalendarTime(event.end)}`)
  assert.equal(google.searchParams.get('details'), event.details.description)
  assert.equal(outlook.searchParams.get('body'), event.details.description)
  for (const url of [google, outlook]) assert.equal(url.searchParams.get('location'), event.details.location)
  assert.ok(ics.includes(`DESCRIPTION:Hosted by Ramy\\nOnline: https://example.com/meet?a=1&b=2\r\n`))
  assert.ok(ics.includes('LOCATION:Cinema\\, Screen\\; 2\r\n'))
  assert.ok(ics.includes(`DTSTART:${toCalendarTime(event.start)}\r\nDTEND:${toCalendarTime(event.end)}`))
})
