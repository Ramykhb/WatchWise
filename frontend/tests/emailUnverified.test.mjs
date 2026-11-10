import assert from 'node:assert/strict'
import { mock, test } from 'node:test'

// Capture the response interceptor so it can be driven without a network.
let onRejected
const posts = []
const client = Object.assign(async () => ({ data: 'retried' }), {
  post: async (url) => { posts.push(url); return { data: {} } },
  // The in-lock session re-check: the session is still expired.
  get: async () => { throw Object.assign(new Error('expired'), { isAxiosError: true, response: { status: 401 } }) },
  interceptors: { response: { use(_onFulfilled, rejected) { onRejected = rejected } } },
})
mock.module('axios', { defaultExport: {
  create: () => client,
  isAxiosError: (error) => error?.isAxiosError === true,
  isCancel: () => false,
} })

const events = []
globalThis.window = {
  location: { pathname: '/home-page', replace() {} },
  dispatchEvent: (event) => events.push(event.type),
}

const { EMAIL_UNVERIFIED_EVENT, isEmailUnverified } = await import('../src/lib/api.ts')

const failure = (status, data) => Object.assign(new Error('Request failed'), {
  isAxiosError: true,
  config: { url: '/home' },
  response: { status, data },
})

test('an email_unverified 403 announces the unverified account and is not refreshed', async () => {
  events.length = 0
  posts.length = 0
  const error = failure(403, { message: 'Verify your email address to continue.', code: 'email_unverified' })

  await assert.rejects(onRejected(error), (reason) => reason === error)

  assert.deepEqual(events, [EMAIL_UNVERIFIED_EVENT])
  assert.deepEqual(posts, [])
})

test('other 403s are neither announced nor refreshed', async () => {
  events.length = 0
  posts.length = 0
  const error = failure(403, { message: 'Forbidden.' })

  await assert.rejects(onRejected(error), (reason) => reason === error)

  assert.deepEqual(events, [])
  assert.deepEqual(posts, [])
})

test('a 401 refreshes the session and retries the request', async () => {
  events.length = 0
  posts.length = 0

  const response = await onRejected(failure(401, { message: 'Unauthenticated.' }))

  assert.deepEqual(posts, ['/auth/refresh'])
  assert.equal(response.data, 'retried')
})

test('a failed initial session probe never logs out or redirects', async () => {
  events.length = 0
  posts.length = 0
  const error = failure(401, {})
  error.config = { url: '/auth/me', _probe: true, _retried: true }

  await assert.rejects(onRejected(error), (reason) => reason === error)

  assert.deepEqual(posts, [])
  assert.deepEqual(events, [])
})

test('isEmailUnverified only matches the 403 with the email_unverified code', () => {
  assert.equal(isEmailUnverified(failure(403, { code: 'email_unverified' })), true)
  assert.equal(isEmailUnverified(failure(401, { code: 'email_unverified' })), false)
  assert.equal(isEmailUnverified(failure(403, { message: 'Forbidden.' })), false)
  assert.equal(isEmailUnverified(new Error('boom')), false)
})
