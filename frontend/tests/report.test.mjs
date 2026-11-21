import test from 'node:test'
import assert from 'node:assert/strict'
import { REPORT_REASONS, reportEndpoint, reportPayload } from '../src/components/ReportButton/report.ts'

test('report routes address each target and encode profile usernames', () => {
  assert.equal(reportEndpoint({ type: 'review', id: 7 }), '/reviews/7/report')
  assert.equal(reportEndpoint({ type: 'list', id: 7 }), '/lists/7/report')
  assert.equal(reportEndpoint({ type: 'room', id: 7 }), '/rooms/7/report')
  assert.equal(reportEndpoint({ type: 'message', id: 8, roomId: 7 }), '/rooms/7/messages/8/report')
  assert.equal(reportEndpoint({ type: 'profile', username: 'a/b' }), '/users/a%2Fb/report')
})
test('review reasons keep the API capitalization and omit unsupported details', () => {
  assert.deepEqual(reportPayload('review', 'Spoilers', 'Explanation'), { reason: 'Spoilers' })
  assert.throws(() => reportPayload('review', 'spoilers', ''), /reason/)
})
test('other targets accept their own reasons with trimmed optional details', () => {
  for (const type of ['message', 'list', 'room', 'profile']) {
    for (const reason of REPORT_REASONS[type]) assert.deepEqual(reportPayload(type, reason, '  Details  '), { reason, details: 'Details' })
    assert.deepEqual(reportPayload(type, 'spam', '  '), { reason: 'spam', details: null })
    assert.throws(() => reportPayload(type, 'spam', 'x'.repeat(501)), /500/)
  }
})
