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
import globeIcon from '../../assets/lists/icon-globe.svg'
import lockIcon from '../../assets/lists/icon-lock.svg'
import { useEntranceAnimation } from '../../hooks/useEntranceAnimation.ts'
import { useAuth } from '../../contexts/useAuth.ts'
import { api, useApi } from '../../lib/api.ts'
import { apiErrorMessage } from '../../lib/auth.ts'
import { PICKABLE_MOVIES, tmdbIdOf } from '../../lib/movies.ts'
import type { ApiMember, ApiMessage, ApiRoom } from '../../lib/types.ts'
import { gradientCss } from '../Home/homeData.ts'
import { applyRead, hasExpired, mergeMessages, syncWithServer, waitingFor, withoutExpired } from './roomHistory.ts'
import type { RoomSocket, RoomSocketStatus } from './roomSocket.ts'
import {
  EMOJIS,
  ROOM_KINDS,
  ROOM_SCOPES,
  channelName,
  matchesFilters,
  rulesFor,
  sectionOf,
  toMessages,
  toRoom,
  toRoomMember,
  withNewMessage,
  type Member,
  type RoomKind,
  type RoomScope,
  type RoomSection,
} from './roomsData.ts'
import styles from './Rooms.module.css'

/** A burst of new messages is confirmed as read once it settles. */
const READ_DELAY_MS = 250
/** The most message ids `POST /rooms/{id}/read` accepts in one request. */
const READ_BATCH_SIZE = 500
/** How often messages whose 24 hours are up are taken off the screen. */
const EXPIRY_CHECK_MS = 60_000

/** The room list's sections, in order; "All rooms" shows all three, "My rooms" only yours. */
const SECTIONS: { id: RoomSection; title: string }[] = [
  { id: 'invites', title: 'INVITATIONS' },
  { id: 'mine', title: 'YOUR ROOMS' },
  { id: 'public', title: 'PUBLIC ROOMS' },
]

/**
 * The open room's conversation, for one signed-in account (`key` = "userId:roomId"), held only while the room is
 * open. `readLate`: the messages this account read after their 24 hours were up; they stay on screen until it
 * leaves the room. `loaded`: the server's messages have arrived.
 */
type Thread = { key: string; messages: ApiMessage[]; readLate: number[]; loaded: boolean }

