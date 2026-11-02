import { useRef, useState, type ChangeEvent, type FormEvent } from 'react'
import { Link } from 'react-router'
import AppNav from '../../components/AppNav/AppNav.tsx'
import AvatarImage from '../../components/AvatarImage/AvatarImage.tsx'
import PillButton from '../../components/PillButton/PillButton.tsx'
import SplitHeadline from '../../components/SplitHeadline/SplitHeadline.tsx'
import TextField from '../../components/TextField/TextField.tsx'
import cameraIcon from '../../assets/settings/icon-camera.svg'
import pinIcon from '../../assets/settings/icon-pin.svg'
import { useEntranceAnimation } from '../../hooks/useEntranceAnimation.ts'
import { useAuth } from '../../contexts/useAuth.ts'
import { api, useApi } from '../../lib/api.ts'
import { apiErrorMessage, fieldErrors } from '../../lib/auth.ts'
import { colorOf, initialsOf } from '../../lib/format.ts'
import type { ApiMe } from '../../lib/types.ts'
import settingsStyles from '../Settings/Settings.module.css'
import styles from './EditProfile.module.css'

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
const USERNAME_PATTERN = /^@?[a-z0-9_.]{3,20}$/i
const BIO_MAX = 160

type Fields = { name: string; username: string; email: string; location: string; bio: string }
type ProfileErrors = Partial<Record<'name' | 'username' | 'email', string>>

const fieldsOf = (user: ApiMe['user']): Fields => ({
  name: user.name,
  username: `@${user.username}`,
  email: user.email,
  location: user.location ?? '',
  bio: user.bio ?? '',
})

/** Loads the account (GET /api/me) before showing the form, so its fields start filled in. */
export default function EditProfile() {
  const { data, setData } = useApi<ApiMe>('/me')
  if (!data) {
    return (
      <div className={settingsStyles.page}>
        <AppNav />
        <p className={settingsStyles.subtitle} role="status">
          Loading your profile…
        </p>
      </div>
    )
  }
  return <EditProfilePage key={data.user.id} me={data} onChange={setData} />
}

