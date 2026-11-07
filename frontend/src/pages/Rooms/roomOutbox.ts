import type { ApiMessage } from '../../lib/types.ts'

export type OutgoingMessage = {
  id: string
  threadKey: string
  userId: number
  content: string
  createdAt: string
  status: 'sending' | 'failed'
  error?: string
  knownIds: number[]
}

/** A socket echo may arrive before the POST response. Count each echo only once,
 * including when several identical messages are in flight. */
export function visibleOutgoing(outbox: OutgoingMessage[], messages: ApiMessage[], threadKey: string) {
  const used = new Set<number>()
  return outbox.filter((item) => {
    if (item.threadKey !== threadKey) return false
    const echo = messages.find((message) =>
      !used.has(message.id) && !item.knownIds.includes(message.id) &&
      message.user.id === item.userId && message.content === item.content,
    )
    if (!echo) return true
    used.add(echo.id)
    return false
  })
}

/** Acknowledged messages cannot also acknowledge another identical pending send. */
export function confirmOutgoing(outbox: OutgoingMessage[], id: string, messageId: number): OutgoingMessage[] {
  const sent = outbox.find((item) => item.id === id)
  return outbox.filter((item) => item.id !== id).map((item) =>
    item.threadKey === sent?.threadKey ? { ...item, knownIds: [...item.knownIds, messageId] } : item,
  )
}
