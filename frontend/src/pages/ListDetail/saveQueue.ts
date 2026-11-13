/** Serializes list edits and discards pending edits after their source copy becomes stale. */
export function createSaveQueue() {
  let tail = Promise.resolve()
  let generation = 0

  return {
    enqueue(task: () => Promise<void>): Promise<void> {
      const started = generation
      const pending = tail.then(() => {
        if (started === generation) return task()
      })
      tail = pending.catch(() => {})
      return pending
    },
    invalidate() {
      generation++
    },
  }
}
