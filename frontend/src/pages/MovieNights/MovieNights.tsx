import LoadingState from '../../components/LoadingState/LoadingState.tsx'
import { useRef, useState, type CSSProperties, type FormEvent } from 'react'
import { Link } from 'react-router'
import { ArrowRight, CalendarDays, Clapperboard, Users } from 'lucide-react'
import AppNav from '../../components/AppNav/AppNav.tsx'
import ButtonPrimary from '../../components/ButtonPrimary/ButtonPrimary.tsx'
import PillButton from '../../components/PillButton/PillButton.tsx'
import SplitHeadline from '../../components/SplitHeadline/SplitHeadline.tsx'
import TextField from '../../components/TextField/TextField.tsx'
import calendarIcon from '../../assets/nights/icon-calendar-yellow.svg'
import videoYellowIcon from '../../assets/nights/icon-video-yellow.svg'
import homeYellowIcon from '../../assets/nights/icon-home-yellow.svg'
import checkIcon from '../../assets/watchlist/icon-check-sm.svg'
import xCreamIcon from '../../assets/nights/icon-x-cream.svg'
import crownIcon from '../../assets/nights/icon-crown.svg'
import pinIcon from '../../assets/nights/icon-pin.svg'
import homeIcon from '../../assets/nights/icon-home.svg'
import videoIcon from '../../assets/nights/icon-video.svg'
import searchIcon from '../../assets/home/icon-search.svg'
import xIcon from '../../assets/listmodal/icon-x-sm.svg'
import { useEntranceAnimation } from '../../hooks/useEntranceAnimation.ts'
import { gradientCss } from '../Home/homeData.ts'
import { useAuth } from '../../contexts/useAuth.ts'
import { useApi } from '../../lib/api.ts'
import { apiErrorMessage } from '../../lib/auth.ts'
import { tmdbIdOf } from '../../lib/movies.ts'
import type { ApiMember } from '../../lib/types.ts'
import { dateTile, formatWhen, toGuest, toNight, type Guest, type Night } from './nightsData.ts'
import { answerInvitation, createNight, useNights } from './nightsStore.ts'
import MovieSearch from '../../components/MovieSearch/MovieSearch.tsx'
import type { Movie } from '../../components/MovieTile/MovieTile.tsx'
import { Thumb } from '../Lists/ListDialog.tsx'
import styles from './MovieNights.module.css'

type Tab = 'upcoming' | 'invitations' | 'past'

const STATUS_CLASS = { voting: styles.statusVoting, picked: styles.statusPicked, shortlisting: styles.statusShortlist }

const HOW_IT_WORKS = [
  { title: 'Plan & invite', text: 'Pick a date, where you will watch, and the friends to invite.' },
  { title: 'Shortlist & vote', text: 'Everyone adds candidates and votes until voting closes.' },
  { title: 'Watch together', text: 'The winning film is announced and added to everyone’s calendar.' },
]

/** Segmented-control icons are masks filled with the text colour, so they follow the selected option. */
const maskIcon = (icon: string) => ({ '--icon': `url("${icon}")` }) as CSSProperties

type FormErrors = Partial<Record<'title' | 'date' | 'time' | 'friends' | 'films', string>>

