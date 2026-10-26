import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { api } from '../../lib/api.ts'
import type { ApiMessage, ApiRoomRead } from '../../lib/types.ts'

// Live updates over Reverb. The open room: new messages (`message.sent`) and read receipts (`messages.read`)
// on the private channel `room.{id}`. The room list: new messages (`message.sent`) and room changes
// (`rooms.changed`) on `rooms.public` (public rooms) and `rooms.user.{id}` (your private rooms and
// invitations). Loaded on demand, like the chat assistant's socket.

export type RoomSocketStatus = 'connecting' | 'connected' | 'disconnected'

/** Public Reverb settings; the chat assistant's endpoint serves the same ones. */
type Connection = { key: string; host: string; port: number; scheme: 'http' | 'https' }

type RoomHandlers = {
  onMessage: (message: ApiMessage) => void
  onRead: (read: ApiRoomRead) => void
  /** Subscribed, first time and after every reconnect: anything sent meanwhile is fetched again. */
  onReady: () => void
}

type ListHandlers = {
  /** A new message in any room the list shows. */
  onMessage: (message: ApiMessage) => void
  /** A room was created, joined, or someone was invited or declined: the list should load again. */
  onChange: () => void
  /** Subscribed, first time and after every reconnect: the list should load again. */
  onReady: () => void
}

export type RoomSocket = {
  /** Listens to one room; returns the function that stops listening. */
  join: (roomId: number, handlers: RoomHandlers) => () => void
  /** Listens for changes to the signed-in user's room list; returns the function that stops listening. */
  watchList: (userId: number, handlers: ListHandlers) => () => void
  disconnect: () => void
}

export async function openRoomSocket(onStatus: (status: RoomSocketStatus) => void): Promise<RoomSocket> {
  const { data: settings } = await api.get<Connection>('/chat/connection')
  if (!settings.key || !settings.host) throw new Error('Live updates are unavailable.')

  const echo = new Echo<'reverb'>({
    broadcaster: 'reverb',
    Pusher,
    key: settings.key,
    wsHost: settings.host,
    wsPort: settings.port,
    wssPort: settings.port,
    forceTLS: settings.scheme === 'https',
    enabledTransports: ['ws', 'wss'],
    authorizer: (channel: { name: string }) => ({
      authorize: (socketId: string, callback: (error: Error | null, data: { auth: string; channel_data?: string; shared_secret?: string } | null) => void) => {
        api.post<{ auth: string }>('/rooms/socket-auth', { socket_id: socketId, channel_name: channel.name })
          .then(({ data }) => callback(null, data))
          .catch(() => callback(new Error('Unable to join the room.'), null))
      },
    }),
  })

  const stopWatching = echo.connector.onConnectionChange((status) => {
    onStatus(status === 'connected' ? 'connected' : status === 'failed' || status === 'disconnected' ? 'disconnected' : 'connecting')
  })

  return {
    join(roomId, { onMessage, onRead, onReady }) {
      const name = `room.${roomId}`
      echo.private(name)
        .subscribed(onReady)
        .listen('.message.sent', onMessage)
        .listen('.messages.read', onRead)
      return () => echo.leave(name)
    },
    watchList(userId, { onMessage, onChange, onReady }) {
      const names = ['rooms.public', `rooms.user.${userId}`]
      for (const name of names) {
        echo.private(name)
          .subscribed(onReady)
          .listen('.message.sent', onMessage)
          .listen('.rooms.changed', onChange)
      }
      return () => names.forEach((name) => echo.leave(name))
    },
    disconnect() {
      stopWatching()
      echo.disconnect()
    },
  }
}
