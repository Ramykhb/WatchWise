import type { ReactNode } from 'react'
import { Link } from 'react-router'
import { apiErrorMessage } from '../../lib/auth.ts'
import type { ContentReport, Member } from '../../lib/adminTools.ts'
import s from './AdminTools.module.css'
import { dateText } from './adminToolsState.tsx'
export function Person({ person }: { person?: Pick<Member, 'id' | 'name'> | null }) {
  return person?.id ? <Link to={`/admin/users/${person.id}`}>{person.name ?? `Member #${person.id}`}</Link> : <>Deleted member</>
}
export function Page({ title, lead, children }: { title: string; lead?: string; children: ReactNode }) {
  return (
    <div className={s.page}>
      <header>
        <h1>{title}</h1>
        {lead && <p className={s.lead}>{lead}</p>}
      </header>
      {children}
    </div>
  )
}
export function Field({ label, children }: { label: string; children: ReactNode }) {
  return (
    <label className={s.field}>
      <span>{label}</span>
      {children}
    </label>
  )
}
export function Select({
  label,
  value,
  options,
  onChange,
}: {
  label: string
  value: string
  options: string[]
  onChange: (v: string) => void
}) {
  return (
    <Field label={label}>
      <select value={value} onChange={(e) => onChange(e.target.value)}>
        {options.map((v) => (
          <option key={v} value={v}>
            {v === '' ? 'All' : v.replaceAll('_', ' ')}
          </option>
        ))}
      </select>
    </Field>
  )
}
export function LoadState({ error, loading, reload }: { error: unknown; loading: boolean; reload: () => void }) {
  return error ? (
    <div role="alert" className={s.notice}>
      {apiErrorMessage(error, 'Unable to load this page.')} <button onClick={reload}>Retry</button>
    </div>
  ) : loading ? (
    <p role="status">Loading…</p>
  ) : null
}
export function Reports({ reports }: { reports: ContentReport[] }) {
  return (
    <section className={s.card}>
      <h2>Reports</h2>
      {!reports.length && <p>No reports.</p>}
      <div className={s.list}>
        {reports.map((r) => (
          <article key={r.id}>
            <Link to={`/admin/reports/${r.id}`}>Report #{r.id}</Link> · {r.reason} · {r.status ?? 'open'}
            <p>{r.details}</p>
            <p className={s.meta}>
              Filed by <Person person={r.reporter} /> · {dateText(r.created_at)}
            </p>
            {r.resolution_note && <p>Decision: {r.resolution_note}</p>}
          </article>
        ))}
      </div>
    </section>
  )
}
export function Metrics({ values }: { values: Record<string, number | string | null> }) {
  return (
    <dl className={s.stats}>
      {Object.entries(values).map(([key, value]) => (
        <div className={s.stat} key={key}>
          <dt>{key.replaceAll('_', ' ')}</dt>
          <dd>
            <strong>{value ?? '—'}</strong>
          </dd>
        </div>
      ))}
    </dl>
  )
}
