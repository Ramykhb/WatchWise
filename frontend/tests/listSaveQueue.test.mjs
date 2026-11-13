import assert from 'node:assert/strict'
import { test } from 'node:test'
import { createSaveQueue } from '../src/pages/ListDetail/saveQueue.ts'

test('a conflict reload discards pending stale edits and allows new edits afterwards', async () => {
  const queue = createSaveQueue()
  const writes = []
  let finishReload
  const reloading = new Promise((resolve) => { finishReload = resolve })

  const conflict = queue.enqueue(async () => {
    await reloading
    queue.invalidate()
  })
  const pending = queue.enqueue(async () => { writes.push('stale edit') })
  await Promise.resolve()
  const duringReload = queue.enqueue(async () => { writes.push('edit during reload') })
  finishReload()
  await Promise.all([conflict, pending, duringReload])
  assert.deepEqual(writes, [])

  await queue.enqueue(async () => { writes.push('fresh edit') })
  assert.deepEqual(writes, ['fresh edit'])
})

test('successful saves run in order and use the preceding save revision', async () => {
  const queue = createSaveQueue()
  let revision = 1
  const revisions = []
  const save = async () => {
    revisions.push(revision)
    await Promise.resolve()
    revision++
  }

  await Promise.all([queue.enqueue(save), queue.enqueue(save)])

  assert.deepEqual(revisions, [1, 2])
})
