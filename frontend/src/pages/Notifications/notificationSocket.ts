import axios from 'axios'
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { api } from '../../lib/api.ts'
import type { ApiNotification } from '../../lib/types.ts'

/** Public Reverb settings; shared with the rooms and assistant sockets. */
type Connection = { key: string; host: string; port: number; scheme: 'http' | 'https' }

export type PushedNotification = { notification: ApiNotification; silent: boolean }

type Handlers = {
  onPushed: (pushed: PushedNotification) => void
  onChanged: () => void
  onReady: () => void
  /** True only after this user's private channel has subscribed successfully. */
  onConnected: (connected: boolean) => void
}

export type NotificationSocket = { disconnect: () => void }

const INITIAL_RETRY_MS = 2_000
const MAX_RETRY_MS = 60_000
const SUBSCRIBE_TIMEOUT_MS = 20_000

export async function openNotificationSocket(userId: number, handlers: Handlers): Promise<NotificationSocket> {
  const name = `notifications.${userId}`
  let echo: Echo<'reverb'> | null = null
  let stopWatching = () => {}
  let closed = false
  let generation = 0
  let retryDelay = INITIAL_RETRY_MS
  let retryTimer: ReturnType<typeof setTimeout> | undefined
  let subscribeTimer: ReturnType<typeof setTimeout> | undefined

  function clearTimers() {
    clearTimeout(retryTimer)
    clearTimeout(subscribeTimer)
    retryTimer = undefined
    subscribeTimer = undefined
  }

  function closeTransport() {
    stopWatching()
    echo?.leave(name)
    echo?.disconnect()
    echo = null
  }

  function retry() {
    if (closed) return
    handlers.onConnected(false)
    if (retryTimer !== undefined) return
    retryTimer = setTimeout(() => {
      retryTimer = undefined
      void connect()
    }, retryDelay)
    retryDelay = Math.min(retryDelay * 2, MAX_RETRY_MS)
  }

  async function connect() {
    if (closed) return
    const attempt = ++generation
    const current = () => !closed && attempt === generation
    clearTimers()
    closeTransport()
    handlers.onConnected(false)

    try {
      // Retry settings too: an old connection can still point at localhost after a deployment.
      const { data: settings } = await api.get<Connection>('/chat/connection', { timeout: 10_000 })
      if (!current()) return
      if (!settings.key || !settings.host) throw new Error('Missing notification connection settings.')

      echo = new Echo<'reverb'>({
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
            api.post<{ auth: string }>('/notifications/socket-auth', { socket_id: socketId, channel_name: channel.name }, { timeout: 10_000 })
              .then(({ data }) => callback(null, data))
              .catch((error: unknown) => {
                if (current()) {
                  console.warn('Notification channel authorization failed; retrying.', {
                    status: axios.isAxiosError(error) ? error.response?.status : undefined,
                  })
                  retry()
                }
                callback(new Error('Unable to listen for notifications.'), null)
              })
          },
        }),
      })

      stopWatching = echo.connector.onConnectionChange((status) => {
        if (!current() || status === 'connected') return
        handlers.onConnected(false)
        if (status === 'failed' || status === 'disconnected') retry()
      })
      subscribeTimer = setTimeout(() => {
        if (!current()) return
        console.warn('Notification channel did not subscribe; reconnecting.')
        retry()
      }, SUBSCRIBE_TIMEOUT_MS)

      echo.private(name)
        .subscribed(() => {
          if (!current()) return
          clearTimers()
          retryDelay = INITIAL_RETRY_MS
          handlers.onConnected(true)
          handlers.onReady()
        })
        .error(() => { if (current()) retry() })
        .listen('.notification.pushed', (pushed: PushedNotification) => { if (current()) handlers.onPushed(pushed) })
        .listen('.notifications.changed', () => { if (current()) handlers.onChanged() })
    } catch (error) {
      if (!current()) return
      console.warn('Notification socket setup failed; retrying.', {
        status: axios.isAxiosError(error) ? error.response?.status : undefined,
      })
      retry()
    }
  }

  await connect()

  return {
    disconnect() {
      closed = true
      generation++
      clearTimers()
      closeTransport()
    },
  }
}
