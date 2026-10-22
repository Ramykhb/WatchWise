import { useEffect, useRef, useState, type ChangeEvent, type CSSProperties, type FormEvent } from 'react'
import { Link } from 'react-router'
import { useLenis } from 'lenis/react'
import AppNav from '../../components/AppNav/AppNav.tsx'
import PillButton from '../../components/PillButton/PillButton.tsx'
import SplitHeadline from '../../components/SplitHeadline/SplitHeadline.tsx'
import TextField from '../../components/TextField/TextField.tsx'
import Toggle from '../../components/Toggle/Toggle.tsx'
import type { SocialProvider } from '../../components/SocialButton/SocialButton.tsx'
import userIcon from '../../assets/settings/icon-user.svg'
import lockIcon from '../../assets/settings/icon-lock.svg'
import shieldIcon from '../../assets/settings/icon-shield.svg'
import linkIcon from '../../assets/settings/icon-link.svg'
import plugIcon from '../../assets/settings/icon-plug.svg'
import bellIcon from '../../assets/settings/icon-bell.svg'
import trashIcon from '../../assets/settings/icon-trash.svg'
import cameraIcon from '../../assets/settings/icon-camera.svg'
import calendarIcon from '../../assets/home/icon-calendar.svg'
import pinIcon from '../../assets/settings/icon-pin.svg'
import bellVioletIcon from '../../assets/settings/icon-bell-violet.svg'
import chatIcon from '../../assets/settings/icon-chat.svg'
import googleIcon from '../../assets/auth/icon-google.svg'
import githubIcon from '../../assets/auth/icon-github.svg'
import facebookIcon from '../../assets/auth/icon-facebook.svg'
import { useEntranceAnimation } from '../../hooks/useEntranceAnimation.ts'
import {
  ACCOUNT,
  CONNECTED,
  INTEGRATIONS,
  PRIVACY,
  VISIBILITY,
  type IntegrationKey,
  type PrivacyKey,
  type Visibility,
} from './settingsData.ts'
import styles from './Settings.module.css'

type Section = 'profile' | 'account' | 'privacy' | 'connected' | 'integrations'

// Notifications (rendered after this list) has no panel of its own in Figma; it opens Integrations,
// where the email digest lives.
const MENU: { id: Section; label: string; icon: string }[] = [
  { id: 'profile', label: 'Profile', icon: userIcon },
  { id: 'account', label: 'Account & password', icon: lockIcon },
  { id: 'privacy', label: 'Privacy & activity', icon: shieldIcon },
  { id: 'connected', label: 'Connected accounts', icon: linkIcon },
  { id: 'integrations', label: 'Integrations', icon: plugIcon },
]

const PROVIDERS: { id: SocialProvider; label: string; icon: string }[] = [
  { id: 'google', label: 'Google', icon: googleIcon },
  { id: 'github', label: 'GitHub', icon: githubIcon },
  { id: 'facebook', label: 'Facebook', icon: facebookIcon },
]

const INTEGRATION_ICONS: Record<IntegrationKey, string> = {
  calendar: calendarIcon,
  location: pinIcon,
  weather: bellVioletIcon,
  digest: chatIcon,
}

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
const USERNAME_PATTERN = /^@?[a-z0-9_.]{3,20}$/i
const PASSWORD_PATTERN = /^(?=.*\d).{8,}$/

type ProfileErrors = Partial<Record<'name' | 'username' | 'email', string>>
type PasswordErrors = Partial<Record<'current' | 'next' | 'confirm', string>>

/** Menu icons are masks filled with the text colour, so they follow the active item. */
const maskIcon = (icon: string) => ({ '--icon': `url("${icon}")` }) as CSSProperties

