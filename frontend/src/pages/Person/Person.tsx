import { useRef, useState } from 'react'
import { Link, useParams } from 'react-router'
import AppNav from '../../components/AppNav/AppNav.tsx'
import MovieTile, { type Movie } from '../../components/MovieTile/MovieTile.tsx'
import SplitHeadline from '../../components/SplitHeadline/SplitHeadline.tsx'
import Placeholder from '../Placeholder/Placeholder.tsx'
import headerWave from '../../assets/person/header-wave.svg'
import starIcon from '../../assets/person/icon-star.svg'
import plusIcon from '../../assets/person/icon-plus.svg'
import awardIcon from '../../assets/person/icon-award.svg'
import checkIcon from '../../assets/person/icon-check.svg'
import { useEntranceAnimation } from '../../hooks/useEntranceAnimation.ts'
import { api, isStatus, useApi } from '../../lib/api.ts'
import { posterAt } from '../../lib/movieDetails.ts'
import { tmdbIdOf } from '../../lib/movies.ts'
import type { ApiWatchItem } from '../../lib/types.ts'
import { gradientCss } from '../Home/homeData.ts'
import { toCollaborators, toPerson, type ApiCollaborators, type ApiPerson } from './personApi.ts'
import { getPerson, type Person as PersonData } from './personData.ts'
import styles from './Person.module.css'

/** GET /api/watchlist (the same shape the Watchlist page reads). */
type WatchlistResponse = { items: ApiWatchItem[]; ratings: Record<string, number> }

/** The filmography starts with this many films; "Show all" lists the rest. */
const FILMOGRAPHY_SHOWN = 15

/** Longer biographies start clamped to a few lines with "Read more". */
const LONG_BIO = 420

export default function Person() {
  const { slug } = useParams()
  // TMDB people have numeric ids (/people/8767); the design's demo people have names (/people/lena-okafor).
  if (slug && /^\d+$/.test(slug)) return <TmdbPerson key={slug} id={slug} />
  const person = getPerson(slug)
  if (!person) return <Placeholder title="Person not found" notFound />
  // Keyed by slug so the watchlist toggles reset when moving between people.
  return <PersonPage key={person.slug} person={person} />
}

/** A real person from GET /api/people/{id}. */
function TmdbPerson({ id }: { id: string }) {
  const { data, error } = useApi<ApiPerson>(`/people/${id}`)
  // Loaded separately (it takes up to ten TMDB calls the first time), so the page doesn't wait for it.
  const collaborators = useApi<ApiCollaborators>(`/people/${id}/collaborators`)
  if (isStatus(error, 404)) return <Placeholder title="Person not found" notFound />
  if (error) return <Placeholder title="Couldn't load this person" />
  if (!data) return <PersonSkeleton />
  const person = { ...toPerson(data), collaborators: collaborators.data ? toCollaborators(collaborators.data) : [] }
  return <PersonPage person={person} collaboratorsLoading={!collaborators.data && !collaborators.error} />
}

/** The page's shape with shimmering blocks, while the person loads. */
function PersonSkeleton() {
  return (
    <div className={styles.page} aria-busy="true">
      <AppNav />
      <main>
        <span className="sr-only" role="status">
          Loading…
        </span>
        <section className={styles.header} aria-hidden="true">
          <div className={styles.headerInner}>
            <div className={`${styles.portrait} ${styles.skeletonDark}`} />
            <div className={styles.info}>
              <span className={`${styles.skeletonDark} ${styles.skeletonChip}`} />
              <span className={`${styles.skeletonDark} ${styles.skeletonName}`} />
              <span className={`${styles.skeletonDark} ${styles.skeletonLine}`} />
              <span className={`${styles.skeletonDark} ${styles.skeletonLineShort}`} />
            </div>
          </div>
        </section>
        <img className={styles.headerWave} src={headerWave} width={1440} height={70} alt="" />
        <section className={styles.knownFor} aria-hidden="true">
          <span className={`${styles.skeleton} ${styles.skeletonHeading}`} />
          <div className={styles.row}>
            {Array.from({ length: 6 }, (_, index) => (
              <span key={index} className={`${styles.skeleton} ${styles.skeletonPoster}`} />
            ))}
          </div>
        </section>
      </main>
    </div>
  )
}

