import ChooseWinnerDialog from './ChooseWinnerDialog.tsx'
import MemberSearch from './MemberSearch.tsx'
import ConfirmDialog from '../../components/ConfirmDialog/ConfirmDialog.tsx'
import EditNightDialog from './EditNightDialog.tsx'
import { NightDetailSkeleton } from '../../components/LoadingState/ScreenSkeletons.tsx'
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
import { apiErrorMessage } from '../../lib/auth.ts'
import { api, isStatus, useApi } from '../../lib/api.ts'
import { tmdbIdOf } from '../../lib/movies.ts'
import type { ApiNight } from '../../lib/types.ts'
import { dateTile, formatWhen, toNight, type Night } from './nightsData.ts'
import { apiRsvp, toNightDetail, type NightDetail, type Rsvp } from './nightDetailData.ts'
import MovieSearch from '../../components/MovieSearch/MovieSearch.tsx'
import { useMovies } from '../../lib/movieDetails.ts'
import { useIntegrations } from '../../lib/integrations.ts'
import { Thumb } from '../Lists/ListDialog.tsx'
import styles from './NightDetail.module.css'
import { downloadWatchEvent, googleCalendarUrl, outlookCalendarUrl, toCalendarTime } from '../MovieDetails/calendar.ts'
import { movieNightEvent } from './nightCalendar.ts'

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

