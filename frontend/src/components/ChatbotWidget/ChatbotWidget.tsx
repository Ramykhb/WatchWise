import { useEffect, useRef, useState, type FormEvent, type ReactNode } from 'react'
import { Bot, Film, MessageCircle, RotateCcw, Send, Sparkles, X } from 'lucide-react'
import { api } from '../../lib/api.ts'
import { apiErrorMessage } from '../../lib/auth.ts'
import type { ChatConnection, ChatDelta, ChatSocketStatus } from './chatSocket.ts'
import styles from './ChatbotWidget.module.css'

type Message = { id: string; from: 'assistant' | 'you'; text: string; failed?: boolean }
type Turn = { role: 'user' | 'assistant'; content: string }
type FailedTurn = { message: string; userId: string; assistantId: string }
type ChatReply = { request_id: string; message: string }

const STARTERS = [
  { label: 'Pick a movie for tonight', icon: Film },
  { label: 'Find something like a film I love', icon: Sparkles },
  { label: 'Give me a spoiler-free overview', icon: MessageCircle },
]

const INITIAL_MESSAGES: Message[] = [
  {
    id: 'greeting',
    from: 'assistant',
    text: 'Hey! I’m your WatchWise movie sidekick. Tell me what you’re in the mood for, and we’ll find your next favorite.',
  },
]

function renderInline(text: string): ReactNode[] {
  return text.split(/(\*\*[^*]+\*\*|\*[^*]+\*)/g).map((part, index) => {
    if (part.startsWith('**') && part.endsWith('**')) return <strong key={index}>{part.slice(2, -2)}</strong>
    if (part.startsWith('*') && part.endsWith('*')) return <em key={index}>{part.slice(1, -1)}</em>
    return part
  })
}

