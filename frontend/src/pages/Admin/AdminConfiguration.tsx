import { useState } from 'react'
import { useApi } from '../../lib/api.ts'
import { useAuth } from '../../contexts/useAuth.ts'
import { mutate, type Genre, type Announcement, type SettingEntry } from '../../lib/adminTools.ts'
import { Page, Field, Select, Person, LoadState } from './AdminToolsUI.tsx'
import { useAction, dateText, s } from './adminToolsState.tsx'
function GenreEditor({ genre, type, reload, editable }: { genre: Genre; type: string; reload: () => void; editable: boolean }) {
  const [name, setName] = useState(genre.name_override ?? '')
  const [position, setPosition] = useState(genre.position?.toString() ?? '')
  const [hidden, setHidden] = useState(genre.is_hidden)
  const action = useAction()
  return (
    <form
      className={s.card}
      onSubmit={(e) => {
        e.preventDefault()
        void action.run(
          () =>
            mutate(`genres/${type}/${genre.tmdb_id}`, 'patch', {
              name: name.trim() || null,
              is_hidden: hidden,
              position: position === '' ? null : Number(position),
            }),
          'Genre saved.',
          reload,
        )
      }}
    >
      <h2>{genre.tmdb_name}</h2>
      <p className={s.meta}>
        TMDB #{genre.tmdb_id} · {genre.movie_count} local movies · Updated {dateText(genre.updated_at)}
      </p>
      <div className={s.bar}>
        <Field label="Display name">
          <input
            maxLength={60}
            disabled={!editable || action.busy}
            value={name}
            placeholder={genre.tmdb_name}
            onChange={(e) => setName(e.target.value)}
          />
        </Field>
        <Field label="Position (optional)">
          <input
            type="number"
            min="0"
            max="999"
            disabled={!editable || action.busy}
            value={position}
            onChange={(e) => setPosition(e.target.value)}
          />
        </Field>
        <label className={s.check}>
          <input type="checkbox" disabled={!editable || action.busy} checked={hidden} onChange={(e) => setHidden(e.target.checked)} />
          Hidden from genre selection
        </label>
      </div>
      {editable && (
        <div className={s.bar}>
          <button disabled={action.busy}>Save</button>
          <button
            type="button"
            disabled={action.busy}
            onClick={() => {
              if (confirm('Reset this genre to its TMDB defaults?'))
                void action.run(() => mutate(`genres/${type}/${genre.tmdb_id}`, 'delete'), 'Genre reset.', reload)
            }}
          >
            Reset override
          </button>
        </div>
      )}
      {action.notice}
    </form>
  )
}
export function AdminGenres() {
  const { user } = useAuth()
  const [type, setType] = useState('movie')
  const result = useApi<{ genres: Genre[] }>(`/admin/genres?type=${type}`)
  return (
    <Page
      title="Genres"
      lead={
        user?.role === 'admin'
          ? 'Manage display names, visibility and order for movies and TV.'
          : 'Inspect genre overrides. Administrators manage these settings.'
      }
    >
      <Select label="Media" value={type} options={['movie', 'tv']} onChange={setType} />
      <LoadState {...result} />
      <div className={s.grid}>
        {result.data?.genres.map((g) => (
          <GenreEditor
            key={`${type}:${g.tmdb_id}:${g.updated_at ?? 'default'}`}
            genre={g}
            type={type}
            reload={result.reload}
            editable={user?.role === 'admin'}
          />
        ))}
      </div>
    </Page>
  )
}
const labels: Record<string, string> = {
  registration_open: 'Allow new accounts',
  reviews_require_approval: 'Require staff approval for every written review',
  review_auto_moderation: 'Automatically classify written reviews',
  rooms_creation_enabled: 'Allow members to create rooms',
}
function SettingEditor({ name, entry, reload }: { name: string; entry: SettingEntry; reload: () => void }) {
  const [value, setValue] = useState(entry.value)
  const action = useAction()
  const announcement = typeof value === 'object' ? value : null
  const update = (fields: Partial<Announcement>) => {
    if (announcement) setValue({ ...announcement, ...fields })
  }
  return (
    <form
      className={s.card}
      onSubmit={(e) => {
        e.preventDefault()
        void action.run(() => mutate('settings', 'patch', { [name]: value }), 'Setting saved.', reload)
      }}
    >
      <h2>{labels[name] ?? 'Public announcement'}</h2>
      {typeof value === 'boolean' ? (
        <label className={s.check}>
          <input disabled={action.busy} type="checkbox" checked={value} onChange={(e) => setValue(e.target.checked)} />
          Enabled
        </label>
      ) : (
        announcement && (
          <div className={s.list}>
            <label className={s.check}>
              <input type="checkbox" checked={announcement.enabled} onChange={(e) => update({ enabled: e.target.checked })} />
              Show announcement
            </label>
            <Field label="Message">
              <textarea
                required={announcement.enabled}
                maxLength={300}
                value={announcement.message}
                onChange={(e) => update({ message: e.target.value })}
              />
            </Field>
            <Select
              label="Level"
              value={announcement.level}
              options={['info', 'warning', 'critical']}
              onChange={(level) => update({ level })}
            />
            <Field label="Expiry (optional; your local time)">
              <input
                type="datetime-local"
                value={announcement.expires_at ? localDate(announcement.expires_at) : ''}
                onChange={(e) =>
                  update({
                    expires_at: e.target.value ? new Date(e.target.value).toISOString() : null,
                  })
                }
              />
            </Field>
          </div>
        )
      )}
      <p className={s.meta}>
        Updated {dateText(entry.updated_at)} by {entry.updated_by ? <Person person={entry.updated_by} /> : 'default / deleted member'}
      </p>
      <button disabled={action.busy}>Save setting</button>
      {action.notice}
    </form>
  )
}
function localDate(value: string) {
  const date = new Date(value)
  return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16)
}
export function AdminSettings() {
  const result = useApi<{ settings: Record<string, SettingEntry> }>('/admin/settings')
  return (
    <Page title="App settings" lead="Changes apply across WatchWise. Each setting is saved separately.">
      <LoadState {...result} />
      <div className={s.grid}>
        {Object.entries(result.data?.settings ?? {}).map(([name, entry]) => (
          <SettingEditor key={`${name}:${entry.updated_at}`} name={name} entry={entry} reload={result.reload} />
        ))}
      </div>
    </Page>
  )
}
