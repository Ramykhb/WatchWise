import assert from 'node:assert/strict'
import { mock, test } from 'node:test'

// Capture the response interceptor so it can be driven without a network.
let onRejected
const posts = []
const client = Object.assign(async () => ({ data: 'retried' }), {
  post: async (url) => { posts.push(url); return { data: {} } },
  get: async () => ({ data: {} }),
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

test('other 403s do not announce an unverified account', async () => {
  events.length = 0
  posts.length = 0

  await onRejected(failure(403, { message: 'Forbidden.' }))

  assert.deepEqual(events, [])
  assert.deepEqual(posts, ['/auth/refresh'])
})

test('isEmailUnverified only matches the 403 with the email_unverified code', () => {
  assert.equal(isEmailUnverified(failure(403, { code: 'email_unverified' })), true)
  assert.equal(isEmailUnverified(failure(401, { code: 'email_unverified' })), false)
  assert.equal(isEmailUnverified(failure(403, { message: 'Forbidden.' })), false)
  assert.equal(isEmailUnverified(new Error('boom')), false)
})
