import type { CSSProperties } from 'react'
import calendarIcon from '../../assets/home/icon-calendar-sm.svg'
import crownIcon from '../../assets/nights/icon-crown.svg'
import chatIcon from '../../assets/profile/icon-chat.svg'
import chatSmallIcon from '../../assets/movie/icon-chat-sm.svg'
import userIcon from '../../assets/settings/icon-user.svg'
import lockIcon from '../../assets/settings/icon-lock.svg'
import checkIcon from '../../assets/movie/icon-check.svg'
import roomCheckIcon from '../../assets/rooms/icon-check.svg'
import heartIcon from '../../assets/movie/icon-heart.svg'
import filmIcon from '../../assets/foryou/icon-film.svg'
import trashIcon from '../../assets/settings/icon-trash.svg'
import type { ApiMe, ApiNotification } from '../../lib/types.ts'

// What each kind of notification looks like, shared by the Notifications page, the pop-ups and Settings.

export type NotificationKind = ApiNotification['kind']

/** The notification types a member can switch off (GET/PATCH /api/me › preferences.notifications). */
export type NotifyKey = keyof ApiMe['preferences']['notifications']

/** Icon and circle colour per kind; icons are masks so they all take the same ink colour. */
export const KIND_STYLE: Record<NotificationKind, { icon: string; bg: string; label: string }> = {
  follow: { icon: userIcon, bg: 'var(--surface-violet-tint)', label: 'New follower' },
  follow_request: { icon: lockIcon, bg: 'var(--accent-yellow)', label: 'Follow request' },
  follow_accepted: { icon: checkIcon, bg: 'var(--surface-mint-tint)', label: 'Follow request accepted' },
  room_invite: { icon: chatIcon, bg: 'var(--accent-yellow)', label: 'Room invite' },
  room_joined: { icon: roomCheckIcon, bg: 'var(--surface-mint-tint)', label: 'Room invite accepted' },
  room_deleted: { icon: trashIcon, bg: 'var(--surface-coral-tint)', label: 'Room deleted' },
  new_messages: { icon: chatIcon, bg: 'var(--accent-mint)', label: 'New messages' },
  mention: { icon: chatSmallIcon, bg: 'var(--surface-coral-tint)', label: 'Mention' },
  night_invite: { icon: calendarIcon, bg: 'var(--accent-yellow)', label: 'Movie night invite' },
  night_rsvp: { icon: checkIcon, bg: 'var(--surface-yellow-tint)', label: 'Movie night answer' },
  night_vote: { icon: crownIcon, bg: 'var(--surface-yellow-tint)', label: 'Vote' },
  night_update: { icon: filmIcon, bg: 'var(--surface-cream-deep)', label: 'Movie night update' },
  review_helpful: { icon: heartIcon, bg: 'var(--surface-coral-tint)', label: 'Helpful review' },
  review_removed: { icon: trashIcon, bg: 'var(--surface-coral-tint)', label: 'Review removed' },
}

export const maskIcon = (icon: string) => ({ '--icon': `url("${icon}")` }) as CSSProperties

/** The Notifications page tabs after All and Unread. */
export const TAB_KINDS: Record<'social' | 'rooms' | 'nights', NotificationKind[]> = {
  social: ['follow', 'follow_request', 'follow_accepted', 'review_helpful', 'review_removed'],
  rooms: ['room_invite', 'room_joined', 'room_deleted', 'new_messages', 'mention'],
  nights: ['night_invite', 'night_rsvp', 'night_vote', 'night_update'],
}

/** Settings › Notifications: one switch per type, all on until the member switches them off. */
export const NOTIFICATION_GROUPS: { title: string; items: { id: NotifyKey; title: string; text: string }[] }[] = [
  {
    title: 'Followers',
    items: [
      { id: 'follow', title: 'New followers', text: 'Someone starts following you' },
      { id: 'follow_accepted', title: 'Accepted follow requests', text: 'Someone lets you follow their profile' },
    ],
  },
  {
    title: 'Rooms',
    items: [
      { id: 'new_messages', title: 'New messages', text: 'Messages in rooms you’re a member of, one notification per room' },
      { id: 'mention', title: 'Mentions', text: 'Someone writes your @username in a room' },
      { id: 'room_invite', title: 'Room invites', text: 'Someone invites you to a private room' },
      { id: 'room_joined', title: 'Accepted room invites', text: 'Someone you invited joins the room' },
      { id: 'room_deleted', title: 'Deleted rooms', text: 'A room you’re in is deleted by its creator or an admin' },
    ],
  },
  {
    title: 'Movie nights',
    items: [
      { id: 'night_invite', title: 'Invites', text: 'A host invites you to a movie night' },
      { id: 'night_rsvp', title: 'Guest answers', text: 'Guests say whether they can come to your night' },
      { id: 'night_vote', title: 'Votes', text: 'Guests vote on your night’s shortlist' },
      { id: 'night_update', title: 'Night updates', text: 'The host locks in the pick, cancels, reopens voting or changes the time' },
    ],
  },
  {
    title: 'Reviews',
    items: [{ id: 'review_helpful', title: 'Helpful votes', text: 'Someone finds your review helpful' }],
  },
]

/** Shown under the switches: these can't be switched off. */
export const ALWAYS_ON_NOTE = 'Follow requests always arrive, since they wait for your answer, and so do notices that a moderator removed your review.'
