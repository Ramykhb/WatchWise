import assert from 'node:assert/strict'
import { test } from 'node:test'
import { socialAuthUrl } from '../src/lib/backendUrl.ts'
import { loginPayload } from '../src/lib/loginPayload.ts'
import { nameRuleError } from '../src/lib/nameRule.ts'
import { classifyResetFailure, parseResetLink, resetBody } from '../src/pages/ResetPassword/resetPassword.ts'

const failure = (status, data) => Object.assign(new Error('Request failed'), { isAxiosError: true, response: { status, data } })

test('social URL trims trailing slashes and encodes the provider', () => {
  assert.equal(socialAuthUrl('github', 'http://api.test/'), 'http://api.test/api/auth/social/github')
  assert.equal(socialAuthUrl('google', 'http://api.test'), 'http://api.test/api/auth/social/google')
  assert.equal(socialAuthUrl('a/b', 'http://api.test//'), 'http://api.test/api/auth/social/a%2Fb')
})

test('login payload carries remember', () => {
  assert.deepEqual(loginPayload('a@b.co', 'pw', true), { email: 'a@b.co', password: 'pw', remember: true })
  assert.equal(loginPayload('a@b.co', 'pw', false).remember, false)
})

test('name rule: 1 to 255 after trim, no control characters', () => {
  assert.equal(nameRuleError('A'), null)
  assert.equal(nameRuleError('  '), 'Tell us your name.')
  assert.equal(nameRuleError('x'.repeat(255)), null)
  assert.notEqual(nameRuleError('x'.repeat(256)), null)
  assert.notEqual(nameRuleError('a\u0007b'), null)
})

test('reset link parsing', () => {
  assert.deepEqual(parseResetLink('?token=abc123&email=maya%2Bx%40example.com'), { token: 'abc123', email: 'maya+x@example.com' })
  assert.equal(parseResetLink('?token=abc'), null)
  assert.equal(parseResetLink('?email=a@b.co'), null)
  assert.equal(parseResetLink('?token=&email=a@b.co'), null)
  assert.equal(parseResetLink('?token=abc&email=nope'), null)
  assert.equal(parseResetLink(''), null)
  assert.deepEqual(resetBody({ token: 't', email: 'e@x.co' }, 'p', 'c'), { email: 'e@x.co', token: 't', password: 'p', password_confirmation: 'c' })
})

test('reset failures: bare 422 is an invalid link, 422 with errors are field errors', () => {
  assert.equal(classifyResetFailure(failure(422, { message: 'Invalid or expired reset token.' })).kind, 'invalid-link')
  const fields = classifyResetFailure(failure(422, { errors: { password: ['Too short.'] } }))
  assert.equal(fields.kind, 'fields')
  assert.equal(fields.errors.password, 'Too short.')
  assert.equal(classifyResetFailure(failure(500, {})).kind, 'other')
  assert.equal(classifyResetFailure(new Error('x')).kind, 'other')
})