export default function Rooms() {
  const { roomId } = useParams()
  const navigate = useNavigate()
  const { user } = useAuth()
  const pageRef = useRef<HTMLDivElement>(null)
  const messagesRef = useRef<HTMLDivElement>(null)
  useEntranceAnimation(pageRef)

  // Messages leave the screen once their 24 hours are up; this clock checks every minute.
  const [now, setNow] = useState(Date.now)
  useEffect(() => {
    const timer = window.setInterval(() => setNow(Date.now()), EXPIRY_CHECK_MS)
    return () => window.clearInterval(timer)
  }, [])

  const roomsQuery = useApi<{ rooms: ApiRoom[] }>('/rooms')
  const rooms = (roomsQuery.data?.rooms ?? []).map((r) => toRoom(r, now))
  const setRoomsData = roomsQuery.setData
  const reloadRooms = roomsQuery.reload
  const [query, setQuery] = useState('')
  const [scope, setScope] = useState<RoomScope>('all')
  const [kind, setKind] = useState<RoomKind>('any')
  const [creating, setCreating] = useState(false)
  const [newPrivate, setNewPrivate] = useState(false)
  const [createError, setCreateError] = useState('')
  const [draft, setDraft] = useState('')
  // Sending, joining, declining or inviting failed.
  const [chatError, setChatError] = useState('')
  const [showEmoji, setShowEmoji] = useState(false)
  const [showMenu, setShowMenu] = useState(false)
  // The "invite people you follow" picker, opened from the members card or (when that is hidden) the ⋯ menu.
  const [inviteFrom, setInviteFrom] = useState<'panel' | 'menu' | null>(null)
  const [muted, setMuted] = useState<string[]>([])
  const [menuNote, setMenuNote] = useState('')

  const room = rooms.find((r) => r.id === roomId) ?? rooms[0]

  const viewerId = user?.id
  const openRoomId = room?.id

  // The open room's conversation: the server's messages, kept current by the socket, and nothing saved in the
  // browser. A message leaves 24 hours after it was sent unless it still waits for you; one you read after that
  // stays until you leave the room.
  const threadKey = room && viewerId !== undefined ? `${viewerId}:${room.id}` : ''
  const [thread, setThread] = useState<Thread>({ key: '', messages: [], readLate: [], loaded: false })
  if (room && viewerId !== undefined && thread.key !== threadKey) {
    setThread({ key: threadKey, messages: [], readLate: [], loaded: false })
  }
  const loaded = thread.key === threadKey && thread.loaded
  const messages = thread.key === threadKey && viewerId !== undefined ? withoutExpired(thread.messages, viewerId, now, thread.readLate) : []
  const threadRef = useRef(thread)
  useEffect(() => {
    threadRef.current = thread
  }, [thread])

  // One socket connection while the page is open; the open room listens on its own channel.
  const [socket, setSocket] = useState<RoomSocket | null>(null)
  const [live, setLive] = useState<RoomSocketStatus>('connecting')
  useEffect(() => {
    let active = true
    let opened: RoomSocket | null = null
    import('./roomSocket.ts')
      .then(({ openRoomSocket }) => openRoomSocket((status) => active && setLive(status)))
      .then((connected) => {
        if (!active) return connected.disconnect()
        opened = connected
        setSocket(connected)
      })
      .catch(() => active && setLive('disconnected'))
    return () => {
      active = false
      opened?.disconnect()
    }
  }, [])

  // Fetch what the server still stores when the room opens and whenever the socket (re)subscribes, so
  // nothing sent while disconnected is missed; new messages and read receipts then arrive over the socket.
  const [syncError, setSyncError] = useState('')
  useEffect(() => {
    if (!openRoomId || viewerId === undefined) return
    const key = `${viewerId}:${openRoomId}`
    let active = true
    let syncing = false
    let again = false

    const sync = () => {
      if (syncing) {
        again = true
        return
      }
      syncing = true
      again = false
      const current = threadRef.current
      const knownBefore = new Set(current.key === key ? current.messages.map((message) => message.id) : [])
      api
        .get<{ messages: ApiMessage[] }>(`/rooms/${openRoomId}/messages`)
        .then(({ data }) => {
          if (!active) return
          setThread((thread) =>
            thread.key === key
              ? { ...thread, messages: syncWithServer(thread.messages, data.messages, knownBefore, thread.readLate), loaded: true }
              : thread,
          )
          setSyncError('')
        })
        .catch(() => active && setSyncError("Couldn't load this room's messages. Open it again to retry."))
        .finally(() => {
          syncing = false
          if (again && active) sync()
        })
    }

    sync()
    const leave = socket?.join(Number(openRoomId), {
      onMessage: (message) => active && setThread((thread) => (thread.key === key ? { ...thread, messages: mergeMessages(thread.messages, [message]) } : thread)),
      onRead: (read) => active && setThread((thread) => (thread.key === key ? { ...thread, messages: applyRead(thread.messages, read) } : thread)),
      onReady: sync,
    })
    return () => {
      active = false
      leave?.()
    }
  }, [openRoomId, viewerId, socket])

  // The room list stays current too: a new message in any room becomes its latest one (and counts as unread
  // when it waits for you); a room created, joined or an invitation sent or declined loads the list again, as
  // does every (re)subscription, so nothing missed while disconnected stays out.
  useEffect(() => {
    if (!socket || viewerId === undefined) return
    return socket.watchList(viewerId, {
      onMessage: (message) => setRoomsData((current) => ({ rooms: withNewMessage(current.rooms, message, viewerId) })),
      onChange: reloadRooms,
      onReady: reloadRooms,
    })
  }, [socket, viewerId, setRoomsData, reloadRooms])

  // While the room is open and the tab is visible, messages waiting for this member count as read.
  const [visible, setVisible] = useState(() => document.visibilityState === 'visible')
  useEffect(() => {
    const update = () => setVisible(document.visibilityState === 'visible')
    document.addEventListener('visibilitychange', update)
    return () => document.removeEventListener('visibilitychange', update)
  }, [])

  const confirming = useRef(new Set<number>())
  useEffect(() => {
    if (!visible || !user || !openRoomId || thread.key !== `${user.id}:${openRoomId}`) return
    const ids = waitingFor(thread.messages, user.id).filter((id) => !confirming.current.has(id))
    if (ids.length === 0) return
    const key = thread.key
    const reader: ApiMember = { id: user.id, username: user.username, name: user.name, avatar: user.avatar }
    const pending = confirming.current
    ids.forEach((id) => pending.add(id))
    let sent = false
    const timer = window.setTimeout(() => {
      sent = true
      // Read after their 24 hours were up: they stay on screen until you leave the room.
      const readAt = Date.now()
      const late = thread.messages.filter((message) => ids.includes(message.id) && hasExpired(message, readAt)).map((message) => message.id)
      if (late.length > 0) setThread((current) => (current.key === key ? { ...current, readLate: [...current.readLate, ...late] } : current))
      for (let start = 0; start < ids.length; start += READ_BATCH_SIZE) {
        const batch = ids.slice(start, start + READ_BATCH_SIZE)
        api
          .post<{ message_ids: number[]; deleted_ids: number[] }>(`/rooms/${openRoomId}/read`, { message_ids: batch })
          .then(({ data }) => {
            setThread((thread) => (thread.key === key ? { ...thread, messages: applyRead(thread.messages, { reader, ...data }) } : thread))
            setRoomsData((current) => ({
              rooms: current.rooms.map((r) => (String(r.id) === openRoomId ? { ...r, unread: Math.max(0, r.unread - data.message_ids.length) } : r)),
            }))
          })
          .catch(() => undefined)
          .finally(() => batch.forEach((id) => pending.delete(id)))
      }
    }, READ_DELAY_MS)
    return () => {
      if (sent) return
      window.clearTimeout(timer)
      ids.forEach((id) => pending.delete(id))
    }
  }, [thread, visible, user, openRoomId, setRoomsData])

  // The list: the filters and the search pick the rooms; each section shows the most recently active first.
  const search = query.trim().toLowerCase()
  const visibleRooms = rooms.filter((r) => matchesFilters(r, scope, kind) && r.name.toLowerCase().includes(search))
  const sections = SECTIONS.map((section) => ({
    ...section,
    rooms: visibleRooms.filter((r) => sectionOf(r) === section.id).sort((a, b) => b.lastActivity.localeCompare(a.lastActivity)),
  })).filter((section) => section.rooms.length > 0)

  const roomMessages = toMessages(messages, viewerId)

  // Keep the newest message in view.
  useEffect(() => {
    const el = messagesRef.current
    if (el) el.scrollTop = el.scrollHeight
  }, [room?.id, messages.length])

  const openRoom = (id: string) => {
    setShowMenu(false)
    setShowEmoji(false)
    setInviteFrom(null)
    setMenuNote('')
    setChatError('')
    // Fresh unread counts and previews for the other rooms.
    reloadRooms()
    navigate(`/rooms/${id}`, { replace: true })
  }

  const send = async (text: string) => {
    const body = text.trim()
    if (!body || !room) return
    setDraft('')
    setShowEmoji(false)
    setChatError('')
    try {
      const response = await api.post<{ message: ApiMessage }>(`/rooms/${room.id}/messages`, { content: body })
      // The socket delivers it too; merging by id keeps one copy.
      setThread((current) => (current.key === threadKey ? { ...current, messages: mergeMessages(current.messages, [response.data.message]) } : current))
    } catch (error) {
      setDraft(body)
      setChatError(apiErrorMessage(error, "Couldn't send your message. Please try again."))
    }
  }

  const replaceRoom = (updated: ApiRoom) => setRoomsData((current) => ({ rooms: current.rooms.map((r) => (r.id === updated.id ? updated : r)) }))

  // Joining a public room, or accepting the invitation to a private one: from now on messages wait for you too.
  const joinRoom = async () => {
    if (!room) return
    setChatError('')
    try {
      const response = await api.post<{ room: ApiRoom }>(`/rooms/${room.id}/join`)
      replaceRoom(response.data.room)
    } catch (error) {
      setChatError(apiErrorMessage(error, "Couldn't join the room."))
    }
  }

  const declineInvite = async () => {
    if (!room) return
    setChatError('')
    try {
      await api.delete(`/rooms/${room.id}/invite`)
      setRoomsData((current) => ({ rooms: current.rooms.filter((r) => String(r.id) !== room.id) }))
      navigate('/rooms', { replace: true })
    } catch (error) {
      setChatError(apiErrorMessage(error, "Couldn't decline the invitation."))
    }
  }

  // Members of a private room invite people they follow; invitees can read the room, then join or decline.
  const following = useApi<{ users: ApiMember[] }>(`/users/${user?.username ?? ''}/following`)
  const canInvite = !!room && room.isPrivate && room.joined
  const invitable = canInvite
    ? (following.data?.users ?? [])
        .filter((person) => !room.members.some((m) => m.id === person.id) && !room.invitees.some((m) => m.id === person.id))
        .map(toRoomMember)
    : []

  // Opening the picker loads who you follow again, so someone followed since the page opened is there too.
  const toggleInvite = (from: 'panel' | 'menu') => {
    if (inviteFrom !== from) following.reload()
    setInviteFrom(inviteFrom === from ? null : from)
  }

  const invite = async (person: Member) => {
    if (!room) return
    setInviteFrom(null)
    setChatError('')
    try {
      const response = await api.post<{ room: ApiRoom }>(`/rooms/${room.id}/invites`, { invitees: [person.handle] })
      replaceRoom(response.data.room)
      setMenuNote(`Invited ${person.name} — they can read the room and join.`)
    } catch (error) {
      setChatError(apiErrorMessage(error, `Couldn't invite ${person.name}.`))
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
      const response = await api.post<{ room: { id: number } }>('/rooms', {
        name,
        tmdb_id: tmdbIdOf(movie),
        spoilers: data.get('spoilers') === 'on',
        is_private: newPrivate,
      })
      setCreating(false)
      setNewPrivate(false)
      reloadRooms()
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
  // The room's members (the host and everyone who joined), host first.
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
              <div className={styles.segmented} role="radiogroup" aria-label="Who can see the room">
                <button
                  type="button"
                  role="radio"
                  aria-checked={!newPrivate}
                  className={newPrivate ? styles.segment : styles.segmentOn}
                  onClick={() => setNewPrivate(false)}
                >
                  <img src={globeIcon} width={14} height={14} alt="" />
                  Public
                </button>
                <button
                  type="button"
                  role="radio"
                  aria-checked={newPrivate}
                  className={newPrivate ? styles.segmentOn : styles.segment}
                  onClick={() => setNewPrivate(true)}
                >
                  <img src={lockIcon} width={14} height={14} alt="" />
                  Private
                </button>
              </div>
              <p className={styles.createHint}>
                {newPrivate ? 'Only people you invite can see it, read it and join.' : 'Anyone can read it and join.'}
              </p>
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

          <div className={styles.filters}>
            <div className={styles.segmented} role="radiogroup" aria-label="Show">
              {ROOM_SCOPES.map((option) => (
                <button
                  key={option.id}
                  type="button"
                  role="radio"
                  aria-checked={scope === option.id}
                  className={scope === option.id ? styles.segmentOn : styles.segment}
                  onClick={() => setScope(option.id)}
                >
                  {option.label}
                </button>
              ))}
            </div>
            {scope === 'mine' && (
              <div className={styles.kinds} role="radiogroup" aria-label="Which of my rooms">
                {ROOM_KINDS.map((option) => (
                  <button key={option.id} type="button" role="radio" aria-checked={kind === option.id} className={styles.kind} onClick={() => setKind(option.id)}>
                    {option.id !== 'any' && <img src={option.id === 'private' ? lockIcon : globeIcon} width={12} height={12} alt="" />}
                    {option.label}
                  </button>
                ))}
              </div>
            )}
          </div>

          <ul className={styles.roomList} data-lenis-prevent>
            {sections.map((section) => (
              <li key={section.id} className={styles.section}>
                <p className={styles.liveLabel}>{section.title}</p>
                <ul className={styles.sectionRooms}>
                  {section.rooms.map((r) => (
                    <li key={r.id}>
                      <button
                        type="button"
                        className={styles.roomItem}
                        aria-current={r.id === room?.id ? 'true' : undefined}
                        onClick={() => openRoom(r.id)}
                      >
                        <span className={styles.roomThumb} style={{ background: gradientCss(r.movie.gradient) }} aria-hidden="true" />
                        <span className={styles.roomText}>
                          <span className={styles.roomName}>
                            {r.isPrivate && <img className={styles.roomLock} src={lockIcon} width={12} height={12} alt="" />}
                            {r.name}
                            {r.isPrivate && <span className="sr-only"> (private)</span>}
                          </span>
                          <span className={styles.roomPreview}>{r.preview}</span>
                        </span>
                        <span className={styles.roomMeta}>
                          <span className={styles.roomOnline}>
                            <img src={dotSmIcon} width={7} height={7} alt="" />
                            {r.members.length}
                            <span className="sr-only"> members</span>
                          </span>
                          {r.invited && <span className={styles.invitedTag}>Invited</span>}
                          {r.unread > 0 && r.id !== room?.id && (
                            <span className={styles.unread}>
                              {r.unread}
                              <span className="sr-only"> unread</span>
                            </span>
                          )}
                        </span>
                      </button>
                    </li>
                  ))}
                </ul>
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
            {rooms.length > 0 && visibleRooms.length === 0 && (
              <li className={styles.noRooms}>{search ? `No rooms match “${query.trim()}”.` : 'No rooms match these filters.'}</li>
            )}
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
                {people.length} {people.length === 1 ? 'member' : 'members'} ·
                <span className={styles.liveKind}>
                  <img src={room.isPrivate ? lockIcon : globeIcon} width={12} height={12} alt="" />
                  {room.isPrivate ? 'Private' : 'Public'}
                </span>
                · {live === 'connected' ? 'live' : live === 'connecting' ? 'connecting…' : 'offline'}
              </p>
            </div>
            {isMuted && <span className={styles.mutedChip}>Muted</span>}
            <span className={room.spoilers ? styles.spoilerChip : styles.safeChip}>{room.spoilers ? 'Spoilers allowed' : 'Spoiler-free'}</span>
            <div className={styles.menuWrap}>
              <button
                type="button"
                className={styles.dots}
                aria-label="Room options"
                aria-expanded={showMenu}
                onClick={() => {
                  setShowMenu((v) => !v)
                  setInviteFrom(null)
                }}
              >
                <img src={dotsIcon} width={18} height={18} alt="" />
              </button>
              {showMenu && (
                <ul className={styles.menu}>
                  {canInvite && (
                    <li>
                      <button
                        type="button"
                        onClick={() => {
                          toggleInvite('menu')
                          setShowMenu(false)
                        }}
                      >
                        Invite people you follow
                      </button>
                    </li>
                  )}
                  {room.joined && (
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
                  )}
                  <li>
                    <button type="button" onClick={copyLink}>
                      Copy room link
                    </button>
                  </li>
                </ul>
              )}
              {inviteFrom === 'menu' && <InvitePicker people={invitable} loading={following.loading} onInvite={invite} />}
            </div>
          </header>
          {menuNote && (
            <p className={styles.menuNote} role="status">
              {menuNote}
            </p>
          )}

          <div ref={messagesRef} className={styles.messages} data-lenis-prevent role="log" aria-live="polite" aria-label={`${room.name} messages`}>
            {roomMessages.length === 0 && (loaded || !syncError) && (
              <p className={styles.system}>
                <span>
                  {!loaded
                    ? 'Loading messages…'
                    : !room.joined
                      ? 'No new messages right now.'
                      : canInvite && people.length === 1
                        ? 'It’s just you here — invite people you follow from the ⋯ menu.'
                        : 'No messages yet — start the conversation.'}
                </span>
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
                  <p className={styles.time}>
                    {message.time}
                    {message.receipt && ` · ${message.receipt}`}
                  </p>
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
                    {message.receipt && <p className={styles.receipt}>{message.receipt}</p>}
                  </div>
                </div>
              ),
            )}
          </div>
          {chatError && (
            <p className={styles.menuNote} role="alert">
              {chatError}
            </p>
          )}
          {syncError && (
            <p className={styles.menuNote} role="status">
              {syncError}
            </p>
          )}

          {/* Only members write. Anyone else reads (their reading counts for nothing) until they join. */}
          {!room.joined ? (
            <div className={styles.joinBar}>
              <p className={styles.joinText}>
                {room.invited ? (
                  <>
                    <strong>{room.invitedBy?.name ?? 'A member'}</strong> invited you to this private room. Join to chat with everyone.
                  </>
                ) : (
                  <>
                    You’re reading this public room. <strong>Join</strong> to send messages.
                  </>
                )}
              </p>
              <div className={styles.joinActions}>
                {room.invited && (
                  <PillButton variant="outline" onClick={() => void declineInvite()}>
                    Decline
                  </PillButton>
                )}
                <PillButton onClick={() => void joinRoom()}>Join room</PillButton>
              </div>
            </div>
          ) : (
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
          )}
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
            <div className={styles.onlineHead}>
              <h3 id="online-title" className={styles.cardTitle}>
                In this room · {people.length}
              </h3>
              {canInvite && (
                <button
                  type="button"
                  className={styles.addMember}
                  aria-label="Invite people you follow"
                  aria-expanded={inviteFrom === 'panel'}
                  onClick={() => toggleInvite('panel')}
                >
                  <img src={plusIcon} width={16} height={16} alt="" />
                </button>
              )}
              {inviteFrom === 'panel' && <InvitePicker people={invitable} loading={following.loading} onInvite={invite} />}
            </div>
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
              {/* Invited but not joined yet: they can read the room, and messages do not wait for them. */}
              {room.invitees.map((member) => (
                <li key={`invited-${member.id}`} className={styles.invitee}>
                  <span className={styles.avatar} style={{ background: member.color }} aria-hidden="true">
                    {member.initials}
                  </span>
                  <Link className={styles.memberName} to={isMine(member) ? '/profile' : `/profile/${member.handle}`}>
                    {isMine(member) ? `${member.name} (you)` : member.name}
                  </Link>
                  <span className={styles.invitedChip}>Invited</span>
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

/** People you follow who can still be invited to the open private room. */
function InvitePicker({ people, loading, onInvite }: { people: Member[]; loading: boolean; onInvite: (person: Member) => void }) {
  return (
    <div className={styles.invitePicker} role="group" aria-label="Invite people you follow">
      <p className={styles.inviteTitle}>Invite people you follow</p>
      {people.length > 0 ? (
        <ul data-lenis-prevent>
          {people.map((person) => (
            <li key={person.id}>
              <button type="button" onClick={() => onInvite(person)}>
                <span className={`${styles.avatar} ${styles.avatarSm}`} style={{ background: person.color }} aria-hidden="true">
                  {person.initials}
                </span>
                {person.name}
              </button>
            </li>
          ))}
        </ul>
      ) : (
        <p className={styles.inviteEmpty}>{loading ? 'Loading…' : 'Everyone you follow is already here. Follow more people to invite them.'}</p>
      )}
    </div>
  )
}
