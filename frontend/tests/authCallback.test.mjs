import assert from 'node:assert/strict'
import { test } from 'node:test'
import { callbackFailureMessage } from '../src/pages/AuthCallback/authCallback.ts'

const REASONS = ['email_required', 'email_unverified', 'account_disabled', 'invalid_state', 'provider_error', 'provider_unavailable', 'too_many_attempts', 'access_denied']

test('every reason the backend sends has its own message', () => {
  const generic = callbackFailureMessage(null)
  const bodies = new Set()
  for (const reason of REASONS) {
    const message = callbackFailureMessage(reason)
    assert.equal(typeof message.title, 'string')
    assert.ok(message.body.length > 0)
    assert.notDeepEqual(message, generic, reason)
    bodies.add(message.body)
  }
  assert.equal(bodies.size, REASONS.length)
})

test('a missing email explains what to do on GitHub and Discord', () => {
  const { body } = callbackFailureMessage('email_required')
  assert.match(body, /GitHub/)
  assert.match(body, /verified primary email/)
  assert.doesNotMatch(body, /public|private/)
  assert.match(body, /Discord/)
  assert.match(body, /verify/)
})

test('the reasons say what happened', () => {
  assert.match(callbackFailureMessage('email_unverified').body, /not verified/)
  assert.match(callbackFailureMessage('account_disabled').title, /disabled/)
  assert.match(callbackFailureMessage('invalid_state').body, /expired/)
  assert.match(callbackFailureMessage('provider_unavailable').body, /not available/)
  assert.match(callbackFailureMessage('access_denied').title, /cancelled/)
  assert.match(callbackFailureMessage('too_many_attempts').body, /Wait a minute/)
})

test('a missing or unknown reason falls back to a generic message', () => {
  const generic = callbackFailureMessage(undefined)
  assert.deepEqual(callbackFailureMessage(null), generic)
  assert.deepEqual(callbackFailureMessage('toString'), generic)
  assert.deepEqual(callbackFailureMessage('<script>'), generic)
  assert.match(generic.body, /try again/)
})
