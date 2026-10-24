import { useRef, useState, type CSSProperties } from 'react'
import { useParams } from 'react-router'
import AppNav from '../../components/AppNav/AppNav.tsx'
import PillButton from '../../components/PillButton/PillButton.tsx'
import SplitHeadline from '../../components/SplitHeadline/SplitHeadline.tsx'
import Placeholder from '../Placeholder/Placeholder.tsx'
import heroWave from '../../assets/movie/hero-wave.svg'
import clockIcon from '../../assets/watchlist/icon-clock.svg'
import calendarYellowIcon from '../../assets/night/icon-calendar-yellow.svg'
import pinYellowIcon from '../../assets/night/icon-pin-yellow.svg'
import linkYellowIcon from '../../assets/night/icon-link-yellow.svg'
import checkIcon from '../../assets/watchlist/icon-check-sm.svg'
import xMutedIcon from '../../assets/night/icon-x-muted.svg'
import calendarCreamIcon from '../../assets/profile/icon-calendar.svg'
import shareIcon from '../../assets/listdetail/icon-share.svg'
import crownSmIcon from '../../assets/night/icon-crown-sm.svg'
import crownYellowIcon from '../../assets/night/icon-crown-yellow.svg'
import crownRowIcon from '../../assets/night/icon-crown-violet-bg.svg'
import xInkIcon from '../../assets/night/icon-x-ink.svg'
import { useEntranceAnimation } from '../../hooks/useEntranceAnimation.ts'
import { gradientCss } from '../Home/homeData.ts'
import { useAuth } from '../../contexts/useAuth.ts'
import { api, isStatus, useApi } from '../../lib/api.ts'
import { PICKABLE_MOVIES, movieFor, tmdbIdOf } from '../../lib/movies.ts'
import type { ApiMember, ApiNight } from '../../lib/types.ts'
import { dateTile, formatWhen, toGuest, toNight, type Night } from './nightsData.ts'
import { apiRsvp, toNightDetail, type NightDetail, type Rsvp } from './nightDetailData.ts'
import styles from './NightDetail.module.css'

const RSVP_OPTIONS: { id: Exclude<Rsvp, 'pending'>; label: string; icon?: string }[] = [
  { id: 'going', label: 'Going', icon: checkIcon },
  { id: 'maybe', label: 'Maybe' },
  { id: 'declined', label: "Can't go", icon: xMutedIcon },
]

const RSVP_CHIP: Record<Rsvp, { label: string; icon?: string; className: string }> = {
  going: { label: 'Going', icon: checkIcon, className: styles.chipGoing },
  maybe: { label: 'Maybe', className: styles.chipPending },
  pending: { label: 'Pending', icon: clockIcon, className: styles.chipPending },
  declined: { label: 'Declined', icon: xInkIcon, className: styles.chipDeclined },
}

/** RSVP icons are masks filled with the text colour, so they follow the selected option. */
const maskIcon = (icon: string) => ({ '--icon': `url("${icon}")` }) as CSSProperties

/** Google Calendar "add event" link for a 3-hour movie night. */
function calendarUrl(night: Night, detail: NightDetail, where: string) {
  const stamp = (d: Date) => d.toISOString().replace(/[-:]/g, '').slice(0, 15)
  const start = new Date(`${night.date}T${night.time}:00`)
  const end = new Date(start.getTime() + 3 * 60 * 60 * 1000)
  const params = new URLSearchParams({
    action: 'TEMPLATE',
    text: `${night.title} — WatchWise movie night`,
    dates: `${stamp(start)}/${stamp(end)}`,
    details: [`Hosted by ${detail.host.name}`, detail.link && `Online: ${detail.link}`].filter(Boolean).join('\n'),
    location: where,
  })
  return `https://calendar.google.com/calendar/render?${params}`
}

export default function NightDetailRoute() {
  const { slug } = useParams()
  const { user } = useAuth()
  const { data, error, setData } = useApi<{ night: ApiNight }>(`/movie-nights/${Number.parseInt(slug ?? '', 10)}`)
  // Nights you are not part of answer 403; treat them like missing ones.
  if (isStatus(error, 404) || isStatus(error, 403)) return <Placeholder title="Movie night not found" notFound />
  if (error) return <Placeholder title="Couldn't load this movie night" />
  if (!data) return null
  // Keyed by id so open popovers reset when moving between nights.
  return (
    <NightDetailPage
      key={data.night.id}
      night={toNight(data.night, user?.id)}
      detail={toNightDetail(data.night, user?.id)}
      onChange={(night) => setData({ night })}
    />
  )
}