function PersonPage({ person, collaboratorsLoading = false }: { person: PersonData; collaboratorsLoading?: boolean }) {
  const pageRef = useRef<HTMLDivElement>(null)
  const filmographyRef = useRef<HTMLElement>(null)
  useEntranceAnimation(pageRef)
  const [photoFailed, setPhotoFailed] = useState(false)
  const [bioOpen, setBioOpen] = useState(false)
  const [allFilms, setAllFilms] = useState(false)
  const longBio = person.bio.length > LONG_BIO
  const films = allFilms ? person.filmography : person.filmography.slice(0, FILMOGRAPHY_SHOWN)
  const details = person.details ?? []
  const hasSide = collaboratorsLoading || person.collaborators.length > 0 || person.awards.length > 0 || details.length > 0

  // The signed-in user's watchlist: "+" adds a film, the green tick removes it again.
  const { data: watchlistData, setData: setWatchlistData, reload: reloadWatchlist } = useApi<WatchlistResponse>('/watchlist')
  const watchItems = watchlistData?.items ?? []
  const watchItemFor = (movie: Movie) => watchItems.find((item) => item.tmdb_id === tmdbIdOf(movie))

  const toggleWatchlist = (movie: Movie) => {
    const item = watchItemFor(movie)
    if (item) {
      setWatchlistData((current) => ({ ...current, items: current.items.filter((i) => i.id !== item.id) }))
      api.delete(`/watchlist/${item.id}`).catch(reloadWatchlist)
      return
    }
    api
      .post<{ item: ApiWatchItem }>('/watchlist', { tmdb_id: tmdbIdOf(movie) })
      .then(({ data }) => setWatchlistData((current) => ({ ...current, items: [data.item, ...current.items] })))
      .catch(reloadWatchlist)
  }

  return (
    <div ref={pageRef} className={styles.page}>
      <AppNav />

      <main>
        <section className={styles.header} aria-labelledby="person-name">
          <div className={styles.headerInner}>
            <div
              className={styles.portrait}
              style={{ background: gradientCss(person.gradient) }}
              aria-hidden="true"
              data-enter="pop"
              data-enter-delay="0.1"
            >
              {person.photoUrl && !photoFailed ? (
                // A photo that fails to load falls back to the initials.
                <img className={styles.photo} src={person.photoUrl} alt="" onError={() => setPhotoFailed(true)} />
              ) : (
                person.initials
              )}
            </div>
            <div className={styles.info} data-enter data-enter-delay="0.25">
              <ul className={styles.roles}>
                {person.roles.map((role, index) => (
                  <li key={role} className={index === 0 ? styles.roleMain : styles.role}>
                    {role}
                  </li>
                ))}
              </ul>
              <SplitHeadline id="person-name" className={styles.name} lines={[{ text: person.name }]} />
              <div className={styles.bioBlock}>
                <p className={longBio && !bioOpen ? `${styles.bio} ${styles.bioClamped}` : styles.bio}>{person.bio}</p>
                {longBio && (
                  <button type="button" className={styles.bioToggle} aria-expanded={bioOpen} onClick={() => setBioOpen((open) => !open)}>
                    {bioOpen ? 'Show less' : 'Read more'}
                  </button>
                )}
              </div>
              <dl className={styles.stats}>
                {person.stats.map((stat) => (
                  <div key={stat.label} className={styles.stat}>
                    <dt>{stat.label}</dt>
                    <dd>{stat.value}</dd>
                  </div>
                ))}
              </dl>
            </div>
          </div>
        </section>
        <img className={styles.headerWave} src={headerWave} width={1440} height={70} alt="" />

        <section className={styles.knownFor} aria-labelledby="known-title">
          <div className={styles.sectionHeader}>
            <h2 id="known-title" className={styles.sectionTitle}>
              Known for
            </h2>
            <button
              type="button"
              className={styles.sectionAction}
              onClick={() => filmographyRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' })}
            >
              Full filmography ↓
            </button>
          </div>
          <div className={styles.row}>
            {person.knownFor.map((movie) => (
              <MovieTile key={movie.slug} movie={movie} />
            ))}
          </div>
        </section>

        <div className={styles.twoCol}>
          <section ref={filmographyRef} className={styles.filmography} aria-labelledby="filmography-title">
            <h2 id="filmography-title" className={styles.cardTitleLg}>
              Filmography
            </h2>
            {person.filmography.length === 0 && <p className={styles.empty}>No films on TMDB yet.</p>}
            <ul className={styles.credits}>
              {films.map((credit) => {
                const added = Boolean(watchItemFor(credit.movie))
                return (
                  <li key={credit.movie.slug} className={styles.credit}>
                    <span className={styles.year}>{credit.year}</span>
                    <Link to={`/movies/${credit.movie.slug}`} className={styles.creditLink}>
                      <span className={styles.thumb} style={{ background: gradientCss(credit.movie.gradient) }}>
                        {credit.movie.posterUrl && (
                          <img
                            className={styles.thumbImage}
                            src={posterAt(credit.movie.posterUrl, 'w92')}
                            alt=""
                            loading="lazy"
                            onError={(event) => {
                              event.currentTarget.hidden = true
                            }}
                          />
                        )}
                      </span>
                      <span className={styles.creditText}>
                        <span className={styles.creditTitle}>{credit.movie.title}</span>
                        <span className={styles.creditRoles}>{credit.roles}</span>
                      </span>
                    </Link>
                    <span className={styles.score}>
                      <img src={starIcon} width={16} height={16} alt="" />
                      <span className="sr-only">Rated </span>
                      {credit.movie.score}
                    </span>
                    <button
                      type="button"
                      className={added ? styles.addOn : styles.add}
                      aria-pressed={added}
                      aria-label={added ? `Remove ${credit.movie.title} from watchlist` : `Add ${credit.movie.title} to watchlist`}
                      onClick={() => toggleWatchlist(credit.movie)}
                    >
                      <img src={added ? checkIcon : plusIcon} width={16} height={16} alt="" />
                    </button>
                  </li>
                )
              })}
            </ul>
            {person.filmography.length > FILMOGRAPHY_SHOWN && (
              <button type="button" className={styles.showAll} aria-expanded={allFilms} onClick={() => setAllFilms((all) => !all)}>
                {allFilms ? 'Show fewer' : `Show all ${person.filmography.length} films`}
              </button>
            )}
          </section>

          {/* TMDB people show "Personal details"; the design's demo people show collaborators and awards. */}
          {hasSide && (
            <aside className={styles.side}>
              {details.length > 0 && (
                <section className={styles.details} aria-labelledby="details-title">
                  <h2 id="details-title" className={styles.cardTitle}>
                    Personal details
                  </h2>
                  <dl className={styles.detailList}>
                    {details.map((detail) => (
                      <div key={detail.label}>
                        <dt>{detail.label}</dt>
                        <dd>{detail.value}</dd>
                      </div>
                    ))}
                  </dl>
                </section>
              )}

              {(collaboratorsLoading || person.collaborators.length > 0) && (
                <section className={styles.collaborators} aria-labelledby="collab-title" aria-busy={collaboratorsLoading}>
                  <h2 id="collab-title" className={styles.cardTitle}>
                    Frequent collaborators
                  </h2>
                  <ul className={styles.people}>
                    {collaboratorsLoading &&
                      [0, 1, 2].map((row) => (
                        <li key={`skeleton-${row}`} className={styles.collaborator} aria-hidden="true">
                          <span className={`${styles.avatar} ${styles.skeleton}`} />
                          <span className={styles.collabText}>
                            <span className={`${styles.skeleton} ${styles.skeletonCollabName}`} />
                            <span className={`${styles.skeleton} ${styles.skeletonCollabMeta}`} />
                          </span>
                        </li>
                      ))}
                    {person.collaborators.map((collaborator) => (
                      <li key={collaborator.slug}>
                        <Link to={`/people/${collaborator.slug}`} className={styles.collaborator}>
                          <span className={styles.avatar} style={{ background: collaborator.color }} aria-hidden="true">
                            {collaborator.photoUrl ? (
                              <img className={styles.avatarImage} src={collaborator.photoUrl} alt="" loading="lazy" />
                            ) : (
                              collaborator.initials
                            )}
                          </span>
                          <span className={styles.collabText}>
                            <span className={styles.collabName}>{collaborator.name}</span>
                            <span className={styles.collabMeta}>{collaborator.meta}</span>
                          </span>
                        </Link>
                      </li>
                    ))}
                  </ul>
                </section>
              )}

              {person.awards.length > 0 && (
                <section className={styles.awards} aria-labelledby="awards-title">
                  <div className={styles.awardsHead}>
                    <img src={awardIcon} width={22} height={22} alt="" />
                    <h2 id="awards-title" className={styles.cardTitle}>
                      Awards &amp; honours
                    </h2>
                  </div>
                  <ul className={styles.awardList}>
                    {person.awards.map((award) => (
                      <li key={award}>
                        <img src={checkIcon} width={16} height={16} alt="" />
                        {award}
                      </li>
                    ))}
                  </ul>
                </section>
              )}
            </aside>
          )}
        </div>
      </main>
    </div>
  )
}
