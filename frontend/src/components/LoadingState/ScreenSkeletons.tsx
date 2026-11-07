import type { CSSProperties, ReactNode } from 'react'
import AppNav from '../AppNav/AppNav.tsx'
import profile from '../../pages/Profile/Profile.module.css'
import settings from '../../pages/Settings/Settings.module.css'
import edit from '../../pages/EditProfile/EditProfile.module.css'
import list from '../../pages/ListDetail/ListDetail.module.css'
import night from '../../pages/MovieNights/NightDetail.module.css'
import profileWave from '../../assets/movie/hero-wave.svg'
import styles from './ScreenSkeletons.module.css'

// Only the visual primitives are shared. Each screen composes its own layout.
function Bone({
  width = '100%',
  height = 16,
  className = '',
}: {
  width?: CSSProperties['width']
  height?: number
  className?: string
}) {
  return (
    <span className={`${styles.bone} ${className}`} style={{ width, height }} />
  )
}
function Copy() {
  return (
    <div className={styles.copy}>
      <Bone width="72%" />
      <Bone width="44%" height={12} />
    </div>
  )
}
function Repeat({
  count = 3,
  children,
}: {
  count?: number
  children: ReactNode
}) {
  return Array.from({ length: count }, (_, index) => (
    <div key={index}>{children}</div>
  ))
}
function Status({
  label,
  children,
  className = '',
}: {
  label: string
  children: ReactNode
  className?: string
}) {
  return (
    <div
      className={`${styles.skeleton} ${className}`}
      role="status"
      aria-busy="true"
    >
      <span className="sr-only">{label}</span>
      <div aria-hidden="true">{children}</div>
    </div>
  )
}
function Page({ children }: { children: ReactNode }) {
  return (
    <div className={styles.page}>
      <AppNav />
      <main>{children}</main>
    </div>
  )
}
function Panel({ children }: { children: ReactNode }) {
  return <div className={styles.panel}>{children}</div>
}
function Heading() {
  return (
    <div className={styles.heading}>
      <Bone width="min(70%, 360px)" height={60} />
      <Bone width="min(90%, 560px)" height={20} />
    </div>
  )
}
function Toolbar() {
  return (
    <div className={styles.toolbar}>
      <Bone width="48%" height={44} />
      <Bone width={120} height={44} />
    </div>
  )
}
function FilmRow({ action = false }: { action?: boolean }) {
  return (
    <div className={styles.row}>
      <Bone width={64} height={92} />
      <Copy />
      {action && <Bone width={110} height={36} className={styles.rowAction} />}
    </div>
  )
}
function PersonRow() {
  return (
    <div className={styles.row}>
      <Bone width={40} height={40} className={styles.round} />
      <Copy />
    </div>
  )
}
function Review() {
  return (
    <Panel>
      <div className={styles.toolbar}>
        <PersonRow />
        <Bone width={100} height={24} />
      </div>
      <Bone />
      <Bone />
      <Bone width="65%" />
      <div className={styles.inline}>
        <Bone width={80} height={24} />
        <Bone width={64} height={24} />
      </div>
    </Panel>
  )
}
function ToggleRows() {
  return (
    <div className={styles.stack}>
      <Repeat>
        <div className={styles.row}>
          <Copy />
          <Bone width={42} height={24} className={styles.pill} />
        </div>
      </Repeat>
    </div>
  )
}

export function ProfileSkeleton() {
  return (
    <Page>
      <Status label="Loading profile…">
        <div className={profile.header}>
          <div className={`${profile.headerInner} ${styles.onViolet}`}>
            <span className={`${profile.avatar} ${styles.bone} ${styles.round}`} />
            <div className={`${profile.info} ${styles.profileInfo}`}>
              <div className={styles.toolbar}>
                <Bone width="55%" height={64} />
                <Bone width={120} height={44} />
              </div>
              <Bone width="35%" />
              <Bone width="80%" />
              <div className={styles.inline}>
                <Repeat count={4}>
                  <Bone width={70} height={44} />
                </Repeat>
              </div>
            </div>
          </div>
        </div>
        <img className={profile.headerWave} src={profileWave} alt="" />
        <div className={profile.content}>
          <div className={profile.tabs}>
            <Repeat count={4}>
              <Bone width={90} height={24} />
            </Repeat>
          </div>
          <div className={profile.body}>
            <div className={profile.feed}>
              <Repeat>
                <Panel>
                  <PersonRow />
                  <FilmRow />
                </Panel>
              </Repeat>
            </div>
            <div className={profile.side}>
              <Panel>
                <Bone width="70%" height={24} />
                <Repeat>
                  <PersonRow />
                </Repeat>
              </Panel>
              <Panel>
                <Bone width="60%" height={24} />
                <div className={styles.posters}>
                  <Repeat count={4}>
                    <Bone height={100} />
                  </Repeat>
                </div>
              </Panel>
            </div>
          </div>
        </div>
      </Status>
    </Page>
  )
}