export default function MovieNights() {
  const pageRef = useRef<HTMLDivElement>(null)
  const planFormRef = useRef<HTMLFormElement>(null)
  useEntranceAnimation(pageRef)

  const { user } = useAuth()
  const store = useNights()
  const upcoming = store.upcoming.map((n) => toNight(n, user?.id))
  const past = store.past.map((n) => toNight(n, user?.id))
  const invitations = store.invitations.map((n) => ({ id: n.id, host: toGuest(n.organizer, user?.id), night: toNight(n, user?.id) }))
  const declined = store.declined
  const [tab, setTab] = useState<Tab>('upcoming')
  const [showHelp, setShowHelp] = useState(false)
  const [answerError, setAnswerError] = useState('')

  // People the user follows can be invited.
  const followingQuery = useApi<{ users: ApiMember[] }>(`/users/${user?.username ?? ''}/following`)
  const people = (followingQuery.data?.users ?? []).map((m) => toGuest(m, user?.id))

  // "Plan a movie night" form.
  const [online, setOnline] = useState(false)
  const [friends, setFriends] = useState<Guest[]>([])
  const [friendQuery, setFriendQuery] = useState('')
  const [films, setFilms] = useState<Movie[]>([])
  const [errors, setErrors] = useState<FormErrors>({})
  const [created, setCreated] = useState('')
  const [saving, setSaving] = useState(false)

  const startPlanning = () => {
    setTab('upcoming')
    const titleInput = planFormRef.current?.querySelector<HTMLInputElement>('input[name="title"]')
    titleInput?.focus({ preventScroll: true })
    titleInput?.scrollIntoView({
      block: 'center',
      behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth',
    })
  }

  const friendMatches = friendQuery.trim()
    ? people.filter(
        (f) =>
          !friends.some((p) => p.id === f.id) &&
          `${f.name} ${f.handle}`.toLowerCase().includes(friendQuery.trim().replace(/^@/, '').toLowerCase()),
      )
    : []

  const addFriend = (friend: Guest) => {
    setFriends((list) => [...list, friend])
    setFriendQuery('')
  }

  const handleCreate = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const form = event.currentTarget
    const data = new FormData(form)
    const title = String(data.get('title') ?? '').trim()
    const date = String(data.get('date') ?? '')
    const time = String(data.get('time') ?? '')
    const next: FormErrors = {}
    if (title.length < 2) next.title = 'Give your movie night a name.'
    if (!date) next.date = 'Pick a date.'
    if (!time) next.time = 'Pick a time.'
    else if (date && new Date(`${date}T${time}`).getTime() <= Date.now()) next.date = 'Pick a time in the future.'
    if (friends.length === 0) next.friends = 'Invite at least one friend.'
    if (films.length === 0) next.films = 'Add at least one film to the shortlist.'
    setErrors(next)
    if (Object.keys(next).length > 0) {
      setCreated('')
      return
    }

    const where = String(data.get('where') ?? '').trim()
    // The API wants a full URL for meeting links.
    const link = online && where ? (/^https?:\/\//i.test(where) ? where : `https://${where}`) : null
    setSaving(true)
    try {
      await createNight({
        title,
        scheduled_at: new Date(`${date}T${time}`).toISOString(),
        location: online ? null : where || null,
        meeting_link: link,
        invitees: friends.map((f) => f.handle),
        tmdb_ids: films.map(tmdbIdOf),
      })
      setCreated(`“${title}” is planned — invites sent to ${friends.length} friend${friends.length > 1 ? 's' : ''}.`)
      form.reset()
      setFriends([])
      setFilms([])
      setOnline(false)
      setTab('upcoming')
    } catch (error) {
      setCreated('')
      setErrors({ title: apiErrorMessage(error, "Couldn't create the movie night. Please try again.") })
    } finally {
      setSaving(false)
    }
  }

  const answer = (id: number, accept: boolean) => {
    setAnswerError('')
    answerInvitation(id, accept).catch(() => setAnswerError("Couldn't save your answer. Please try again."))
  }

  const tabs: { id: Tab; label: string; count?: number }[] = [
    { id: 'upcoming', label: 'Upcoming', count: upcoming.length },
    { id: 'invitations', label: 'Invitations', count: invitations.length },
    { id: 'past', label: 'Past' },
  ]

  return (
    <div ref={pageRef} className={styles.page}>
      <AppNav />

      <main className={styles.main}>
        <header className={styles.header} data-enter data-enter-delay="0.05">
          <div className={styles.titleBlock}>
            <SplitHeadline className={styles.title} lines={[{ text: 'Movie nights' }]} />
            <p className={styles.lead}>Get the gang together, shortlist a few films and let everyone vote on the winner.</p>
          </div>
          <PillButton variant="outline" pressed={showHelp} onClick={() => setShowHelp((v) => !v)}>
            How it works
          </PillButton>
        </header>

        {showHelp && (
          <ol className={styles.help} aria-label="How movie nights work">
            {HOW_IT_WORKS.map((step, index) => (
              <li key={step.title} className={styles.helpStep}>
                <span className={styles.helpNumber}>{index + 1}</span>
                <span>
                  <strong>{step.title}</strong>
                  <span className={styles.helpText}>{step.text}</span>
                </span>
              </li>
            ))}
          </ol>
        )}

        <div className={styles.tabs} role="tablist" aria-label="Movie nights">
          {tabs.map((t) => (
            <button
              key={t.id}
              type="button"
              role="tab"
              aria-selected={tab === t.id}
              className={styles.tab}
              onClick={() => setTab(t.id)}
            >
              <span>
                {t.label}
                {t.count !== undefined && <span className={styles.tabCount}>{t.count}</span>}
              </span>
            </button>
          ))}
        </div>

        <div className={styles.body}>
          <div className={styles.column} aria-busy={!store.loaded} data-enter data-enter-delay="0.2">
            {created && (
              <p className={styles.created} role="status">
                {created}
              </p>
            )}

            {!store.loaded && <LoadingState label="Loading movie nights…" />}
            {store.loaded && tab !== 'past' && (invitations.length > 0 || tab === 'invitations') && (
              <section className={styles.invites} aria-labelledby="invites-title">
                <div className={styles.invitesHead}>
                  <h2 id="invites-title" className={styles.invitesTitle}>
                    You&apos;re invited
                  </h2>
                  {invitations.length > 0 && <span className={styles.pending}>{invitations.length} pending</span>}
                </div>
                {invitations.map(({ id, host, night }) => (
                  <article key={id} className={styles.invite}>
                    <span className={styles.avatar} style={{ background: host.color }} aria-hidden="true">
                      {host.initials}
                    </span>
                    <div className={styles.inviteText}>
                      <p className={styles.inviteLine}>
                        {host.name} invited you to <strong>{night.title}</strong>
                      </p>
                      <p className={styles.inviteMeta}>
                        <span>
                          <img src={calendarIcon} width={14} height={14} alt="" />
                          {formatWhen(night)}
                        </span>
                        <span>
                          <img src={night.online ? videoYellowIcon : homeYellowIcon} width={14} height={14} alt="" />
                          {night.online ? `Online · ${night.where}` : night.where}
                        </span>
                      </p>
                    </div>
                    <button type="button" className={styles.accept} onClick={() => answer(id, true)}>
                      <img src={checkIcon} width={14} height={14} alt="" />
                      Accept
                    </button>
                    <button type="button" className={styles.decline} onClick={() => answer(id, false)}>
                      <img src={xCreamIcon} width={14} height={14} alt="" />
                      Decline
                    </button>
                  </article>
                ))}
                {invitations.length === 0 && <p className={styles.invitesEmpty}>No pending invitations. You&apos;re all caught up.</p>}
                {answerError && (
                  <p className={styles.invitesEmpty} role="alert">
                    {answerError}
                  </p>
                )}
                {declined.length > 0 && (
                  <p className={styles.invitesEmpty} role="status">
                    Declined: {declined.join(', ')}
                  </p>
                )}
              </section>
            )}

            {store.loaded && tab !== 'invitations' && (
              <section aria-labelledby="nights-title" className={styles.nights}>
                <h2 id="nights-title" className={styles.sectionTitle}>
                  {tab === 'past' ? 'Past nights' : 'Coming up'}
                </h2>
                {(tab === 'past' ? past : upcoming).length > 0 ? (
                  <ul className={styles.grid}>
                    {(tab === 'past' ? past : upcoming).map((night) => (
                      <li key={night.slug}>
                        <NightCard night={night} />
                      </li>
                    ))}
                  </ul>
                ) : (
                  <div className={styles.empty}>
                    <div className={styles.emptyArt} aria-hidden="true">
                      <span className={styles.emptyCalendar}><CalendarDays size={24} strokeWidth={1.7} /></span>
                      <span className={styles.emptyFilm}><Clapperboard size={48} strokeWidth={1.5} /></span>
                      <span className={styles.emptyPeople}><Users size={24} strokeWidth={1.7} /></span>
                    </div>
                    <h3 className={styles.emptyTitle}>
                      {tab === 'past' ? 'Good nights become great memories' : 'Make a night of it'}
                    </h3>
                    <p className={styles.emptyText}>
                      {tab === 'past'
                        ? 'Your past movie nights will live here. Plan your first one and give everyone something to look back on.'
                        : 'A great film is even better with your people. Pick a date, invite friends, and let everyone vote on what to watch.'}
                    </p>
                    <PillButton onClick={startPlanning}>
                      Plan a movie night <ArrowRight size={16} aria-hidden="true" />
                    </PillButton>
                    <p className={styles.emptyHint}>On the couch or online. You bring the popcorn.</p>
                  </div>
                )}
              </section>
            )}
          </div>

          <aside className={styles.plan} aria-labelledby="plan-title" data-enter data-enter-delay="0.35" data-enter-rise="60">
            <div className={styles.planHead}>
              <h2 id="plan-title" className={styles.planTitle}>
                Plan a movie night
              </h2>
              <p className={styles.planText}>Pick a time, invite friends, add a few candidates.</p>
            </div>

            <form ref={planFormRef} className={styles.form} onSubmit={handleCreate} noValidate>
              <TextField label="Title" name="title" placeholder="Friday Frights" error={errors.title} />
              <div className={styles.row}>
                <TextField label="Date" name="date" type="date" error={errors.date} />
                <TextField label="Time" name="time" type="time" error={errors.time} />
              </div>

              <div className={styles.group}>
                <span className={styles.label} id="where-label">
                  Where
                </span>
                <div className={styles.segmented} role="radiogroup" aria-labelledby="where-label">
                  {[
                    { value: false, label: 'In person', icon: homeIcon },
                    { value: true, label: 'Online', icon: videoIcon },
                  ].map((option) => (
                    <button
                      key={option.label}
                      type="button"
                      role="radio"
                      aria-checked={online === option.value}
                      className={styles.segment}
                      onClick={() => setOnline(option.value)}
                    >
                      <span className={styles.segmentIcon} style={maskIcon(option.icon)} aria-hidden="true" />
                      {option.label}
                    </button>
                  ))}
                </div>
                <TextField
                  label={online ? 'Meeting link (optional)' : 'Location (optional)'}
                  name="where"
                  placeholder={online ? 'meet.watchwise.app/friday' : "Lina's place, Hamra"}
                />
              </div>

              <div className={styles.group}>
                <label className={styles.label} htmlFor="invite-search">
                  Invite friends
                </label>
                <div className={styles.searchWrap}>
                  <div className={styles.search}>
                    <img src={searchIcon} width={18} height={18} alt="" />
                    <input
                      id="invite-search"
                      type="search"
                      value={friendQuery}
                      onChange={(event) => setFriendQuery(event.target.value)}
                      placeholder="Search people you follow…"
                      autoComplete="off"
                    />
                  </div>
                  {friendMatches.length > 0 && (
                    <ul className={styles.suggestions}>
                      {friendMatches.map((friend) => (
                        <li key={friend.id}>
                          <button type="button" onClick={() => addFriend(friend)}>
                            <span className={styles.avatarSm} style={{ background: friend.color }} aria-hidden="true">
                              {friend.initials}
                            </span>
                            {friend.name}
                          </button>
                        </li>
                      ))}
                    </ul>
                  )}
                  {friendQuery.trim() && friendMatches.length === 0 && <p className={styles.hint}>No one you follow matches “{friendQuery}”.</p>}
                  {!friendQuery.trim() && people.length === 0 && followingQuery.data && (
                    <p className={styles.hint}>Follow people from their profiles to invite them here.</p>
                  )}
                </div>
                <ul className={styles.chips}>
                  {friends.map((friend) => (
                    <li key={friend.id} className={styles.person}>
                      <span className={styles.avatarSm} style={{ background: friend.color }} aria-hidden="true">
                        {friend.initials}
                      </span>
                      {friend.name}
                      <button
                        type="button"
                        className={styles.remove}
                        aria-label={`Remove ${friend.name}`}
                        onClick={() => setFriends((list) => list.filter((f) => f.id !== friend.id))}
                      >
                        <img src={xIcon} width={12} height={12} alt="" />
                      </button>
                    </li>
                  ))}
                </ul>
                {errors.friends && <p className={styles.error}>{errors.friends}</p>}
              </div>

              <div className={styles.group}>
                <span className={styles.label}>Starting shortlist</span>
                <ul className={styles.chips}>
                  {films.map((movie) => {
                    return (
                      <li key={movie.slug} className={styles.film}>
                        <Thumb movie={movie} className={styles.filmThumb} />
                        {movie.title}
                        <button
                          type="button"
                          className={styles.remove}
                          aria-label={`Remove ${movie.title}`}
                          onClick={() => setFilms((list) => list.filter((item) => item.slug !== movie.slug))}
                        >
                          <img src={xIcon} width={12} height={12} alt="" />
                        </button>
                      </li>
                    )
                  })}
                </ul>
                <MovieSearch excluded={films.map(tmdbIdOf)} onPick={(movie) => setFilms((list) => [...list, movie])} />
                {errors.films && <p className={styles.error}>{errors.films}</p>}
              </div>

              <ButtonPrimary type="submit" label={saving ? 'Creating…' : 'Create & send invites'} fullWidth disabled={saving} />
            </form>
          </aside>
        </div>
      </main>
    </div>
  )
}

