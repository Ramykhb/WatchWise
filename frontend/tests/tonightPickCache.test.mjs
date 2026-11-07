import assert from 'node:assert/strict'
import { test } from 'node:test'
import { cacheTonightPick, PICK_CACHE_TTL, readTonightPick } from '../src/pages/Home/tonightPickCache.ts'

const location = { latitude: 33.89, longitude: 35.5 }
const movie = { id: 1, title: 'Tonight’s film', genre_ids: [18], reason: 'Fits the weather' }

function setup(t) {
  const storage = new Map()
  const previous = Object.getOwnPropertyDescriptor(globalThis, 'localStorage')
  Object.defineProperty(globalThis, 'localStorage', { configurable: true, value: {
    getItem: (key) => storage.get(key) ?? null,
    setItem: (key, value) => storage.set(key, value),
    removeItem: (key) => storage.delete(key),
  } })
  t.after(() => {
    if (previous) Object.defineProperty(globalThis, 'localStorage', previous)
    else delete globalThis.localStorage
  })
  return storage
}

test('revisiting Home keeps the selected film without extending its one-hour expiry', (t) => {
  setup(t)
  let now = 1000
  t.mock.method(Date, 'now', () => now)
  cacheTonightPick(movie, location)
  now += PICK_CACHE_TTL - 1
  assert.deepEqual(readTonightPick(location).movie, movie)
  assert.equal(readTonightPick(location).cachedAt, 1000)
  now += 1
  assert.equal(readTonightPick(location), null)
})

test('a different weather location does not reuse the previous location’s pick', (t) => {
  setup(t)
  cacheTonightPick(movie, location)
  assert.equal(readTonightPick({ ...location, latitude: 40 }), null)
})

test('corrupt and future-dated cache entries are ignored', (t) => {
  const storage = setup(t)
  t.mock.method(Date, 'now', () => 1000)
  cacheTonightPick(movie, location)
  const key = [...storage.keys()][0]
  storage.set(key, '{broken')
  assert.equal(readTonightPick(location), null)
  storage.set(key, JSON.stringify({ ...location, cachedAt: 2000, movie }))
  assert.equal(readTonightPick(location), null)
})

test('blocked browser storage leaves the freshly selected pick usable', (t) => {
  setup(t)
  t.mock.method(localStorage, 'setItem', () => { throw new Error('Storage blocked') })
  t.mock.method(localStorage, 'getItem', () => { throw new Error('Storage blocked') })
  assert.deepEqual(cacheTonightPick(movie, location).movie, movie)
  assert.equal(readTonightPick(location), null)
})
