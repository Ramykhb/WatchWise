import { Link } from 'react-router'
import Logo from '../../components/Logo/Logo.tsx'
import ButtonPrimary from '../../components/ButtonPrimary/ButtonPrimary.tsx'
import styles from './Placeholder.module.css'

type PlaceholderProps = {
  title: string
  notFound?: boolean
}

/** Temporary screen for routes whose Figma designs aren't implemented yet. */
export default function Placeholder({ title, notFound = false }: PlaceholderProps) {
  return (
    <div className={styles.page}>
      <Logo />
      <main className={styles.content}>
        <h1 className={styles.title}>{title}</h1>
        <p className={styles.text}>
          {notFound ? "We couldn't find that page." : 'This screen is designed and coming soon.'}
        </p>
        <ButtonPrimary label="Back to home" to="/" />
        {!notFound && (
          <Link to="/" className={styles.link}>
            or keep exploring WatchWise
          </Link>
        )}
      </main>
    </div>
  )
}