export function SettingsSkeleton() {
  return (
    <Page>
      <Status label="Loading your settings…">
        <div className={settings.main}>
          <Heading />
          <div className={settings.body}>
            <div className={settings.menu}>
              <Repeat count={6}>
                <div className={styles.row}>
                  <Bone width={20} height={20} />
                  <Bone width="65%" />
                </div>
              </Repeat>
            </div>
            <div className={settings.panels}>
              <Panel>
                <Bone width="45%" height={28} />
                <Bone width="80%" />
                <div className={styles.fields}>
                  <Repeat>
                    <div className={styles.copy}>
                      <Bone width="65%" height={12} />
                      <Bone height={48} />
                    </div>
                  </Repeat>
                </div>
                <Bone width={150} height={44} />
              </Panel>
              <Panel>
                <Bone width="40%" height={28} />
                <Bone width="65%" height={44} />
                <ToggleRows />
              </Panel>
              <Panel>
                <Bone width="55%" height={28} />
                <ToggleRows />
              </Panel>
            </div>
          </div>
        </div>
      </Status>
    </Page>
  )
}

export function EditProfileSkeleton() {
  return (
    <Page>
      <Status label="Loading your profile…">
        <div className={settings.main}>
          <Heading />
          <div className={edit.layout}>
            <div className={edit.preview}>
              <div className={edit.cover} />
              <div className={edit.previewBody}>
                <Bone
                  width={112}
                  height={112}
                  className={`${styles.round} ${styles.previewAvatar}`}
                />
                <Bone width="75%" height={28} />
                <Bone width="45%" />
                <Bone height={64} />
                <Bone height={44} />
              </div>
            </div>
            <Panel>
              <Bone width="50%" height={28} />
              <Bone width="85%" />
              <div className={styles.fieldGrid}>
                <Repeat count={4}>
                  <div className={styles.copy}>
                    <Bone width="40%" height={12} />
                    <Bone height={48} />
                  </div>
                </Repeat>
              </div>
              <Bone width="25%" />
              <Bone height={120} />
              <Bone width="45%" height={28} />
              <Bone height={48} />
              <Bone width={150} height={44} />
            </Panel>
          </div>
        </div>
      </Status>
    </Page>
  )
}

export function ListDetailSkeleton() {
  return (
    <Page>
      <Status label="Loading this list…">
        <div className={list.header}>
          <div className={`${list.headerInner} ${styles.onViolet}`}>
            <div className={list.cover}>
              <div className={styles.coverPosters}>
                <Repeat count={3}>
                  <Bone height={230} />
                </Repeat>
              </div>
            </div>
            <div className={list.info}>
              <div className={styles.inline}>
                <Bone width={90} height={28} />
                <Bone width={70} height={28} />
              </div>
              <Bone width="80%" height={72} />
              <Bone />
              <Bone width="75%" />
              <PersonRow />
              <Bone width={120} height={44} />
            </div>
          </div>
        </div>
        <div className={styles.detailBody}>
          <div className={styles.stack}>
            <Toolbar />
            <Repeat count={4}>
              <Panel>
                <FilmRow action />
              </Panel>
            </Repeat>
          </div>
          <div className={styles.stack}>
            <Panel>
              <Bone width="70%" height={24} />
              <Repeat>
                <Copy />
              </Repeat>
            </Panel>
            <Panel>
              <Bone width="65%" height={24} />
              <Repeat>
                <FilmRow />
              </Repeat>
            </Panel>
          </div>
        </div>
      </Status>
    </Page>
  )
}

