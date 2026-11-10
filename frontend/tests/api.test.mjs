import assert from 'node:assert/strict'
import { mock, test } from 'node:test'

let slots = []
let cursor = 0
let effects = []
let get
const client = { get: (...args) => get(...args), interceptors: { response: { use() {} } } }
mock.module('axios', { defaultExport: {
  create: () => client,
  isAxiosError: (error) => error?.isAxiosError === true,
  isCancel: () => false,
} })
// Model state and effect cleanup so request races can be tested without a DOM.
mock.module('react', { namedExports: {
  useState(initial) {
    const index = cursor++
    slots[index] ??= { value: typeof initial === 'function' ? initial() : initial }
    return [slots[index].value, (update) => {
      slots[index].value = typeof update === 'function' ? update(slots[index].value) : update
    }]
  },
  useEffect(effect, deps) {
    const index = cursor++
    if (!slots[index] || deps.some((dep, i) => dep !== slots[index].deps[i])) {
      slots[index]?.cleanup?.()
      slots[index] = { deps }
      effects.push(() => { slots[index].cleanup = effect() })
    }
  },
  useCallback: (callback) => callback,
} })
const { useApi, clearApiCache, isGuestRoute, shouldSkipRefresh } = await import('../src/lib/api.ts')
const flush = () => new Promise((resolve) => setImmediate(resolve))
const failure = (status) => Object.assign(new Error('Request failed'), {
  isAxiosError: true, response: status ? { status } : undefined,
})
function render(options) {
  cursor = 0
  const result = useApi('/rooms', options)
  effects.splice(0).forEach((effect) => effect())
  return result
}
function setup(t) {
  slots = []
  effects = []
  clearApiCache()
  t.after(() => slots.forEach((slot) => slot.cleanup?.()))
}

test('failed background refresh preserves rooms; manual retry updates them', async (t) => {
  setup(t)
  const data = { rooms: [{ id: 1 }] }
  get = async () => ({ data })
  render()
  await flush()
  let query = render()
  assert.deepEqual(query.data, data)
  get = async () => { throw failure(503) }
  query.reload()
  render()
  await flush()
  query = render()
  assert.equal(query.loading, false)
  assert.equal(query.error.response.status, 503)
  assert.deepEqual(query.data, data)
  get = async () => ({ data: { rooms: [{ id: 2 }] } })
  query.reload()
  render()
  await flush()
  query = render()
  assert.equal(query.error, undefined)
  assert.deepEqual(query.data.rooms, [{ id: 2 }])
})

test('temporary initial failures keep loading and automatically recover', async (t) => {
  setup(t)
  t.mock.timers.enable({ apis: ['setTimeout'] })
  let calls = 0
  get = async () => {
    if (++calls <= 2) throw failure(calls === 1 ? undefined : 503)
    return { data: { rooms: [{ id: 1 }] } }
  }
  render({ retries: 2 })
  await flush()
  assert.equal(render({ retries: 2 }).loading, true)
  t.mock.timers.tick(1000)
  await flush()
  assert.equal(render({ retries: 2 }).loading, true)
  t.mock.timers.tick(2000)
  await flush()
  const query = render({ retries: 2 })
  assert.equal(calls, 3)
  assert.equal(query.loading, false)
  assert.deepEqual(query.data.rooms, [{ id: 1 }])
})

test('exhausted retries report failure rather than successful empty data', async (t) => {
  setup(t)
  t.mock.timers.enable({ apis: ['setTimeout'] })
  let calls = 0
  get = async () => { calls++; throw failure(500) }
  render({ retries: 2 })
  await flush()
  t.mock.timers.tick(1000)
  await flush()
  t.mock.timers.tick(2000)
  await flush()
  const query = render({ retries: 2 })
  assert.equal(calls, 3)
  assert.equal(query.loading, false)
  assert.equal(query.data, undefined)
  assert.equal(query.error.response.status, 500)
})

test('authorization errors are not retried', async (t) => {
  setup(t)
  let calls = 0
  get = async () => { calls++; throw failure(403) }
  render({ retries: 2 })
  await flush()
  const query = render({ retries: 2 })
  assert.equal(calls, 1)
  assert.equal(query.loading, false)
  assert.equal(query.error.response.status, 403)
})

test('unmount cancels a pending retry', async (t) => {
  setup(t)
  t.mock.timers.enable({ apis: ['setTimeout'] })
  let calls = 0
  get = async () => { calls++; throw failure(503) }
  render({ retries: 2 })
  await flush()
  slots.forEach((slot) => slot.cleanup?.())
  t.mock.timers.tick(10_000)
  await flush()
  assert.equal(calls, 1)
})

test('account-link pages count as guest routes, lookalikes do not', () => {
  for (const path of ['/', '/signin', '/account-link', '/account-link/email-confirm', '/account-link/result']) assert.equal(isGuestRoute(path), true, path)
  for (const path of ['/account-link-evil', '/settings', '/account-linkx/a']) assert.equal(isGuestRoute(path), false, path)
})

test('signed confirm POSTs and auth endpoints never trigger a refresh', () => {
  const query = `?expires=1&signature=${'a'.repeat(64)}`
  for (const kind of ['email/confirm', 'email/authorize', 'delete/confirm']) {
    assert.equal(shouldSkipRefresh(`/me/${kind}/7/${'b'.repeat(64)}${query}`), true, kind)
    assert.equal(shouldSkipRefresh(`https://api.test/api/me/${kind}/7/x${query}`), true, kind)
  }
  assert.equal(shouldSkipRefresh('/auth/login'), true)
  assert.equal(shouldSkipRefresh('/me'), false)
  assert.equal(shouldSkipRefresh('/me/email/cancel/7/x'), false)
})
