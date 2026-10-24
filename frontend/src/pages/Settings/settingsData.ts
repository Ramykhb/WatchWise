// Labels for the Settings switches; their values come from GET /api/me (preferences).

export type Visibility = 'public' | 'followers' | 'private'

export const VISIBILITY: { id: Visibility; label: string }[] = [
  { id: 'public', label: 'Public' },
  { id: 'followers', label: 'Followers only' },
  { id: 'private', label: 'Private' },
]

export type PrivacyKey = 'ratings' | 'reviews' | 'lists' | 'watchlist' | 'history' | 'following'

export const PRIVACY: { id: PrivacyKey; title: string; text: string }[] = [
  { id: 'ratings', title: 'Ratings', text: 'Show the stars you give films' },
  { id: 'reviews', title: 'Reviews', text: 'Show reviews you write' },
  { id: 'lists', title: 'Public lists', text: "Show lists you've marked public" },
  { id: 'watchlist', title: 'Watchlist', text: 'Let others see what you plan to watch' },
  { id: 'history', title: 'Watch history', text: "Show films you've marked as watched" },
  { id: 'following', title: 'Following', text: 'Let others see who you follow' },
]

export type IntegrationKey = 'calendar' | 'location' | 'weather' | 'digest'

export const INTEGRATIONS: { id: IntegrationKey; title: string; text: string }[] = [
  { id: 'calendar', title: 'Google Calendar', text: 'Add releases and movie nights to your calendar' },
  { id: 'location', title: 'Location', text: 'Find nearby cinemas and showtimes' },
  { id: 'weather', title: 'Weather-based suggestions', text: 'Adapt picks to the weather where you are' },
  { id: 'digest', title: 'Email digest', text: "Weekly picks and friends' activity" },
]
