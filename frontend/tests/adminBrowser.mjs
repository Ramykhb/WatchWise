import { spawn } from 'node:child_process'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
const qaUrl = process.env.ADMIN_QA_URL || 'http://localhost:5173'
const chromePath =
  process.env.CHROME_PATH ||
  (process.platform === 'darwin' ? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' : 'google-chrome')
const chrome = spawn(
  chromePath,
  [
    '--headless=new',
    '--disable-gpu',
    '--no-first-run',
    '--no-sandbox',
    '--remote-debugging-port=9224',
    `--user-data-dir=${join(tmpdir(), `watchwise-qa-${Date.now()}`)}`,
    'about:blank',
  ],
  { stdio: 'ignore' },
)
process.on('exit', () => chrome.kill())
chrome.on('error', (error) => {
  console.error('Unable to start Chrome. Set CHROME_PATH to its executable.', error.message)
  process.exit(1)
})
for (let i = 0; i < 80; i++) {
  try {
    await fetch('http://localhost:9224/json')
    break
  } catch {
    await new Promise((r) => setTimeout(r, 100))
  }
}
import assert from 'node:assert/strict'
import { writeFileSync } from 'node:fs'
const targets = await (await fetch('http://localhost:9224/json')).json()
const ws = new WebSocket(targets.find((t) => t.type === 'page').webSocketDebuggerUrl)
await new Promise((r) => ws.addEventListener('open', r, { once: true }))
let n = 0
const pending = new Map()
let role = 'admin'
const mutations = []
const errors = []
const send = (method, params = {}) =>
  new Promise((resolve, reject) => {
    const id = ++n
    pending.set(id, { resolve, reject })
    ws.send(JSON.stringify({ id, method, params }))
  })
const member = { id: 2, name: 'Maya Haddad', username: 'maya', role: 'user' }
const time = '2026-11-13T10:00:00Z'
const report = {
  id: 1,
  status: 'open',
  reason: 'Spam',
  details: 'Repeated spam in this discussion.',
  reporter: member,
  target: {
    id: 1,
    type: 'review',
    label: 'Review by Maya',
    excerpt: 'A thoughtful film with a memorable ending.',
    owner: member,
    removed: false,
  },
  created_at: time,
  resolved_at: null,
  resolved_by: null,
  resolution_note: null,
}
const review = {
  id: 1,
  tmdb_id: 550,
  media_type: 'movie',
  content: 'A thoughtful film with a memorable ending.',
  rating: 4,
  sentiment: 'positive',
  has_spoilers: false,
  is_approved: false,
  status: 'flagged',
  moderation_labels: ['spam'],
  reports_count: 1,
  reasons: ['Spam'],
  author: { ...member, reviews_with_text: 5, removed_reviews: 1, open_reports_against_their_reviews: 1 },
  movie: { tmdb_id: 550, title: 'Fight Club', poster_path: null, release_date: null },
  created_at: time,
  reports: [report],
  helpful_votes: 2,
  removed_at: null,
}
const user = {
  ...member,
  email: 'maya@example.test',
  avatar: null,
  is_active: true,
  status: 'active',
  reviews_count: 5,
  email_verified_at: time,
  last_active_at: time,
  created_at: time,
  profile: { bio: 'Cinema enthusiast', avatar: null, profile_visibility: 'public' },
  counts: {
    reviews_with_text: 5,
    ratings: 8,
    lists: 2,
    followers: 3,
    following: 4,
    rooms_created: 1,
    reports_against_open: 0,
    reports_filed: 1,
  },
  social_providers: ['google'],
  active_sessions: 2,
  suspension: null,
  notes_count: 1,
  recent_audit: [],
}
const room = {
  id: 1,
  name: 'Thriller Thursday',
  tmdb_id: 550,
  media_type: 'movie',
  film: 'Fight Club',
  is_private: false,
  is_locked: false,
  spoilers: false,
  creator: member,
  members_count: 1,
  messages_count: 1,
  open_reports_count: 1,
  open_message_reports_count: 0,
  created_at: time,
  last_activity_at: time,
  members: [{ ...member, joined_at: time }],
  open_reports: [report],
}
const list = {
  id: 1,
  title: 'Weekend cinema',
  description: 'A few favorites',
  visibility: 'public',
  owner: member,
  items_count: 1,
  open_reports_count: 1,
  created_at: time,
  updated_at: time,
  items: [{ tmdb_id: 550, media_type: 'movie', title: 'Fight Club' }],
  reports: [report],
}
const page = (data, summary) => ({
  data,
  meta: { current_page: 1, last_page: 1, per_page: 20, total: data.length },
  ...(summary ? { summary } : {}),
})
const stats = {
  users_total: 5,
  users_suspended: 1,
  users_unverified: 1,
  new_users: { last_7_days: 2, last_30_days: 4 },
  reviews_total: 5,
  reviews_pending: 1,
  flagged_reviews: 1,
  new_reviews: { last_7_days: 2, last_30_days: 4 },
  active_today: 2,
  active_yesterday: 1,
  reports: { open: 1, by_target_type: { review: 1, message: 0, list: 0, room: 0, profile: 0 }, resolved_last_7_days: 2 },
  ratings: { total: 8, new_last_7_days: 2, new_last_30_days: 4, average: 4 },
  rooms: { total: 1, public: 1, private: 0, locked: 0, reported: 1 },
  lists: { total: 1, public: 1, private: 0, reported: 1 },
  range: {
    name: '30',
    days: 30,
    new_users: 4,
    previous_new_users: 2,
    new_reviews: 4,
    previous_new_reviews: 2,
    new_ratings: 4,
    previous_new_ratings: 2,
    new_lists: 1,
    previous_new_lists: 0,
  },
  most_watched: [],
  top_rated: [],
  popular_genres: [],
  activity: [],
}
function response(url, method, body) {
  const p = new URL(url).pathname.replace('/api', '')
  if (method !== 'GET') {
    mutations.push({ p, method, body: body ? JSON.parse(body) : undefined })
    return { message: 'Saved.', user, review, list, room, results: [{ id: 2, status: 'suspended', reason: null }] }
  }
  if (p === '/notifications') return { notifications: [], unread_count: 0 }
  if (p === '/chat/connection') return { key: '', host: '', port: 8080, scheme: 'http' }
  if (p === '/auth/me') return { user: { ...user, id: 99, role, name: 'Staff Member' } }
  if (p === '/admin/stats') return stats
  if (p === '/admin/users') return page([user], { total: 1, active: 1, unverified: 0, inactive: 0, admins: 0, moderators: 0 })
  if (p === '/admin/users/2') return { user }
  if (p === '/admin/users/2/notes')
    return page([{ id: 1, body: 'Review activity at next check-in.', author: { id: 99, name: 'Staff Member', role }, created_at: time }])
  if (p === '/admin/reviews')
    return page([review], {
      flagged: 1,
      removed: 0,
      sentiment_total: 1,
      sentiment: { positive: { count: 1, percent: 100 }, neutral: { count: 0, percent: 0 }, negative: { count: 0, percent: 0 } },
    })
  if (p === '/admin/reviews/1') return { review }
  if (p === '/admin/reports')
    return page([report], {
      by_status: { open: 1, resolved: 0, dismissed: 0 },
      by_target_type: { review: 1, message: 0, list: 0, room: 0, profile: 0 },
    })
  if (p === '/admin/reports/1') return { report }
  if (p === '/admin/lists') return page([list], stats.lists)
  if (p === '/admin/lists/1') return { list }
  if (p === '/admin/rooms') return page([room], stats.rooms)
  if (p === '/admin/rooms/1') return { room }
  if (p === '/admin/rooms/1/messages')
    return page([{ id: 1, author: member, body: 'Tonight we discuss the ending.', created_at: time, open_reports_count: 0 }])
  if (p === '/admin/ratings')
    return page(
      [
        {
          id: 1,
          tmdb_id: 550,
          media_type: 'movie',
          rating: 4,
          moderated: false,
          author: member,
          film: { title: 'Fight Club' },
          created_at: time,
        },
      ],
      { total: 1, average: 4, distribution: { 1: 0, 2: 0, 3: 0, 4: 1, 5: 0 } },
    )
  if (p === '/admin/ratings/bursts')
    return page([{ user: { ...member, created_at: time }, count: 25, window_start: time, window_end: time }])
  if (p === '/admin/ratings/films/550')
    return {
      total: 1,
      average: 4,
      distribution: { 1: 0, 2: 0, 3: 0, 4: 1, 5: 0 },
      with_text_count: 1,
      first_rated_at: time,
      last_rated_at: time,
      film: { title: 'Fight Club' },
    }
  if (p === '/admin/genres')
    return {
      genres: [
        {
          tmdb_id: 18,
          tmdb_name: 'Drama',
          name: 'Drama',
          name_override: null,
          is_hidden: false,
          position: null,
          movie_count: 8,
          updated_at: null,
        },
      ],
    }
  if (p === '/admin/settings')
    return {
      settings: {
        registration_open: { value: true, updated_at: null, updated_by: null },
        reviews_require_approval: { value: false, updated_at: null, updated_by: null },
        review_auto_moderation: { value: true, updated_at: null, updated_by: null },
        rooms_creation_enabled: { value: true, updated_at: null, updated_by: null },
        announcement: { value: { enabled: false, message: '', level: 'info', expires_at: null }, updated_at: null, updated_by: null },
      },
    }
  if (p === '/admin/audit-log')
    return page([
      {
        id: 1,
        action: 'user.suspended',
        actor: { id: 99, name: 'Staff Member', role: 'admin' },
        subject: { type: 'user', id: 2, label: 'maya' },
        changes: { is_active: { from: true, to: false } },
        created_at: time,
      },
    ])
  if (p === '/admin/movies') return page([], { sync: { cached: 0, overrides: 0, last_synced_at: null } })
  if (p === '/genres') return { genres: [] }
  throw new Error(`Unhandled ${p}`)
}
ws.addEventListener('message', async (e) => {
  const m = JSON.parse(e.data)
  if (m.id) {
    const p = pending.get(m.id)
    pending.delete(m.id)
    m.error ? p.reject(m.error) : p.resolve(m.result)
  }
  if (m.method === 'Fetch.requestPaused') {
    try {
      const data = response(m.params.request.url, m.params.request.method, m.params.request.postData)
      await send('Fetch.fulfillRequest', {
        requestId: m.params.requestId,
        responseCode: 200,
        responseHeaders: [{ name: 'Content-Type', value: 'application/json' }],
        body: Buffer.from(JSON.stringify(data)).toString('base64'),
      })
    } catch (error) {
      if (error.code === -32602) return
      errors.push(error.message)
      await send('Fetch.fulfillRequest', {
        requestId: m.params.requestId,
        responseCode: 500,
        body: Buffer.from('{}').toString('base64'),
      }).catch(() => {})
    }
  }
  if (m.method === 'Runtime.exceptionThrown')
    errors.push(m.params.exceptionDetails.exception?.description ?? m.params.exceptionDetails.text)
  if (m.method === 'Page.javascriptDialogOpening') await send('Page.handleJavaScriptDialog', { accept: true })
})
await send('Page.enable')
await send('Runtime.enable')
await send('Fetch.enable', { patterns: [{ urlPattern: '*/api/*' }] })
const evalJS = async (expression) => (await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true })).result?.value
const pause = (ms) => new Promise((r) => setTimeout(r, ms))
async function visit(path, heading) {
  await send('Page.navigate', { url: `${qaUrl}${path}` })
  for (let i = 0; i < 80; i++) {
    await pause(100)
    if (
      await evalJS(
        `document.querySelector('main h1')?.textContent===${JSON.stringify(heading)} && !document.querySelector('[role="status"]')?.textContent.includes('Loading')`,
      )
    ) {
      await pause(200)
      return
    }
  }
  throw Error(`Failed ${path}: ${await evalJS('document.body.innerText.slice(0,700)')}`)
}
for (const [path, title] of [
  ['/admin', 'Overview'],
  ['/admin/users', 'Users'],
  ['/admin/users/2', 'Maya Haddad'],
  ['/admin/reviews', 'Review moderation'],
  ['/admin/reviews/1', 'Review #1'],
  ['/admin/reports', 'Reports'],
  ['/admin/reports/1', 'Report #1'],
  ['/admin/ratings', 'Star ratings'],
  ['/admin/ratings/bursts', 'Rating bursts'],
  ['/admin/ratings/films/550', 'Fight Club'],
  ['/admin/lists', 'Lists'],
  ['/admin/lists/1', 'Weekend cinema'],
  ['/admin/rooms', 'Discussion rooms'],
  ['/admin/rooms/1', 'Thriller Thursday'],
  ['/admin/genres', 'Genres'],
  ['/admin/settings', 'App settings'],
  ['/admin/audit-log', 'Audit history'],
  ['/admin/movies', 'Movies'],
]) {
  await visit(path, title)
  console.log('PASS', path)
}
await visit('/admin/reports/1', 'Report #1')
await evalJS(`[...document.querySelectorAll('button')].find(b=>b.textContent==='Dismiss').click()`)
await pause(200)
assert.equal(mutations.at(-1).body.status, 'dismissed')
await visit('/admin/rooms/1', 'Thriller Thursday')
await evalJS(`[...document.querySelectorAll('button')].find(b=>b.textContent==='Lock room').click()`)
await pause(200)
assert.equal(mutations.at(-1).body.is_locked, true)
await visit('/admin/settings', 'App settings')
await evalJS(`document.querySelector('form button').click()`)
await pause(200)
assert.equal(mutations.at(-1).body.registration_open, true)
await visit('/admin/reviews/1', 'Review #1')
await evalJS(`[...document.querySelectorAll('button')].find(b=>b.textContent==='Flag spoilers').click()`)
await pause(200)
assert.equal(mutations.at(-1).body.has_spoilers, true)
await visit('/admin/lists/1', 'Weekend cinema')
await evalJS(`[...document.querySelectorAll('button')].find(b=>b.textContent==='Make private').click()`)
await pause(200)
assert.equal(mutations.at(-1).body.visibility, 'private')
await visit('/admin/rooms/1', 'Thriller Thursday')
await evalJS(`[...document.querySelectorAll('button')].find(b=>b.textContent==='Delete message').click()`)
await pause(200)
assert.equal(mutations.at(-1).p, '/admin/rooms/1/messages/1')
await visit('/admin/users/2', 'Maya Haddad')
await evalJS(`[...document.querySelectorAll('button')].find(b=>b.textContent==='Sign out everywhere').click()`)
await pause(200)
assert.equal(mutations.at(-1).p, '/admin/users/2/sign-out')
await visit('/admin/settings', 'App settings')
const image = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true })
writeFileSync(join(tmpdir(), 'watchwise-admin-settings.png'), Buffer.from(image.data, 'base64'))
role = 'moderator'
await visit('/admin/genres', 'Genres')
assert.equal(await evalJS(`[...document.querySelectorAll('button')].some(b=>b.textContent==='Save')`), false)
for (const path of ['/admin/settings', '/admin/audit-log', '/admin/movies']) {
  await visit(path, 'Overview')
  assert.equal(await evalJS('location.pathname'), '/admin')
}
await visit('/admin/users/2', 'Maya Haddad')
assert.equal(await evalJS(`[...document.querySelectorAll('button')].some(b=>b.textContent==='Delete account')`), false)
await visit('/admin/rooms/1', 'Thriller Thursday')
assert.equal(await evalJS(`[...document.querySelectorAll('button')].some(b=>b.textContent==='Delete room')`), false)
await send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true })
await visit('/admin/reports', 'Reports')
assert.equal(await evalJS('document.documentElement.scrollWidth <= window.innerWidth'), true)
const mobile = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true })
writeFileSync(join(tmpdir(), 'watchwise-admin-mobile.png'), Buffer.from(mobile.data, 'base64'))
assert.deepEqual(errors, [])
console.log('PASS moderation actions, moderator permissions, mobile overflow and runtime errors')
ws.close()
