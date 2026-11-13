import { useState } from 'react'
import { Link, useParams } from 'react-router'
import { useApi } from '../../lib/api.ts'
import { mutate, reportDestination, useStaffPage, type Report } from '../../lib/adminTools.ts'
import AdminPagination from '../../components/AdminPagination/AdminPagination.tsx'
import { Page, Field, Select, Person, LoadState } from './AdminToolsUI.tsx'
import { useAction, dateText, s } from './adminToolsState.tsx'
export function AdminReports() {
  const [page, setPage] = useState(1)
  const [filters, setFilters] = useState({
    status: 'open',
    target_type: '',
    reason: '',
    reporter_id: '',
  })
  const result = useStaffPage<Report>('reports', { ...filters, page })
  const change = (key: keyof typeof filters, value: string) => {
    setFilters((f) => ({ ...f, [key]: value }))
    setPage(1)
  }
  return (
    <Page title="Reports" lead="Triage reports, then open the content to take moderation action.">
      <div className={s.bar}>
        <Select
          label="Status"
          value={filters.status}
          options={['open', 'resolved', 'dismissed', 'all']}
          onChange={(v) => change('status', v)}
        />
        <Select
          label="Content"
          value={filters.target_type}
          options={['', 'review', 'message', 'list', 'room', 'profile']}
          onChange={(v) => change('target_type', v)}
        />
        <Select
          label="Reason"
          value={filters.reason}
          options={[
            '',
            'Spam',
            'Links',
            'Spoilers',
            'Offensive language',
            'spam',
            'harassment',
            'hate',
            'spoilers',
            'inappropriate',
            'other',
          ]}
          onChange={(v) => change('reason', v)}
        />
        <Field label="Reporter ID">
          <input type="number" min="1" value={filters.reporter_id} onChange={(e) => change('reporter_id', e.target.value)} />
        </Field>
      </div>
      <LoadState {...result} />
      {result.data?.summary && (
        <div className={s.bar}>
          {Object.entries(result.data.summary).map(([group, counts]) => (
            <section key={group} className={s.card}>
              <h2>{group === 'by_status' ? 'Report status' : 'Open reports by content'}</h2>
              {Object.entries(counts as Record<string, number>).map(([k, v]) => (
                <p key={k}>
                  {k}: {v}
                </p>
              ))}
            </section>
          ))}
        </div>
      )}
      <section className={s.card}>
        <div className={s.scroll}>
          <table className={s.table}>
            <thead>
              <tr>
                <th>Report</th>
                <th>Content</th>
                <th>Reporter</th>
                <th>Status</th>
                <th>Filed</th>
              </tr>
            </thead>
            <tbody>
              {result.data?.data.map((r) => (
                <tr key={r.id}>
                  <td>
                    <Link to={`/admin/reports/${r.id}`}>
                      #{r.id} · {r.reason}
                    </Link>
                  </td>
                  <td>
                    {r.target.label}
                    {r.target.removed && ' (removed)'}
                    <p>{r.target.excerpt}</p>
                  </td>
                  <td>
                    <Person person={r.reporter} />
                  </td>
                  <td>{r.status}</td>
                  <td>{dateText(r.created_at)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        {result.data?.data.length === 0 && <p>No matching reports.</p>}
        {result.data && <AdminPagination meta={result.data.meta} shown={result.data.data.length} onPage={setPage} />}
      </section>
    </Page>
  )
}
export function AdminReportDetail() {
  const { id } = useParams()
  const result = useApi<{ report: Report }>(`/admin/reports/${id}`)
  const r = result.data?.report
  const [note, setNote] = useState('')
  const [all, setAll] = useState(true)
  const action = useAction()
  return (
    <Page title={`Report #${id}`}>
      <Link to="/admin/reports">← Reports</Link>
      <LoadState {...result} />
      {action.notice}
      {r && (
        <>
          <section className={s.card}>
            <h2>{r.target.label}</h2>
            <p>{r.target.excerpt}</p>
            <p>
              {r.reason} · {r.status} · {dateText(r.created_at)}
            </p>
            <p>{r.details}</p>
            <p>
              Filed by <Person person={r.reporter} /> · Content by <Person person={r.target.owner} />
            </p>
            {reportDestination(r.target) && (
              <Link className={s.button} to={reportDestination(r.target)!}>
                Open moderation tools
              </Link>
            )}
            {r.target.removed && <p>The reported content has been removed.</p>}
          </section>
          {r.status === 'open' ? (
            <form className={s.card} onSubmit={(e) => e.preventDefault()}>
              <h2>Decision</h2>
              <p>Closing a report records a decision. Use the content tools to remove content or suspend an account.</p>
              <Field label="Decision note (optional)">
                <textarea maxLength={500} value={note} onChange={(e) => setNote(e.target.value)} />
              </Field>
              <label className={s.check}>
                <input type="checkbox" checked={all} onChange={(e) => setAll(e.target.checked)} />
                Apply to every open report on this content
              </label>
              <div className={s.bar}>
                {['resolved', 'dismissed'].map((status) => (
                  <button
                    key={status}
                    disabled={action.busy}
                    onClick={() =>
                      void action.run(
                        () =>
                          mutate(`reports/${id}`, 'patch', {
                            status,
                            note: note || null,
                            apply_to_target: all,
                          }),
                        `Report ${status}.`,
                        result.reload,
                      )
                    }
                  >
                    {status === 'resolved' ? 'Resolve' : 'Dismiss'}
                  </button>
                ))}
              </div>
            </form>
          ) : (
            <section className={s.card}>
              <h2>Decision</h2>
              <p>{r.resolution_note || 'No note recorded.'}</p>
              <p>
                Closed by <Person person={r.resolved_by} /> · {dateText(r.resolved_at)}
              </p>
            </section>
          )}
        </>
      )}
    </Page>
  )
}