export default function NightDetailRoute() {
  const { slug } = useParams()
  const { user } = useAuth()
  const { data, error, setData } = useApi<{ night: ApiNight }>(`/movie-nights/${Number.parseInt(slug ?? '', 10)}`)
  // Nights you are not part of answer 403; treat them like missing ones.
  if (isStatus(error, 404) || isStatus(error, 403)) return <Placeholder title="Movie night not found" notFound />
  if (error) return <Placeholder title="Couldn't load this movie night" />
  if (!data) return <NightDetailSkeleton />
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
  const calendarOn = useIntegrations().isOn('calendar')

  const [choosingWinner, setChoosingWinner] = useState(false)
  const [editing, setEditing] = useState(false)
  const [cancelling, setCancelling] = useState(false)
  const [suggesting, setSuggesting] = useState(false)
  const [inviting, setInviting] = useState(false)
  const [inviteLink, setInviteLink] = useState('')
  const [sharing, setSharing] = useState(false)
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
  const calendarEvent = movieNightEvent(night, detail, where)
  const myVote = detail.myVote
  const topVotes = Math.max(0, ...candidates.map((c) => c.voters.length))
  const leaders = detail.winner
    ? candidates.filter((c) => c.tmdbId === detail.winner)
    : candidates.filter((c) => topVotes > 0 && c.voters.length === topVotes)
  const movieOf = useMovies(candidates.map((candidate) => candidate.tmdbId))
  const winner = leaders[0] ? movieOf(leaders[0].tmdbId) : undefined
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
  const toggleVoting = () => {
    if (closed) save(api.patch(`/movie-nights/${night.id}`, { status: 'pending', winning_tmdb_id: null }))
    else setChoosingWinner(true)
  }

  const handleShare = async () => {
    if (sharing) return
    setSharing(true)
    setShareNote('')
    try {
      const { data } = await api.post<{ url: string }>(`/movie-nights/${night.id}/invite-link`)
      setInviteLink(data.url)
      try {
        await navigator.clipboard.writeText(data.url)
        setShareNote('Invite link copied. Anyone with this link can sign in and join.')
      } catch {
        setShareNote('Copy the invite link below. Anyone with it can sign in and join.')
      }
    } catch (error) { setShareNote(apiErrorMessage(error, "Couldn't create an invite link.")) }
    finally { setSharing(false) }
  }
  const revokeLink = async () => {
    setSharing(true)
    try {
      await api.delete(`/movie-nights/${night.id}/invite-link`)
      setInviteLink('')
      setShareNote('Invite link disabled. Previously shared copies no longer work.')
    } catch (error) { setShareNote(apiErrorMessage(error, "Couldn't disable the invite link.")) }
    finally { setSharing(false) }
  }

  const { month, day } = dateTile(night.date)
  const statusText = detail.cancelled ? 'Cancelled' : closed
    ? winner
      ? `Voting closed · ${winner.title} wins`
      : 'Voting closed'
    : night.statusLabel

  return (
    <div ref={pageRef} className={styles.page}>
      <AppNav />
      {choosingWinner && <ChooseWinnerDialog nightId={night.id} candidates={candidates} titleOf={(id) => movieOf(id).title} onSaved={onChange} onClose={() => setChoosingWinner(false)} />}
      {editing && <EditNightDialog night={night} detail={detail} onSaved={onChange} onClose={() => setEditing(false)} />}
      <ConfirmDialog open={cancelling} title="Cancel this movie night?" message="Guests will be notified and voting will close." confirmLabel="Cancel movie night" busyLabel="Cancelling…" cancelLabel="Keep movie night" onCancel={() => setCancelling(false)} onConfirm={async () => {
        const { data } = await api.patch<{ night: ApiNight }>(`/movie-nights/${night.id}`, { status: 'cancelled' })
        onChange(data.night)
        setCancelling(false)
      }} />

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
                {rsvp !== null && !detail.cancelled && (
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
                {/* Settings › Integrations › Calendar: off hides "Add to calendar". */}
                {calendarOn && !detail.cancelled && <details className={styles.calendarPicker} onKeyDown={(event) => {
                  if (event.key === 'Escape') {
                    event.currentTarget.open = false
                    event.currentTarget.querySelector('summary')?.focus()
                  }
                }}>
                  <summary className={styles.heroBtn}>
                    <img src={calendarCreamIcon} width={16} height={16} alt="" />
                    Add to calendar
                  </summary>
                  <div className={styles.calendarOptions}>
                    <a href={googleCalendarUrl(calendarEvent.title, toCalendarTime(calendarEvent.start), toCalendarTime(calendarEvent.end), calendarEvent.details)} target="_blank" rel="noreferrer">
                      Google Calendar
                    </a>
                    <button type="button" onClick={(event) => {
                      downloadWatchEvent(calendarEvent.title, calendarEvent.start, calendarEvent.end, calendarEvent.details)
                      event.currentTarget.closest('details')?.removeAttribute('open')
                    }}>
                      Apple Calendar (iOS)
                    </button>
                    <a href={outlookCalendarUrl(calendarEvent.title, calendarEvent.start, calendarEvent.end, calendarEvent.details)} target="_blank" rel="noreferrer">
                      Outlook Calendar
                    </a>
                  </div>
                </details>}
                {detail.isHost && !detail.cancelled && <>
                  <PillButton variant="soft" onClick={() => setEditing(true)}>Edit details</PillButton>
                  <PillButton variant="outline" onClick={() => setCancelling(true)}>Cancel movie night</PillButton>
                </>}
                {detail.isHost && !detail.cancelled && <button type="button" className={styles.heroBtn} disabled={sharing} onClick={handleShare}>
                  <img src={shareIcon} width={16} height={16} alt="" />
                  Share invite
                </button>}
                <span className={styles.shareNote} role="status">
                  {actionError || shareNote}
                </span>
              </div>
              {detail.isHost && !detail.cancelled && inviteLink && <div className={styles.inviteLink}>
                <label>Invite link<input aria-label="Movie night invite link" readOnly value={inviteLink} onFocus={(event) => event.target.select()} /></label>
                <PillButton variant="outline" disabled={sharing} onClick={() => void revokeLink()}>Disable invite link</PillButton>
              </div>}
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
              {!closed && (
                <div className={styles.popWrap}>
                  <PillButton pressed={suggesting} onClick={() => setSuggesting((v) => !v)}>
                    + Suggest a movie
                  </PillButton>
                  {suggesting && (
                    <MovieSearch excluded={candidates.map((candidate) => candidate.tmdbId)} onPick={(movie) => {
                      vote(tmdbIdOf(movie))
                      setSuggesting(false)
                    }} />
                  )}
                </div>
              )}
            </div>

            <ul className={styles.candidates}>
              {candidates.map((candidate) => {
                const movie = movieOf(candidate.tmdbId)
                const leading = !detail.cancelled && leaders.includes(candidate)
                const mine = myVote === candidate.tmdbId
                const width = topVotes > 0 ? (candidate.voters.length / topVotes) * 80 : 0
                return (
                  <li key={candidate.tmdbId} className={leading ? styles.candidateLeading : styles.candidate}>
                    <Thumb movie={movie} className={styles.thumb} />
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
                {detail.cancelled ? <><span className={styles.finalLabel}>CANCELLED</span><span className={styles.finalTitle}>This movie night is cancelled.</span><span className={styles.finalBody}>Guests have been notified. Voting and invitations are closed.</span></> : closed ? (
                  <>
                    <span className={styles.finalLabel}>VOTING CLOSED</span>
                    <span className={styles.finalTitle}>{winner ? `${winner.title} it is!` : 'No votes were cast'}</span>
                    <span className={styles.finalBody}>The final details are available to all participants. Calendar invitations are emailed to participants with Calendar enabled.</span>
                  </>
                ) : (
                  <>
                    <span className={styles.finalLabel}>WHEN VOTING CLOSES</span>
                    <span className={styles.finalTitle}>The winner is announced to everyone</span>
                    <span className={styles.finalBody}>Confirm a winning movie to notify participants and email calendar invitations to those with Calendar enabled.</span>
                  </>
                )}
              </div>
              {detail.isHost && !detail.cancelled && <PillButton disabled={!closed && candidates.length === 0} onClick={toggleVoting}>{closed ? 'Reopen voting' : 'Close voting now'}</PillButton>}
              {detail.isHost && !closed && candidates.length === 0 && <p>Add a candidate movie before closing voting.</p>}
            </div>
          </section>

          <aside className={styles.side} data-enter data-enter-delay="0.4">
            <section className={styles.guests} aria-labelledby="guests-title">
              <div className={styles.guestsHead}>
                <h2 id="guests-title" className={styles.cardTitle}>
                  Guests
                </h2>
                {detail.isHost && !detail.cancelled && (
                  <div className={styles.popWrap}>
                    <button type="button" className={styles.textBtn} aria-expanded={inviting} onClick={() => setInviting((v) => !v)}>
                      Invite more
                    </button>
                    {inviting && (
                      <div className={`${styles.popover} ${styles.popoverRight}`}>
                        <MemberSearch excludedIds={guests.map((entry) => entry.guest.id)} onPick={async (member) => {
                          const { data } = await api.post<{ night: ApiNight }>(`/movie-nights/${night.id}/invites`, { invitees: [member.username] })
                          onChange(data.night)
                          setInviting(false)
                        }} />
                      </div>
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
                  value={detail.cancelled ? 'Cancelled' : winner ? `${winner.title} (${closed ? 'winner' : 'leading'})` : 'Still voting'}
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
