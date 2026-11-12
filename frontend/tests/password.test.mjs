import assert from 'node:assert/strict'
import { mock, test } from 'node:test'

const calls = []
let outcome = null
const respond = (method) => (...args) => {
  calls.push([method, ...args])
  return outcome ? Promise.reject(outcome) : Promise.resolve({ data: {} })
}
mock.module('../src/lib/api.ts', { namedExports: { api: { put: respond('put') } } })
const { changePassword, setPassword, settlePassword, passwordConflictCode } = await import('../src/lib/password.ts')

const failure = (status, data) => Object.assign(new Error('Request failed'), { isAxiosError: true, response: { status, data } })
const reset = (error = null) => {
  calls.length = 0
  outcome = error
}

test('set password calls PUT /me/password/set without a current password', async () => {
  reset()
  await setPassword({ password: 'secret123', password_confirmation: 'secret123' })
  assert.deepEqual(calls, [['put', '/me/password/set', { password: 'secret123', password_confirmation: 'secret123' }]])
})

test('change password calls PUT /me/password with the current password', async () => {
  reset()
  await changePassword({ current_password: 'old12345', password: 'secret123', password_confirmation: 'secret123' })
  assert.equal(calls[0][1], '/me/password')
  assert.equal(calls[0][2].current_password, 'old12345')
})

test('a successful request settles as ok', async () => {
  reset()
  assert.deepEqual(await settlePassword(setPassword({ password: 'a', password_confirmation: 'a' }), 'x'), { ok: true })
})

test('a 409 password_already_set becomes a friendly conflict, not the raw message', async () => {
  reset(failure(409, { message: 'Raw backend text.', code: 'password_already_set' }))
  const result = await settlePassword(setPassword({ password: 'a', password_confirmation: 'a' }), 'x')
  assert.equal(result.ok, false)
  assert.equal(result.conflict, 'password_already_set')
  assert.match(result.message, /already has a password/)
  assert.doesNotMatch(result.message, /Raw backend/)
})

test('a 409 no_password_set becomes a friendly conflict', async () => {
  reset(failure(409, { message: 'Raw.', code: 'no_password_set' }))
  const result = await settlePassword(changePassword({ current_password: 'a', password: 'b', password_confirmation: 'b' }), 'x')
  assert.equal(result.conflict, 'no_password_set')
  assert.match(result.message, /set one/)
})

test('a 409 with an unknown code is not treated as a password conflict', () => {
  assert.equal(passwordConflictCode(failure(409, { code: 'other' })), null)
  assert.equal(passwordConflictCode(failure(422, { code: 'no_password_set' })), null)
  assert.equal(passwordConflictCode(new Error('plain')), null)
})

test('a 422 returns field errors and the first validation message', async () => {
  reset(failure(422, { message: 'Invalid.', errors: { password: ['Too short.'], password_confirmation: ['Does not match.'] } }))
  const result = await settlePassword(setPassword({ password: 'a', password_confirmation: 'b' }), 'x')
  assert.equal(result.ok, false)
  assert.equal(result.conflict, null)
  assert.equal(result.message, 'Too short.')
  assert.deepEqual(result.errors, { password: 'Too short.', password_confirmation: 'Does not match.' })
})

test('an unknown failure falls back to the given message', async () => {
  reset(new Error('network'))
  const result = await settlePassword(setPassword({ password: 'a', password_confirmation: 'a' }), "Couldn't set your password.")
  assert.equal(result.message, "Couldn't set your password.")
})

test('password rule needs letters and numbers, 8 characters and at most 72 bytes', async () => {
  const { passwordRuleError } = await import('../src/lib/password.ts')
  assert.equal(passwordRuleError('abcdefg1'), null)
  assert.equal(passwordRuleError('12345678') === null, false, 'digits only')
  assert.equal(passwordRuleError('abcdefgh') === null, false, 'letters only')
  assert.equal(passwordRuleError('abc123') === null, false, 'too short')
  assert.equal(passwordRuleError('a1' + 'x'.repeat(70)), null, '72 bytes')
  assert.equal(passwordRuleError('a1' + 'x'.repeat(71)) === null, false, '73 bytes')
  // 36 two-byte letters + digit = 73 bytes though only 37 characters
  assert.equal(passwordRuleError('é'.repeat(36) + '1') === null, false, 'multibyte over 72 bytes')
  assert.equal(passwordRuleError('é'.repeat(35) + '1'), null, '71 bytes multibyte')
  assert.equal(passwordRuleError('abcdefg1\0') === null, false, 'NUL byte')
})
