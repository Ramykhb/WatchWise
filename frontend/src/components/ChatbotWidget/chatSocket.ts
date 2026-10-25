import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { api } from '../../lib/api.ts'

export type ChatConnection = {
  key: string
  host: string
  port: number
  scheme: 'http' | 'https'
  max_message_chars: number
  max_history_messages: number
  max_history_chars: number
}

export type ChatSocketStatus = 'connecting' | 'connected' | 'disconnected' | 'error'
export type ChatDelta = { request_id: string; delta: string }

export function connectChatSocket(
  settings: ChatConnection,
  channelName: string,
  onStatus: (status: ChatSocketStatus) => void,
  onDelta: (event: ChatDelta) => void,
) {
  if (!settings.key || !settings.host) throw new Error('Chat connection is unavailable.')
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
        api.post<{ auth: string }>('/chat/auth', { socket_id: socketId, channel_name: channel.name })
          .then(({ data }) => callback(null, data))
          .catch(() => callback(new Error('Unable to authorize chat.'), null))
      },
    }),
  })

  const unsubscribeStatus = echo.connector.onConnectionChange((status) => {
    if (status === 'failed') onStatus('error')
    else if (status === 'disconnected') onStatus('disconnected')
    else if (status !== 'connected') onStatus('connecting')
  })

  echo.private(channelName)
    .subscribed(() => onStatus('connected'))
    .error(() => onStatus('error'))
    .listen('.chat.delta', onDelta)

  return () => {
    unsubscribeStatus()
    echo.leave(channelName)
    echo.disconnect()
  }
}
