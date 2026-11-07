import assert from 'node:assert/strict'
import { test } from 'node:test'
import { outlookCalendarUrl, watchEventIcs } from '../src/pages/MovieDetails/calendar.ts'

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
