import assert from 'node:assert/strict'
import { test } from 'node:test'
import {
  decideRefresh, getLocks, refreshAcrossTabs, shouldEndSession, shouldRefreshStatus, withRefreshLock, REFRESH_LOCK_NAME,
} from '../src/lib/sessionRefresh.ts'
import { AUTH_CHANNEL, AUTH_SYNC_KEY, createAuthSync, parseAuthMessage, shouldRecheckOnVisible, transitionType } from '../src/lib/authSync.ts'
import { returnToFrom } from '../src/lib/returnTo.ts'
import { USER_STORAGE_KEYS, clearUserStorage } from '../src/lib/userStorage.ts'

const base = { status: 401, hasConfig: true, aborted: false, skipUrl: false, retried: false }

test('only a 401 refreshes; 403 and other statuses are rejected as they are', () => {
  assert.equal(shouldRefreshStatus(401), true)
  for (const status of [403, 419, 500, undefined]) assert.equal(shouldRefreshStatus(status), false, String(status))
  assert.equal(decideRefresh(base), 'refresh')
  assert.equal(decideRefresh({ ...base, status: 403 }), 'reject')
  assert.equal(decideRefresh({ ...base, retried: true }), 'clear')
  assert.equal(decideRefresh({ ...base, retried: true, status: 403 }), 'reject')
  assert.equal(decideRefresh({ ...base, skipUrl: true }), 'reject')
  assert.equal(decideRefresh({ ...base, aborted: true }), 'reject')
  assert.equal(decideRefresh({ ...base, hasConfig: false }), 'reject')
})

test('the initial session probe never ends the session', () => {
  assert.equal(shouldEndSession(true), false)
  assert.equal(shouldEndSession(false), true)
  assert.equal(shouldEndSession(undefined), true)
})

function fakeLocks() {
  const log = []
  let tail = Promise.resolve()
  return {
    log,
    request(name, callback) {
      log.push(name)
      const run = tail.then(callback)
      tail = run.then(() => undefined, () => undefined)
      return run
    },
  }
}

test('withRefreshLock uses navigator.locks when present and falls back to running the task', async () => {
  const locks = fakeLocks()
  assert.equal(await withRefreshLock(async () => 'a', locks), 'a')
  assert.deepEqual(locks.log, [REFRESH_LOCK_NAME])
  assert.equal(await withRefreshLock(async () => 'b', undefined), 'b')
  assert.equal(getLocks({}), undefined)
  assert.equal(getLocks({ locks: {} }), undefined)
  assert.equal(getLocks({ locks }), locks)
})

test('two tabs refreshing together post /auth/refresh once; the second finds the session valid', async () => {
  const locks = fakeLocks()
  let valid = false
  let posts = 0
  const tab = () => refreshAcrossTabs({
    isSessionValid: async () => valid,
    refresh: async () => { posts++; valid = true },
  }, locks)

  const results = await Promise.all([tab(), tab()])

  assert.deepEqual(results, ['refreshed', 'already'])
  assert.equal(posts, 1)
})

test('refreshAcrossTabs refreshes when the re-check fails, and propagates a refresh failure', async () => {
  const locks = fakeLocks()
  let posts = 0
  const result = await refreshAcrossTabs({
    isSessionValid: async () => { throw new Error('offline') },
    refresh: async () => { posts++ },
  }, locks)
  assert.equal(result, 'refreshed')
  assert.equal(posts, 1)

  await assert.rejects(refreshAcrossTabs({
    isSessionValid: async () => false,
    refresh: async () => { throw new Error('rejected') },
  }, undefined), /rejected/)
})

test('returnToFrom keeps the full internal location and refuses anything else', () => {
  assert.equal(returnToFrom({ from: { pathname: '/movies/123', search: '?tab=cast', hash: '#reviews' } }), '/movies/123?tab=cast#reviews')
  assert.equal(returnToFrom({ from: { pathname: '/lists' } }), '/lists')
  assert.equal(returnToFrom(null), '/home-page')
  assert.equal(returnToFrom({ from: '/lists' }), '/home-page')
  assert.equal(returnToFrom({ from: { pathname: '//evil.test' } }), '/home-page')
  assert.equal(returnToFrom({ from: { pathname: 'https://evil.test' } }), '/home-page')
  assert.equal(returnToFrom({ from: { pathname: '/\\evil.test' } }), '/home-page')
  assert.equal(returnToFrom({ from: { pathname: '/x', search: 'evil', hash: 'evil' } }), '/x')
  assert.equal(returnToFrom({}, '/elsewhere'), '/elsewhere')
})

