import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { api } from '../../lib/api.ts'
import type { ApiNotification } from '../../lib/types.ts'

// Live notifications over Reverb on the signed-in user's private channel `notifications.{id}`:
// `notification.pushed` brings a new or updated notification, `notifications.changed` means the list should load
// again (read, dismissed or answered in another tab, or a follow request withdrawn). Opened once per session by
// the notifications store, like the Rooms page and the chat assistant open theirs.

/** Public Reverb settings; the chat assistant's endpoint serves the same ones. */
type Connection = { key: string; host: string; port: number; scheme: 'http' | 'https' }

export type PushedNotification = { notification: ApiNotification; silent: boolean }

type Handlers = {
  onPushed: (pushed: PushedNotification) => void
  onChanged: () => void
  /** Subscribed, first time and after every reconnect: anything sent meanwhile is fetched again. */
  onReady: () => void
  onConnected: (connected: boolean) => void
}

export type NotificationSocket = { disconnect: () => void }

export async function openNotificationSocket(userId: number, handlers: Handlers): Promise<NotificationSocket> {
  const { data: settings } = await api.get<Connection>('/chat/connection')
  if (!settings.key || !settings.host) throw new Error('Live notifications are unavailable.')

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
        api.post<{ auth: string }>('/notifications/socket-auth', { socket_id: socketId, channel_name: channel.name })
          .then(({ data }) => callback(null, data))
          .catch(() => callback(new Error('Unable to listen for notifications.'), null))
      },
    }),
  })

  // A connected transport may still fail private-channel authorization. Keep polling until subscribed.
  const stopWatching = echo.connector.onConnectionChange(() => handlers.onConnected(false))
  const name = `notifications.${userId}`
  echo.private(name)
    .subscribed(() => {
      handlers.onConnected(true)
      handlers.onReady()
    })
    .error(() => handlers.onConnected(false))
    .listen('.notification.pushed', handlers.onPushed)
    .listen('.notifications.changed', handlers.onChanged)

  return {
    disconnect() {
      stopWatching()
      echo.leave(name)
      echo.disconnect()
    },
  }
}