function renderAssistantText(text: string): ReactNode[] {
  const lines = text.replace(/\r/g, '').split('\n')
  const blocks: ReactNode[] = []
  let index = 0

  while (index < lines.length) {
    if (!lines[index].trim()) { index += 1; continue }

    if (lines[index].includes('|')) {
      const rows: string[][] = []
      while (index < lines.length && lines[index].includes('|')) {
        const row = lines[index].trim().replace(/^\|/, '').replace(/\|$/, '')
        const cells = row.split('|').map((cell) => cell.trim())
        if (!cells.every((cell) => /^:?-{3,}:?$/.test(cell))) rows.push(cells)
        index += 1
      }
      const header = rows.length > 1 && rows[0].length > 1 && /movie|film|title|show|recommendation/i.test(rows[0][0])
      const items = header ? rows.slice(1) : rows
      blocks.push(
        <div className={styles.recommendations} key={`table-${index}`}>
          {items.map((cells, rowIndex) => (
            <article className={styles.recommendation} key={rowIndex}>
              <strong>{renderInline(cells[0] ?? '')}</strong>
              {cells.slice(1).filter(Boolean).map((cell, cellIndex) => <p key={cellIndex}>{renderInline(cell)}</p>)}
            </article>
          ))}
        </div>,
      )
      continue
    }

    const bullet = lines[index].match(/^\s*[-*+]\s+(.+)/)
    if (bullet) {
      const items: string[] = []
      while (index < lines.length) {
        const match = lines[index].match(/^\s*[-*+]\s+(.+)/)
        if (!match) break
        items.push(match[1])
        index += 1
      }
      blocks.push(<ul className={styles.answerList} key={`list-${index}`}>{items.map((item, itemIndex) => <li key={itemIndex}>{renderInline(item)}</li>)}</ul>)
      continue
    }

    const heading = lines[index].match(/^#{1,3}\s+(.+)/)
    if (heading) {
      blocks.push(<strong className={styles.answerHeading} key={`heading-${index}`}>{renderInline(heading[1])}</strong>)
      index += 1
      continue
    }

    const paragraph: string[] = []
    while (index < lines.length && lines[index].trim() && !lines[index].includes('|') && !/^\s*[-*+]\s+/.test(lines[index]) && !/^#{1,3}\s+/.test(lines[index])) {
      paragraph.push(lines[index])
      index += 1
    }
    if (paragraph.length) blocks.push(<p key={`paragraph-${index}`}>{renderInline(paragraph.join(' '))}</p>)
  }

  return blocks
}

/** Keep complete recent turns within the server's context budget. The visible transcript stays intact. */
function recentHistory(history: Turn[], settings: ChatConnection): Turn[] {
  let recent = history.slice(-settings.max_history_messages)
  while (recent.length && recent.reduce((sum, turn) => sum + [...turn.content].length, 0) > settings.max_history_chars) {
    recent = recent.slice(2)
  }
  return recent
}

export default function ChatbotWidget({ userId }: { userId: number }) {
  const [open, setOpen] = useState(false)
  const [draft, setDraft] = useState('')
  const [messages, setMessages] = useState(INITIAL_MESSAGES)
  const [conversationId] = useState(() => crypto.randomUUID())
  const [socketStatus, setSocketStatus] = useState<ChatSocketStatus>('connecting')
  const [settings, setSettings] = useState<ChatConnection | null>(null)
  const [connectionAttempt, setConnectionAttempt] = useState(0)
  const [sending, setSending] = useState(false)
  const [error, setError] = useState('')
  const [failedTurn, setFailedTurn] = useState<FailedTurn | null>(null)
  const historyRef = useRef<Turn[]>([])
  const busyRef = useRef(false)
  const activeRequest = useRef<{ requestId: string; assistantId: string } | null>(null)
  const abortRef = useRef<AbortController | null>(null)
  const inputRef = useRef<HTMLInputElement>(null)
  const launcherRef = useRef<HTMLButtonElement>(null)
  const scrollRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (open) inputRef.current?.focus()
  }, [open])

  useEffect(() => {
    if (open && scrollRef.current) scrollRef.current.scrollTop = scrollRef.current.scrollHeight
  }, [messages, open, sending])

  useEffect(() => () => {
    activeRequest.current = null
    abortRef.current?.abort()
  }, [])

  useEffect(() => {
    if (!open) return
    let active = true
    let disconnect: (() => void) | undefined
    const controller = new AbortController()

    const receiveDelta = (event: ChatDelta) => {
      if (!active || event.request_id !== activeRequest.current?.requestId || typeof event.delta !== 'string') return
      const assistantId = activeRequest.current.assistantId
      setMessages((current) => current.map((message) =>
        message.id === assistantId ? { ...message, text: message.text + event.delta } : message,
      ))
    }

    void Promise.all([
      api.get<ChatConnection>('/chat/connection', { signal: controller.signal }),
      import('./chatSocket.ts'),
    ]).then(([{ data }, { connectChatSocket }]) => {
      if (!active) return
      setSettings(data)
      disconnect = connectChatSocket(data, `chat.${userId}.${conversationId}`, (status) => {
        if (active) setSocketStatus(status)
      }, receiveDelta)
    }).catch(() => {
      if (active) setSocketStatus('error')
    })

    return () => {
      active = false
      controller.abort()
      disconnect?.()
    }
  }, [open, userId, conversationId, connectionAttempt])

  const close = () => {
    setOpen(false)
    setSocketStatus('connecting')
    launcherRef.current?.focus()
  }

  useEffect(() => {
    if (!open) return
    const closeOnEscape = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        setOpen(false)
        setSocketStatus('connecting')
        launcherRef.current?.focus()
      }
    }
    document.addEventListener('keydown', closeOnEscape)
    return () => document.removeEventListener('keydown', closeOnEscape)
  }, [open])

  const sendMessage = async (text: string, retry?: FailedTurn) => {
    const content = text.trim()
    if (!content || busyRef.current || socketStatus !== 'connected' || !settings) return
    if ([...content].length > settings.max_message_chars) {
      setError(`Keep your message within ${settings.max_message_chars} characters.`)
      return
    }

    busyRef.current = true
    setSending(true)
    setError('')
    const requestId = crypto.randomUUID()
    const turn = retry ?? { message: content, userId: `${requestId}-user`, assistantId: `${requestId}-assistant` }
    activeRequest.current = { requestId, assistantId: turn.assistantId }
    const controller = new AbortController()
    abortRef.current = controller

    setMessages((current) => {
      const oldIds = failedTurn ? [failedTurn.userId, failedTurn.assistantId] : []
      const previous = current.filter((message) => !oldIds.includes(message.id))
      return [
        ...previous,
        { id: turn.userId, from: 'you', text: content },
        { id: turn.assistantId, from: 'assistant', text: '' },
      ]
    })
    setFailedTurn(null)
    if (!retry) setDraft('')

    try {
      const { data } = await api.post<ChatReply>('/chat/messages', {
        message: content,
        history: recentHistory(historyRef.current, settings),
        conversation_id: conversationId,
        request_id: requestId,
      }, { signal: controller.signal, timeout: 90000 })
      if (controller.signal.aborted || activeRequest.current?.requestId !== requestId) return
      if (data.request_id !== requestId || typeof data.message !== 'string' || !data.message.trim()) {
        throw new Error('The assistant returned an empty reply.')
      }
      activeRequest.current = null
      setMessages((current) => current.map((message) =>
        message.id === turn.assistantId ? { ...message, text: data.message } : message,
      ))
      historyRef.current = recentHistory([
        ...historyRef.current,
        { role: 'user', content },
        { role: 'assistant', content: data.message },
      ], settings)
    } catch (requestError) {
      if (controller.signal.aborted) return
      activeRequest.current = null
      setError(apiErrorMessage(requestError, 'Couldn’t get a reply. Please try again.'))
      setFailedTurn(turn)
      setMessages((current) => current.map((message) =>
        message.id === turn.assistantId ? { ...message, failed: true } : message,
      ))
    } finally {
      if (!controller.signal.aborted) {
        busyRef.current = false
        setSending(false)
      }
    }
  }

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    void sendMessage(draft)
  }

  const reset = () => {
    if (busyRef.current) return
    activeRequest.current = null
    historyRef.current = []
    setMessages(INITIAL_MESSAGES)
    setFailedTurn(null)
    setError('')
    setDraft('')
    inputRef.current?.focus()
  }

  const connected = socketStatus === 'connected'
  const statusText = connected ? 'Here for your next movie night'
    : socketStatus === 'connecting' ? 'Connecting to your assistant…' : 'Chat is currently offline'

  return (
    <div className={styles.widget}>
      {open && (
        <section id="chatbot-panel" className={styles.panel} role="dialog" aria-labelledby="chatbot-title" aria-describedby="chatbot-status">
          <header className={styles.header}>
            <span className={styles.brandIcon} aria-hidden="true"><Sparkles size={20} strokeWidth={2.2} /></span>
            <div className={styles.headerText}>
              <h2 id="chatbot-title">WatchWise Assistant</h2>
              <p id="chatbot-status" role="status">
                <span className={connected ? styles.onlineDot : styles.offlineDot} /> {statusText}
              </p>
            </div>
            <button type="button" className={styles.close} aria-label="Start a new chat" disabled={sending} onClick={reset}>
              <RotateCcw size={16} />
            </button>
            <button type="button" className={styles.close} aria-label="Close chat" onClick={close}><X size={18} /></button>
          </header>

          <div ref={scrollRef} className={styles.conversation} role="log" aria-label="Chat messages" aria-live="polite" aria-relevant="additions text" data-lenis-prevent>
            <p className={styles.dayLabel}>YOUR MOVIE SIDEKICK</p>
            {messages.map((message) => (
              <div key={message.id} className={message.from === 'you' ? styles.userRow : styles.assistantRow}>
                {message.from === 'assistant' && <span className={styles.messageIcon} aria-hidden="true"><Bot size={16} /></span>}
                <div className={message.from === 'you' ? styles.userMessage : styles.assistantMessage}>
                  {message.text ? message.from === 'assistant' ? renderAssistantText(message.text) : message.text
                    : message.failed ? 'Reply interrupted.' : <span className={styles.typing} aria-label="Assistant is thinking"><i /><i /><i /></span>}
                  {message.failed && message.text && <span className={styles.interrupted}>Reply interrupted</span>}
                </div>
              </div>
            ))}

            {messages.length === 1 && (
              <div className={styles.starters}>
                <p>Try asking</p>
                {STARTERS.map(({ label, icon: Icon }) => (
                  <button key={label} type="button" disabled={!connected || sending} onClick={() => void sendMessage(label)}>
                    <Icon size={15} aria-hidden="true" />{label}
                  </button>
                ))}
              </div>
            )}
          </div>

          {error && (
            <div className={styles.error} role="alert">
              <p>{error}</p>
              {failedTurn && <button type="button" disabled={!connected || sending} onClick={() => void sendMessage(failedTurn.message, failedTurn)}>Try again</button>}
            </div>
          )}
          {!connected && (
            <div className={styles.connectionNote}>
              <span>{socketStatus === 'connecting' ? 'Getting your chat ready…' : 'Connection lost. Your messages are still here.'}</span>
              {socketStatus !== 'connecting' && <button type="button" onClick={() => { setSocketStatus('connecting'); setConnectionAttempt((value) => value + 1) }}>Reconnect</button>}
            </div>
          )}

          <form className={styles.composer} onSubmit={submit}>
            <label className="sr-only" htmlFor="chatbot-message">Message WatchWise Assistant</label>
            <input
              ref={inputRef}
              id="chatbot-message"
              value={draft}
              onChange={(event) => setDraft(event.target.value)}
              placeholder="Ask about a movie…"
              maxLength={settings?.max_message_chars ?? 2000}
            />
            <button type="submit" aria-label="Send message" disabled={!draft.trim() || sending || !connected}><Send size={17} /></button>
          </form>
          <p className={styles.previewNote}>AI can make mistakes · chat resets when you refresh</p>
        </section>
      )}

      <button
        ref={launcherRef}
        type="button"
        className={open ? styles.launcherOpen : styles.launcher}
        aria-label={open ? 'Close WatchWise Assistant' : 'Open WatchWise Assistant'}
        aria-expanded={open}
        aria-controls="chatbot-panel"
        onClick={() => open ? close() : setOpen(true)}
      >
        {open ? <X size={24} /> : <MessageCircle size={25} />}
        {!open && <span className={styles.launcherSparkle} aria-hidden="true"><Sparkles size={13} /></span>}
      </button>
    </div>
  )
}
