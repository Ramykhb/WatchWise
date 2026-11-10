import { SettingsSkeleton } from '../../components/LoadingState/ScreenSkeletons.tsx'
import LoadError from '../../components/LoadingState/LoadError.tsx'
import { useEffect, useRef, useState, type CSSProperties, type FormEvent } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router'
import { useLenis } from 'lenis/react'
import AppNav from '../../components/AppNav/AppNav.tsx'
import PillButton from '../../components/PillButton/PillButton.tsx'
import SplitHeadline from '../../components/SplitHeadline/SplitHeadline.tsx'
import TextField from '../../components/TextField/TextField.tsx'
import Toggle from '../../components/Toggle/Toggle.tsx'
import type { SocialProvider } from '../../components/SocialButton/SocialButton.tsx'
import lockIcon from '../../assets/settings/icon-lock.svg'
import shieldIcon from '../../assets/settings/icon-shield.svg'
import linkIcon from '../../assets/settings/icon-link.svg'
import plugIcon from '../../assets/settings/icon-plug.svg'
import bellIcon from '../../assets/settings/icon-bell.svg'
import trashIcon from '../../assets/settings/icon-trash.svg'
import calendarIcon from '../../assets/home/icon-calendar.svg'
import pinIcon from '../../assets/settings/icon-pin.svg'
import bellVioletIcon from '../../assets/settings/icon-bell-violet.svg'
import chatIcon from '../../assets/settings/icon-chat.svg'
import googleIcon from '../../assets/auth/icon-google.svg'
import githubIcon from '../../assets/auth/icon-github.svg'
import discordIcon from '../../assets/auth/icon-discord.svg'
import { useEntranceAnimation } from '../../hooks/useEntranceAnimation.ts'
import { INTEGRATIONS, PRIVACY, VISIBILITY, type IntegrationKey, type Visibility } from './settingsData.ts'
import { accountDeletionNotice, deleteAccountPrompt, pendingDeletionMessage } from './accountDeletion.ts'
import { FOLLOW_REQUEST_NOTE, NOTIFICATION_GROUPS } from '../Notifications/notificationKinds.ts'
import styles from './Settings.module.css'
import { useAuth } from '../../contexts/useAuth.ts'
import { api, useApi } from '../../lib/api.ts'
import { apiErrorMessage, fieldErrors } from '../../lib/auth.ts'
import type { ApiMe } from '../../lib/types.ts'

type Section = 'account' | 'privacy' | 'connected' | 'integrations' | 'notifications'

// The profile lives on its own Edit profile page.
const MENU: { id: Section; label: string; icon: string }[] = [
  { id: 'account', label: 'Account & password', icon: lockIcon },
  { id: 'privacy', label: 'Privacy & activity', icon: shieldIcon },
  { id: 'connected', label: 'Connected accounts', icon: linkIcon },
  { id: 'integrations', label: 'Integrations', icon: plugIcon },
  { id: 'notifications', label: 'Notifications', icon: bellIcon },
]

const isSection = (id: string): id is Section => MENU.some((item) => item.id === id)

const PROVIDERS: { id: SocialProvider; label: string; icon: string }[] = [
  { id: 'google', label: 'Google', icon: googleIcon },
  { id: 'github', label: 'GitHub', icon: githubIcon },
  { id: 'discord', label: 'Discord', icon: discordIcon },
]

const INTEGRATION_ICONS: Record<IntegrationKey, string> = {
  calendar: calendarIcon,
  location: pinIcon,
  weather: bellVioletIcon,
  digest: chatIcon,
}

const PASSWORD_PATTERN = /^(?=.*\d).{8,}$/

type PasswordErrors = Partial<Record<'current' | 'next' | 'confirm', string>>

/** Menu icons are masks filled with the text colour, so they follow the active item. */
const maskIcon = (icon: string) => ({ '--icon': `url("${icon}")` }) as CSSProperties

const backendUrl = (import.meta.env.VITE_BACKEND_URL ?? 'http://localhost:8000').replace(/\/$/, '')

/** Loads the account (GET /api/me) before showing the forms, so their fields start filled in. */
export default function Settings() {
  const { data, setData, error, loading, reload } = useApi<ApiMe>('/me')
  if (error && !loading) return <LoadError label="Couldn’t load your settings." onRetry={reload} />
  if (!data) return <SettingsSkeleton />
  return <SettingsPage key={data.user.id} me={data} onChange={setData} />
}