/** The public profile (photo, name, username, location, bio, email); everything else lives in Settings. */
function EditProfilePage({ me, onChange }: { me: ApiMe; onChange: (me: ApiMe) => void }) {
  const pageRef = useRef<HTMLDivElement>(null)
  useEntranceAnimation(pageRef)
  const { setUser } = useAuth()

  const [fields, setFields] = useState<Fields>(() => fieldsOf(me.user))
  const [saved, setSaved] = useState<Fields>(() => fieldsOf(me.user))
  const [errors, setErrors] = useState<ProfileErrors>({})
  const [note, setNote] = useState('')
  const [saving, setSaving] = useState(false)
  const [photoNote, setPhotoNote] = useState('')
  const photo = me.user.avatar ?? undefined

  const dirty = (Object.keys(fields) as (keyof Fields)[]).some((key) => fields[key].trim() !== saved[key].trim())
  const handle = fields.username.trim().replace(/^@/, '')
  const bioLeft = BIO_MAX - fields.bio.length

  const update = (key: keyof Fields) => (event: ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    const value = event.target.value
    setFields((current) => ({ ...current, [key]: value }))
    setNote('')
    if (key in errors) setErrors((current) => ({ ...current, [key]: undefined }))
  }

  /** Saves a change; the API answers with the whole account, which refreshes the page and header. */
  const apply = async (request: Promise<{ data: ApiMe }>) => {
    const response = await request
    onChange(response.data)
    setUser(response.data.user)
    return response.data
  }

  const handlePhoto = (event: ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0]
    event.target.value = ''
    if (!file) return
    const body = new FormData()
    body.append('avatar', file)
    setPhotoNote('Uploading…')
    apply(api.post('/me/avatar', body))
      .then(() => setPhotoNote('Photo updated.'))
      .catch((error) => setPhotoNote(apiErrorMessage(error, "Couldn't upload that photo (images up to 2 MB).")))
  }

  const removePhoto = () => {
    setPhotoNote('')
    apply(api.delete('/me/avatar')).catch(() => setPhotoNote("Couldn't remove the photo."))
  }

  const discard = () => {
    setFields(saved)
    setErrors({})
    setNote('')
  }

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const name = fields.name.trim()
    const username = fields.username.trim()
    const email = fields.email.trim()
    const next: ProfileErrors = {}
    if (name.length < 2) next.name = 'Tell us your name.'
    if (!USERNAME_PATTERN.test(username)) next.username = '3–20 letters, numbers, dots or underscores.'
    if (!EMAIL_PATTERN.test(email)) next.email = 'Enter a valid email address.'
    setErrors(next)
    if (Object.keys(next).length > 0) {
      setNote('Check the highlighted fields.')
      return
    }
    setSaving(true)
    setNote('Saving…')
    try {
      const account = await apply(
        api.patch('/me', {
          name,
          username,
          email,
          location: fields.location.trim() || null,
          bio: fields.bio.trim() || null,
        }),
      )
      const fresh = fieldsOf(account.user)
      setFields(fresh)
      setSaved(fresh)
      setNote(account.user.email_verified_at ? 'Profile saved.' : 'Profile saved. Check your inbox to confirm your new email.')
    } catch (error) {
      setErrors(fieldErrors(error))
      setNote(apiErrorMessage(error, "Couldn't save your profile."))
    } finally {
      setSaving(false)
    }
  }

  return (
    <div ref={pageRef} className={settingsStyles.page}>
      <AppNav />

      <main className={settingsStyles.main}>
        <header className={`${settingsStyles.header} ${styles.headerRow}`} data-enter data-enter-delay="0.05">
          <div>
            <SplitHeadline className={settingsStyles.title} lines={[{ text: 'Edit profile' }]} />
            <p className={settingsStyles.subtitle}>Choose how you appear to other members.</p>
          </div>
          <Link to="/profile" className={styles.viewLink}>
            View your profile →
          </Link>
        </header>

        <div className={styles.layout}>
          <aside className={styles.preview} aria-label="Profile preview" data-enter data-enter-delay="0.15">
            <div className={styles.cover} aria-hidden="true" />
            <div className={styles.previewBody}>
              <span className={styles.previewAvatar} style={{ background: colorOf(me.user.id) }}>
                <AvatarImage src={photo} alt="Your profile photo" fallback={initialsOf(fields.name || me.user.name)} />
              </span>
              <p className={styles.previewName}>{fields.name.trim() || 'Your name'}</p>
              <p className={styles.previewHandle}>@{handle || 'username'}</p>
              {fields.location.trim() && (
                <p className={styles.previewLocation}>
                  <img src={pinIcon} width={16} height={16} alt="" />
                  {fields.location.trim()}
                </p>
              )}
              <p className={fields.bio.trim() ? styles.previewBio : `${styles.previewBio} ${styles.previewEmpty}`}>
                {fields.bio.trim() || 'Add a short bio so people know what you love to watch.'}
              </p>

              <div className={styles.photoControls}>
                <div className={styles.photoButtons}>
                  <label className={settingsStyles.upload}>
                    <input type="file" accept="image/*" className="sr-only" onChange={handlePhoto} />
                    <img src={cameraIcon} width={16} height={16} alt="" />
                    {photo ? 'Change photo' : 'Upload photo'}
                  </label>
                  {photo && (
                    <PillButton variant="outline" onClick={removePhoto}>
                      Remove
                    </PillButton>
                  )}
                </div>
                <p className={photoNote ? settingsStyles.note : styles.previewCaption} role="status">
                  {photoNote || 'JPG or PNG, up to 2 MB. Your photo saves as soon as you pick it.'}
                </p>
              </div>
            </div>
          </aside>

          <section className={`${settingsStyles.panel} ${styles.formPanel}`} aria-label="Profile details" data-enter data-enter-delay="0.25">
            <form className={settingsStyles.form} onSubmit={handleSubmit} noValidate>
              <div className={styles.group} role="group" aria-labelledby="about-title">
                <div className={styles.groupHead}>
                  <h2 id="about-title" className={styles.groupTitle}>
                    About you
                  </h2>
                  <p className={styles.groupText}>Shown on your profile, in reviews and in rooms.</p>
                </div>
                <div className={settingsStyles.row}>
                  <TextField label="Display name" name="name" value={fields.name} onChange={update('name')} autoComplete="name" error={errors.name} />
                  <TextField
                    label="Username"
                    name="username"
                    value={fields.username}
                    onChange={update('username')}
                    autoComplete="username"
                    hint={errors.username ? undefined : `Your profile link: /profile/${handle || 'username'}`}
                    error={errors.username}
                  />
                </div>
                <TextField
                  label="Location"
                  name="location"
                  value={fields.location}
                  onChange={update('location')}
                  placeholder="City, country"
                  autoComplete="address-level2"
                />
                <div className={settingsStyles.bio}>
                  <div className={styles.bioHead}>
                    <label htmlFor="profile-bio" className={settingsStyles.bioLabel}>
                      Bio
                    </label>
                    <span id="profile-bio-count" className={bioLeft <= 20 ? `${styles.counter} ${styles.counterNear}` : styles.counter}>
                      {fields.bio.length}/{BIO_MAX}
                    </span>
                  </div>
                  <textarea
                    id="profile-bio"
                    name="bio"
                    aria-describedby="profile-bio-count"
                    className={settingsStyles.textarea}
                    value={fields.bio}
                    onChange={update('bio')}
                    maxLength={BIO_MAX}
                    rows={3}
                    placeholder="Favourite genres, directors, comfort films…"
                  />
                </div>
              </div>

              <div className={styles.group} role="group" aria-labelledby="email-title">
                <div className={styles.groupHead}>
                  <h2 id="email-title" className={styles.groupTitle}>
                    Sign-in email
                  </h2>
                  <p className={styles.groupText}>Only you can see this. Changing it sends a confirmation link to the new address.</p>
                </div>
                <TextField label="Email" name="email" type="email" value={fields.email} onChange={update('email')} autoComplete="email" error={errors.email} />
              </div>

              <div className={styles.saveBar}>
                <PillButton type="submit" disabled={!dirty || saving}>
                  {saving ? 'Saving…' : 'Save changes'}
                </PillButton>
                {dirty && !saving && (
                  <button type="button" className={styles.discard} onClick={discard}>
                    Discard changes
                  </button>
                )}
                <p className={note ? settingsStyles.note : styles.dirtyNote} role="status">
                  {note || (dirty ? 'You have unsaved changes.' : '')}
                </p>
              </div>
            </form>
          </section>
        </div>
      </main>
    </div>
  )
}
