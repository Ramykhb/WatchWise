import { useEffect, useRef, useState, type ChangeEvent, type FormEvent } from 'react'
import { Link, useNavigate, useParams } from 'react-router'
import AppNav from '../../components/AppNav/AppNav.tsx'
import PillButton from '../../components/PillButton/PillButton.tsx'
import plusIcon from '../../assets/rooms/icon-plus.svg'
import searchIcon from '../../assets/listdetail/icon-search.svg'
import dotSmIcon from '../../assets/rooms/dot-sm.svg'
import dotIcon from '../../assets/home/dot-online.svg'
import dotsIcon from '../../assets/rooms/icon-dots.svg'
import smileIcon from '../../assets/rooms/icon-smile.svg'
import clipIcon from '../../assets/rooms/icon-clip.svg'
import sendIcon from '../../assets/rooms/icon-send.svg'
import starIcon from '../../assets/cinemas/icon-star.svg'
import shieldIcon from '../../assets/rooms/icon-shield.svg'
import checkIcon from '../../assets/rooms/icon-check.svg'
import { useEntranceAnimation } from '../../hooks/useEntranceAnimation.ts'
import { useAuth } from '../../contexts/useAuth.ts'
import { api, useApi } from '../../lib/api.ts'
import { apiErrorMessage } from '../../lib/auth.ts'
import { PICKABLE_MOVIES, tmdbIdOf } from '../../lib/movies.ts'
import type { ApiMessage, ApiRoom } from '../../lib/types.ts'
import { gradientCss } from '../Home/homeData.ts'
import { EMOJIS, channelName, rulesFor, toMessages, toRoom, type Member } from './roomsData.ts'
import styles from './Rooms.module.css'

/** How often an open room checks for new messages (no real-time channel yet). */
const POLL_MS = 5000

