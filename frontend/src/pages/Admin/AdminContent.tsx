import { useState } from 'react'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router'
import { useAuth } from '../../contexts/useAuth.ts'
import { useApi } from '../../lib/api.ts'
import {
  mutate,
  useStaffPage,
  type StaffList,
  type StaffRoom,
  type ListDetail,
  type RoomDetail,
  type StaffMessage,
} from '../../lib/adminTools.ts'
import AdminPagination from '../../components/AdminPagination/AdminPagination.tsx'
import { Page, Field, Select, Person, Reports, LoadState, Metrics } from './AdminToolsUI.tsx'
import { useAction, dateText, s } from './adminToolsState.tsx'

export function AdminContentIndex({ kind }: { kind: 'lists' | 'rooms' }) {
  const [params] = useSearchParams()
  const [filters, setFilters] = useState<Record<string, string>>({
    q: '',
    visibility: '',
    reported: params.get('reported') ?? '',
    locked: '',
    owner_id: '',
    creator_id: '',
    tmdb_id: '',
  })
  const [page, setPage] = useState(1)
  const change = (key: string, value: string) => {
    setFilters((v) => ({ ...v, [key]: value }))
    setPage(1)
  }
  const query = {
    q: filters.q,
    visibility: filters.visibility,
    reported: filters.reported,
    ...(kind === 'rooms'
      ? {
          locked: filters.locked,
          creator_id: filters.creator_id,
          tmdb_id: filters.tmdb_id,
        }
      : { owner_id: filters.owner_id }),
    page,
  }
  const result = useStaffPage<StaffList | StaffRoom>(kind, query)
  return (
    <Page title={kind === 'lists' ? 'Lists' : 'Discussion rooms'} lead="Review member content, visibility and open reports.">
      <div className={s.bar}>
        <Field label="Search">
          <input type="search" value={filters.q} onChange={(e) => change('q', e.target.value)} />
        </Field>
        <Select
          label="Visibility"
          value={filters.visibility}
          options={['', 'public', 'private']}
          onChange={(v) => change('visibility', v)}
        />
        <Field label="Reports">
          <select value={filters.reported} onChange={(e) => change('reported', e.target.value)}>
            <option value="">All</option>
            <option value="1">Reported</option>
            <option value="0">No open reports</option>
          </select>
        </Field>
        <Field label={kind === 'lists' ? 'Owner ID' : 'Creator ID'}>
          <input
            type="number"
            min="1"
            value={filters[kind === 'lists' ? 'owner_id' : 'creator_id']}
            onChange={(e) => change(kind === 'lists' ? 'owner_id' : 'creator_id', e.target.value)}
          />
        </Field>
        {kind === 'rooms' && (
          <>
            <Field label="Film TMDB ID">
              <input type="number" min="1" value={filters.tmdb_id} onChange={(e) => change('tmdb_id', e.target.value)} />
            </Field>
            <Field label="Lock">
              <select value={filters.locked} onChange={(e) => change('locked', e.target.value)}>
                <option value="">All</option>
                <option value="1">Locked</option>
                <option value="0">Unlocked</option>
              </select>
            </Field>
          </>
        )}
      </div>
      <LoadState {...result} />
      {result.data?.summary && <Metrics values={result.data.summary as Record<string, number>} />}
      <section className={s.card}>
        <div className={s.scroll}>
          <table className={s.table}>
            <thead>
              <tr>
                <th>Title</th>
                <th>Owner</th>
                <th>Visibility</th>
                <th>Content</th>
                <th>Reports</th>
                <th>Created</th>
              </tr>
            </thead>
            <tbody>
              {result.data?.data.map((row) => (
                <tr key={row.id}>
                  <td>
                    <Link to={`/admin/${kind}/${row.id}`}>{'title' in row ? row.title : row.name}</Link>
                    {'film' in row && <p>{row.film}</p>}
                  </td>
                  <td>
                    <Person person={'owner' in row ? row.owner : row.creator} />
                  </td>
                  <td>
                    {'visibility' in row ? row.visibility : `${row.is_private ? 'Private' : 'Public'}${row.is_locked ? ' · Locked' : ''}`}
                  </td>
                  <td>
                    {'items_count' in row ? `${row.items_count} titles` : `${row.members_count} members · ${row.messages_count} messages`}
                  </td>
                  <td>
                    {row.open_reports_count}
                    {'open_message_reports_count' in row && ` + ${row.open_message_reports_count} message reports`}
                  </td>
                  <td>{dateText(row.created_at)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        {result.data?.data.length === 0 && <p>No matching {kind}.</p>}
        {result.data && <AdminPagination meta={result.data.meta} shown={result.data.data.length} onPage={setPage} />}
      </section>
    </Page>
  )
}

export function AdminListDetail() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { user } = useAuth()
  const result = useApi<{ list: ListDetail }>(`/admin/lists/${id}`)
  const action = useAction()
  const list = result.data?.list
  return (
    <Page title={list?.title ?? 'List details'}>
      <Link to="/admin/lists">← Lists</Link>
      <LoadState {...result} />
      {action.notice}
      {list && (
        <>
          <section className={s.card}>
            <p>{list.description}</p>
            <p>
              By <Person person={list.owner} /> · {list.visibility} · {list.items_count} titles
            </p>
            <div className={s.bar}>
              {list.visibility === 'public' && (
                <button
                  disabled={action.busy}
                  onClick={() => {
                    if (confirm('Make this list private? The owner will be notified.'))
                      void action.run(
                        () =>
                          mutate(`lists/${id}`, 'patch', {
                            visibility: 'private',
                          }),
                        'List made private.',
                        result.reload,
                      )
                  }}
                >
                  Make private
                </button>
              )}
              {user?.role === 'admin' && (
                <button
                  disabled={action.busy}
                  className={s.danger}
                  onClick={() => {
                    if (confirm('Permanently delete this list and all its items?'))
                      void action.run(
                        () => mutate(`lists/${id}`, 'delete'),
                        'List deleted.',
                        () => navigate('/admin/lists'),
                      )
                  }}
                >
                  Delete list
                </button>
              )}
            </div>
          </section>
          <Reports reports={list.reports} />
          <section className={s.card}>
            <h2>Titles</h2>
            <p className={s.meta}>
              Showing {list.items.length} of {list.items_count} titles (detail limit: 100).
            </p>
            {list.items.map((item) => (
              <div className={s.bar} key={`${item.media_type}:${item.tmdb_id}`}>
                <Link to={`/movies/${item.media_type === 'tv' ? 'tv-' : ''}${item.tmdb_id}`}>
                  {item.title ?? `${item.media_type} #${item.tmdb_id}`}
                </Link>
                <button
                  className={s.danger}
                  disabled={action.busy}
                  onClick={() => {
                    if (confirm('Remove this title from the list?'))
                      void action.run(
                        () => mutate(`lists/${id}/items/${item.tmdb_id}?type=${item.media_type}`, 'delete'),
                        'Title removed.',
                        result.reload,
                      )
                  }}
                >
                  Remove title
                </button>
              </div>
            ))}
            {!list.items.length && <p>No titles.</p>}
          </section>
        </>
      )}
    </Page>
  )
}
function RoomMessages({ room, after }: { room: RoomDetail; after: () => void }) {
  const [page, setPage] = useState(1)
  const result = useStaffPage<StaffMessage>(`rooms/${room.id}/messages`, {
    page,
  })
  const action = useAction()
  return (
    <section className={s.card}>
      <h2>Messages</h2>
      <LoadState {...result} />
      {action.notice}
      <div className={s.list}>
        {result.data?.data.map((m) => (
          <article key={m.id}>
            <p>
              <Person person={m.author} /> · {dateText(m.created_at)} · {m.open_reports_count} reports
            </p>
            <p>{m.body}</p>
            <button
              disabled={action.busy}
              className={s.danger}
              onClick={() => {
                if (confirm('Permanently remove this message?'))
                  void action.run(
                    () => mutate(`rooms/${room.id}/messages/${m.id}`, 'delete'),
                    'Message removed.',
                    () => {
                      result.reload()
                      after()
                    },
                  )
              }}
            >
              Delete message
            </button>
          </article>
        ))}
      </div>
      {result.data?.data.length === 0 && <p>No stored messages.</p>}
      {result.data && <AdminPagination meta={result.data.meta} shown={result.data.data.length} onPage={setPage} />}
    </section>
  )
}
export function AdminRoomDetail() {
  const { id } = useParams()
  const { user } = useAuth()
  const navigate = useNavigate()
  const result = useApi<{ room: RoomDetail }>(`/admin/rooms/${id}`)
  const action = useAction()
  const room = result.data?.room
  return (
    <Page title={room?.name ?? 'Room details'}>
      <Link to="/admin/rooms">← Rooms</Link>
      <LoadState {...result} />
      {action.notice}
      {room && (
        <>
          <section className={s.card}>
            <p>
              Created by <Person person={room.creator} /> · {room.is_private ? 'Private' : 'Public'} ·{' '}
              {room.is_locked ? 'Locked' : 'Unlocked'}
            </p>
            <p>
              {room.film ?? `${room.media_type} #${room.tmdb_id}`} · {room.spoilers ? 'Spoilers allowed' : 'No spoilers'}
            </p>
            <Metrics
              values={{
                members: room.members_count,
                messages: room.messages_count,
                room_reports: room.open_reports_count,
                message_reports: room.open_message_reports_count,
              }}
            />
            <div className={s.bar}>
              <button
                disabled={action.busy}
                onClick={() => {
                  if (confirm(`${room.is_locked ? 'Unlock' : 'Lock'} this room?`))
                    void action.run(
                      () =>
                        mutate(`rooms/${id}`, 'patch', {
                          is_locked: !room.is_locked,
                        }),
                      'Room updated.',
                      result.reload,
                    )
                }}
              >
                {room.is_locked ? 'Unlock room' : 'Lock room'}
              </button>
              {user?.role === 'admin' && (
                <button
                  disabled={action.busy}
                  className={s.danger}
                  onClick={() => {
                    if (confirm('Permanently delete this room and all its messages?'))
                      void action.run(
                        () => mutate(`rooms/${id}`, 'delete'),
                        'Room deleted.',
                        () => navigate('/admin/rooms'),
                      )
                  }}
                >
                  Delete room
                </button>
              )}
            </div>
          </section>
          <section className={s.card}>
            <h2>Members</h2>
            <p className={s.meta}>
              Showing {room.members.length} of {room.members_count} members (limit: 200).
            </p>
            {room.members.map((m) => (
              <p key={m.id}>
                <Person person={m} /> · Joined {dateText(m.joined_at)}
              </p>
            ))}
          </section>
          <Reports reports={room.open_reports} />
          {!room.is_private || room.open_reports_count + room.open_message_reports_count > 0 ? (
            <RoomMessages room={room} after={result.reload} />
          ) : (
            <p className={s.notice}>Private-room messages are available to staff only while there is an open report.</p>
          )}
        </>
      )}
    </Page>
  )
}
