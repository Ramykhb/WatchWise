import assert from 'node:assert/strict'
import { test } from 'node:test'
import {
  buildConfirmRequest, confirmRequestUrl, outcomeFromResponse, resolveOutcome, resultPath,
} from '../src/pages/AccountLink/accountLink.ts'

const HASH = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
const SIG = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef'
const search = `?id=7&hash=${HASH}&expires=1900000000&signature=${SIG}`

test('the request keeps the signed query exactly as received', () => {
  const request = buildConfirmRequest('email-confirm', search)
  assert.equal(confirmRequestUrl(request), `/me/email/confirm/7/${HASH}?expires=1900000000&signature=${SIG}`)
})

test('each kind posts to its own endpoint', () => {
  assert.match(confirmRequestUrl(buildConfirmRequest('email-authorize', search)), /^\/me\/email\/authorize\/7\/a{64}\?/)
  assert.match(confirmRequestUrl(buildConfirmRequest('delete-confirm', search)), /^\/me\/delete\/confirm\/7\/a{64}\?/)
})

test('unknown kinds and incomplete links build no request', () => {
  assert.equal(buildConfirmRequest('result', search), null)
  assert.equal(buildConfirmRequest('toString', search), null)
  assert.equal(buildConfirmRequest(undefined, search), null)
  for (const missing of ['id', 'hash', 'expires', 'signature']) {
    const partial = search.slice(1).split('&').filter((part) => !part.startsWith(`${missing}=`)).join('&')
    assert.equal(buildConfirmRequest('email-confirm', `?${partial}`), null)
  }
  assert.equal(buildConfirmRequest('email-confirm', `?id=7&hash=${HASH}&expires=&signature=${SIG}`), null)
})

test('responses map to outcomes, with 403 and 410 as bad signatures', () => {
  assert.equal(outcomeFromResponse(200, { outcome: 'success' }), 'success')
  assert.equal(outcomeFromResponse(403, { outcome: 'invalid' }), 'invalid')
  assert.equal(outcomeFromResponse(410, { outcome: 'expired' }), 'expired')
  assert.equal(outcomeFromResponse(403, undefined), 'invalid')
  assert.equal(outcomeFromResponse(410, null), 'expired')
})

test('network and unknown failures are a retryable generic failure', () => {
  assert.equal(outcomeFromResponse(undefined, null), 'failed')
  assert.equal(outcomeFromResponse(500, { outcome: 'success' }), 'failed')
  assert.equal(outcomeFromResponse(200, {}), 'failed')
  assert.equal(resolveOutcome('email-confirm', 'failed').next, 'retry')
})

test('every documented outcome has a message and a next step', () => {
  const cases = {
    'email-confirm': ['success', 'invalid', 'expired', 'taken'],
    'email-authorize': ['check-new-inbox', 'invalid', 'expired', 'taken'],
    'delete-confirm': ['deleted', 'invalid', 'expired'],
    'email-cancel': ['cancelled', 'invalid', 'expired'],
    'delete-cancel': ['cancelled', 'invalid', 'expired'],
  }
  for (const [kind, outcomes] of Object.entries(cases)) {
    for (const outcome of outcomes) {
      const resolution = resolveOutcome(kind, outcome)
      assert.ok(resolution.title && resolution.body && resolution.nextLabel, `${kind}/${outcome}`)
    }
  }
})

test('success and deletion end the local session; approvals do not', () => {
  assert.equal(resolveOutcome('email-confirm', 'success').endsSession, true)
  assert.equal(resolveOutcome('delete-confirm', 'deleted').endsSession, true)
  assert.equal(resolveOutcome('email-authorize', 'check-new-inbox').endsSession, undefined)
  assert.match(resolveOutcome('email-authorize', 'check-new-inbox').body, /new address/)
})

test('unknown kinds or outcomes fall back to a calm invalid-link message', () => {
  for (const [kind, outcome] of [['nope', 'success'], ['email-confirm', 'toString'], ['email-cancel', 'success'], [null, null]]) {
    assert.equal(resolveOutcome(kind, outcome).title, 'Link not valid.')
  }
})

test('the result path carries only the kind and outcome', () => {
  assert.equal(resultPath('email-confirm', 'success'), '/account-link/result?kind=email-confirm&outcome=success')
  assert.equal(resultPath('delete-confirm', 'a&b'), '/account-link/result?kind=delete-confirm&outcome=a%26b')
  assert.doesNotMatch(resultPath('email-confirm', 'taken'), /signature|hash/)
})

test('crafted or malformed values never build a request', () => {
  const make = (over) => {
    const base = { id: '7', hash: HASH, expires: '1900000000', signature: SIG, ...over }
    return `?id=${base.id}&hash=${base.hash}&expires=${base.expires}&signature=${base.signature}`
  }
  assert.notEqual(buildConfirmRequest('delete-confirm', make({})), null)
  const bad = [
    { id: '../../auth/logout' }, { id: '%2e%2e%2f' }, { id: '7/8' }, { id: '7%0a' }, { id: '7 ' }, { id: '-1' }, { id: '' },
    { hash: '../../auth/logout' }, { hash: '%2e%2e' + HASH.slice(6) }, { hash: HASH.slice(1) }, { hash: HASH + 'a' },
    { hash: HASH.toUpperCase() }, { hash: HASH.slice(1) + '/' }, { hash: HASH.slice(1) + '?' }, { hash: HASH.slice(1) + '#' },
    { hash: HASH.slice(1) + '%0a' }, { hash: 'g'.repeat(64) },
    { expires: '1e9' }, { expires: '' }, { expires: '../1' },
    { signature: SIG.toUpperCase() }, { signature: SIG.slice(1) }, { signature: SIG + '0' }, { signature: SIG.slice(1) + '%26' },
    { signature: SIG.slice(1) + '#' }, { signature: 'a%2Bb' }, { signature: '' },
  ]
  for (const over of bad) assert.equal(buildConfirmRequest('delete-confirm', make(over)), null, JSON.stringify(over))
  for (const missing of ['id', 'hash', 'expires', 'signature']) {
    const partial = make({}).slice(1).split('&').filter((part) => !part.startsWith(`${missing}=`)).join('&')
    assert.equal(buildConfirmRequest('delete-confirm', `?${partial}`), null)
  }
})
