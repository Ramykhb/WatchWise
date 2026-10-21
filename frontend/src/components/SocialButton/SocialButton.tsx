import googleIcon from '../../assets/auth/icon-google.svg'
import githubIcon from '../../assets/auth/icon-github.svg'
import facebookIcon from '../../assets/auth/icon-facebook.svg'
import styles from './SocialButton.module.css'

export type SocialProvider = 'google' | 'github' | 'facebook'

const PROVIDERS: Record<SocialProvider, { label: string; icon: string }> = {
  google: { label: 'Google', icon: googleIcon },
  github: { label: 'GitHub', icon: githubIcon },
  facebook: { label: 'Facebook', icon: facebookIcon },
}

type SocialButtonProps = {
  provider: SocialProvider
  /** e.g. "Sign in" / "Sign up" — used for the accessible name. */
  action: string
  onSelect: (provider: SocialProvider) => void
}

/** Outlined pill for OAuth providers (Google, GitHub, Facebook). */
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