test('only sign-in, sign-out, account switch and verification changes are announced', () => {
  const user = (id, verified = true) => ({ id, verified })
  assert.equal(transitionType(null, user(1)), 'login')
  assert.equal(transitionType(user(1), null), 'logout')
  assert.equal(transitionType(user(1), user(2)), 'login')
  assert.equal(transitionType(user(1, false), user(1, true)), 'user-change')
  assert.equal(transitionType(user(1, true), user(1, false)), 'user-change')
  assert.equal(transitionType(user(1), user(1)), null, 'a same-account update stays local')
  assert.equal(transitionType(null, null), null)
})

test('parseAuthMessage drops junk and the tab\'s own messages', () => {
  const message = { type: 'logout', origin: 'tab-a', at: 5 }
  assert.deepEqual(parseAuthMessage(message, 'tab-b'), message)
  assert.deepEqual(parseAuthMessage(JSON.stringify(message), 'tab-b'), message)
  assert.equal(parseAuthMessage(message, 'tab-a'), null)
  for (const junk of [null, 'nope', 7, { type: 'other', origin: 'x' }, { type: 'login' }]) assert.equal(parseAuthMessage(junk, 'tab-b'), null)
})

test('a tab only reacts to other tabs over BroadcastChannel and does not hear itself', () => {
  const channels = []
  const createChannel = (name) => {
    const channel = {
      name, onmessage: null, closed: false,
      postMessage(message) { channels.filter((other) => other !== channel).forEach((other) => other.onmessage?.({ data: message })) },
      close() { channel.closed = true },
    }
    channels.push(channel)
    return channel
  }
  const heardA = []
  const heardB = []
  const a = createAuthSync((m) => heardA.push(m.type), { createChannel })
  const b = createAuthSync((m) => heardB.push(m.type), { createChannel })

  a.broadcast('login')
  b.broadcast('logout')

  assert.equal(channels[0].name, AUTH_CHANNEL)
  assert.deepEqual(heardA, ['logout'])
  assert.deepEqual(heardB, ['login'])
  a.close()
  assert.equal(channels[0].closed, true)
})

test('without BroadcastChannel the storage event carries the message', () => {
  const written = []
  let listener
  const heard = []
  const sync = createAuthSync((m) => heard.push(m.type), {
    storage: { setItem: (key, value) => written.push([key, value]) },
    addStorageListener: (fn) => { listener = fn; return () => { listener = undefined } },
  })

  sync.broadcast('user-change')
  assert.equal(written[0][0], AUTH_SYNC_KEY)

  listener({ key: 'other', newValue: written[0][1] })
  listener({ key: AUTH_SYNC_KEY, newValue: null })
  listener({ key: AUTH_SYNC_KEY, newValue: written[0][1] })
  assert.deepEqual(heard, [], 'own message and unrelated keys are ignored')

  listener({ key: AUTH_SYNC_KEY, newValue: JSON.stringify({ type: 'login', origin: 'someone-else', at: 1 }) })
  assert.deepEqual(heard, ['login'])
  sync.close()
  assert.equal(listener, undefined)
})

test('a tab re-checks its session only after being hidden for over a minute', () => {
  assert.equal(shouldRecheckOnVisible(null, 100_000), false)
  assert.equal(shouldRecheckOnVisible(0, 60_000), false)
  assert.equal(shouldRecheckOnVisible(0, 60_001), true)
})

test('clearUserStorage removes the precise-location caches and tolerates blocked storage', () => {
  const removed = []
  clearUserStorage({ removeItem: (key) => removed.push(key) })
  assert.deepEqual(removed, USER_STORAGE_KEYS)
  assert.ok(removed.includes('watchwise:weather-mood:v1'))
  assert.ok(removed.includes('watchwise:weather-recommendations:v1'))
  assert.ok(removed.includes('watchwise:tonights-pick:v1'))
  assert.doesNotThrow(() => clearUserStorage({ removeItem: () => { throw new Error('blocked') } }))
  assert.doesNotThrow(() => clearUserStorage(undefined))
})
