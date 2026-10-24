import googleIcon from '../../assets/auth/icon-google.svg'
import githubIcon from '../../assets/auth/icon-github.svg'
import discordIcon from '../../assets/auth/icon-discord.svg'
import styles from './SocialButton.module.css'

export type SocialProvider = 'google' | 'github' | 'discord'

const PROVIDERS: Record<SocialProvider, { label: string; icon: string }> = {
  google: { label: 'Google', icon: googleIcon },
  github: { label: 'GitHub', icon: githubIcon },
  discord: { label: 'Discord', icon: discordIcon },
}

type SocialButtonProps = {
  provider: SocialProvider
  /** e.g. "Sign in" / "Sign up" — used for the accessible name. */
  action: string
  onSelect: (provider: SocialProvider) => void
}

/** Outlined pill for OAuth providers (Google, GitHub, Discord). */
export default function SocialButton({ provider, action, onSelect }: SocialButtonProps) {
  const { label, icon } = PROVIDERS[provider]
  return (
    <button
      type="button"
      className={styles.button}
      aria-label={`${action} with ${label}`}
      onClick={() => onSelect(provider)}
    >
      <img src={icon} width={20} height={20} alt="" />
      <span>{label}</span>
    </button>
  )
}