export default function Rooms() {
  const { roomId } = useParams()
  const navigate = useNavigate()
  const { user } = useAuth()
  const pageRef = useRef<HTMLDivElement>(null)
  const messagesRef = useRef<HTMLDivElement>(null)
  useEntranceAnimation(pageRef)

  const roomsQuery = useApi<{ rooms: ApiRoom[] }>('/rooms')
  const rooms = (roomsQuery.data?.rooms ?? []).map(toRoom)
  const [query, setQuery] = useState('')
  const [creating, setCreating] = useState(false)
  const [createError, setCreateError] = useState('')
  const [draft, setDraft] = useState('')
  const [sendError, setSendError] = useState('')
  const [agreed, setAgreed] = useState<string[]>([])
  const [showEmoji, setShowEmoji] = useState(false)
  const [showMenu, setShowMenu] = useState(false)
  const [muted, setMuted] = useState<string[]>([])
  const [menuNote, setMenuNote] = useState('')

  const room = rooms.find((r) => r.id === roomId) ?? rooms[0]
  const visibleRooms = rooms.filter((r) => r.name.toLowerCase().includes(query.trim().toLowerCase()))

  // Messages of the open room: loaded once, then only newer ones every few seconds.
  const [thread, setThread] = useState<{ roomId: string; messages: ApiMessage[] }>({ roomId: '', messages: [] })
  const messages = thread.roomId === room?.id ? thread.messages : []
  useEffect(() => {
    if (!room) return
    let active = true
    let lastId = 0
    const fetchNew = async () => {
      try {
        const response = await api.get<{ messages: ApiMessage[] }>(`/rooms/${room.id}/messages`, { params: lastId ? { after: lastId } : {} })
        if (!active) return
        const incoming = response.data.messages
        if (incoming.length) lastId = incoming.at(-1)!.id
        setThread((current) => {
          const base = current.roomId === room.id && lastId ? current.messages : []
          const known = new Set(base.map((m) => m.id))
          return { roomId: room.id, messages: [...base, ...incoming.filter((m) => !known.has(m.id))] }
        })
      } catch {
        // Try again on the next tick.
      }
    }
    void fetchNew()
    const timer = window.setInterval(() => void fetchNew(), POLL_MS)
    return () => {
      active = false
      window.clearInterval(timer)
    }
  }, [room?.id]) // eslint-disable-line react-hooks/exhaustive-deps

  const roomMessages = toMessages(messages)

  // Keep the newest message in view.
  useEffect(() => {
    const el = messagesRef.current
    if (el) el.scrollTop = el.scrollHeight
  }, [room?.id, messages.length])

  const openRoom = (id: string) => {
    setShowMenu(false)
    setShowEmoji(false)
    setMenuNote('')
    navigate(`/rooms/${id}`, { replace: true })
  }

  const send = async (text: string) => {
    const body = text.trim()
    if (!body || !room) return
    setDraft('')
    setShowEmoji(false)
    setSendError('')
    try {
      const response = await api.post<{ message: ApiMessage }>(`/rooms/${room.id}/messages`, { content: body })
      setThread((current) =>
        current.roomId === room.id && !current.messages.some((m) => m.id === response.data.message.id)
          ? { ...current, messages: [...current.messages, response.data.message] }
          : current,
      )
      if (!room.joined) roomsQuery.reload()
    } catch (error) {
      setDraft(body)
      setSendError(apiErrorMessage(error, "Couldn't send your message. Please try again."))
    }
  }

  const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    void send(draft)
  }

  const handleFile = (event: ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0]
    // TODO: upload attachments through the API; for now only the file name is shared.
    if (file) void send(`📎 ${file.name}`)
    event.target.value = ''
  }

  const handleCreate = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const data = new FormData(event.currentTarget)
    const name = String(data.get('name') ?? '').trim()
    if (name.length < 3) return
    const movie = PICKABLE_MOVIES.find((m) => m.slug === data.get('movie')) ?? PICKABLE_MOVIES[0]
    setCreateError('')
    try {
      const response = await api.post<{ room: { id: number } }>('/rooms', { name, tmdb_id: tmdbIdOf(movie), spoilers: data.get('spoilers') === 'on' })
      setCreating(false)
      roomsQuery.reload()
      navigate(`/rooms/${response.data.room.id}`, { replace: true })
    } catch (error) {
      setCreateError(apiErrorMessage(error, "Couldn't create the room."))
    }
  }

  const copyLink = async () => {
    try {
      await navigator.clipboard.writeText(window.location.href)
      setMenuNote('Room link copied')
    } catch {
      setMenuNote('')
    }
    setShowMenu(false)
  }

  // With no rooms (or while they load) the page keeps its normal layout: each column shows an empty state.
  const loadingRooms = roomsQuery.loading && rooms.length === 0
  const openCreate = () => setCreating(true)

  const isMuted = room ? muted.includes(room.id) : false
  const isMine = (member: Member) => member.id === user?.id
  // Everyone who has posted, host first; there is no presence channel yet.
  const people = room
    ? [room.host, ...room.members].filter((m, i, list): m is Member => m !== null && list.findIndex((x) => x?.id === m.id) === i)
    : []

  return (
    <div ref={pageRef} className={styles.page}>
      <AppNav />

      <main className={styles.body}>
        <aside className={styles.list} aria-label="Rooms" data-enter data-enter-delay="0.05">
          <div className={styles.listHead}>
            <h1 className={styles.listTitle}>Rooms</h1>
            <button type="button" className={styles.newRoom} aria-label="Create a room" aria-expanded={creating} onClick={() => setCreating((v) => !v)}>
              <img src={plusIcon} width={18} height={18} alt="" />
            </button>
          </div>

          {creating && (
            <form className={styles.createForm} onSubmit={handleCreate}>
              <input name="name" className={styles.createInput} placeholder="Room name" aria-label="Room name" required minLength={3} autoFocus />
              <select name="movie" className={styles.createInput} aria-label="Movie" defaultValue="neon-tide">
                {PICKABLE_MOVIES.map((m) => (
                  <option key={m.slug} value={m.slug}>
                    {m.title}
                  </option>
                ))}
              </select>
              <label className={styles.createCheck}>
                <input type="checkbox" name="spoilers" /> Spoilers allowed
              </label>
              <PillButton type="submit">Create room</PillButton>
              {createError && <p className={styles.noRooms} role="alert">{createError}</p>}
            </form>
          )}

          <label className={styles.search}>
            <img src={searchIcon} width={16} height={16} alt="" />
            <input type="search" value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Search rooms" aria-label="Search rooms" />
          </label>

          <p className={styles.liveLabel}>ROOMS</p>
          <ul className={styles.roomList} data-lenis-prevent>
            {visibleRooms.map((r) => (
              <li key={r.id}>
                <button
                  type="button"
                  className={styles.roomItem}
                  aria-current={r.id === room?.id ? 'true' : undefined}
                  onClick={() => openRoom(r.id)}
                >
                  <span className={styles.roomThumb} style={{ background: gradientCss(r.movie.gradient) }} aria-hidden="true" />
                  <span className={styles.roomText}>
                    <span className={styles.roomName}>{r.name}</span>
                    <span className={styles.roomPreview}>{r.preview}</span>
                  </span>
                  <span className={styles.roomMeta}>
                    <span className={styles.roomOnline}>
                      <img src={dotSmIcon} width={7} height={7} alt="" />
                      {r.members.length}
                      <span className="sr-only"> members</span>
                    </span>
                  </span>
                </button>
              </li>
            ))}
            {loadingRooms && (
              <li className={styles.noRooms} role="status">
                Loading rooms…
              </li>
            )}
            {!loadingRooms && rooms.length === 0 && (
              <li className={styles.emptyList}>
                <p className={styles.emptyListTitle}>No rooms yet</p>
                <p className={styles.noRooms}>Start one about a film and invite people to talk about it.</p>
                {!creating && (
                  <PillButton variant="soft" onClick={openCreate}>
                    Create a room
                  </PillButton>
                )}
              </li>
            )}
            {rooms.length > 0 && visibleRooms.length === 0 && <li className={styles.noRooms}>No rooms match “{query}”.</li>}
          </ul>
        </aside>

        {!room && (
          <section className={styles.chat} aria-labelledby="room-title" data-enter data-enter-delay="0.15">
            <header className={styles.chatHead}>
              <span className={`${styles.chatThumb} ${styles.thumbPlaceholder}`} aria-hidden="true" />
              <div className={styles.chatTitle}>
                <h2 id="room-title">{loadingRooms ? 'Loading rooms…' : 'No room open'}</h2>
                <p className={styles.live}>Rooms are live chats about a film.</p>
              </div>
            </header>

            <div className={styles.messages}>
              {loadingRooms ? (
                <p className={styles.system}>
                  <span>Loading rooms…</span>
                </p>
              ) : (
                <div className={styles.emptyChat}>
                  <p className={styles.emptyChatTitle}>Start the first conversation</p>
                  <p className={styles.emptyChatText}>
                    Create a room for a film: an ending to explain, a theory to argue, or a watch-along tonight.
                  </p>
                  <PillButton onClick={openCreate}>Create a room</PillButton>
                </div>
              )}
            </div>

            {/* The composer keeps its place, disabled until there is a room to write in. */}
            <div className={styles.composer} aria-hidden="true">
              <div className={`${styles.inputWrap} ${styles.composerDisabled}`}>
                <span className={styles.iconBtn}>
                  <img src={smileIcon} width={20} height={20} alt="" />
                </span>
                <input className={styles.input} placeholder="Create a room to start chatting…" disabled tabIndex={-1} />
                <span className={styles.iconBtn}>
                  <img src={clipIcon} width={20} height={20} alt="" />
                </span>
              </div>
              <button type="button" className={styles.send} disabled tabIndex={-1}>
                <img src={sendIcon} width={22} height={22} alt="" />
              </button>
            </div>
          </section>
        )}

        {!room && (
          <aside className={styles.info} aria-label="Room info" data-enter data-enter-delay="0.25">
            <section className={styles.movie}>
              <div className={styles.movieRow}>
                <span className={`${styles.moviePoster} ${styles.thumbPlaceholder}`} aria-hidden="true" />
                <div className={styles.movieText}>
                  <p className={styles.movieTitle}>No movie yet</p>
                  <p className={styles.movieMeta}>Each room is about one film. You pick it when you create the room.</p>
                </div>
              </div>
              <PillButton variant="accent" to="/discover">
                Browse movies
              </PillButton>
            </section>

            <section className={styles.rules} aria-labelledby="rules-title">
              <h3 id="rules-title" className={styles.cardTitle}>
                <img src={shieldIcon} width={18} height={18} alt="" />
                Room rules
              </h3>
              <ul>
                {rulesFor(false).map((rule) => (
                  <li key={rule}>
                    <img src={checkIcon} width={14} height={14} alt="" />
                    {rule}
                  </li>
                ))}
              </ul>
            </section>

            <section className={styles.online} aria-labelledby="online-title">
              <h3 id="online-title" className={styles.cardTitle}>
                In this room · 0
              </h3>
              <p className={styles.noRooms}>No one here yet.</p>
            </section>
          </aside>
        )}

        {room && (
        <>
        <section className={styles.chat} aria-labelledby="room-title" data-enter data-enter-delay="0.15">
          <header className={styles.chatHead}>
            <span className={styles.chatThumb} style={{ background: gradientCss(room.movie.gradient) }} aria-hidden="true" />
            <div className={styles.chatTitle}>
              <h2 id="room-title">{room.name}</h2>
              <p className={styles.live}>
                <img src={dotIcon} width={8} height={8} alt="" />
                {people.length} {people.length === 1 ? 'member' : 'members'} · updates every few seconds
              </p>
            </div>
            {isMuted && <span className={styles.mutedChip}>Muted</span>}
            <span className={room.spoilers ? styles.spoilerChip : styles.safeChip}>{room.spoilers ? 'Spoilers allowed' : 'Spoiler-free'}</span>
            <div className={styles.menuWrap}>
              <button type="button" className={styles.dots} aria-label="Room options" aria-expanded={showMenu} onClick={() => setShowMenu((v) => !v)}>
                <img src={dotsIcon} width={18} height={18} alt="" />
              </button>
              {showMenu && (
                <ul className={styles.menu}>
                  <li>
                    <button
                      type="button"
                      onClick={() => {
                        setMuted((list) => (isMuted ? list.filter((id) => id !== room.id) : [...list, room.id]))
                        setShowMenu(false)
                      }}
                    >
                      {isMuted ? 'Unmute notifications' : 'Mute notifications'}
                    </button>
                  </li>
                  <li>
                    <button type="button" onClick={copyLink}>
                      Copy room link
                    </button>
                  </li>
                </ul>
              )}
            </div>
          </header>
          {menuNote && (
            <p className={styles.menuNote} role="status">
              {menuNote}
            </p>
          )}

          <div ref={messagesRef} className={styles.messages} data-lenis-prevent role="log" aria-live="polite" aria-label={`${room.name} messages`}>
            {roomMessages.length === 0 && (
              <p className={styles.system}>
                <span>No messages yet — start the conversation.</span>
              </p>
            )}
            {roomMessages.map((message) =>
              message.kind === 'system' ? (
                <p key={message.id} className={styles.system}>
                  <span>{message.text}</span>
                </p>
              ) : isMine(message.from) ? (
                <div key={message.id} className={styles.own}>
                  <p className={styles.ownBubble}>{message.text}</p>
                  <p className={styles.time}>{message.time}</p>
                </div>
              ) : (
                <div key={message.id} className={styles.message}>
                  <span className={styles.avatar} style={{ background: message.from.color }} aria-hidden="true">
                    {message.from.initials}
                  </span>
                  <div className={styles.messageBody}>
                    <p className={styles.messageMeta}>
                      <strong>{message.from.name}</strong>
                      <span className={styles.time}>{message.time}</span>
                    </p>
                    <p className={styles.bubble}>{message.text}</p>
                    {/* TODO: store reactions in the API; "agree" is only kept on this device for now. */}
                    <div className={styles.reactions}>
                      <button
                        type="button"
                        className={styles.reaction}
                        aria-pressed={agreed.includes(message.id)}
                        onClick={() =>
                          setAgreed((list) => (list.includes(message.id) ? list.filter((id) => id !== message.id) : [...list, message.id]))
                        }
                      >
                        {agreed.includes(message.id) ? '1 agree' : 'Agree'}
                      </button>
                    </div>
                  </div>
                </div>
              ),
            )}
          </div>
          {sendError && (
            <p className={styles.menuNote} role="alert">
              {sendError}
            </p>
          )}

          <form className={styles.composer} onSubmit={handleSubmit}>
            <div className={styles.inputWrap}>
              <div className={styles.emojiWrap}>
                <button type="button" className={styles.iconBtn} aria-label="Add emoji" aria-expanded={showEmoji} onClick={() => setShowEmoji((v) => !v)}>
                  <img src={smileIcon} width={20} height={20} alt="" />
                </button>
                {showEmoji && (
                  <div className={styles.emojiPicker} role="group" aria-label="Emoji">
                    {EMOJIS.map((emoji) => (
                      <button key={emoji} type="button" onClick={() => setDraft((d) => `${d}${emoji}`)}>
                        {emoji}
                      </button>
                    ))}
                  </div>
                )}
              </div>
              <input
                className={styles.input}
                value={draft}
                onChange={(e) => setDraft(e.target.value)}
                placeholder={`Message ${channelName(room)}…`}
                aria-label={`Message ${room.name}`}
                maxLength={500}
              />
              <label className={styles.iconBtn} aria-label="Attach a file">
                <input type="file" className="sr-only" onChange={handleFile} />
                <img src={clipIcon} width={20} height={20} alt="" />
              </label>
            </div>
            <button type="submit" className={styles.send} aria-label="Send message" disabled={!draft.trim()}>
              <img src={sendIcon} width={22} height={22} alt="" />
            </button>
          </form>
        </section>

        <aside className={styles.info} aria-label="Room info" data-enter data-enter-delay="0.25">
          <section className={styles.movie}>
            <div className={styles.movieRow}>
              <span className={styles.moviePoster} style={{ background: gradientCss(room.movie.gradient) }} aria-hidden="true" />
              <div className={styles.movieText}>
                <p className={styles.movieTitle}>{room.movie.title}</p>
                <p className={styles.movieMeta}>{room.movie.meta}</p>
                <p className={styles.movieScore}>
                  <img src={starIcon} width={14} height={14} alt="" />
                  {room.movie.score}
                </p>
              </div>
            </div>
            <PillButton variant="accent" to={`/movies/${room.movie.slug}`}>
              Open movie page
            </PillButton>
          </section>

          <section className={styles.rules} aria-labelledby="rules-title">
            <h3 id="rules-title" className={styles.cardTitle}>
              <img src={shieldIcon} width={18} height={18} alt="" />
              Room rules
            </h3>
            <ul>
              {room.rules.map((rule) => (
                <li key={rule}>
                  <img src={checkIcon} width={14} height={14} alt="" />
                  {rule}
                </li>
              ))}
            </ul>
          </section>

          <section className={styles.online} aria-labelledby="online-title">
            <h3 id="online-title" className={styles.cardTitle}>
              In this room · {people.length}
            </h3>
            <ul data-lenis-prevent>
              {people.map((member) => (
                <li key={member.id}>
                  <span className={styles.avatar} style={{ background: member.color }} aria-hidden="true">
                    {member.initials}
                  </span>
                  <Link className={styles.memberName} to={isMine(member) ? '/profile' : `/profile/${member.handle}`}>
                    {isMine(member) ? `${member.name} (you)` : member.name}
                  </Link>
                  {member.id === room.host?.id && <span className={styles.hostChip}>Host</span>}
                </li>
              ))}
            </ul>
          </section>
        </aside>
        </>
        )}
      </main>
    </div>
  )
}
