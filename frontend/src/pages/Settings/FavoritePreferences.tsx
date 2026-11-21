import { useEffect, useId, useState, type FormEvent } from 'react'
import { Link } from 'react-router'
import PillButton from '../../components/PillButton/PillButton.tsx'
import { api } from '../../lib/api.ts'
import { apiErrorMessage } from '../../lib/auth.ts'
import type { ApiMe } from '../../lib/types.ts'
import styles from './FavoritePreferences.module.css'
import settings from './Settings.module.css'

type Person = { id: number; name: string; department?: string | null }

function PersonPicker({ label, ids, onChange }: { label: string; ids: number[]; onChange: (ids: number[]) => void }) {
  const inputId = useId()
  const [query, setQuery] = useState('')
  const [result, setResult] = useState<{ query: string; people: Person[]; failed: boolean } | null>(null)
  const [names, setNames] = useState<Record<number, string>>({})
  const search = query.trim()

  useEffect(() => {
    const controller = new AbortController()
    for (const id of ids) {
      api.get<Person>(`/people/${id}`, { signal: controller.signal }).then(({ data }) => {
        setNames((current) => ({ ...current, [id]: data.name }))
      }).catch(() => { /* Keep the removable ID if this person's details are unavailable. */ })
    }
    return () => controller.abort()
  }, [ids])

  useEffect(() => {
    if (search.length < 2) return
    const controller = new AbortController()
    const timer = window.setTimeout(() => {
      api.get<{ people: Person[] }>('/people/search', { params: { q: search }, signal: controller.signal })
        .then(({ data }) => {
          if (!controller.signal.aborted) setResult({ query: search, people: data.people, failed: false })
        })
        .catch(() => {
          if (!controller.signal.aborted) setResult({ query: search, people: [], failed: true })
        })
    }, 300)
    return () => { window.clearTimeout(timer); controller.abort() }
  }, [search])

  const current = result?.query === search ? result : null
  return (
    <div className={styles.picker}>
      <label htmlFor={inputId}>{label} <span className={styles.hint}>({ids.length}/5)</span></label>
      <ul className={styles.selected} aria-label={`Selected ${label.toLowerCase()}`}>
        {ids.map((id) => <li key={id}>
          <Link to={`/people/${id}`}>{names[id] ?? `Person #${id}`}</Link>
          <button type="button" aria-label={`Remove ${names[id] ?? `person ${id}`}`} onClick={() => onChange(ids.filter((item) => item !== id))}>×</button>
        </li>)}
      </ul>
      <input id={inputId} type="search" value={query} maxLength={100} disabled={ids.length >= 5}
        placeholder={ids.length >= 5 ? 'Remove someone to add another' : 'Search by name…'} onChange={(event) => setQuery(event.target.value)} />
      {search.length >= 2 && ids.length < 5 && <ul className={styles.results} aria-label={`${label} search results`}>
        {!current && <li role="status">Searching…</li>}
        {current?.failed && <li role="alert">Search unavailable. Change the search to try again.</li>}
        {current && !current.failed && current.people.filter((person) => !ids.includes(person.id)).length === 0 && <li>No matching people.</li>}
        {current?.people.filter((person) => !ids.includes(person.id)).map((person) => <li key={person.id}>
          <button type="button" onClick={() => {
            setNames((value) => ({ ...value, [person.id]: person.name }))
            onChange([...ids, person.id])
            setQuery('')
          }}>{person.name}{person.department && <span className={styles.hint}> · {person.department}</span>}</button>
        </li>)}
      </ul>}
    </div>
  )
}

export default function FavoritePreferences({ me, onChange }: { me: ApiMe; onChange: (me: ApiMe) => void }) {
  const [genres, setGenres] = useState(me.preferences.favorite_genres)
  const [actors, setActors] = useState(me.preferences.favorite_actor_ids ?? [])
  const [directors, setDirectors] = useState(me.preferences.favorite_director_ids ?? [])
  const [saving, setSaving] = useState(false)
  const [note, setNote] = useState('')
  const options = [...new Set([...(me.preferences.favorite_genre_options ?? []), ...genres])]
  const save = async (event: FormEvent) => {
    event.preventDefault()
    if (saving) return
    setSaving(true)
    setNote('')
    try {
      const { data } = await api.patch<ApiMe>('/me/preferences', {
        favorite_genres: genres, favorite_actor_ids: actors, favorite_director_ids: directors,
      })
      onChange(data)
      setNote('Movie preferences saved. Your next recommendations will use them.')
    } catch (error) {
      setNote(apiErrorMessage(error, "Couldn't save movie preferences. Please try again."))
    } finally {
      setSaving(false)
    }
  }
  return (
    <section id="taste" data-section className={settings.panel} aria-labelledby="taste-title">
      <h2 id="taste-title" className={settings.panelTitle}>Movie preferences</h2>
      <p className={settings.panelText}>Choose favorite genres, actors and directors to shape your recommendations.</p>
      <form onSubmit={save} className={styles.form}>
        <fieldset disabled={saving} className={styles.fields}>
          <legend>Favorite genres ({genres.length}/10)</legend>
          <div className={styles.genres}>
            {options.map((genre) => <label key={genre}>
              <input type="checkbox" checked={genres.includes(genre)} disabled={!genres.includes(genre) && genres.length >= 10}
                onChange={() => setGenres((current) => current.includes(genre) ? current.filter((item) => item !== genre) : [...current, genre])} />
              {genre}
            </label>)}
          </div>
          <PersonPicker label="Favorite actors" ids={actors} onChange={setActors} />
          <PersonPicker label="Favorite directors" ids={directors} onChange={setDirectors} />
        </fieldset>
        <PillButton type="submit" disabled={saving}>{saving ? 'Saving…' : 'Save movie preferences'}</PillButton>
        <p role="status" className={settings.note}>{note}</p>
      </form>
    </section>
  )
}