function NightCard({ night }: { night: Night }) {
  const { month, day } = dateTile(night.date)
  return (
    <Link to={`/movie-nights/${night.slug}`} className={styles.card}>
      <span className={styles.cardTop}>
        <span className={styles.date} style={{ background: gradientCss(night.gradient) }}>
          <span className={styles.month}>{month}</span>
          <span className={styles.day}>{day}</span>
        </span>
        <span className={styles.cardText}>
          <span className={styles.cardTitle}>{night.title}</span>
          <span className={`${styles.status} ${STATUS_CLASS[night.status]}`}>{night.statusLabel}</span>
        </span>
      </span>
      <span className={styles.leadLine}>
        <img src={crownIcon} width={16} height={16} alt="" />
        {night.lead}
      </span>
      <span className={styles.cardFoot}>
        <span className={styles.faces}>
          {night.guests.map((guest) => (
            <span key={guest.id} className={styles.face} style={{ background: guest.color }} title={guest.name}>
              {guest.initials}
            </span>
          ))}
          {night.moreGuests > 0 && (
            <span className={styles.face} style={{ background: 'var(--surface-violet-tint)' }}>
              +{night.moreGuests}
            </span>
          )}
        </span>
        <span className={styles.where}>
          <img src={pinIcon} width={14} height={14} alt="" />
          {night.online ? 'Online' : night.where}
        </span>
      </span>
    </Link>
  )
}
