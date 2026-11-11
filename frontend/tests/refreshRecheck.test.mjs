import assert from 'node:assert/strict'
import { test } from 'node:test'

globalThis.window = { location: { pathname: '/home-page', replace() {} }, dispatchEvent() {} }

const { api } = await import('../src/lib/api.ts')

// A fake adapter drives the real axios interceptor chain: the access cookie is expired until /auth/refresh.
const calls = []
let refreshed = false
api.defaults.adapter = async (config) => {
  const path = config.url
  calls.push(path)
  const respond = (status, data) => {
    const response = { status, statusText: String(status), data, headers: {}, config, request: {} }
    if (status < 400) return response
    const error = new Error(`Request failed with status code ${status}`)
    error.isAxiosError = true
    error.config = config
    error.response = response
    error.toJSON = () => ({})
    throw error
  }
  if (path === '/auth/refresh') { refreshed = true; return respond(200, {}) }
  if (!refreshed) return respond(401, { message: 'Unauthenticated.' })
  return respond(200, { ok: path })
}

test('a 401 refreshes through the real interceptor: the in-lock /auth/me re-check does not queue behind the refresh', async () => {
  const timeout = new Promise((_, reject) => setTimeout(() => reject(new Error('refresh hung')), 1000).unref())

  const response = await Promise.race([api.get('/home'), timeout])

  assert.deepEqual(response.data, { ok: '/home' })
  assert.deepEqual(calls, ['/home', '/auth/me', '/auth/refresh', '/home'])
})
