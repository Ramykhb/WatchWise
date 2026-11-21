import { useEffect, useId, useState } from 'react'
import { api } from '../../lib/api.ts'
import { apiErrorMessage } from '../../lib/auth.ts'
import type { ApiMember } from '../../lib/types.ts'
import styles from './MemberSearch.module.css'

export default function MemberSearch({ excludedIds, onPick, disabled = false }: {
  excludedIds: number[]; onPick: (member: ApiMember) => void | Promise<void>; disabled?: boolean
}) {
  const id = useId()
  const [query, setQuery] = useState('')
  const [retry, setRetry] = useState(0)
  const [result, setResult] = useState<{ query: string; members: ApiMember[]; total: number; error: string } | null>(null)
  const [saving, setSaving] = useState(false)
  const [note, setNote] = useState('')
  const q = query.trim()
  useEffect(() => {
    if (!q) return
    const controller = new AbortController()
    const timer = window.setTimeout(() => {
      api.get<{ users: ApiMember[]; total: number }>('/users', { params: { q, limit: 20 }, signal: controller.signal })
        .then(({ data }) => { if (!controller.signal.aborted) setResult({ query: q, members: data.users, total: data.total, error: '' }) })
        .catch((error) => { if (!controller.signal.aborted) setResult({ query: q, members: [], total: 0, error: apiErrorMessage(error, "Couldn't search members.") }) })
    }, 250)
    return () => { controller.abort(); window.clearTimeout(timer) }
  }, [q, retry])
  const current = result?.query === q ? result : null
  const members = current?.members.filter((member) => !excludedIds.includes(member.id)) ?? []
  const pick = async (member: ApiMember) => {
    if (saving) return
    setSaving(true)
    setNote('')
    try { await onPick(member); setQuery('') }
    catch (error) { setNote(apiErrorMessage(error, "Couldn't invite this member. Please try again.")) }
    finally { setSaving(false) }
  }
  return <div className={styles.picker}>
    <label htmlFor={id}>Search members by name or @username</label>
    <input id={id} type="search" maxLength={100} value={query} disabled={disabled || saving} autoComplete="off"
      placeholder={disabled ? 'Guest limit reached' : 'Search all members…'} onChange={(event) => setQuery(event.target.value)}
      onKeyDown={(event) => { if (event.key === 'Enter') event.preventDefault(); if (event.key === 'Escape') setQuery('') }} />
    {q && !disabled && <ul className={styles.results} aria-label="Matching members" data-lenis-prevent>
      {!current && <li role="status">Searching…</li>}
      {current?.error ? <li role="alert">{current.error} <button type="button" onClick={() => setRetry((value) => value + 1)}>Try again</button></li>
        : current && members.length === 0 ? <li>No uninvited members match this search.</li> : null}
      {members.map((member) => <li key={member.id}><button type="button" disabled={saving} onClick={() => void pick(member)}>
        <strong>{member.name}</strong><span>@{member.username}</span>
      </button></li>)}
      {current && current.total > current.members.length && <li>Refine your search to see more matches.</li>}
    </ul>}
    {note && <p role="alert">{note}</p>}
  </div>
}
