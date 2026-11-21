import { useState } from 'react'
import { Link, useParams } from 'react-router'
import AppNav from '../../components/AppNav/AppNav.tsx'
import PillButton from '../../components/PillButton/PillButton.tsx'
import LoadError from '../../components/LoadingState/LoadError.tsx'
import { useApi } from '../../lib/api.ts'
import styles from './Information.module.css'

type Person = { id: number | null; name: string; profile_url: string | null; character?: string; job?: string; department?: string }
type Extras = { title: string; cast: Person[]; crew: Person[]; videos: { youtube_key: string; name: string; type: string; official: boolean }[] }

export default function MovieExtras({ kind }: { kind: 'credits' | 'videos' }) {
  const { slug = '' } = useParams()
  const match = /^(tv-)?([1-9][0-9]*)$/.exec(slug)
  const query = useApi<Extras>(match ? `/movies/${match[2]}/extras?type=${match[1] ? 'tv' : 'movie'}` : '', { enabled: Boolean(match) })
  const [selection, setSelection] = useState<{ slug: string; key: string } | null>(null)
  if (!match) return <LoadError label="This title link is invalid." onRetry={() => window.history.back()} />
  if (query.error) return <LoadError label="Couldn't load this title's extras." onRetry={query.reload} />
  const data = query.data
  const selected = selection?.slug === slug ? data?.videos.find(video => video.youtube_key === selection.key) : null
  const people = (items: Person[], crew = false) => <div className={styles.grid}>{items.map((person, index) => <article className={`${styles.card} ${styles.person}`} key={`${person.id}-${index}`}>
    {person.profile_url && <img src={person.profile_url} alt="" loading="lazy" />}
    <div><h3>{person.id ? <Link to={`/people/${person.id}`}>{person.name}</Link> : person.name}</h3><p>{crew ? [person.job, person.department].filter(Boolean).join(' · ') : person.character || 'Cast'}</p></div>
  </article>)}</div>
  return <><AppNav /><main className={styles.main}>
    <Link to={`/movies/${slug}`}>← Back to title</Link>
    <h1>{kind === 'credits' ? 'Full credits' : 'Videos'}</h1>
    {query.loading && <p role="status">Loading…</p>}
    {data && <><p>{data.title}</p>
      {kind === 'credits' ? <>
        <h2>Cast ({data.cast.length})</h2>{people(data.cast)}{!data.cast.length && <p>No cast information available.</p>}
        <h2>Crew ({data.crew.length})</h2>{people(data.crew, true)}{!data.crew.length && <p>No crew information available.</p>}
      </> : <>
        <p>Choose a video to play it through YouTube. Videos are supplied by TMDB.</p>
        {selected && <iframe className={styles.video} src={`https://www.youtube-nocookie.com/embed/${encodeURIComponent(selected.youtube_key)}`} title={selected.name} allow="encrypted-media; picture-in-picture; fullscreen" allowFullScreen referrerPolicy="strict-origin-when-cross-origin" />}
        <div className={styles.grid}>{data.videos.map(video => <article className={styles.card} key={video.youtube_key}>
          <h3>{video.name}</h3><p>{video.type}{video.official ? ' · Official' : ''}</p>
          <PillButton aria-pressed={selected?.youtube_key === video.youtube_key} onClick={() => setSelection({ slug, key: video.youtube_key })}>Play video</PillButton>
          <p><a href={`https://www.youtube.com/watch?v=${encodeURIComponent(video.youtube_key)}`} target="_blank" rel="noreferrer">Open on YouTube</a></p>
        </article>)}</div>
        {!data.videos.length && <p>No videos available for this title.</p>}
      </>}
    </>}
  </main></>
}
