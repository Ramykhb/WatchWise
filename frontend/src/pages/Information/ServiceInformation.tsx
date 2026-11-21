import { Link } from 'react-router'
import Logo from '../../components/Logo/Logo.tsx'
import styles from './Information.module.css'

export default function ServiceInformation({ kind }: { kind: 'terms' | 'privacy' }) {
  return <main className={`${styles.main} ${styles.legal}`}>
    <Logo />
    <nav aria-label="Service information"><Link to="/terms">Terms of Service</Link><Link to="/privacy">Privacy Policy</Link><Link to="/">Home</Link></nav>
    <h1>{kind === 'terms' ? 'Terms of Service' : 'Privacy Policy'}</h1>
    <p>WatchWise is a university movie and TV discovery project. These pages describe the service and its account controls.</p>
    {kind === 'terms' ? <>
      <h2>Using WatchWise</h2>
      <p>You can discover titles, save a watchlist, rate and review content, create lists, chat in rooms and organize movie nights. WatchWise provides discovery information and links; it does not stream films or sell cinema tickets. Availability, release dates and third-party information can change.</p>
      <h2>Your account and contributions</h2>
      <p>Keep your sign-in details secure and use an email address you control. Only post content you have permission to share. You remain responsible for your reviews, lists, profile and messages. Content you share is displayed according to your profile and privacy settings.</p>
      <h2>Community rules</h2>
      <p>Be respectful. Do not post harassment, threats, spam, impersonation, unlawful material or someone else's private information. Mark spoilers when the app offers that control. Report inappropriate reviews, profiles, lists, rooms or messages through their report actions.</p>
      <h2>Moderation and availability</h2>
      <p>Staff can review reports, remove content, restrict rooms and disable accounts. Features depend on external services and may be unavailable while those services or this project are being maintained. Recommendations are suggestions based on available information.</p>
      <h2>Account controls</h2>
      <p>You can edit your profile, change sharing and email preferences, and request account deletion in Settings. Read the Privacy Policy for a description of information used by the app.</p>
    </> : <>
      <h2>Information the app uses</h2>
      <p>Account information includes your name, username, email address, password hash, optional profile details and avatar. If you connect Google, the app stores the account identifier needed to recognize that sign-in. WatchWise also stores the preferences, watchlist entries, ratings, reviews, lists, follows, room messages and movie-night details you create.</p>
      <h2>Visibility and sharing</h2>
      <p>Use Settings → Privacy to choose profile visibility and which activity sections other members can see. Public lists and rooms are intended for other members to discover. Private rooms and movie nights are available to their participants; an active movie-night invitation link can allow a signed-in recipient to join. Staff have moderation access. Reports include a snapshot of the reported content.</p>
      <h2>External services</h2>
      <p>Movie and person information and images come from TMDB. Playing a video loads YouTube. Cinema and weather features use external providers; location-based searches send the coordinates you choose or permit to the relevant service. Cinema website, map and calendar links open third-party services governed by their own policies. Email delivery uses the project's configured mail provider. Some recommendation features may use the configured AI service.</p>
      <h2>Cookies and local storage</h2>
      <p>Sign-in uses secure session credentials in cookies, including HttpOnly authentication cookies. The browser also stores preferences and cached catalogue information. Browser location access is requested when you choose a location feature; you can revoke that permission in your browser.</p>
      <h2>Your controls</h2>
      <p>You can edit profile details, remove your own saved content, change visibility and email preferences, and request account deletion from Settings. Deletion requires confirmation through your email. Shared content and reports may be subject to the service's moderation processes. Clearing browser storage removes local cached data but does not delete your server account.</p>
    </>}
    <p><Link to="/settings">Open account settings</Link></p>
  </main>
}
