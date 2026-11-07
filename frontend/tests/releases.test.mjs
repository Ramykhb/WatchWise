import assert from 'node:assert/strict'
import { execFileSync } from 'node:child_process'
import { test } from 'node:test'
import { upcomingReleaseParams } from '../src/pages/Releases/releasesApi.ts'

test('release boundary rolls over to the next local month and year', () => {
  assert.equal(upcomingReleaseParams(new Date(2026, 10, 30)).release_from, '2026-12-01')
  assert.deepEqual(upcomingReleaseParams(new Date(2026, 11, 31)), {
    list: 'upcoming', sort: 'popularity', release_from: '2027-01-01',
  })
})

test('release boundary follows the viewer date on either side of UTC', () => {
  const moduleUrl = new URL('../src/pages/Releases/releasesApi.ts', import.meta.url).href
  const script = `import { upcomingReleaseParams } from ${JSON.stringify(moduleUrl)};
    console.log(upcomingReleaseParams(new Date('2026-11-07T23:30:00Z')).release_from)`
  for (const [timezone, expected] of [['Asia/Beirut', '2026-11-09'], ['America/Los_Angeles', '2026-11-08']]) {
    const actual = execFileSync(process.execPath, ['--experimental-strip-types', '--input-type=module', '-e', script], {
      env: { ...process.env, TZ: timezone }, encoding: 'utf8',
    })
    assert.equal(actual.trim(), expected)
  }
})
