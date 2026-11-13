import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router'
import { useAuth } from '../../contexts/useAuth.ts'
import { useApi } from '../../lib/api.ts'
import { adminApi, canSuspendUser, type AdminUserRole } from '../../lib/adminApi.ts'
import { mutate, useStaffPage, type UserDetail, type UserNote } from '../../lib/adminTools.ts'
import AdminPagination from '../../components/AdminPagination/AdminPagination.tsx'
import { AuditEntries } from './AdminAudit.tsx'
import { Page, Field, Select, Person, Metrics, LoadState } from './AdminToolsUI.tsx'
import { useAction, dateText, s } from './adminToolsState.tsx'
export function SuspensionFields({
  reason,
  until,
  setReason,
  setUntil,
}: {
  reason: string
  until: string
  setReason: (s: string) => void
  setUntil: (s: string) => void
}) {
  return (
    <div className={s.page}>
      <div className={s.bar}>
        <Field label="Suspension reason (emailed to member)">
          <input maxLength={255} value={reason} onChange={(e) => setReason(e.target.value)} />
        </Field>
        <Field label="Suspend until (optional; local time)">
          <input type="datetime-local" value={until} onChange={(e) => setUntil(e.target.value)} />
        </Field>
      </div>
    </div>
  )
}
function Notes({ id }: { id: string }) {
  const { user } = useAuth()
  const [page, setPage] = useState(1)
  const [body, setBody] = useState('')
  const result = useStaffPage<UserNote>(`users/${id}/notes`, { page })
  const action = useAction()
  return (
    <section className={s.card}>
      <h2>Internal staff notes</h2>
      <p className={s.meta}>Visible to authorized staff only.</p>
      <LoadState {...result} />
      {action.notice}
      <form
        onSubmit={(e) => {
          e.preventDefault()
          void action.run(
            () => mutate(`users/${id}/notes`, 'post', { body }),
            'Note added.',
            () => {
              setBody('')
              setPage(1)
              result.reload()
            },
          )
        }}
      >
        <Field label="New note">
          <textarea required maxLength={1000} value={body} onChange={(e) => setBody(e.target.value)} />
        </Field>
        <button disabled={action.busy || !body.trim()}>Add note</button>
      </form>
      <div className={s.list}>
        {result.data?.data.map((note) => (
          <article key={note.id}>
            <p>{note.body}</p>
            <p className={s.meta}>
              <Person person={note.author} /> · {dateText(note.created_at)}
            </p>
            {(user?.role === 'admin' || user?.id === note.author?.id) && (
              <button
                disabled={action.busy}
                className={s.danger}
                onClick={() => {
                  if (confirm('Delete this staff note?'))
                    void action.run(() => mutate(`users/${id}/notes/${note.id}`, 'delete'), 'Note deleted.', result.reload)
                }}
              >
                Delete note
              </button>
            )}
          </article>
        ))}
      </div>
      {result.data && <AdminPagination meta={result.data.meta} shown={result.data.data.length} onPage={setPage} />}
    </section>
  )
}
export default function AdminUserDetail() {
  const { id } = useParams()
  return <MemberDetail key={id} />
}

