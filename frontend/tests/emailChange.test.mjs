import assert from 'node:assert/strict'
import { test } from 'node:test'
import { emailChangeNotice, pendingEmailMessage } from '../src/pages/EditProfile/emailChange.ts'

test('every status an email-change link can return has a message', () => {
  for (const status of ['success', 'check-new-inbox', 'cancelled', 'invalid', 'expired', 'taken']) {
    assert.equal(typeof emailChangeNotice(status), 'string')
  }
  assert.match(emailChangeNotice('success'), /confirmed/)
})

test('approving from the current address points to the new inbox', () => {
  assert.match(emailChangeNotice('check-new-inbox'), /new address/)
})

test('the cancel link lands on a cancelled message', () => {
  assert.match(emailChangeNotice('cancelled'), /cancelled/)
  assert.match(emailChangeNotice('cancelled'), /stays the same/)
})

test('a missing or unknown flag shows nothing', () => {
  assert.equal(emailChangeNotice(null), null)
  assert.equal(emailChangeNotice('toString'), null)
  assert.equal(emailChangeNotice('<script>'), null)
})

test('a change waiting for the current address says to confirm there first', () => {
  const message = pendingEmailMessage('new@example.com', true)
  assert.match(message, /We sent a link to your current address\. Confirm it first; then we'll email the new address\./)
  assert.match(message, /new@example\.com/)
})

test('a change waiting for the new address points to that inbox', () => {
  const message = pendingEmailMessage('new@example.com', false)
  assert.match(message, /We sent a link to new@example\.com/)
  assert.doesNotMatch(message, /current address/)
})
