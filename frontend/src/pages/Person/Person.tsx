import { useRef, useState } from 'react'
import { Link, useParams } from 'react-router'
import AppNav from '../../components/AppNav/AppNav.tsx'
import MovieTile from '../../components/MovieTile/MovieTile.tsx'
import SplitHeadline from '../../components/SplitHeadline/SplitHeadline.tsx'
import Placeholder from '../Placeholder/Placeholder.tsx'
import headerWave from '../../assets/person/header-wave.svg'
import starIcon from '../../assets/person/icon-star.svg'
import plusIcon from '../../assets/person/icon-plus.svg'
import awardIcon from '../../assets/person/icon-award.svg'
import checkIcon from '../../assets/person/icon-check.svg'
import { useEntranceAnimation } from '../../hooks/useEntranceAnimation.ts'
import { gradientCss } from '../Home/homeData.ts'
import { getPerson, type Person as PersonData } from './personData.ts'
import styles from './Person.module.css'

export default function Person() {
  const { slug } = useParams()
  const person = getPerson(slug)
  if (!person) return <Placeholder title="Person not found" notFound />
  // Keyed by slug so the watchlist toggles reset when moving between people.
  return <PersonPage key={person.slug} person={person} />
}

function PersonPage({ person }: { person: PersonData }) {
  const pageRef = useRef<HTMLDivElement>(null)
  const filmographyRef = useRef<HTMLElement>(null)
  useEntranceAnimation(pageRef)

  // TODO: read and write the signed-in user's watchlist through the API.
  const [watchlist, setWatchlist] = useState<string[]>([])
  const toggleWatchlist = (slug: string) =>
    setWatchlist((list) => (list.includes(slug) ? list.filter((s) => s !== slug) : [...list, slug]))

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
              {person.initials}
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
              <p className={styles.bio}>{person.bio}</p>
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
            <ul className={styles.credits}>
              {person.filmography.map((credit) => {
                const added = watchlist.includes(credit.movie.slug)
                return (
                  <li key={credit.movie.slug} className={styles.credit}>
                    <span className={styles.year}>{credit.year}</span>
                    <Link to={`/movies/${credit.movie.slug}`} className={styles.creditLink}>
                      <span className={styles.thumb} style={{ background: gradientCss(credit.movie.gradient) }} />
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
                      onClick={() => toggleWatchlist(credit.movie.slug)}
                    >
                      <img src={added ? checkIcon : plusIcon} width={16} height={16} alt="" />
                    </button>
                  </li>
                )
              })}
            </ul>
          </section>

          <aside className={styles.side}>
            <section className={styles.collaborators} aria-labelledby="collab-title">
              <h2 id="collab-title" className={styles.cardTitle}>
                Frequent collaborators
              </h2>
              <ul className={styles.people}>
                {person.collaborators.map((collaborator) => (
                  <li key={collaborator.slug}>
                    <Link to={`/people/${collaborator.slug}`} className={styles.collaborator}>
                      <span className={styles.avatar} style={{ background: collaborator.color }} aria-hidden="true">
                        {collaborator.initials}
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
          </aside>
        </div>
      </main>
    </div>
  )
}