function SettingsPage({ me, onChange }: { me: ApiMe; onChange: (me: ApiMe) => void }) {
  const pageRef = useRef<HTMLDivElement>(null)
  useEntranceAnimation(pageRef)
  const lenis = useLenis()
  const navigate = useNavigate()
  const { setUser, logout } = useAuth()

  const [active, setActive] = useState<Section>('account')
  const [passwordErrors, setPasswordErrors] = useState<PasswordErrors>({})
  const [passwordNote, setPasswordNote] = useState('')
  const [settingsNote, setSettingsNote] = useState('')
  // The panel whose change the note is about.
  const [notePanel, setNotePanel] = useState<'privacy' | 'connected' | 'notifications'>('privacy')
  // An account-deletion link lands here with ?account_deletion=…; show it once, then drop it from the URL.
  const [params, setParams] = useSearchParams()
  const [deletionNote, setDeletionNote] = useState(() => accountDeletionNotice(params.get('account_deletion')) ?? '')
  useEffect(() => {
    if (params.has('account_deletion')) {
      setParams((current) => {
        const next = new URLSearchParams(current)
        next.delete('account_deletion')
        return next
      }, { replace: true })
    }
  }, [params, setParams])
  const [locationNote, setLocationNote] = useState('')
  const visibility = me.user.profile_visibility
  const privacy = me.preferences.privacy
  const connected = me.connected
  // Social sign-ups have no password of their own yet, so there is no current one to ask for.
  const hasPassword = me.has_password
  const integrations = me.preferences.integrations
  const notifications = me.preferences.notifications

  /** Saves a change; the API answers with the whole account, which refreshes the page and header. */
  const apply = async (request: Promise<{ data: ApiMe }>) => {
    const response = await request
    onChange(response.data)
    setUser(response.data.user)
    return response.data
  }
  const saveSettings = (body: Partial<Record<'privacy' | 'integrations' | 'notifications', Record<string, boolean>>>) => {
    setSettingsNote('')
    setNotePanel(body.notifications ? 'notifications' : 'privacy')
    onChange({ ...me, preferences: { ...me.preferences, ...Object.fromEntries(Object.entries(body).map(([k, v]) => [k, { ...me.preferences[k as 'privacy'], ...v }])) } })
    apply(api.patch('/me/preferences', body)).catch(() => {
      onChange(me)
      setSettingsNote("Couldn't save that setting. Please try again.")
    })
  }

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
      if (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 4) setActive('notifications')
    }
    window.addEventListener('scroll', onScroll, { passive: true })
    return () => {
      observer.disconnect()
      window.removeEventListener('scroll', onScroll)
    }
  }, [])

  const goTo = (section: Section) => {
    setActive(section)
    const panel = document.getElementById(section)
    if (!panel) return
    // Lenis owns the page scroll, so a native scrollIntoView would be overridden.
    if (lenis) lenis.scrollTo(panel, { offset: -24 })
    else panel.scrollIntoView({ behavior: 'smooth', block: 'start' })
  }

  // Links such as the Notifications page's "All notification settings" open a section (/settings#notifications).
  useEffect(() => {
    const section = window.location.hash.slice(1)
    if (!lenis || !isSection(section)) return
    const frame = requestAnimationFrame(() => {
      const panel = document.getElementById(section)
      if (!panel) return
      // The page has only just grown from its loading state, so Lenis measures it again first.
      lenis.resize()
      lenis.scrollTo(panel, { offset: -24 })
      setActive(section)
    })
    return () => cancelAnimationFrame(frame)
  }, [lenis])

  const handlePassword = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const form = event.currentTarget
    const data = new FormData(form)
    const current = String(data.get('current') ?? '')
    const nextPassword = String(data.get('next') ?? '')
    const confirm = String(data.get('confirm') ?? '')
    const next: PasswordErrors = {}
    if (hasPassword && !current) next.current = 'Enter your current password.'
    if (!PASSWORD_PATTERN.test(nextPassword)) next.next = 'Use at least 8 characters, including a number.'
    else if (hasPassword && nextPassword === current) next.next = 'Pick a password you have not used here.'
    if (confirm !== nextPassword) next.confirm = "Passwords don't match."
    setPasswordErrors(next)
    if (Object.keys(next).length > 0) {
      setPasswordNote('')
      return
    }
    setPasswordNote('Saving…')
    try {
      await api.put('/me/password', {
        ...(hasPassword ? { current_password: current } : {}),
        password: nextPassword,
        password_confirmation: confirm,
      })
      form.reset()
      setPasswordNote(hasPassword ? 'Password updated. Other devices have been signed out.' : 'Password created. You can now also sign in with your email.')
      if (!hasPassword) onChange({ ...me, has_password: true })
    } catch (error) {
      const errors = fieldErrors(error)
      setPasswordErrors({ current: errors.current_password, next: errors.password })
      setPasswordNote(apiErrorMessage(error, "Couldn't update your password."))
    }
  }

  const toggleProvider = (provider: SocialProvider) => {
    if (connected[provider]) {
      setSettingsNote('')
      apply(api.delete(`/me/social/${provider}`)).catch((error) => {
        setNotePanel('connected')
        // The API refuses to remove the last way to sign in of an account without a password, and says why.
        setSettingsNote(apiErrorMessage(error, `Couldn't disconnect ${provider}.`))
      })
      return
    }
    // Linking goes through the provider's sign-in; an account with the same verified email is linked.
    window.location.assign(`${backendUrl}/api/auth/social/${provider}`)
  }

  const toggleIntegration = (id: IntegrationKey, on: boolean) => {
    if (id !== 'location' || !on) {
      if (id === 'location') setLocationNote('')
      saveSettings({ integrations: { [id]: on } })
      return
    }
    // Turning location on asks the browser for permission straight away.
    if (!navigator.geolocation) {
      setLocationNote("This browser can't share your location.")
      return
    }
    navigator.geolocation.getCurrentPosition(
      () => {
        saveSettings({ integrations: { location: true } })
        setLocationNote('Location allowed. Nearby cinemas will use where you are.')
      },
      () => setLocationNote('Location permission was blocked. Allow it in your browser to use this.'),
    )
  }

  const setVisibility = (value: Visibility) => {
    setSettingsNote('')
    setNotePanel('privacy')
    apply(api.patch('/me', { profile_visibility: value })).catch(() => setSettingsNote("Couldn't change who can see your profile."))
  }

  // Accounts whose password proves nothing confirm from their inbox: the API mails a link (202) instead.
  const handleDelete = async () => {
    const byEmail = me.deletion_by_email
    if (!window.confirm(deleteAccountPrompt(byEmail, me.user.email))) return
    const password = byEmail ? null : window.prompt('Enter your password to delete your account.')
    if (!byEmail && !password) return
    try {
      const response = await api.delete<{ message: string }>('/me', { data: byEmail ? {} : { password } })
      if (response.status === 202) {
        onChange({ ...me, deletion_pending: true })
        setDeletionNote('')
        goTo('account')
        return
      }
      await logout().catch(() => undefined)
      navigate('/signin?account_deletion=deleted', { replace: true })
    } catch (error) {
      window.alert(apiErrorMessage(error, "Couldn't delete your account."))
    }
  }

  const keepAccount = () => {
    setDeletionNote('')
    apply(api.delete('/me/delete-request'))
      .then(() => setDeletionNote(accountDeletionNotice('cancelled') ?? ''))
      .catch((error) => setDeletionNote(apiErrorMessage(error, "Couldn't cancel the deletion. Please try again.")))
  }

  return (
    <div ref={pageRef} className={styles.page}>
      <AppNav />

      <main className={styles.main}>
        <header className={styles.header} data-enter data-enter-delay="0.05">
          <SplitHeadline className={styles.title} lines={[{ text: 'Settings' }]} />
          <p className={styles.subtitle}>Manage your password, privacy, connected services and notifications.</p>
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
            <button type="button" className={`${styles.menuItem} ${styles.danger}`} onClick={handleDelete}>
              <img src={trashIcon} width={20} height={20} alt="" />
              Delete account
            </button>
          </nav>

          <div className={styles.panels} data-enter data-enter-delay="0.25">
            <section id="account" data-section className={styles.panel} aria-labelledby="password-title">
              <PanelHead
                id="password-title"
                title={hasPassword ? 'Password' : 'Create a password'}
                text={
                  hasPassword
                    ? "Use at least 8 characters with a number. We'll sign you out of other devices after a change."
                    : "You signed up with a connected account, so you don't have a password yet. Create one to also sign in with your email."
                }
              />
              <form className={styles.form} onSubmit={handlePassword} noValidate>
                <div className={styles.row3}>
                  <TextField
                    label="Current password"
                    name="current"
                    type="password"
                    placeholder={hasPassword ? '••••••••••' : 'Not needed yet'}
                    autoComplete="current-password"
                    revealable
                    disabled={!hasPassword}
                    error={passwordErrors.current}
                  />
                  <TextField label="New password" name="next" type="password" placeholder="••••••••••••" autoComplete="new-password" revealable error={passwordErrors.next} />
                  <TextField label="Confirm new password" name="confirm" type="password" placeholder="••••••••••••" autoComplete="new-password" revealable error={passwordErrors.confirm} />
                </div>
                <div className={styles.actions}>
                  <PillButton type="submit">{hasPassword ? 'Update password' : 'Create password'}</PillButton>
                  <Link to="/forgot-password" className={styles.textLink}>
                    Forgot your password?
                  </Link>
                  <p className={styles.note} role="status">
                    {passwordNote}
                  </p>
                </div>
              </form>
              {me.deletion_pending && (
                <div className={styles.pendingDeletion}>
                  <p className={styles.rowDesc}>{pendingDeletionMessage(me.user.email)}</p>
                  <PillButton variant="outline" onClick={keepAccount}>
                    Keep my account
                  </PillButton>
                </div>
              )}
              <p className={styles.note} role="status">
                {deletionNote}
              </p>
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
                      onChange={(on) => saveSettings({ privacy: { [item.id]: on } })}
                    />
                  </li>
                ))}
              </ul>
              <p className={styles.note} role="status">
                {notePanel === 'privacy' ? settingsNote : ''}
              </p>
            </section>

            <section id="connected" data-section className={styles.panel} aria-labelledby="connected-title">
              <PanelHead id="connected-title" title="Connected accounts" text="Sign in faster with Google, GitHub or Discord." />
              <ul className={styles.accounts}>
                {PROVIDERS.map((provider) => {
                  const account = connected[provider.id]
                  return (
                    <li key={provider.id} className={styles.account}>
                      <img src={provider.icon} width={28} height={28} alt="" />
                      <span className={styles.rowText}>
                        <span className={styles.rowTitle}>{provider.label}</span>
                        <span className={account ? styles.accountOn : styles.rowDesc}>
                          {account ? `Connected · since ${new Date(account).toLocaleDateString('en-US', { month: 'short', year: 'numeric' })}` : 'Not connected'}
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
              <p className={styles.note} role="status">
                {notePanel === 'connected' ? settingsNote : ''}
              </p>
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

            <section id="notifications" data-section className={styles.panel} aria-labelledby="notifications-title">
              <PanelHead
                id="notifications-title"
                title="Notifications"
                text="Choose what you hear about, in the bell and as pop-ups. Everything is on until you switch it off."
              />
              {NOTIFICATION_GROUPS.map((group) => (
                <div key={group.title} className={styles.rowGroup}>
                  <h3 className={styles.rowGroupTitle}>{group.title}</h3>
                  <ul className={styles.rows}>
                    {group.items.map((item) => (
                      <li key={item.id} className={styles.settingRow}>
                        <span className={styles.rowText}>
                          <span id={`notify-${item.id}`} className={styles.rowTitle}>
                            {item.title}
                          </span>
                          <span className={styles.rowDesc}>{item.text}</span>
                        </span>
                        <Toggle
                          checked={notifications[item.id]}
                          labelledBy={`notify-${item.id}`}
                          onChange={(on) => saveSettings({ notifications: { [item.id]: on } })}
                        />
                      </li>
                    ))}
                  </ul>
                </div>
              ))}
              <p className={styles.rowDesc}>{FOLLOW_REQUEST_NOTE}</p>
              <div className={styles.actions}>
                <Link to="/notifications" className={styles.textLink}>
                  See your notifications →
                </Link>
                <p className={styles.note} role="status">
                  {notePanel === 'notifications' ? settingsNote : ''}
                </p>
              </div>
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
