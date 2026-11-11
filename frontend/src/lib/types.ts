// Shapes returned by the Laravel API (backend/app/Http/Controllers, backend/app/Http/Resources).
// Films are TMDB ids; lib/movies.ts turns them into the poster data the components draw.

import type { TmdbMovie } from '../pages/Discover/discoverApi.ts'

/** Small public card of a user (MemberResource). */
export type ApiMember = { id: number; username: string; name: string; avatar: string | null }

/** A person in search results (GET /api/users?q=), with the signed-in user's follow state. */
export type ApiSearchUser = ApiMember & {
  followers: number
  is_following: boolean
  /** Your follow request is waiting for this followers-only or private profile to answer. */
  is_requested: boolean
  follows_you: boolean
}

/** POST / DELETE /api/users/{username}/follow: following a followers-only or private profile sends a request. */
export type ApiFollowState = { following: boolean; requested: boolean; followers: number }

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
  /** The first five titles with their type (posters holds the same ids without it). */
  cover?: { tmdb_id: number; media_type: 'movie' | 'tv' }[]
  items?: { tmdb_id: number; media_type?: 'movie' | 'tv'; note: string | null; added_at: string | null }[]
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
  /** Your follow request is waiting for this followers-only or private profile to answer. */
  is_requested: boolean
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
    /** One switch per notification type, all on by default. Follow requests have none: they always arrive. */
    notifications: Record<Exclude<ApiNotification['kind'], 'follow_request'>, boolean>
  }
  /** Linked providers → when they were connected. */
  connected: Partial<Record<'google' | 'github' | 'discord', string>>
  /** False for accounts with no password (Google/GitHub/Discord sign-ups until they set one); true otherwise. */
  has_password: boolean
  /** A new sign-in email waiting for its confirmation link to be opened; `user.email` stays in use until then. */
  pending_email: string | null
  /** True while the current address still has to approve the change (no password, or a first one not yet proven); the new one is not mailed yet. */
  pending_email_awaiting_current: boolean
  /** True when deleting the account mails a confirmation link instead of asking for the password (no password of its own, or a first one not yet proven). */
  deletion_by_email: boolean
  /** True while a deletion confirmation link mailed to the account is still valid. */
  deletion_pending: boolean
  counts: { unread_notifications: number; followers: number; following: number }
}

export type ApiWatchItem = {
  id: number
  tmdb_id: number
  /** Missing on rows from before TV shows could be saved (those are movies). */
  media_type?: 'movie' | 'tv'
  status: 'to_watch' | 'watching' | 'watched'
  watched_at: string | null
  created_at: string
}

/**
 * One notification (GET /api/notifications, or pushed live on `notifications.{id}`), shown as
 * "**actor** text **target**" with `detail` (a quote) and `film` (its title is looked up) underneath.
 */
export type ApiNotification = {
  id: number
  kind:
    | 'follow'
    | 'follow_request'
    | 'follow_accepted'
    | 'room_invite'
    | 'room_joined'
    | 'room_deleted'
    | 'new_messages'
    | 'mention'
    | 'night_invite'
    | 'night_rsvp'
    | 'night_vote'
    | 'night_update'
    | 'review_helpful'
  actor: ApiMember
  /** Whether you already follow the actor (drives "Follow back"). */
  actor_followed: boolean
  /** Whether you asked to follow the actor and they haven't answered. */
  actor_requested: boolean
  text: string
  target: string | null
  detail: string | null
  film: { tmdb_id: number; media_type: 'movie' | 'tv' } | null
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
  /** Whether `tmdb_id` is a movie or a TV show (rooms made before this are movies). */
  media_type?: 'movie' | 'tv'
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

export type ApiHomeActivity = {
  id: string
  user: ApiMember
  tmdb_id: number | null
  media_type: 'movie' | 'tv'
  created_at: string
} & (
  | { kind: 'rated' | 'reviewed'; rating: number | null; excerpt: string | null; has_spoilers: boolean }
  | { kind: 'list'; list: { id: number; title: string } }
  | { kind: 'watchlist' | 'watched' }
)

export type ApiHome = {
  activity: ApiHomeActivity[]
  /** Films the admins feature on Home (visible ones only, most recently edited first, at most 10). */
  featured: TmdbMovie[]
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