export function NightDetailSkeleton() {
  return (
    <Page>
      <Status label="Loading movie night…">
        <div className={night.hero}>
          <div className={`${night.heroInner} ${styles.onViolet}`}>
            <Bone width={160} height={180} />
            <div className={night.info}>
              <div className={styles.inline}>
                <Bone width={90} height={28} />
                <Bone width={140} height={28} />
              </div>
              <Bone width="75%" height={72} />
              <Bone width="65%" />
              <div className={styles.inline}>
                <Bone width={200} height={44} />
                <Bone width={120} height={44} />
              </div>
            </div>
          </div>
        </div>
        <div className={styles.detailBody}>
          <div className={styles.stack}>
            <Bone width="50%" height={32} />
            <Bone width="80%" />
            <Repeat>
              <Panel>
                <FilmRow action />
                <Bone height={10} />
              </Panel>
            </Repeat>
          </div>
          <div className={styles.stack}>
            <Panel>
              <Bone width="50%" height={28} />
              <div className={styles.inline}>
                <Repeat>
                  <Bone width={60} height={44} />
                </Repeat>
              </div>
              <Repeat count={4}>
                <PersonRow />
              </Repeat>
            </Panel>
            <Panel>
              <Bone width="65%" height={24} />
              <Repeat>
                <Copy />
              </Repeat>
            </Panel>
          </div>
        </div>
      </Status>
    </Page>
  )
}

export function RoomListSkeleton() {
  return (
    <Status label="Loading room list…">
      <div className={styles.stack}>
        <Bone width="50%" height={12} />
        <Repeat count={5}>
          <div className={styles.roomRow}>
            <Bone width={40} height={56} />
            <Copy />
            <Bone width={20} height={12} />
          </div>
        </Repeat>
      </div>
    </Status>
  )
}
export function RoomChatSkeleton() {
  return (
    <Status label="Loading room conversation…">
      <div className={styles.chat}>
        <Bone width={90} height={20} className={styles.chatDate} />
        <Repeat count={4}>
          <div className={styles.message}>
            <Bone width={32} height={32} className={styles.round} />
            <div className={styles.copy}>
              <Bone width={100} height={12} />
              <Bone height={58} className={styles.bubble} />
            </div>
          </div>
        </Repeat>
      </div>
    </Status>
  )
}

export function RoomInfoSkeleton() {
  return (
    <Status label="Loading room details…">
      <div className={styles.stack}>
        <Panel>
          <FilmRow />
          <Bone height={40} />
        </Panel>
        <Panel>
          <Bone width="60%" height={24} />
          <Repeat>
            <Copy />
          </Repeat>
        </Panel>
        <Panel>
          <Bone width="65%" height={24} />
          <Repeat>
            <PersonRow />
          </Repeat>
        </Panel>
      </div>
    </Status>
  )
}

