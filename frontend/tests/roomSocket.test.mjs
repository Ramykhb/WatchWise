import assert from 'node:assert/strict'
import { mock, test } from 'node:test'
const channels = new Map()
const left = []
class Echo {
  connector = { onConnectionChange: () => () => {} }
  private(name) {
    const listeners = new Map()
    channels.set(name, listeners)
    return { subscribed() { return this }, listen(event, callback) { listeners.set(event, callback); return this } }
  }
  leave(name) { left.push(name) }
  disconnect() {}
}
mock.module('laravel-echo', { defaultExport: Echo })
mock.module('pusher-js', { defaultExport: class Pusher {} })
mock.module('../src/lib/api.ts', { namedExports: { api: { get: async () => ({ data: { key: 'test', host: 'localhost', port: 8080, scheme: 'http' } }) } } })
const { openRoomSocket } = await import('../src/pages/Rooms/roomSocket.ts')
test('staff deletion reaches the open-room handler on its private channel', async () => {
  const received = []
  const socket = await openRoomSocket(() => {})
  const leave = socket.join(7, { onMessage() {}, onRead() {}, onReady() {}, onDeleted: (event) => received.push(event) })
  channels.get('room.7').get('.message.deleted')({ room_id: 7, message_id: 42 })
  assert.deepEqual(received, [{ room_id: 7, message_id: 42 }])
  leave()
  assert.deepEqual(left, ['room.7'])
})
