import assert from 'node:assert/strict'
import { mock, test } from 'node:test'

let connectionChanged
let subscribed
let subscriptionFailed
let stopped = false
let disconnected = false
const api = {
  get: async () => ({ data: { key: 'test', host: 'localhost', port: 8080, scheme: 'http' } }),
}

class Echo {
  connector = {
    onConnectionChange(callback) {
      connectionChanged = callback
      return () => { stopped = true }
    },
  }

  private() {
    return {
      subscribed(callback) { subscribed = callback; return this },
      error(callback) { subscriptionFailed = callback; return this },
      listen() { return this },
    }
  }

  leave() {}
  disconnect() { disconnected = true }
}

mock.module('laravel-echo', { defaultExport: Echo })
mock.module('pusher-js', { defaultExport: class Pusher {} })
mock.module('../src/lib/api.ts', { namedExports: { api } })

const { openNotificationSocket } = await import('../src/pages/Notifications/notificationSocket.ts')

test('polling stays enabled until subscription succeeds and resumes on errors or disconnects', async () => {
  const statuses = []
  let refreshes = 0
  const socket = await openNotificationSocket(42, {
    onConnected: (ready) => statuses.push(ready),
    onReady: () => { refreshes++ },
    onPushed() {},
    onChanged() {},
  })

  connectionChanged('connected')
  assert.deepEqual(statuses, [false])
  subscriptionFailed(new Error('Authorization returned 429'))
  assert.equal(statuses.at(-1), false)
  assert.equal(refreshes, 0)

  subscribed()
  assert.equal(statuses.at(-1), true)
  assert.equal(refreshes, 1)

  connectionChanged('disconnected')
  assert.equal(statuses.at(-1), false)
  connectionChanged('connected')
  assert.equal(statuses.at(-1), false)
  subscribed()
  assert.equal(statuses.at(-1), true)
  assert.equal(refreshes, 2)

  subscriptionFailed(new Error('Subscription failed'))
  assert.equal(statuses.at(-1), false)
  socket.disconnect()
  assert.equal(stopped, true)
  assert.equal(disconnected, true)
})