function MemberDetail() {
  const { id = '' } = useParams()
  const { user: me } = useAuth()
  const navigate = useNavigate()
  const result = useApi<{ user: UserDetail }>(`/admin/users/${id}`)
  const member = result.data?.user
  const action = useAction()
  const [reason, setReason] = useState('')
  const [until, setUntil] = useState('')
  const [role, setRole] = useState<AdminUserRole | ''>('')
  const allowed = member && canSuspendUser(me, member)
  const act = (path: string, method: 'post' | 'delete', question: string) => {
    if (confirm(question)) void action.run(() => mutate(`users/${id}/${path}`, method), 'Action completed.', result.reload)
  }
  return (
    <Page title={member?.name ?? 'Member details'}>
      <Link to="/admin/users">← Users</Link>
      <LoadState {...result} />
      {action.notice}
      {member && (
        <>
          <section className={s.card}>
            {member.avatar && <img className={s.avatar} src={member.avatar} alt={`${member.name}'s profile`} />}
            <h2>{member.username ?? member.name}</h2>
            <p>
              {member.email} · {member.role} · {member.status}
            </p>
            <p>{member.profile.bio || 'No bio.'}</p>
            <p>
              Profile: {member.profile.profile_visibility} · Joined {dateText(member.created_at)} · Last active{' '}
              {dateText(member.last_active_at)}
            </p>
            <p>
              Linked sign-ins: {member.social_providers.join(', ') || 'None'} · Open sessions: {member.active_sessions}
            </p>
            {member.suspension && (
              <p className={s.notice}>
                Suspended: {member.suspension.reason || 'No reason supplied'} · Until{' '}
                {member.suspension.until ? dateText(member.suspension.until) : 'reinstated by staff'}
              </p>
            )}
            <Metrics values={member.counts} />
            <div className={s.bar}>
              <Link to={`/admin/reviews?user_id=${id}`}>Written reviews</Link>
              <Link to={`/admin/ratings?user_id=${id}`}>Star ratings</Link>
            </div>
          </section>
          {allowed && (
            <section className={s.card}>
              <h2>Account tools</h2>
              <form
                onSubmit={(e) => {
                  e.preventDefault()
                  if (confirm(`${member.is_active ? 'Suspend' : 'Reinstate'} this member?`))
                    void action.run(
                      () =>
                        adminApi.updateUser(member.id, {
                          is_active: !member.is_active,
                          ...(member.is_active
                            ? {
                                reason: reason || null,
                                suspended_until: until ? new Date(until).toISOString() : null,
                              }
                            : {}),
                        }),
                      'Account access updated.',
                      result.reload,
                    )
                }}
              >
                {member.is_active && <SuspensionFields reason={reason} until={until} setReason={setReason} setUntil={setUntil} />}
                <button disabled={action.busy}>{member.is_active ? 'Suspend member' : 'Reinstate member'}</button>
              </form>
              <div className={s.bar}>
                <button disabled={action.busy} onClick={() => act('sign-out', 'post', 'Sign this member out on every device?')}>
                  Sign out everywhere
                </button>
                <button disabled={action.busy || !member.avatar} onClick={() => act('avatar', 'delete', 'Remove this profile picture?')}>
                  Remove avatar
                </button>
                <button disabled={action.busy || !member.profile.bio} onClick={() => act('bio', 'delete', 'Remove this bio?')}>
                  Remove bio
                </button>
                <button
                  disabled={action.busy || !!member.email_verified_at}
                  onClick={() => act('resend-verification', 'post', 'Send another verification email to this member?')}
                >
                  Resend verification
                </button>
                <button
                  disabled={action.busy}
                  className={s.danger}
                  onClick={() =>
                    act(
                      'ratings',
                      'delete',
                      'Permanently remove all star-only ratings by this member? Written reviews and moderated ratings stay.',
                    )
                  }
                >
                  Purge star ratings
                </button>
              </div>
            </section>
          )}
          {me?.role === 'admin' && (
            <section className={s.card}>
              <h2>Administrator tools</h2>
              <form
                className={s.bar}
                onSubmit={(e) => {
                  e.preventDefault()
                  if (role && confirm(`Change this member's role to ${role}?`))
                    void action.run(() => adminApi.updateUser(member.id, { role }), 'Role updated.', result.reload)
                }}
              >
                <Select
                  label="Role"
                  value={role || member.role}
                  options={['user', 'moderator', 'admin']}
                  onChange={(v) => setRole(v as AdminUserRole)}
                />
                <button disabled={action.busy || !role || role === member.role || (me.id === member.id && role !== 'admin')}>
                  Save role
                </button>
              </form>
              {me.id !== member.id && (
                <button
                  disabled={action.busy}
                  className={s.danger}
                  onClick={() => {
                    if (confirm('Permanently delete this account and its member data?'))
                      void action.run(
                        () => adminApi.deleteUser(member.id),
                        'Account deleted.',
                        () => navigate('/admin/users'),
                      )
                  }}
                >
                  Delete account
                </button>
              )}
            </section>
          )}
          {allowed && <Notes key={id} id={id} />}
          {member.recent_audit && (
            <section className={s.card}>
              <h2>Recent account history</h2>
              <AuditEntries entries={member.recent_audit} />
            </section>
          )}
        </>
      )}
    </Page>
  )
}
