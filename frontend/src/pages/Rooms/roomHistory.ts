import type { ApiMember, ApiMessage, ApiRoomRead } from "../../lib/types.ts";

// The open room's conversation, as the server and the socket deliver it. Nothing is saved in the browser: the
// server keeps a message for 24 hours after it was sent, and after that only while some member has yet to read it.

/** How long a message is shown once sent; the server's `RoomService::MESSAGE_LIFETIME_HOURS`. */
//change to 24 * 60 * 60 * 1000 for real, currently testing
const MESSAGE_LIFETIME_MS = 2 * 60 * 1000;
/** Where an earlier version saved each room's conversation in this browser. */
const SAVED_ROOMS_PREFIX = "watchwise.rooms.v1.";

/** Whether a message's 24 hours are up. */
export const hasExpired = (message: { created_at: string }, now: number) =>
  Date.parse(message.created_at) + MESSAGE_LIFETIME_MS <= now;

/**
 * Leaves out the messages whose 24 hours are up, except the ones still waiting for this member to read them and
 * the `kept` ones (read late: they stay on screen until the member leaves the room).
 */
export function withoutExpired(
  messages: ApiMessage[],
  memberId: number,
  now: number,
  kept: readonly number[] = [],
): ApiMessage[] {
  return messages.filter(
    (message) =>
      !hasExpired(message, now) ||
      isWaitingFor(message, memberId) ||
      kept.includes(message.id),
  );
}

/** Deletes the conversations an earlier version saved in this browser. */
export function forgetSavedRooms() {
  try {
    for (const key of Object.keys(localStorage)) {
      if (key.startsWith(SAVED_ROOMS_PREFIX)) localStorage.removeItem(key);
    }
  } catch {
    // Storage blocked: nothing was saved.
  }
}

/** Adds or replaces messages (the newer copy's receipts win), oldest first. */
export function mergeMessages(
  current: ApiMessage[],
  incoming: ApiMessage[],
): ApiMessage[] {
  const byId = new Map(current.map((message) => [message.id, message]));
  for (const message of incoming) byId.set(message.id, message);
  return [...byId.values()].sort((a, b) => a.id - b.id);
}

/**
 * The server's list of the messages this member can see replaces ours, except a message that arrived over the
 * socket while the request was on its way (not in `knownBefore`) and the `kept` ones the server no longer returns
 * (read late, they stay on screen until the member leaves the room).
 */
export function syncWithServer(
  current: ApiMessage[],
  stored: ApiMessage[],
  knownBefore: ReadonlySet<number>,
  kept: readonly number[],
): ApiMessage[] {
  const storedIds = new Set(stored.map((message) => message.id));
  const keep = current.filter(
    (message) =>
      !storedIds.has(message.id) &&
      (!knownBefore.has(message.id) || kept.includes(message.id)),
  );
  return mergeMessages(keep, stored);
}

/** Applies a `messages.read` event (or the reader's own confirmation). */
export function applyRead(
  messages: ApiMessage[],
  read: ApiRoomRead,
): ApiMessage[] {
  const readIds = new Set(read.message_ids);
  const deletedIds = new Set(read.deleted_ids);
  return messages.map((message) => {
    if (deletedIds.has(message.id))
      return seenByEveryone(withReader(message, read.reader));
    return readIds.has(message.id) ? withReader(message, read.reader) : message;
  });
}

/** Ids of the messages still waiting for this member to read them. */
export const waitingFor = (messages: ApiMessage[], memberId: number) =>
  messages
    .filter((message) => isWaitingFor(message, memberId))
    .map((message) => message.id);

function isWaitingFor(message: ApiMessage, memberId: number) {
  return message.unread_by.some((member) => member.id === memberId);
}

function withReader(message: ApiMessage, reader: ApiMember): ApiMessage {
  return {
    ...message,
    read_by: message.read_by.some((member) => member.id === reader.id)
      ? message.read_by
      : [...message.read_by, reader],
    unread_by: message.unread_by.filter((member) => member.id !== reader.id),
  };
}

function seenByEveryone(message: ApiMessage): ApiMessage {
  const readers = [...message.read_by, ...message.unread_by];
  return {
    ...message,
    read_by: readers.filter(
      (member, i) => readers.findIndex((m) => m.id === member.id) === i,
    ),
    unread_by: [],
  };
}
