import type { ApiMember, ApiMessage, ApiRoomRead } from '../../lib/types.ts'

// This browser's copy of each room's conversation, per signed-in account. The server deletes a message once
// every member has read it, so this copy is what keeps the conversation on screen after a refresh. It is
// cleared on sign-out (see AuthContext).

const PREFIX = 'watchwise.rooms.v1.'
/** Keeps each room's copy well under the browser's storage limit. */
const MAX_MESSAGES = 300

const keyOf = (userId: number, roomId: number) => `${PREFIX}${userId}.${roomId}`

export function loadRoomHistory(userId: number, roomId: number): ApiMessage[] {
  try {
    const stored: unknown = JSON.parse(localStorage.getItem(keyOf(userId, roomId)) ?? '[]')
    return Array.isArray(stored) ? (stored as ApiMessage[]) : []
  } catch {
    return []
  }
}

export function saveRoomHistory(userId: number, roomId: number, messages: ApiMessage[]) {
  try {
    localStorage.setItem(keyOf(userId, roomId), JSON.stringify(messages.slice(-MAX_MESSAGES)))
  } catch {
    // Storage full or blocked: the room still works, its messages just won't survive a refresh.
  }
}

/** Forgets every room's copy (sign-out, or another account signing in on this browser). */
export function clearRoomHistory() {
  try {
    for (const key of Object.keys(localStorage)) {
      if (key.startsWith(PREFIX)) localStorage.removeItem(key)
    }
  } catch {
    // Storage blocked: nothing was kept.
  }
}

/** Adds or replaces messages (the newer copy's receipts win), oldest first. */
export function mergeMessages(current: ApiMessage[], incoming: ApiMessage[]): ApiMessage[] {
  const byId = new Map(current.map((message) => [message.id, message]))
  for (const message of incoming) byId.set(message.id, message)
  return [...byId.values()].sort((a, b) => a.id - b.id)
}

/**
 * Brings the copy up to date with the messages the server still stores. A message this browser already had
 * when it asked (`knownBefore`) but the server no longer stores was deleted because everyone had read it;
 * one that arrived over the socket while the request was on its way is left alone.
 */
export function syncWithServer(current: ApiMessage[], stored: ApiMessage[], knownBefore: ReadonlySet<number>): ApiMessage[] {
  const storedIds = new Set(stored.map((message) => message.id))
  const settled = current.map((message) =>
    knownBefore.has(message.id) && !storedIds.has(message.id) ? seenByEveryone(message) : message,
  )
  return mergeMessages(settled, stored)
}

/** Applies a `messages.read` event (or the reader's own confirmation). */
export function applyRead(messages: ApiMessage[], read: ApiRoomRead): ApiMessage[] {
  const readIds = new Set(read.message_ids)
  const deletedIds = new Set(read.deleted_ids)
  return messages.map((message) => {
    if (deletedIds.has(message.id)) return seenByEveryone(withReader(message, read.reader))
    return readIds.has(message.id) ? withReader(message, read.reader) : message
  })
}

/** Ids of the messages still waiting for this member to read them. */
export const waitingFor = (messages: ApiMessage[], memberId: number) =>
  messages.filter((message) => message.unread_by.some((member) => member.id === memberId)).map((message) => message.id)

function withReader(message: ApiMessage, reader: ApiMember): ApiMessage {
  return {
    ...message,
    read_by: message.read_by.some((member) => member.id === reader.id) ? message.read_by : [...message.read_by, reader],
    unread_by: message.unread_by.filter((member) => member.id !== reader.id),
  }
}

function seenByEveryone(message: ApiMessage): ApiMessage {
  const readers = [...message.read_by, ...message.unread_by]
  return { ...message, read_by: readers.filter((member, i) => readers.findIndex((m) => m.id === member.id) === i), unread_by: [] }
}
