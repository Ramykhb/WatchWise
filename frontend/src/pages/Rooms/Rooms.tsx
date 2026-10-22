import { useEffect, useRef, useState, type ChangeEvent, type FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router'
import AppNav from '../../components/AppNav/AppNav.tsx'
import PillButton from '../../components/PillButton/PillButton.tsx'
import plusIcon from '../../assets/rooms/icon-plus.svg'
import searchIcon from '../../assets/listdetail/icon-search.svg'
import dotSmIcon from '../../assets/rooms/dot-sm.svg'
import dotIcon from '../../assets/home/dot-online.svg'
import dotsIcon from '../../assets/rooms/icon-dots.svg'
import typingDots from '../../assets/rooms/typing-dots.svg'
import smileIcon from '../../assets/rooms/icon-smile.svg'
import clipIcon from '../../assets/rooms/icon-clip.svg'
import sendIcon from '../../assets/rooms/icon-send.svg'
import starIcon from '../../assets/cinemas/icon-star.svg'
import shieldIcon from '../../assets/rooms/icon-shield.svg'
import checkIcon from '../../assets/rooms/icon-check.svg'
import { useEntranceAnimation } from '../../hooks/useEntranceAnimation.ts'
import { MOVIES, gradientCss } from '../Home/homeData.ts'
import { EMOJIS, ROOMS, YOU, channelName, clockTime, type Member, type Message, type Room } from './roomsData.ts'
import styles from './Rooms.module.css'

const ALL_MOVIES = Object.values(MOVIES)

// Demo only: canned replies so the room feels live until the real-time channel exists.
const REPLIES = ['Okay that reading changes everything.', 'Agreed — the score sells it too.', 'Rewatching this weekend because of you all.']

export default function Rooms() {
  const { roomId } = useParams()
  const navigate = useNavigate()
  const pageRef = useRef<HTMLDivElement>(null)
  const messagesRef = useRef<HTMLDivElement>(null)
  const timers = useRef<number[]>([])
  useEntranceAnimation(pageRef)

  const [rooms, setRooms] = useState<Room[]>(() =>
    ROOMS.map((r) => (r.id === (roomId ?? ROOMS[0].id) ? { ...r, unread: 0 } : r)),
  )
  const [query, setQuery] = useState('')
  const [creating, setCreating] = useState(false)
  const [draft, setDraft] = useState('')
  const [agreed, setAgreed] = useState<string[]>([])
  // Figma shows Rana typing in the first room; her message lands a few seconds after opening.
  const [typing, setTyping] = useState<{ roomId: string; member: Member } | null>(() => {
    const rana = ROOMS[0].members.find((m) => m.initials === 'RH')
    return rana ? { roomId: ROOMS[0].id, member: rana } : null
  })
  const [showEmoji, setShowEmoji] = useState(false)
  const [showMenu, setShowMenu] = useState(false)
  const [muted, setMuted] = useState<string[]>([])
  const [menuNote, setMenuNote] = useState('')

  const room = rooms.find((r) => r.id === roomId) ?? rooms[0]
  const visibleRooms = rooms.filter((r) => r.name.toLowerCase().includes(query.trim().toLowerCase()))

  const append = (id: string, message: Message, preview: string) =>
    setRooms((list) => list.map((r) => (r.id === id ? { ...r, messages: [...r.messages, message], preview } : r)))

  // Keep the newest message in view.
  useEffect(() => {
    const el = messagesRef.current
    if (el) el.scrollTop = el.scrollHeight
  }, [room.id, room.messages.length, typing])

  // Rana's reply from the Figma frame, then clear every pending demo timer on unmount.
  useEffect(() => {
    const pending = timers.current
    pending.push(
      window.setTimeout(() => {
        const rana = ROOMS[0].members.find((m) => m.initials === 'RH')!
        append(ROOMS[0].id, { id: 'n6', kind: 'message', from: rana, time: clockTime(), text: "I think the lighthouse is a time beacon. That's why it only lights up in the memories." }, 'Rana: the lighthouse is a time beacon')
        setTyping(null)
      }, 4500),
    )
    return () => pending.forEach((t) => window.clearTimeout(t))
  }, [])

  const openRoom = (id: string) => {
    setRooms((list) => list.map((r) => (r.id === id ? { ...r, unread: 0 } : r)))
    setShowMenu(false)
    setShowEmoji(false)
    setMenuNote('')
    navigate(`/rooms/${id}`, { replace: true })
  }

  const send = (text: string) => {
    const body = text.trim()
    if (!body) return
    const target = room
    // TODO: post to the room's real-time channel instead of local state.
    append(target.id, { id: crypto.randomUUID(), kind: 'message', from: YOU, time: clockTime(), text: body, seenBy: 0 }, `You: ${body}`)
    setDraft('')
    setShowEmoji(false)
    const replier = target.members.find((m) => m !== YOU)
    if (!replier) return
    timers.current.push(
      window.setTimeout(() => setTyping({ roomId: target.id, member: replier }), 900),
      window.setTimeout(() => {
        const reply = REPLIES[Math.floor(Math.random() * REPLIES.length)]
        append(
          target.id,
          { id: crypto.randomUUID(), kind: 'message', from: replier, time: clockTime(), text: reply },
          `${replier.name.split(' ')[0]}: ${reply}`,
        )
        setTyping(null)
      }, 3200),
    )
  }

  const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    send(draft)
  }

  const handleFile = (event: ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0]
    // TODO: upload attachments through the API; for now only the file name is shared.
    if (file) send(`📎 ${file.name}`)
    event.target.value = ''
  }

  const handleCreate = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const data = new FormData(event.currentTarget)
    const name = String(data.get('name') ?? '').trim()
    if (name.length < 3) return
    const movie = ALL_MOVIES.find((m) => m.slug === data.get('movie')) ?? MOVIES.neonTide
    let id = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'room'
    while (rooms.some((r) => r.id === id)) id += '-2'
    // TODO: create the room through the API.
    const created: Room = {
      id,
      name,
      movie,
      online: 1,
      unread: 0,
      spoilers: data.get('spoilers') === 'on',
      host: YOU,
      members: [],
      preview: 'You created the room',
      rules: ['Be kind — critique films, not people', data.get('spoilers') === 'on' ? 'Spoilers allowed in this room' : 'No spoilers — use spoiler tags', 'No links to pirated content'],
      messages: [{ id: 'created', kind: 'system', text: 'You created the room' }],
    }
    setRooms((list) => [created, ...list])
    setCreating(false)
    navigate(`/rooms/${id}`, { replace: true })
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

  const isMuted = muted.includes(room.id)
  const online = [room.host, ...room.members.filter((m) => m !== room.host), YOU].filter(
    (m, i, list) => list.findIndex((x) => x.initials === m.initials && x.name === m.name) === i,
  )
  const roomTyping = typing && typing.roomId === room.id ? typing.member : null

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
                {ALL_MOVIES.map((m) => (
                  <option key={m.slug} value={m.slug}>
                    {m.title}
                  </option>
                ))}
              </select>
              <label className={styles.createCheck}>
                <input type="checkbox" name="spoilers" /> Spoilers allowed
              </label>
              <PillButton type="submit">Create room</PillButton>
            </form>
          )}

          <label className={styles.search}>
            <img src={searchIcon} width={16} height={16} alt="" />
            <input type="search" value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Search rooms" aria-label="Search rooms" />
          </label>

          <p className={styles.liveLabel}>LIVE NOW</p>
          <ul className={styles.roomList} data-lenis-prevent>
            {visibleRooms.map((r) => (
              <li key={r.id}>
                <button
                  type="button"
                  className={styles.roomItem}
                  aria-current={r.id === room.id ? 'true' : undefined}
                  onClick={() => openRoom(r.id)}
                >
                  <span className={styles.roomThumb} style={{ background: gradientCss(r.movie.gradient) }} aria-hidden="true" />
                  <span className={styles.roomText}>
                    <span className={styles.roomName}>{r.short ?? r.name}</span>
                    <span className={styles.roomPreview}>{r.preview}</span>
                  </span>
                  <span className={styles.roomMeta}>
                    <span className={styles.roomOnline}>
                      <img src={dotSmIcon} width={7} height={7} alt="" />
                      {r.online}
                      <span className="sr-only"> online</span>
                    </span>
                    {r.unread > 0 && (
                      <span className={styles.unread}>
                        {r.unread}
                        <span className="sr-only"> unread</span>
                      </span>
                    )}
                  </span>
                </button>
              </li>
            ))}
            {visibleRooms.length === 0 && <li className={styles.noRooms}>No rooms match “{query}”.</li>}
          </ul>
        </aside>

        <section className={styles.chat} aria-labelledby="room-title" data-enter data-enter-delay="0.15">
          <header className={styles.chatHead}>
            <span className={styles.chatThumb} style={{ background: gradientCss(room.movie.gradient) }} aria-hidden="true" />
            <div className={styles.chatTitle}>
              <h2 id="room-title">{room.name}</h2>
              <p className={styles.live}>
                <img src={dotIcon} width={8} height={8} alt="" />
                {room.online} online · real-time
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
            {room.messages.map((message) =>
              message.kind === 'system' ? (
                <p key={message.id} className={styles.system}>
                  <span>{message.text}</span>
                </p>
              ) : message.from === YOU ? (
                <div key={message.id} className={styles.own}>
                  <p className={styles.ownBubble}>{message.text}</p>
                  <p className={styles.time}>
                    {message.time}
                    {message.seenBy !== undefined && ` · seen by ${message.seenBy}`}
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
                    {(message.agree !== undefined || message.extra) && (
                      <div className={styles.reactions}>
                        <button
                          type="button"
                          className={styles.reaction}
                          aria-pressed={agreed.includes(message.id)}
                          onClick={() =>
                            setAgreed((list) => (list.includes(message.id) ? list.filter((id) => id !== message.id) : [...list, message.id]))
                          }
                        >
                          {(message.agree ?? 0) + (agreed.includes(message.id) ? 1 : 0)} agree
                        </button>
                        {message.extra ? <span className={styles.reactionStatic}>+{message.extra}</span> : null}
                      </div>
                    )}
                  </div>
                </div>
              ),
            )}
            {roomTyping && (
              <p className={styles.typing}>
                <img src={typingDots} width={57} height={27} alt="" />
                {roomTyping.name.split(' ')[0]} is typing…
              </p>
            )}
          </div>

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
              Online now · {room.online}
            </h3>
            <ul data-lenis-prevent>
              {online.map((member) => (
                <li key={member.initials + member.name}>
                  <span className={styles.avatar} style={{ background: member.color }} aria-hidden="true">
                    {member.initials}
                  </span>
                  <span className={styles.memberName}>{member.name}</span>
                  {member === room.host ? <span className={styles.hostChip}>Host</span> : <img src={dotIcon} width={8} height={8} alt="Online" />}
                </li>
              ))}
            </ul>
          </section>
        </aside>
      </main>
    </div>
  )
}
