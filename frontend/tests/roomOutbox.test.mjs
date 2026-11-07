import assert from 'node:assert/strict'
import { test } from 'node:test'
import { confirmOutgoing, visibleOutgoing } from '../src/pages/Rooms/roomOutbox.ts'

const author = { id: 1, name: 'Ramy', username: 'ramy', avatar: null }
const message = (id, content = 'Hello', user = author) => ({
  id, room_id: 10, user, content, created_at: '2026-11-07T12:00:00Z', read_by: [user], unread_by: [],
})
const pending = (id, overrides = {}) => ({
  id, threadKey: '1:10', userId: 1, content: 'Hello', createdAt: '2026-11-07T12:00:00Z',
  status: 'sending', knownIds: [1], ...overrides,
})

test('shows an immediate local message while the server has not responded', () => {
  const item = pending('first')
  assert.deepEqual(visibleOutgoing([item], [message(1)], '1:10'), [item])
})

test('a socket echo before the HTTP response replaces exactly one local bubble', () => {
  const first = pending('first')
  const second = pending('second')
  assert.deepEqual(visibleOutgoing([first, second], [message(1), message(2)], '1:10'), [second])
  assert.deepEqual(visibleOutgoing([first, second], [message(1), message(2), message(3)], '1:10'), [])
})

test('acknowledging one send cannot hide another identical pending message', () => {
  const next = confirmOutgoing([pending('first'), pending('second')], 'first', 2)
  assert.equal(next.length, 1)
  assert.equal(next[0].id, 'second')
  assert.deepEqual(visibleOutgoing(next, [message(1), message(2)], '1:10'), next)
})

test('out of order acknowledgements do not remove other sends or rooms', () => {
  const otherRoom = pending('other', { threadKey: '1:11' })
  const next = confirmOutgoing([pending('first'), pending('second'), otherRoom], 'second', 3)
  assert.deepEqual(next.map((item) => item.id), ['first', 'other'])
  assert.deepEqual(next[1], otherRoom)
  assert.equal(visibleOutgoing(next, [message(3)], '1:10').length, 1)
})

test('failed messages remain available to retry, scoped to their room and account', () => {
  const failed = pending('first', { status: 'failed', error: 'Offline' })
  assert.deepEqual(visibleOutgoing([failed], [], '1:10'), [failed])
  assert.deepEqual(visibleOutgoing([failed], [], '1:11'), [])
  assert.deepEqual(visibleOutgoing([failed], [], '2:10'), [])
  assert.deepEqual(visibleOutgoing([failed], [message(2, 'Hello', { ...author, id: 2 })], '1:10'), [failed])
})
