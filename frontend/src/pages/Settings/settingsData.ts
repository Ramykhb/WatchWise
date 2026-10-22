import type { SocialProvider } from '../../components/SocialButton/SocialButton.tsx'

// Mock content from the Figma "Settings — Account & Privacy" frame. Replace with the user settings API.

export const ACCOUNT = {
  initials: 'MR',
  displayName: 'Maya Rivera',
  username: '@maya',
  email: 'maya@watchwise.app',
  location: 'Beirut, Lebanon',
  bio: 'Sci-fi devotee. Will cry at any ocean movie.',
}

export type Visibility = 'public' | 'followers' | 'private'

export const VISIBILITY: { id: Visibility; label: string }[] = [
  { id: 'public', label: 'Public' },
  { id: 'followers', label: 'Followers only' },
  { id: 'private', label: 'Private' },
]

export type PrivacyKey = 'ratings' | 'reviews' | 'lists' | 'watchlist' | 'history' | 'following'

export const PRIVACY: { id: PrivacyKey; title: string; text: string; on: boolean }[] = [
  { id: 'ratings', title: 'Ratings', text: 'Show the stars you give films', on: true },
  { id: 'reviews', title: 'Reviews', text: 'Show reviews you write', on: true },
  { id: 'lists', title: 'Public lists', text: "Show lists you've marked public", on: true },
  { id: 'watchlist', title: 'Watchlist', text: 'Let others see what you plan to watch', on: false },
  { id: 'history', title: 'Watch history', text: "Show films you've marked as watched", on: true },
  { id: 'following', title: 'Following', text: 'Let others see who you follow', on: false },
]

/** Linked sign-in accounts: the account shown when connected, or null. */
export const CONNECTED: Record<SocialProvider, string | null> = {
  google: 'maya.rivera@gmail.com',
  github: '@mayarivera',
  facebook: null,
}

export type IntegrationKey = 'calendar' | 'location' | 'weather' | 'digest'

export const INTEGRATIONS: { id: IntegrationKey; title: string; text: string; on: boolean }[] = [
  { id: 'calendar', title: 'Google Calendar', text: 'Add releases and movie nights to your calendar', on: true },
  { id: 'location', title: 'Location', text: 'Find nearby cinemas and showtimes', on: true },
  { id: 'weather', title: 'Weather-based suggestions', text: 'Adapt picks to the weather where you are', on: true },
  { id: 'digest', title: 'Email digest', text: "Weekly picks and friends' activity", on: false },
]