export function WatchlistSkeleton() {
  return (
    <Status label="Loading your watchlist…">
      <div className={styles.stack}>
        <Toolbar />
        <Repeat count={4}>
          <Panel>
            <FilmRow action />
          </Panel>
        </Repeat>
      </div>
    </Status>
  )
}
export function HistorySkeleton() {
  return (
    <Status label="Loading watch history…">
      <div className={styles.stack}>
        <Repeat count={4}>
          <FilmRow />
        </Repeat>
      </div>
    </Status>
  )
}
export function NotificationsSkeleton() {
  return (
    <Status label="Loading notifications…">
      <div className={styles.stack}>
        <Bone width={90} height={18} />
        <Repeat count={5}>
          <Panel>
            <div className={styles.row}>
              <Bone width={44} height={44} className={styles.round} />
              <div className={styles.copy}>
                <Bone width="90%" />
                <Bone width="65%" />
                <Bone width="25%" height={12} />
              </div>
              <Bone width={8} height={8} className={styles.round} />
            </div>
          </Panel>
        </Repeat>
      </div>
    </Status>
  )
}
export function NotificationPreferencesSkeleton() {
  return (
    <Status label="Loading preferences…">
      <div className={styles.stack}>
        <Repeat>
          <div className={styles.stack}>
            <Bone width="55%" height={12} />
            <ToggleRows />
          </div>
        </Repeat>
      </div>
    </Status>
  )
}
export function MovieNightsSkeleton() {
  return (
    <Status label="Loading movie nights…">
      <div className={styles.stack}>
        <Bone width={180} height={32} />
        <div className={styles.nightsGrid}>
          <Repeat count={4}>
            <Panel>
              <div className={styles.row}>
                <Bone width={64} height={76} />
                <Copy />
              </div>
              <Bone height={26} />
              <Bone width="70%" />
              <div className={styles.inline}>
                <Repeat>
                  <Bone width={28} height={28} className={styles.round} />
                </Repeat>
              </div>
              <Bone height={40} />
            </Panel>
          </Repeat>
        </div>
      </div>
    </Status>
  )
}
export function TasteProfileSkeleton() {
  return (
    <Status label="Loading your taste profile…">
      <div className={styles.stack}>
        <Bone width="85%" height={20} />
        <div className={styles.inline}>
          <Repeat count={5}>
            <Bone width={130} height={38} className={styles.pill} />
          </Repeat>
        </div>
      </div>
    </Status>
  )
}
export function CinemaSkeleton() {
  return (
    <Status label="Finding cinemas near you…">
      <div className={styles.stack}>
        <Repeat>
          <Panel>
            <div className={styles.row}>
              <Bone width={40} height={40} />
              <Copy />
            </div>
            <Bone width="90%" height={12} />
            <div className={styles.inline}>
              <Bone width={70} height={24} />
              <Bone width={95} height={24} />
            </div>
            <Bone height={38} />
          </Panel>
        </Repeat>
      </div>
    </Status>
  )
}
export function MovieReviewsSkeleton() {
  return (
    <Status label="Loading reviews…">
      <div className={styles.stack}>
        <Repeat>
          <Review />
        </Repeat>
      </div>
    </Status>
  )
}
export function DiscoverListsSkeleton() {
  return (
    <Status label="Loading lists…">
      <div className={styles.listsGrid}>
        <Repeat count={6}>
          <Panel>
            <div className={styles.coverPosters}>
              <Repeat count={3}>
                <Bone height={150} />
              </Repeat>
            </div>
            <Bone width="80%" height={24} />
            <Bone width="50%" height={12} />
            <Bone height={14} />
          </Panel>
        </Repeat>
      </div>
    </Status>
  )
}
export function DiscoverPeopleSkeleton() {
  return (
    <Status label="Searching people…">
      <div className={styles.peopleGrid}>
        <Repeat count={6}>
          <Panel>
            <div className={styles.person}>
              <Bone width={80} height={80} className={styles.round} />
              <Bone width="70%" height={22} />
              <Bone width="45%" height={12} />
              <Bone width="65%" height={12} />
              <Bone width={110} height={38} className={styles.pill} />
            </div>
          </Panel>
        </Repeat>
      </div>
    </Status>
  )
}
export function AdminOverviewSkeleton() {
  return (
    <Status label="Loading the overview…">
      <div className={styles.stack}>
        <div className={styles.stats}>
          <Repeat count={4}>
            <Panel>
              <Bone width="65%" />
              <Bone width="50%" height={42} />
              <Bone width="80%" height={12} />
            </Panel>
          </Repeat>
        </div>
        <div className={styles.overviewGrid}>
          <Panel>
            <Bone width="50%" height={24} />
            <div className={styles.chart}>
              <Repeat count={7}>
                <Bone height={160} />
              </Repeat>
            </div>
          </Panel>
          <Panel>
            <Bone width="60%" height={24} />
            <Repeat count={4}>
              <PersonRow />
            </Repeat>
          </Panel>
        </div>
        <Panel>
          <Toolbar />
          <Repeat>
            <FilmRow />
          </Repeat>
        </Panel>
      </div>
    </Status>
  )
}
export function AdminMoviesSkeleton() {
  return (
    <Status label="Loading movies…">
      <div className={styles.adminTable}>
        <Repeat count={5}>
          <div className={styles.movieTableRow}>
            <FilmRow />
            <Bone width="75%" />
            <Bone width={56} height={28} />
            <Bone width={24} height={24} />
          </div>
        </Repeat>
      </div>
    </Status>
  )
}
export function AdminUsersSkeleton() {
  return (
    <Status label="Loading users…">
      <div className={styles.adminTable}>
        <Repeat count={5}>
          <div className={styles.userTableRow}>
            <Bone width={16} height={16} />
            <PersonRow />
            <Bone />
            <Bone width={60} height={24} />
            <Bone width={60} height={24} />
            <Bone width={50} />
            <Bone width={70} />
            <Bone width={24} height={24} />
          </div>
        </Repeat>
      </div>
    </Status>
  )
}
export function AdminReviewsSkeleton() {
  return (
    <Status label="Loading reviews for moderation…">
      <div className={styles.stack}>
        <Repeat>
          <Panel>
            <div className={styles.toolbar}>
              <PersonRow />
              <Bone width={100} height={28} />
            </div>
            <FilmRow />
            <Bone />
            <Bone width="85%" />
            <div className={styles.inline}>
              <Bone width={110} height={36} />
              <Bone width={110} height={36} />
            </div>
          </Panel>
        </Repeat>
      </div>
    </Status>
  )
}
