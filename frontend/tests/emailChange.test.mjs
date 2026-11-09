import assert from 'node:assert/strict'
import { test } from 'node:test'
import { emailChangeNotice } from '../src/pages/EditProfile/emailChange.ts'

test('every status the confirmation link can return has a message', () => {
  for (const status of ['success', 'invalid', 'expired', 'taken']) {
    assert.equal(typeof emailChangeNotice(status), 'string')
  }
  assert.match(emailChangeNotice('success'), /confirmed/)
})

test('a missing or unknown flag shows nothing', () => {
  assert.equal(emailChangeNotice(null), null)
  assert.equal(emailChangeNotice('toString'), null)
  assert.equal(emailChangeNotice('<script>'), null)
})
