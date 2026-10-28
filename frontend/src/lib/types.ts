// Shapes returned by the Laravel API (backend/app/Http/Controllers, backend/app/Http/Resources).
// Films are TMDB ids; lib/movies.ts turns them into the poster data the components draw.

/** Small public card of a user (MemberResource). */
export type ApiMember = { id: number; username: string; name: string; avatar: string | null }

/** A person in search results (GET /api/users?q=), with the signed-in user's follow state. */
export type ApiSearchUser = ApiMember & { followers: number; is_following: boolean; follows_you: boolean }

export type ApiUserSearch = { total: number; users: ApiSearchUser[] }

export type ApiReview = {
  id: number
  tmdb_id: number
  rating: number
  sentiment: 'positive' | 'neutral' | 'negative'
  content: string
  author: ApiMember | null
  created_at: string
}

export type ApiList = {
  id: number
  title: string
  description: string | null
  is_public: boolean
  films: number
  /** First five films, for covers. */
  posters: number[]
  owner: ApiMember | null
  created_at: string
  updated_at: string
  /** Only with `?contains={tmdbId}`: whether the list already holds that film. */
  has_movie?: boolean
  /** Only on a single list (GET /lists/:id, create, update). */
  items?: { tmdb_id: number; note: string | null; added_at: string | null }[]
}

export type ApiActivity = {
  id: number
  user: ApiMember
  tmdb_id: number | null
  created_at: string
} & (
  | { kind: 'rated' | 'reviewed'; rating: number; review: ApiReview }
  | { kind: 'list'; list: ApiList }
  | { kind: 'watchlist' }
)

export type ApiProfile = {
  user: ApiMember & { bio: string | null; location: string | null; profile_visibility: string; member_since: string | null }
  is_me: boolean
  is_following: boolean
  follows_you: boolean
  /** null for a restricted profile; a section the owner hid has a null count. */
  counts: { followers: number; following: number | null; reviews: number | null; lists: number | null; films: number | null } | null
  restricted: boolean
  activity?: ApiActivity[]
  reviews?: ApiReview[]
  lists?: ApiList[]
  watched?: number[]
  top4?: number[]
  genres?: string[]
  followers?: ApiMember[]
  following?: ApiMember[]
  /** Ids of everyone the signed-in user follows (drives the Follow buttons). */
  my_following?: number[]
}

export type ApiMe = {
  user: {
    id: number
    name: string
    username: string
    email: string
    avatar: string | null
    bio: string | null
    location: string | null
    role: string
    profile_visibility: 'public' | 'followers' | 'private'
    is_active: boolean
    email_verified_at: string | null
    created_at: string
  }
  preferences: {
    favorite_genres: string[]
    privacy: Record<'ratings' | 'reviews' | 'lists' | 'watchlist' | 'history' | 'following', boolean>
    integrations: Record<'calendar' | 'location' | 'weather' | 'digest', boolean>
    notifications: Record<'nights' | 'mentions' | 'social' | 'releases', boolean>
  }
  /** Linked providers → when they were connected. */
  connected: Partial<Record<'google' | 'github' | 'discord', string>>
  counts: { unread_notifications: number; followers: number; following: number }
}

export type ApiWatchItem = {
  id: number
  tmdb_id: number
  status: 'to_watch' | 'watching' | 'watched'
  watched_at: string | null
  created_at: string
}

export type ApiNotification = {
  id: number
  kind: 'invite' | 'vote' | 'mention' | 'follow' | 'like' | 'list' | 'release' | 'watchlist'
  actor: ApiMember | null
  /** Whether you already follow the actor (drives "Follow back"). */
  actor_followed: boolean
  text: string
  target: string | null
  detail: string | null
  to: string
  action: string
  read: boolean
  created_at: string
}

/** A discussion room. Messages are shown for 24 hours, after that only to the members who have yet to read them,
 * so `message_count` and `last_message` describe the messages you can still see. */
export type ApiRoom = {
  id: number
  name: string
  tmdb_id: number
  spoilers: boolean
  /** Private rooms are seen only by their members and the people they invite; public ones by everyone. */
  is_private: boolean
  host: ApiMember | null
  /** The creator and everyone who joined; a message waits for all of them. */
  members: ApiMember[]
  joined: boolean
  /** You are invited to this private room and have not joined or declined yet. */
  invited: boolean
  invited_by: ApiMember | null
  /** People invited who have not answered yet. */
  invitees: ApiMember[]
  message_count: number
  /** Messages still waiting for you to read them. */
  unread: number
  last_message: { author: string | null; content: string; created_at: string } | null
  created_at: string
}

/** A room message with its read receipts: `read_by` starts with the author; it is deleted once its 24 hours are up and `unread_by` is empty. */
export type ApiMessage = {
  id: number
  room_id: number
  user: ApiMember
  content: string
  created_at: string
  read_by: ApiMember[]
  unread_by: ApiMember[]
}

/** `messages.read` on a room's socket channel: `reader` has read these; `deleted_ids` were past their 24 hours and are now deleted. */
export type ApiRoomRead = { reader: ApiMember; message_ids: number[]; deleted_ids: number[] }

export type ApiRsvp = 'pending' | 'accepted' | 'maybe' | 'declined'

export type ApiNight = {
  id: number
  title: string
  scheduled_at: string
  location: string | null
  meeting_link: string | null
  note: string | null
  status: 'pending' | 'confirmed' | 'cancelled'
  winning_tmdb_id: number | null
  organizer: ApiMember
  is_organizer: boolean
  my_rsvp: ApiRsvp | 'host'
  my_vote: number | null
  guests: { user: ApiMember; status: ApiRsvp }[]
  candidates: { tmdb_id: number; added_by: ApiMember | null; voters: ApiMember[] }[]
}

export type ApiHome = {
  /** Not included by the current /api/home response. */
  activity?: ApiActivity[]
  rooms: { id: number; name: string; members: number }[]
  next_night: {
    id: number
    title: string
    scheduled_at: string
    guests: ApiMember[]
    leader: { tmdb_id: number; votes: number } | null
  } | null
}

/** One review on the Movie page (GET /api/reviews, PUT /api/reviews/mine). */
export type ApiMovieReview = {
  id: number
  author: ApiMember
  rating: number
  sentiment: 'positive' | 'neutral' | 'negative' | null
  content: string
  has_spoilers: boolean
  moderation_labels: string[] | null
  helpful: number
  marked_helpful: boolean
  /** Your own review shows "Waiting for approval" until an administrator approves it. */
  approved: boolean
  created_at: string
  updated_at: string
}

/** GET /api/reviews?tmdb_id=&type=&sort=&page= */
export type ApiReviewsPage = {
  summary: {
    average: number | null
    /** Approved star ratings (with or without text). */
    ratings: number
    /** Approved reviews with text. */
    reviews: number
    /** Percent of ratings per star, 5 → 1. */
    distribution: number[]
    sentiment: { positive: number; neutral: number; negative: number }
  }
  mine: ApiMovieReview | null
  reviews: ApiMovieReview[]
  has_more: boolean
}
