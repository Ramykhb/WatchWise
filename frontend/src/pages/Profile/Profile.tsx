import { useRef, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router'
import AppNav from '../../components/AppNav/AppNav.tsx'
import MovieTile from '../../components/MovieTile/MovieTile.tsx'
import PillButton from '../../components/PillButton/PillButton.tsx'
import RatingStars from '../../components/RatingStars/RatingStars.tsx'
import ReviewCard from '../../components/ReviewCard/ReviewCard.tsx'
import SplitHeadline from '../../components/SplitHeadline/SplitHeadline.tsx'
import Placeholder from '../Placeholder/Placeholder.tsx'
import headerWave from '../../assets/movie/hero-wave.svg'
import checkIcon from '../../assets/person/icon-check.svg'
import starIcon from '../../assets/landing/icon-star-badge.svg'
import pinIcon from '../../assets/profile/icon-pin.svg'
import calendarIcon from '../../assets/profile/icon-calendar.svg'
import shareIcon from '../../assets/profile/icon-share.svg'
import chatIcon from '../../assets/profile/icon-chat.svg'
import listIcon from '../../assets/profile/icon-list.svg'
import eyeIcon from '../../assets/profile/icon-eye.svg'
import { useEntranceAnimation } from '../../hooks/useEntranceAnimation.ts'
import { gradientCss } from '../Home/homeData.ts'
import { compactCount, toMember, toProfile, type ActivityItem, type Member, type Profile as ProfileData, type ProfileList } from './profileData.ts'
import styles from './Profile.module.css'
import { useAuth } from '../../contexts/useAuth.ts'
import { api, isStatus, useApi } from '../../lib/api.ts'
import type { ApiMember, ApiProfile } from '../../lib/types.ts'

type Tab = 'activity' | 'reviews' | 'lists' | 'watched'
type PeopleTab = 'followers' | 'following'

/** Chip colours for "Favourite genres", in Figma order. */
const GENRE_COLORS = [
  'var(--accent-yellow)',
  'var(--surface-coral-tint)',
  'var(--surface-violet-tint)',
  'var(--surface-mint-tint)',
  'var(--surface-cream-deep)',
]

export default function Profile() {
  const { handle } = useParams()
  const { user } = useAuth()
  // `/profile` is the signed-in user's own profile.
  const { data, error } = useApi<ApiProfile>(`/users/${encodeURIComponent(handle ?? user?.username ?? '')}`)
  if (isStatus(error, 404)) return <Placeholder title="Profile not found" notFound />
  if (error) return <Placeholder title="Couldn't load this profile" />
  if (!data) return <ProfileLoading />
  // Keyed by user so tabs and follow state reset when moving between profiles.
  return <ProfilePage key={data.user.id} profile={toProfile(data)} />
}

function ProfileLoading() {
  return (
    <div className={styles.page}>
      <AppNav />
      <p className={styles.empty} role="status">
        Loading profile…
      </p>
    </div>
  )
}

function ProfilePage({ profile }: { profile: ProfileData }) {
  const pageRef = useRef<HTMLDivElement>(null)
  useEntranceAnimation(pageRef)

  const isMe = profile.isMe
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  const firstName = profile.name.split(' ')[0]
  const [tab, setTab] = useState<Tab>('activity')
  const [peopleTab, setPeopleTab] = useState<PeopleTab>('followers')
  const [shareNote, setShareNote] = useState('')
  // Ids the signed-in user follows; buttons change at once and are saved through the follows API.
  const [following, setFollowing] = useState<number[]>(profile.myFollowing)
  const isFollowing = following.includes(profile.id)
  const toggleFollow = (member: Pick<Member, 'id' | 'handle'>) => {
    const was = following.includes(member.id)
    const flip = (list: number[]) => (list.includes(member.id) ? list.filter((id) => id !== member.id) : [...list, member.id])
    setFollowing(flip)
    const request = was ? api.delete(`/users/${member.handle}/follow`) : api.post(`/users/${member.handle}/follow`)
    request.catch(() => setFollowing(flip))
  }
  // The follower count moves with the main Follow button.
  const followers = profile.followers + (isFollowing === profile.isFollowing ? 0 : isFollowing ? 1 : -1)

  const handleShare = async () => {
    const url = window.location.href
    try {
      if (navigator.share) {
        await navigator.share({ title: `${profile.name} on WatchWise`, url })
        return
      }
      await navigator.clipboard.writeText(url)
      setShareNote('Profile link copied')
    } catch {
      setShareNote('')
    }
  }

  const tabs: { id: Tab; label: string; count?: number }[] = [
    { id: 'activity', label: 'Activity' },
    { id: 'reviews', label: 'Reviews', count: profile.reviews },
    { id: 'lists', label: 'Lists', count: profile.lists },
    { id: 'watched', label: 'Watched', count: profile.films },
  ]
  const stats = [
    { label: 'Followers', value: compactCount(followers) },
    { label: 'Following', value: compactCount(profile.following) },
    { label: 'Reviews', value: compactCount(profile.reviews) },
    { label: 'Lists', value: compactCount(profile.lists) },
    { label: 'Films', value: compactCount(profile.films) },
  ]
  // The profile carries the first 8 of each list; "View all" loads the rest.
  const [fullLists, setFullLists] = useState<Partial<Record<PeopleTab, Member[]>>>({})
  const [loadingAll, setLoadingAll] = useState(false)
  const people = fullLists[peopleTab] ?? (peopleTab === 'followers' ? profile.followerList : profile.followingList)
  const peopleTotal = peopleTab === 'followers' ? followers : profile.following
  const showAll = async () => {
    setLoadingAll(true)
    try {
      const response = await api.get<{ users: ApiMember[] }>(`/users/${profile.handle}/${peopleTab}`)
      setFullLists((lists) => ({ ...lists, [peopleTab]: response.data.users.map(toMember) }))
    } finally {
      setLoadingAll(false)
    }
  }

  return (
    <div ref={pageRef} className={styles.page}>
      <AppNav />

      <main>
        <section className={styles.header} aria-labelledby="profile-name">
          <div className={styles.headerInner}>
            <div
              className={styles.avatar}
              style={{ background: profile.color }}
              aria-hidden="true"
              data-enter="pop"
              data-enter-delay="0.1"
            >
              {profile.avatar ? <img className={styles.avatarImg} src={profile.avatar} alt="" /> : profile.initials}
            </div>
            <div className={styles.info} data-enter data-enter-delay="0.25">
              <div className={styles.nameRow}>
                <div className={styles.nameBlock}>
                  <SplitHeadline id="profile-name" className={styles.name} lines={[{ text: profile.name }]} />
                  <p className={styles.handle}>
                    <span className={styles.at}>@{profile.handle}</span>
                    {profile.location && (
                      <span className={styles.location}>
                        <img src={pinIcon} width={14} height={14} alt="" />
                        {profile.location}
                      </span>
                    )}
                    <span>· Member since {profile.since}</span>
                    {profile.followsYou && <span>· Follows you</span>}
                  </p>
                </div>
                <div className={styles.actions}>
                  {isMe ? (
                    <Link to="/settings" className={styles.followBtn}>
                      Edit profile
                    </Link>
                  ) : (
                    <>
                      <button
                        type="button"
                        className={isFollowing ? styles.followBtn : styles.followBtnOff}
                        aria-pressed={isFollowing}
                        onClick={() => toggleFollow(profile)}
                      >
                        {isFollowing && <img src={checkIcon} width={16} height={16} alt="" />}
                        {isFollowing ? 'Following' : 'Follow'}
                      </button>
                      <Link to="/movie-nights" className={styles.inviteBtn}>
                        <img src={calendarIcon} width={16} height={16} alt="" />
                        Invite to movie night
                      </Link>
                    </>
                  )}
                  <button type="button" className={styles.moreBtn} aria-label="Share profile" onClick={handleShare}>
                    <img src={shareIcon} width={22} height={22} alt="" />
                  </button>
                  {isMe && (
                    <button
                      type="button"
                      className={styles.logoutBtn}
                      onClick={() => void logout().then(() => navigate('/signin', { replace: true }))}
                    >
                      Log out
                    </button>
                  )}
                </div>
              </div>
              <p className={styles.bio}>{profile.bio || (isMe ? 'Add a short bio in Settings so people know your taste.' : '')}</p>
              <dl className={styles.stats}>
                {stats.map((stat) => (
                  <div key={stat.label} className={styles.stat}>
                    <dt>{stat.label}</dt>
                    <dd>{stat.value}</dd>
                  </div>
                ))}
              </dl>
              <p className={styles.shareNote} role="status">
                {shareNote}
              </p>
            </div>
          </div>
        </section>
        <img className={styles.headerWave} src={headerWave} width={1440} height={70} alt="" />

        {profile.restricted ? (
          <div className={styles.content}>
            <p className={styles.empty}>This profile is private. Only {firstName} can share more of it.</p>
          </div>
        ) : (
        <div className={styles.content}>
          <div className={styles.tabs} role="tablist" aria-label={`${firstName}'s profile`}>
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
            <section className={styles.feed} aria-label={tabs.find((t) => t.id === tab)!.label} data-enter data-enter-delay="0.35">
              {tab === 'activity' &&
                (profile.activity.length > 0 ? (
                  profile.activity.map((item) => <ActivityCard key={item.id} item={item} firstName={firstName} />)
                ) : (
                  <p className={styles.empty}>No activity yet.</p>
                ))}
              {tab === 'reviews' &&
                (profile.reviewList.length > 0 ? (
                  profile.reviewList.map((review) => <ReviewCard key={review.id} review={review} />)
                ) : (
                  <p className={styles.empty}>No reviews yet.</p>
                ))}
              {tab === 'lists' &&
                (profile.listCards.length > 0 ? (
                  profile.listCards.map((list) => (
                    <Link key={list.slug} to={`/lists/${list.slug}`} className={styles.card}>
                      <ListPreview list={list} />
                    </Link>
                  ))
                ) : (
                  <p className={styles.empty}>No lists yet.</p>
                ))}
              {tab === 'watched' &&
                (profile.watched.length > 0 ? (
                  <div className={styles.watched}>
                    {profile.watched.map((movie) => (
                      <MovieTile key={movie.slug} movie={movie} />
                    ))}
                  </div>
                ) : (
                  <p className={styles.empty}>No watched films to show.</p>
                ))}
            </section>

            <aside className={styles.side} data-enter data-enter-delay="0.45">
              <section className={styles.people} aria-label="Followers and following">
                <div className={styles.segmented} role="tablist" aria-label="People">
                  {(['followers', 'following'] as const).map((id) => (
                    <button
                      key={id}
                      type="button"
                      role="tab"
                      aria-selected={peopleTab === id}
                      className={styles.segment}
                      onClick={() => setPeopleTab(id)}
                    >
                      {id === 'followers' ? `Followers ${compactCount(followers)}` : `Following ${profile.following}`}
                    </button>
                  ))}
                </div>
                <ul className={styles.personList}>
                  {people.map((person) => (
                    <PersonRow
                      key={person.id}
                      person={person}
                      isMe={person.id === user?.id}
                      isFollowing={following.includes(person.id)}
                      onToggle={() => toggleFollow(person)}
                    />
                  ))}
                  {people.length === 0 && <li className={styles.personHandle}>Nobody here yet.</li>}
                </ul>
                {people.length < peopleTotal && !fullLists[peopleTab] && (
                  <button type="button" className={styles.viewAll} onClick={showAll} disabled={loadingAll}>
                    {loadingAll ? 'Loading…' : `View all ${peopleTotal.toLocaleString('en-US')} →`}
                  </button>
                )}
              </section>

              {profile.top4.length > 0 && (
              <section className={styles.top4} aria-labelledby="top4-title">
                <h2 id="top4-title" className={styles.top4Title}>
                  {isMe ? 'Your' : `${firstName}'s`} top 4
                </h2>
                <div className={styles.top4Posters}>
                  {profile.top4.map((movie) => (
                    <Link
                      key={movie.slug}
                      to={`/movies/${movie.slug}`}
                      className={styles.top4Poster}
                      style={{ background: gradientCss(movie.gradient) }}
                      aria-label={movie.title}
                    />
                  ))}
                </div>
              </section>
              )}

              {profile.genres.length > 0 && (
              <section className={styles.genres} aria-labelledby="genres-title">
                <h2 id="genres-title" className={styles.cardTitle}>
                  Favourite genres
                </h2>
                <ul className={styles.chips}>
                  {profile.genres.map((genre, index) => (
                    <li key={genre} className={styles.chip} style={{ background: GENRE_COLORS[index % GENRE_COLORS.length] }}>
                      <Link to={`/discover?genre=${encodeURIComponent(genre)}`}>{genre}</Link>
                    </li>
                  ))}
                </ul>
              </section>
              )}
            </aside>
          </div>
        </div>
        )}
      </main>
    </div>
  )
}

const ACTIVITY_ICON = {
  rated: { icon: starIcon, bg: 'var(--accent-yellow)' },
  reviewed: { icon: chatIcon, bg: 'var(--accent-mint)' },
  list: { icon: listIcon, bg: 'var(--surface-violet-tint)' },
  watchlist: { icon: eyeIcon, bg: 'var(--accent-coral)' },
} satisfies Record<ActivityItem['kind'], { icon: string; bg: string }>

function ActivityHead({ item, firstName }: { item: ActivityItem; firstName: string }) {
  const { icon, bg } = ACTIVITY_ICON[item.kind]
  return (
    <div className={styles.activityHead}>
      <span className={styles.activityIcon} style={{ background: bg }}>
        <img src={icon} width={14} height={14} alt="" />
      </span>
      <p className={styles.activityText}>
        {item.kind === 'rated' && (
          <>
            {firstName} rated <Link to={`/movies/${item.movie.slug}`}>{item.movie.title}</Link>
          </>
        )}
        {item.kind === 'reviewed' && (
          <>
            {firstName} reviewed <Link to={`/movies/${item.movie.slug}`}>{item.movie.title}</Link>
          </>
        )}
        {item.kind === 'list' && (
          <>
            {firstName} created the public list <Link to={`/lists/${item.list.slug}`}>{item.list.title}</Link>
          </>
        )}
        {item.kind === 'watchlist' && (
          <>
            {firstName} added <Link to={`/movies/${item.movie.slug}`}>{item.movie.title}</Link>
            {item.more > 0 && ` and ${item.more} more`} to the watchlist
          </>
        )}
      </p>
      <span className={styles.activityTime}>· {item.time}</span>
    </div>
  )
}

function ActivityCard({ item, firstName }: { item: ActivityItem; firstName: string }) {
  if (item.kind === 'reviewed') {
    // Figma: the review card sits under a bare heading instead of inside an activity card.
    return (
      <article className={styles.reviewActivity}>
        <ActivityHead item={item} firstName={firstName} />
        <ReviewCard review={item.review} />
      </article>
    )
  }

  return (
    <article className={styles.card}>
      <ActivityHead item={item} firstName={firstName} />
      {item.kind === 'rated' && (
        <Link to={`/movies/${item.movie.slug}`} className={styles.film}>
          <span className={styles.filmThumb} style={{ background: gradientCss(item.movie.gradient) }} />
          <span className={styles.filmText}>
            <span className={styles.filmTitle}>{item.movie.title}</span>
            <RatingStars value={item.rating} label={`${firstName}'s rating`} />
            <span className={styles.filmMeta}>{item.movie.meta}</span>
          </span>
        </Link>
      )}
      {item.kind === 'list' && (
        <Link to={`/lists/${item.list.slug}`} className={styles.listLink}>
          <ListPreview list={item.list} />
        </Link>
      )}
    </article>
  )
}

function ListPreview({ list }: { list: ProfileList }) {
  return (
    <span className={styles.listRow}>
      <span className={styles.listPosters}>
        {list.posters.slice(0, 5).map((gradient, index) => (
          <span key={index} className={styles.listPoster} style={{ background: gradientCss(gradient) }} />
        ))}
      </span>
      <span className={styles.listText}>
        <span className={styles.filmTitle}>{list.title}</span>
        <span className={styles.filmMeta}>{list.meta}</span>
      </span>
    </span>
  )
}

function PersonRow({ person, isMe, isFollowing, onToggle }: { person: Member; isMe: boolean; isFollowing: boolean; onToggle: () => void }) {
  const name = (
    <span className={styles.personText}>
      <span className={styles.personName}>
        {person.name}
        {isMe && ' (you)'}
      </span>
      <span className={styles.personHandle}>@{person.handle}</span>
    </span>
  )

  return (
    <li className={styles.person}>
      <span className={styles.personAvatar} style={{ background: person.color }} aria-hidden="true">
        {person.avatar ? <img className={styles.avatarImg} src={person.avatar} alt="" /> : person.initials}
      </span>
      <Link to={isMe ? '/profile' : `/profile/${person.handle}`} className={styles.personLink}>
        {name}
      </Link>
      {!isMe && (
        <PillButton variant={isFollowing ? 'outline' : 'solid'} pressed={isFollowing} onClick={onToggle}>
          {isFollowing ? 'Unfollow' : 'Follow'}
        </PillButton>
      )}
    </li>
  )
}