export default function Settings() {
  const pageRef = useRef<HTMLDivElement>(null)
  useEntranceAnimation(pageRef)
  const lenis = useLenis()

  const [active, setActive] = useState<Section>('profile')
  const [photo, setPhoto] = useState<string>()
  const [profileErrors, setProfileErrors] = useState<ProfileErrors>({})
  const [profileNote, setProfileNote] = useState('')
  const [passwordErrors, setPasswordErrors] = useState<PasswordErrors>({})
  const [passwordNote, setPasswordNote] = useState('')
  const [visibility, setVisibility] = useState<Visibility>('public')
  const [privacy, setPrivacy] = useState(() => Object.fromEntries(PRIVACY.map((p) => [p.id, p.on])) as Record<PrivacyKey, boolean>)
  const [connected, setConnected] = useState(CONNECTED)
  const [integrations, setIntegrations] = useState(
    () => Object.fromEntries(INTEGRATIONS.map((i) => [i.id, i.on])) as Record<IntegrationKey, boolean>,
  )
  const [locationNote, setLocationNote] = useState('')

  // Highlight the menu item of the panel in the middle of the screen.
  useEffect(() => {
    const observer = new IntersectionObserver(
      (entries) => {
        const visible = entries.find((entry) => entry.isIntersecting)
        if (visible) setActive(visible.target.id as Section)
      },
      { rootMargin: '-35% 0px -60% 0px' },
    )
    pageRef.current?.querySelectorAll('[data-section]').forEach((el) => observer.observe(el))
    // The last panel never reaches the middle of the screen, so the bottom of the page selects it.
    const onScroll = () => {
      if (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 4) setActive('integrations')
    }
    window.addEventListener('scroll', onScroll, { passive: true })
    return () => {
      observer.disconnect()
      window.removeEventListener('scroll', onScroll)
    }
  }, [])

  // Release the previewed photo's object URL when it is replaced or the page closes.
  useEffect(() => () => {
    if (photo) URL.revokeObjectURL(photo)
  }, [photo])

  const goTo = (section: Section) => {
    setActive(section)
    const panel = document.getElementById(section)
    if (!panel) return
    // Lenis owns the page scroll, so a native scrollIntoView would be overridden.
    if (lenis) lenis.scrollTo(panel, { offset: -24 })
    else panel.scrollIntoView({ behavior: 'smooth', block: 'start' })
  }

  const handlePhoto = (event: ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0]
    // TODO: upload the photo to the API (multipart) once the endpoint exists.
    if (file) setPhoto(URL.createObjectURL(file))
    event.target.value = ''
  }

  const handleProfile = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const data = new FormData(event.currentTarget)
    const name = String(data.get('name') ?? '').trim()
    const username = String(data.get('username') ?? '').trim()
    const email = String(data.get('email') ?? '').trim()
    const next: ProfileErrors = {}
    if (name.length < 2) next.name = 'Tell us your name.'
    if (!USERNAME_PATTERN.test(username)) next.username = '3–20 letters, numbers, dots or underscores.'
    if (!EMAIL_PATTERN.test(email)) next.email = 'Enter a valid email address.'
    setProfileErrors(next)
    if (Object.keys(next).length > 0) {
      setProfileNote('')
      return
    }
    // TODO: save through the API (PATCH /api/user) once the endpoint exists.
    setProfileNote('Profile saved.')
  }

  const handlePassword = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const form = event.currentTarget
    const data = new FormData(form)
    const current = String(data.get('current') ?? '')
    const nextPassword = String(data.get('next') ?? '')
    const confirm = String(data.get('confirm') ?? '')
    const next: PasswordErrors = {}
    if (!current) next.current = 'Enter your current password.'
    if (!PASSWORD_PATTERN.test(nextPassword)) next.next = 'Use at least 8 characters, including a number.'
    else if (nextPassword === current) next.next = 'Pick a password you have not used here.'
    if (confirm !== nextPassword) next.confirm = "Passwords don't match."
    setPasswordErrors(next)
    if (Object.keys(next).length > 0) {
      setPasswordNote('')
      return
    }
    // TODO: send to the API (PUT /api/user/password); it signs out other devices.
    form.reset()
    setPasswordNote('Password updated. Other devices have been signed out.')
  }

  const toggleProvider = (provider: SocialProvider) => {
    // TODO: connect through the Laravel Socialite redirect and disconnect through the API.
    setConnected((accounts) => ({ ...accounts, [provider]: accounts[provider] ? null : 'Connected just now' }))
  }

  const toggleIntegration = (id: IntegrationKey, on: boolean) => {
    setIntegrations((current) => ({ ...current, [id]: on }))
    if (id !== 'location' || !on) {
      if (id === 'location') setLocationNote('')
      return
    }
    // Turning location on asks the browser for permission straight away.
    if (!navigator.geolocation) {
      setIntegrations((current) => ({ ...current, location: false }))
      setLocationNote("This browser can't share your location.")
      return
    }
    navigator.geolocation.getCurrentPosition(
      () => setLocationNote('Location allowed. Nearby cinemas will use where you are.'),
      () => {
        setIntegrations((current) => ({ ...current, location: false }))
        setLocationNote('Location permission was blocked. Allow it in your browser to use this.')
      },
    )
  }

  const handleDelete = () => {
    // TODO: call DELETE /api/user and sign out once the endpoint exists.
    window.confirm('Delete your WatchWise account? Your reviews, lists and history will be removed. This cannot be undone.')
  }

  return (
    <div ref={pageRef} className={styles.page}>
      <AppNav />

      <main className={styles.main}>
        <header className={styles.header} data-enter data-enter-delay="0.05">
          <SplitHeadline className={styles.title} lines={[{ text: 'Settings' }]} />
          <p className={styles.subtitle}>Manage your account, password, privacy and connected services.</p>
        </header>

        <div className={styles.body}>
          <nav className={styles.menu} aria-label="Settings sections" data-enter data-enter-delay="0.15">
            {MENU.map((item) => (
              <button
                key={item.id}
                type="button"
                className={styles.menuItem}
                aria-current={active === item.id ? 'true' : undefined}
                onClick={() => goTo(item.id)}
              >
                <span className={styles.menuIcon} style={maskIcon(item.icon)} aria-hidden="true" />
                {item.label}
              </button>
            ))}
            <button type="button" className={styles.menuItem} onClick={() => goTo('integrations')}>
              <span className={styles.menuIcon} style={maskIcon(bellIcon)} aria-hidden="true" />
              Notifications
            </button>
            <button type="button" className={`${styles.menuItem} ${styles.danger}`} onClick={handleDelete}>
              <img src={trashIcon} width={20} height={20} alt="" />
              Delete account
            </button>
          </nav>

          <div className={styles.panels} data-enter data-enter-delay="0.25">
            <section id="profile" data-section className={styles.panel} aria-labelledby="profile-title">
              <PanelHead id="profile-title" title="Profile" text="This is how other members see you." />
              <form className={styles.form} onSubmit={handleProfile} noValidate>
                <div className={styles.avatarRow}>
                  <span className={styles.avatar} aria-hidden={!photo}>
                    {photo ? <img src={photo} alt="Your new profile photo" /> : ACCOUNT.initials}
                  </span>
                  <div className={styles.avatarButtons}>
                    <label className={styles.upload}>
                      <input type="file" accept="image/*" className="sr-only" onChange={handlePhoto} />
                      <img src={cameraIcon} width={16} height={16} alt="" />
                      Upload photo
                    </label>
                    <PillButton variant="outline" disabled={!photo} onClick={() => setPhoto(undefined)}>
                      Remove
                    </PillButton>
                  </div>
                </div>
                <div className={styles.row}>
                  <TextField label="Display name" name="name" defaultValue={ACCOUNT.displayName} autoComplete="name" error={profileErrors.name} />
                  <TextField label="Username" name="username" defaultValue={ACCOUNT.username} autoComplete="username" error={profileErrors.username} />
                </div>
                <div className={styles.row}>
                  <TextField label="Email" name="email" type="email" defaultValue={ACCOUNT.email} autoComplete="email" error={profileErrors.email} />
                  <TextField label="Location" name="location" defaultValue={ACCOUNT.location} autoComplete="address-level2" />
                </div>
                <label className={styles.bio}>
                  <span className={styles.bioLabel}>Bio</span>
                  <textarea name="bio" className={styles.textarea} defaultValue={ACCOUNT.bio} maxLength={160} rows={2} />
                </label>
                <div className={styles.actions}>
                  <PillButton type="submit">Save profile</PillButton>
                  <p className={styles.note} role="status">
                    {profileNote}
                  </p>
                </div>
              </form>
            </section>

            <section id="account" data-section className={styles.panel} aria-labelledby="password-title">
              <PanelHead
                id="password-title"
                title="Password"
                text="Use at least 8 characters with a number. We'll sign you out of other devices after a change."
              />
              <form className={styles.form} onSubmit={handlePassword} noValidate>
                <div className={styles.row3}>
                  <TextField label="Current password" name="current" type="password" placeholder="••••••••••" autoComplete="current-password" revealable error={passwordErrors.current} />
                  <TextField label="New password" name="next" type="password" placeholder="••••••••••••" autoComplete="new-password" revealable error={passwordErrors.next} />
                  <TextField label="Confirm new password" name="confirm" type="password" placeholder="••••••••••••" autoComplete="new-password" revealable error={passwordErrors.confirm} />
                </div>
                <div className={styles.actions}>
                  <PillButton type="submit">Update password</PillButton>
                  <Link to="/forgot-password" className={styles.textLink}>
                    Forgot your password?
                  </Link>
                  <p className={styles.note} role="status">
                    {passwordNote}
                  </p>
                </div>
              </form>
            </section>

            <section id="privacy" data-section className={styles.panel} aria-labelledby="privacy-title">
              <PanelHead id="privacy-title" title="Privacy & activity" text="Choose what appears on your public profile and in followers' feeds." />
              <div className={styles.segmented} role="radiogroup" aria-label="Profile visibility">
                {VISIBILITY.map((option) => (
                  <button
                    key={option.id}
                    type="button"
                    role="radio"
                    aria-checked={visibility === option.id}
                    className={styles.segment}
                    onClick={() => setVisibility(option.id)}
                  >
                    {option.label}
                  </button>
                ))}
              </div>
              <ul className={styles.rows}>
                {PRIVACY.map((item) => (
                  <li key={item.id} className={styles.settingRow}>
                    <span className={styles.rowText}>
                      <span id={`privacy-${item.id}`} className={styles.rowTitle}>
                        {item.title}
                      </span>
                      <span className={styles.rowDesc}>{item.text}</span>
                    </span>
                    <Toggle
                      checked={privacy[item.id]}
                      labelledBy={`privacy-${item.id}`}
                      onChange={(on) => setPrivacy((current) => ({ ...current, [item.id]: on }))}
                    />
                  </li>
                ))}
              </ul>
            </section>

            <section id="connected" data-section className={styles.panel} aria-labelledby="connected-title">
              <PanelHead id="connected-title" title="Connected accounts" text="Sign in faster with Google, GitHub or Facebook." />
              <ul className={styles.accounts}>
                {PROVIDERS.map((provider) => {
                  const account = connected[provider.id]
                  return (
                    <li key={provider.id} className={styles.account}>
                      <img src={provider.icon} width={28} height={28} alt="" />
                      <span className={styles.rowText}>
                        <span className={styles.rowTitle}>{provider.label}</span>
                        <span className={account ? styles.accountOn : styles.rowDesc}>
                          {account ? `Connected · ${account}` : 'Not connected'}
                        </span>
                      </span>
                      <PillButton variant={account ? 'outline' : 'solid'} onClick={() => toggleProvider(provider.id)}>
                        {account ? 'Disconnect' : 'Connect'}
                        <span className="sr-only"> {provider.label}</span>
                      </PillButton>
                    </li>
                  )
                })}
              </ul>
            </section>

            <section id="integrations" data-section className={styles.panel} aria-labelledby="integrations-title">
              <PanelHead id="integrations-title" title="Integrations" text="Optional services that make WatchWise smarter." />
              <ul className={styles.rows}>
                {INTEGRATIONS.map((item) => (
                  <li key={item.id} className={styles.settingRow}>
                    <span className={styles.integrationIcon}>
                      <img src={INTEGRATION_ICONS[item.id]} width={20} height={20} alt="" />
                    </span>
                    <span className={styles.rowText}>
                      <span id={`integration-${item.id}`} className={styles.rowTitle}>
                        {item.title}
                      </span>
                      <span className={styles.rowDesc}>
                        {item.id === 'location' && locationNote ? locationNote : item.text}
                      </span>
                    </span>
                    <Toggle
                      checked={integrations[item.id]}
                      labelledBy={`integration-${item.id}`}
                      onChange={(on) => toggleIntegration(item.id, on)}
                    />
                  </li>
                ))}
              </ul>
            </section>
          </div>
        </div>
      </main>
    </div>
  )
}

function PanelHead({ id, title, text }: { id: string; title: string; text: string }) {
  return (
    <div className={styles.panelHead}>
      <h2 id={id} className={styles.panelTitle}>
        {title}
      </h2>
      <p className={styles.panelText}>{text}</p>
    </div>
  )
}