function NightDetailPage({ night, detail, onChange }: { night: Night; detail: NightDetail; onChange: (night: ApiNight) => void }) {
  const pageRef = useRef<HTMLDivElement>(null)
  useEntranceAnimation(pageRef)
  const { user } = useAuth()

  const [suggesting, setSuggesting] = useState(false)
  const [inviting, setInviting] = useState(false)
  const [shareNote, setShareNote] = useState('')
  const [actionError, setActionError] = useState('')

  /** Every change goes through the API, which answers with the updated night. */
  const save = (request: Promise<{ data: { night: ApiNight } }>) => {
    setActionError('')
    request.then((response) => onChange(response.data.night)).catch(() => setActionError("Couldn't save that. Please try again."))
  }

  const { candidates, closed } = detail
  const rsvp = detail.myRsvp
  const where = night.online ? 'Online' : (detail.where ?? night.where)
  const myVote = detail.myVote
  const topVotes = Math.max(0, ...candidates.map((c) => c.voters.length))
  const leaders = detail.winner
    ? candidates.filter((c) => c.tmdbId === detail.winner)
    : candidates.filter((c) => topVotes > 0 && c.voters.length === topVotes)
  const winner = leaders[0] ? movieFor(leaders[0].tmdbId) : undefined
  const voted = candidates.reduce((sum, c) => sum + c.voters.length, 0)

  const guests = detail.guests
  const count = (status: Rsvp) => guests.filter((g) => g.rsvp === status).length
  const summary = [
    { label: 'going', n: count('going'), className: styles.chipGoing },
    { label: 'maybe', n: count('maybe'), className: styles.chipPending },
    { label: 'pending', n: count('pending'), className: styles.chipPending },
    { label: 'declined', n: count('declined'), className: styles.chipDeclined },
  ].filter((s) => s.n > 0)

  // One vote each; voting for another film moves it. Suggesting a film also votes for it.
  const vote = (tmdbId: number) => {
    if (tmdbId !== myVote) save(api.put(`/movie-nights/${night.id}/vote`, { tmdb_id: tmdbId }))
  }
  const setRsvp = (option: Exclude<Rsvp, 'pending'>) => save(api.put(`/movie-nights/${night.id}/rsvp`, { status: apiRsvp(option) }))
  const toggleVoting = () =>
    save(
      api.patch(`/movie-nights/${night.id}`, closed ? { status: 'pending', winning_tmdb_id: null } : { status: 'confirmed', winning_tmdb_id: leaders[0]?.tmdbId ?? null }),
    )

  // The host can invite more of the people they follow.
  const following = useApi<{ users: ApiMember[] }>(`/users/${user?.username ?? ''}/following`)
  const suggestable = PICKABLE_MOVIES.filter((m) => !candidates.some((c) => c.tmdbId === tmdbIdOf(m)))
  const invitable = detail.isHost
    ? (following.data?.users ?? []).filter((m) => !guests.some((g) => g.guest.id === m.id)).map((m) => toGuest(m, user?.id))
    : []

  const handleShare = async () => {
    const url = window.location.href
    try {
      if (navigator.share) {
        await navigator.share({ title: `${night.title} on WatchWise`, url })
        return
      }
      await navigator.clipboard.writeText(url)
      setShareNote('Invite link copied')
    } catch {
      setShareNote('')
    }
  }

  const { month, day } = dateTile(night.date)
  const statusText = closed
    ? winner
      ? `Voting closed · ${winner.title} wins`
      : 'Voting closed'
    : night.statusLabel

  return (
    <div ref={pageRef} className={styles.page}>
      <AppNav />

      <main>
        <section className={styles.hero} aria-labelledby="night-title">
          <div className={styles.heroInner}>
            <div className={styles.date} style={{ background: gradientCss(night.gradient) }} data-enter="pop" data-enter-delay="0.1">
              <span className={styles.month}>{month}</span>
              <span className={styles.day}>{day}</span>
            </div>
            <div className={styles.info} data-enter data-enter-delay="0.2">
              <div className={styles.tags}>
                <span className={styles.statusChip}>
                  <img src={clockIcon} width={14} height={14} alt="" />
                  {statusText}
                </span>
                <span className={styles.hostChip}>Hosted by {detail.isHost ? 'you' : detail.host.name}</span>
              </div>
              <SplitHeadline id="night-title" className={styles.title} lines={[{ text: night.title }]} />
              <ul className={styles.meta}>
                <li>
                  <img src={calendarYellowIcon} width={18} height={18} alt="" />
                  {formatWhen(night)}
                </li>
                {!night.online && (
                  <li>
                    <img src={pinYellowIcon} width={18} height={18} alt="" />
                    {where}
                  </li>
                )}
                {detail.link && (
                  <li>
                    <img src={linkYellowIcon} width={18} height={18} alt="" />
                    {detail.link}
                  </li>
                )}
              </ul>
              <div className={styles.actions}>
                {rsvp !== null && (
                <div className={styles.rsvp} role="radiogroup" aria-label="Your RSVP">
                  {RSVP_OPTIONS.map((option) => (
                    <button
                      key={option.id}
                      type="button"
                      role="radio"
                      aria-checked={rsvp === option.id}
                      className={styles.rsvpOption}
                      onClick={() => setRsvp(option.id)}
                    >
                      {option.icon && <span className={styles.maskIcon} style={maskIcon(option.icon)} aria-hidden="true" />}
                      {option.label}
                    </button>
                  ))}
                </div>
                )}
                <a className={styles.heroBtn} href={calendarUrl(night, detail, where)} target="_blank" rel="noreferrer">
                  <img src={calendarCreamIcon} width={16} height={16} alt="" />
                  Add to calendar
                </a>
                <button type="button" className={styles.heroBtn} onClick={handleShare}>
                  <img src={shareIcon} width={16} height={16} alt="" />
                  Share invite
                </button>
                <span className={styles.shareNote} role="status">
                  {actionError || shareNote}
                </span>
              </div>
            </div>
          </div>
        </section>
        <img className={styles.wave} src={heroWave} width={1440} height={70} alt="" />

        <div className={styles.body}>
          <section className={styles.voting} aria-labelledby="votes-title" data-enter data-enter-delay="0.3">
            <div className={styles.votingHead}>
              <div>
                <h2 id="votes-title" className={styles.sectionTitle}>
                  Shortlist &amp; votes
                </h2>
                <p className={styles.sectionText}>
                  {voted} of {count('going') + count('maybe')} guests have voted · one vote each
                </p>
              </div>
              {!closed && suggestable.length > 0 && (
                <div className={styles.popWrap}>
                  <PillButton pressed={suggesting} onClick={() => setSuggesting((v) => !v)}>
                    + Suggest a movie
                  </PillButton>
                  {suggesting && (
                    <ul className={styles.popover}>
                      {suggestable.map((movie) => (
                        <li key={movie.slug}>
                          <button
                            type="button"
                            onClick={() => {
                              vote(tmdbIdOf(movie))
                              setSuggesting(false)
                            }}
                          >
                            <span className={styles.popThumb} style={{ background: gradientCss(movie.gradient) }} aria-hidden="true" />
                            {movie.title}
                          </button>
                        </li>
                      ))}
                    </ul>
                  )}
                </div>
              )}
            </div>

            <ul className={styles.candidates}>
              {candidates.map((candidate) => {
                const movie = movieFor(candidate.tmdbId)
                const leading = leaders.includes(candidate)
                const mine = myVote === candidate.tmdbId
                const width = topVotes > 0 ? (candidate.voters.length / topVotes) * 80 : 0
                return (
                  <li key={candidate.tmdbId} className={leading ? styles.candidateLeading : styles.candidate}>
                    <span className={styles.thumb} style={{ background: gradientCss(movie.gradient) }} aria-hidden="true" />
                    <div className={styles.candidateInfo}>
                      <div className={styles.candidateTitle}>
                        <span>{movie.title}</span>
                        {leading && (
                          <span className={styles.leadingChip}>
                            <img src={crownSmIcon} width={14} height={14} alt="" />
                            {closed ? 'Winner' : 'Leading'}
                          </span>
                        )}
                      </div>
                      <p className={styles.candidateMeta}>
                        {[movie.meta, `suggested by ${candidate.suggestedBy}`].join(' · ')}
                      </p>
                      <div className={styles.votes}>
                        <span className={styles.track}>
                          <span className={leading ? styles.barLeading : styles.bar} style={{ width: `${width}%` }} />
                        </span>
                        <span className={styles.voteCount}>
                          {candidate.voters.length} vote{candidate.voters.length === 1 ? '' : 's'}
                        </span>
                        {candidate.voters.length > 0 && (
                          <span className={styles.faces}>
                            {candidate.voters.slice(0, 3).map((voter) => (
                              <span key={voter.id} className={styles.face} style={{ background: voter.color }} title={voter.name}>
                                {voter.initials}
                              </span>
                            ))}
                            {candidate.voters.length > 3 && (
                              <span className={styles.face} style={{ background: 'var(--surface-violet-tint)' }}>
                                +{candidate.voters.length - 3}
                              </span>
                            )}
                          </span>
                        )}
                      </div>
                    </div>
                    {mine ? (
                      <button type="button" className={styles.yourVote} disabled={closed} aria-pressed="true">
                        <img src={checkIcon} width={14} height={14} alt="" />
                        Your vote
                      </button>
                    ) : (
                      <PillButton variant="soft" disabled={closed} onClick={() => vote(candidate.tmdbId)}>
                        Vote<span className="sr-only"> for {movie.title}</span>
                      </PillButton>
                    )}
                  </li>
                )
              })}
            </ul>

            <div className={styles.final}>
              <span className={styles.finalIcon}>
                <img src={crownYellowIcon} width={25} height={25} alt="" />
              </span>
              <div className={styles.finalText}>
                {closed ? (
                  <>
                    <span className={styles.finalLabel}>VOTING CLOSED</span>
                    <span className={styles.finalTitle}>{winner ? `${winner.title} it is!` : 'No votes were cast'}</span>
                    <span className={styles.finalBody}>Everyone has been sent the final movie, time, place and link — plus a calendar invite.</span>
                  </>
                ) : (
                  <>
                    <span className={styles.finalLabel}>WHEN VOTING CLOSES</span>
                    <span className={styles.finalTitle}>The winner is announced to everyone</span>
                    <span className={styles.finalBody}>All participants get the final movie, time, place and link — plus a calendar invite.</span>
                  </>
                )}
              </div>
              {detail.isHost && <PillButton onClick={toggleVoting}>{closed ? 'Reopen voting' : 'Close voting now'}</PillButton>}
            </div>
          </section>

          <aside className={styles.side} data-enter data-enter-delay="0.4">
            <section className={styles.guests} aria-labelledby="guests-title">
              <div className={styles.guestsHead}>
                <h2 id="guests-title" className={styles.cardTitle}>
                  Guests
                </h2>
                {invitable.length > 0 && (
                  <div className={styles.popWrap}>
                    <button type="button" className={styles.textBtn} aria-expanded={inviting} onClick={() => setInviting((v) => !v)}>
                      Invite more
                    </button>
                    {inviting && (
                      <ul className={`${styles.popover} ${styles.popoverRight}`}>
                        {invitable.map((friend) => (
                          <li key={friend.id}>
                            <button
                              type="button"
                              onClick={() => {
                                save(api.post(`/movie-nights/${night.id}/invites`, { invitees: [friend.handle] }))
                                setInviting(false)
                              }}
                            >
                              <span className={styles.faceSm} style={{ background: friend.color }} aria-hidden="true">
                                {friend.initials}
                              </span>
                              {friend.name}
                            </button>
                          </li>
                        ))}
                      </ul>
                    )}
                  </div>
                )}
              </div>
              <ul className={styles.summary}>
                {summary.map((s) => (
                  <li key={s.label} className={s.className}>
                    {s.n} {s.label}
                  </li>
                ))}
              </ul>
              <ul className={styles.guestList}>
                {guests.map(({ guest, rsvp: status, isHost }) => {
                  const chip = RSVP_CHIP[status]
                  return (
                    <li key={guest.id} className={styles.guest}>
                      <span className={styles.avatar} style={{ background: guest.color }} aria-hidden="true">
                        {guest.initials}
                      </span>
                      <span className={styles.guestName}>
                        {guest.name}
                        {isHost && ' (host)'}
                      </span>
                      <span className={chip.className}>
                        {chip.icon && <img src={chip.icon} width={14} height={14} alt="" />}
                        {chip.label}
                      </span>
                    </li>
                  )
                })}
              </ul>
            </section>

            <section className={styles.details} aria-labelledby="details-title">
              <h2 id="details-title" className={styles.cardTitleLight}>
                Night details
              </h2>
              <dl className={styles.detailList}>
                <DetailRow icon={calendarYellowIcon} label="WHEN" value={formatWhen(night)} />
                {!night.online && <DetailRow icon={pinYellowIcon} label="WHERE" value={where} />}
                {detail.link && <DetailRow icon={linkYellowIcon} label="ONLINE" value={detail.link} />}
                <DetailRow
                  icon={crownRowIcon}
                  label="MOVIE"
                  value={winner ? `${winner.title} (${closed ? 'winner' : 'leading'})` : 'Still voting'}
                />
              </dl>
              {detail.note && <p className={styles.note}>{detail.note}</p>}
            </section>
          </aside>
        </div>
      </main>
    </div>
  )
}

function DetailRow({ icon, label, value }: { icon: string; label: string; value: string }) {
  return (
    <div className={styles.detailRow}>
      <span className={styles.detailIcon}>
        <img src={icon} width={18} height={18} alt="" />
      </span>
      <div>
        <dt>{label}</dt>
        <dd>{value}</dd>
      </div>
    </div>
  )
}
