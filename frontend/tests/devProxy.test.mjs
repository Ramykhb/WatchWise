import assert from 'node:assert/strict'
import { createServer as createHttpServer } from 'node:http'
import { once } from 'node:events'
import test from 'node:test'
import { createServer as createViteServer } from 'vite'

test('local API proxy keeps auth cookies on localhost and forwards them to the backend', async () => {
  const upstream = createHttpServer((request, response) => {
    if (request.url === '/api/auth/login') {
      response.setHeader('Set-Cookie', [
        'access_token=test-access; Path=/; HttpOnly; Secure; SameSite=Lax',
        'refresh_token=test-refresh; Path=/api/auth; Domain=backend.example; HttpOnly; Secure; SameSite=Lax',
      ])
      response.end('{}')
      return
    }
    response.setHeader('Content-Type', 'application/json')
    response.end(JSON.stringify({ cookie: request.headers.cookie }))
  })
  upstream.listen(0, '127.0.0.1')
  await once(upstream, 'listening')

  const previousBackend = process.env.VITE_BACKEND_URL
  process.env.VITE_BACKEND_URL = `http://127.0.0.1:${upstream.address().port}`
  let vite
  try {
    vite = await createViteServer({
      logLevel: 'silent',
      server: { host: '127.0.0.1', port: 0, strictPort: false, watch: null },
    })
    await vite.listen()
    const base = `http://127.0.0.1:${vite.httpServer.address().port}`

    const login = await fetch(`${base}/api/auth/login`, { method: 'POST' })
    assert.equal(login.status, 200)
    const cookies = login.headers.getSetCookie()
    assert.equal(cookies.length, 2)
    for (const cookie of cookies) {
      assert.doesNotMatch(cookie, /;\s*Secure\b/i)
      assert.doesNotMatch(cookie, /;\s*Domain=/i)
      assert.match(cookie, /;\s*HttpOnly\b/i)
      assert.match(cookie, /;\s*SameSite=Lax\b/i)
    }
    assert.match(cookies[1], /Path=\/api\/auth/)

    const cookie = cookies.map((value) => value.split(';')[0]).join('; ')
    const me = await fetch(`${base}/api/auth/me`, { headers: { Cookie: cookie } })
    assert.deepEqual(await me.json(), { cookie: 'access_token=test-access; refresh_token=test-refresh' })
  } finally {
    await vite?.close()
    await new Promise((resolve) => upstream.close(resolve))
    if (previousBackend === undefined) delete process.env.VITE_BACKEND_URL
    else process.env.VITE_BACKEND_URL = previousBackend
  }
})
