import assert from 'node:assert/strict'
import { mock, test } from 'node:test'
const calls = []
mock.module('../src/lib/api.ts', { namedExports: {
  api: { request: async (request) => { calls.push(request); return { data: { message: 'Saved' } } } },
  useApi() {},
} })
const { queryPath, reportDestination, bulkSummary, mutate } = await import('../src/lib/adminTools.ts')
const { canEnterAdminPath, canSuspendUser } = await import('../src/lib/adminApi.ts')
test('moderators can read shared tools but cannot enter admin-only routes or nested routes', () => {
  for (const section of ['users', 'reviews', 'reports', 'ratings', 'ratings/bursts', 'rooms', 'lists', 'genres']) {
    assert.equal(canEnterAdminPath('moderator', `/admin/${section}`), true)
  }
  for (const section of ['settings', 'movies', 'audit-log']) {
    assert.equal(canEnterAdminPath('moderator', `/admin/${section}`), false)
    assert.equal(canEnterAdminPath('moderator', `/admin/${section}/123`), false)
    assert.equal(canEnterAdminPath('admin', `/admin/${section}`), true)
  }
  assert.equal(canEnterAdminPath('user', '/admin'), false)
  assert.equal(canEnterAdminPath(undefined, '/admin'), false)
})
test('account tools prohibit self actions and moderator actions against staff', () => {
  assert.equal(canSuspendUser({ id: 1, role: 'moderator' }, { id: 2, role: 'user' }), true)
  assert.equal(canSuspendUser({ id: 1, role: 'moderator' }, { id: 2, role: 'admin' }), false)
  assert.equal(canSuspendUser({ id: 1, role: 'admin' }, { id: 1, role: 'admin' }), false)
})
test('filters preserve negative boolean choices and escape searches', () => {
  const path = queryPath('/admin/rooms', { reported: 0, locked: '0', q: 'Ali & Maya', page: 2, visibility: '', creator_id: undefined })
  const params = new URL(path, 'http://test').searchParams
  assert.equal(params.get('reported'), '0')
  assert.equal(params.get('locked'), '0')
  assert.equal(params.get('q'), 'Ali & Maya')
  assert.equal(params.has('visibility'), false)
})
test('report content links use staff tools, including removed targets and reported messages', () => {
  assert.equal(reportDestination({ type: 'profile', id: 4, removed: false }), '/admin/users/4')
  assert.equal(reportDestination({ type: 'review', id: 4, removed: false }), '/admin/reviews/4')
  assert.equal(reportDestination({ type: 'room', id: 4, removed: true }), null)
  assert.equal(reportDestination({ type: 'message', id: 4, removed: false }), '/admin/rooms?reported=1')
})
test('bulk outcomes show skipped and failed members rather than reporting blanket success', () => {
  assert.equal(bulkSummary([{ id: 1, status: 'suspended', reason: null }, { id: 2, status: 'skipped', reason: 'not_allowed' }]), '#1: suspended; #2: skipped (not allowed)')
})
test('moderation requests preserve JSON booleans, TV type and decision bodies', async () => {
  await mutate('rooms/7', 'patch', { is_locked: false })
  await mutate('lists/2/items/550?type=tv', 'delete')
  await mutate('reports/9', 'patch', { status: 'dismissed', apply_to_target: false, note: 'Reviewed' })
  assert.deepEqual(calls[0], { url: '/admin/rooms/7', method: 'patch', data: { is_locked: false } })
  assert.equal(calls[1].url, '/admin/lists/2/items/550?type=tv')
  assert.equal(calls[2].data.apply_to_target, false)
})
