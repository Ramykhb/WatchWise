import { Link } from 'react-router'
import AppNav from '../../components/AppNav/AppNav.tsx'
import PillButton from '../../components/PillButton/PillButton.tsx'
import { useApi } from '../../lib/api.ts'
import type { ApiListsPage, ApiRoom, ApiUserSearch } from '../../lib/types.ts'
import { listSlug } from '../Profile/profileData.ts'
import styles from './Information.module.css'

export default function Community() {
  const rooms = useApi<{ rooms: ApiRoom[] }>('/rooms')
  const lists = useApi<ApiListsPage>('/lists?scope=public&per_page=12')
  const members = useApi<ApiUserSearch>('/users?limit=12')
  return <><AppNav /><main className={styles.main}>
    <h1>Find your film people.</h1>
    <p>Explore public conversations, browse members' lists and find people to follow.</p>
    <section><h2>Public rooms</h2>
      {rooms.loading && <p role="status">Loading rooms…</p>}
      {Boolean(rooms.error) && <p role="alert">Couldn't load rooms. <PillButton onClick={rooms.reload}>Try again</PillButton></p>}
      <div className={styles.grid}>{rooms.data?.rooms.filter(room => !room.is_private).slice(0, 12).map(room => <Link className={styles.card} key={room.id} to={`/rooms/${room.id}`}><h3>{room.name}</h3><p>{room.members.length} members · {room.spoilers ? 'Spoilers allowed' : 'Spoiler free'}</p></Link>)}</div>
      {rooms.data && !rooms.data.rooms.some(room => !room.is_private) && <p>No public rooms yet. Start a conversation.</p>}
      <p><Link to="/rooms">Browse or create a room</Link></p>
    </section>
    <section><h2>Community lists</h2>
      {lists.loading && <p role="status">Loading lists…</p>}
      {Boolean(lists.error) && <p role="alert">Couldn't load lists. <PillButton onClick={lists.reload}>Try again</PillButton></p>}
      <div className={styles.grid}>{lists.data?.lists.map(list => <Link className={styles.card} key={list.id} to={`/lists/${listSlug(list)}`}><h3>{list.title}</h3><p>{list.films} titles · {list.owner?.name ?? 'Member'}</p>{list.description && <p>{list.description}</p>}</Link>)}</div>
      {lists.data?.lists.length === 0 && <p>No community lists to show yet.</p>}
      <p><Link to="/lists">Explore lists</Link></p>
    </section>
    <section><h2>Meet members</h2>
      {members.loading && <p role="status">Loading members…</p>}
      {Boolean(members.error) && <p role="alert">Couldn't load members. <PillButton onClick={members.reload}>Try again</PillButton></p>}
      <div className={styles.grid}>{members.data?.users.map(member => <Link className={styles.card} key={member.id} to={`/profile/${encodeURIComponent(member.username)}`}><h3>{member.name}</h3><p>@{member.username} · {member.followers} followers</p></Link>)}</div>
      {members.data?.users.length === 0 && <p>No other members to show yet.</p>}
    </section>
  </main></>
}
