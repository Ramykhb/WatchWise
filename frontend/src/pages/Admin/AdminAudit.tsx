import { useState } from 'react'
import { Link } from 'react-router'
import { useStaffPage, type Audit } from '../../lib/adminTools.ts'
import AdminPagination from '../../components/AdminPagination/AdminPagination.tsx'
import { Page, Field, Select, Person, LoadState } from './AdminToolsUI.tsx'
import { dateText, s } from './adminToolsState.tsx'
export function AuditEntries({ entries }: { entries: Audit[] }) {
  return (
    <div className={s.list}>
      {entries.map((entry) => (
        <article key={entry.id}>
          <h2>{entry.action.replaceAll('_', ' ')}</h2>
          <p>{entry.subject.label ?? `${entry.subject.type} #${entry.subject.id ?? '—'}`}</p>
          <p className={s.meta}>
            {entry.actor.role === 'system' ? 'System' : <Person person={entry.actor} />} · {dateText(entry.created_at)}
          </p>
          {entry.changes && (
            <dl>
              {Object.entries(entry.changes).map(([key, value]) => (
                <div key={key}>
                  <dt>{key.replaceAll('_', ' ')}</dt>
                  <dd>{typeof value === 'object' ? JSON.stringify(value) : String(value)}</dd>
                </div>
              ))}
            </dl>
          )}
        </article>
      ))}
      {!entries.length && <p>No audit entries.</p>}
    </div>
  )
}
export default function AdminAudit() {
  const [page, setPage] = useState(1)
  const [filters, setFilters] = useState({
    action: '',
    actor_id: '',
    subject_type: '',
    subject_id: '',
  })
  const change = (key: keyof typeof filters, value: string) => {
    setFilters((f) => ({
      ...f,
      [key]: value,
      ...(key === 'subject_type' ? { subject_id: '' } : {}),
    }))
    setPage(1)
  }
  const result = useStaffPage<Audit>('audit-log', { ...filters, page })
  return (
    <Page title="Audit history" lead="Recorded staff and system actions, including changes to deleted content.">
      <div className={s.bar}>
        <Select
          label="Action"
          value={filters.action}
          options={[
            '',
            'user.role_changed',
            'user.suspended',
            'user.reinstated',
            'user.deleted',
            'user.note_added',
            'user.note_deleted',
            'user.signed_out',
            'user.avatar_removed',
            'user.bio_removed',
            'user.verification_resent',
            'user.suspension_lifted',
            'review.approved',
            'review.rejected',
            'review.deleted',
            'review.spoiler_changed',
            'movie.updated',
            'genre.updated',
            'genre.reset',
            'settings.updated',
            'report.resolved',
            'report.dismissed',
            'rating.deleted',
            'rating.purged',
            'list.made_private',
            'list.item_removed',
            'list.deleted',
            'room.locked',
            'room.unlocked',
            'room.deleted',
            'message.deleted',
          ]}
          onChange={(v) => change('action', v)}
        />
        <Field label="Staff member ID">
          <input type="number" min="1" value={filters.actor_id} onChange={(e) => change('actor_id', e.target.value)} />
        </Field>
        <Select
          label="Subject type"
          value={filters.subject_type}
          options={['', 'user', 'review', 'movie', 'genre', 'report', 'rating', 'list', 'room', 'message', 'setting']}
          onChange={(v) => change('subject_type', v)}
        />
        <Field label="Subject ID">
          <input
            type="number"
            min="1"
            disabled={!filters.subject_type}
            value={filters.subject_id}
            onChange={(e) => change('subject_id', e.target.value)}
          />
        </Field>
      </div>
      <LoadState {...result} />
      <section className={s.card}>
        {result.data && (
          <>
            <AuditEntries entries={result.data.data} />
            <AdminPagination meta={result.data.meta} shown={result.data.data.length} onPage={setPage} />
          </>
        )}
      </section>
      <Link to="/admin">Back to overview</Link>
    </Page>
  )
}
