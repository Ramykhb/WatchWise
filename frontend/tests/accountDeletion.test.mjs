import assert from 'node:assert/strict'
import { test } from 'node:test'
import { accountDeletionNotice, deleteAccountPrompt, pendingDeletionMessage } from '../src/pages/Settings/accountDeletion.ts'

test('every status an account-deletion link can return has a message', () => {
  for (const status of ['deleted', 'cancelled', 'invalid', 'expired']) {
    assert.equal(typeof accountDeletionNotice(status), 'string')
  }
  assert.match(accountDeletionNotice('deleted'), /deleted/)
  assert.match(accountDeletionNotice('cancelled'), /cancelled/)
})

test('a missing or unknown flag shows nothing', () => {
  assert.equal(accountDeletionNotice(null), null)
  assert.equal(accountDeletionNotice('toString'), null)
  assert.equal(accountDeletionNotice('<script>'), null)
})

test('an account that confirms by email is told a link is coming instead of a password', () => {
  const prompt = deleteAccountPrompt(true, 'me@example.com')
  assert.match(prompt, /We'll email you a confirmation link at me@example\.com/)
  assert.match(prompt, /cannot be undone/)
})

test('an account with a password gets the plain warning', () => {
  const prompt = deleteAccountPrompt(false, 'me@example.com')
  assert.doesNotMatch(prompt, /email/)
  assert.match(prompt, /cannot be undone/)
})

test('a pending deletion points to the inbox and says nothing is deleted yet', () => {
  const message = pendingDeletionMessage('me@example.com')
  assert.match(message, /We sent a link to me@example\.com/)
  assert.match(message, /Nothing is deleted until you open it/)
})
