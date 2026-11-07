import { useEffect, useRef } from 'react'
import { Outlet, ScrollRestoration } from 'react-router'
import { ReactLenis, useLenis, type LenisRef } from 'lenis/react'
import { gsap, ScrollTrigger } from '../lib/gsap.ts'
import { useAuth } from '../contexts/useAuth.ts'
import ChatbotWidget from '../components/ChatbotWidget/ChatbotWidget.tsx'
import NotificationPopups from '../components/NotificationPopups/NotificationPopups.tsx'
import { useLiveNotifications } from '../pages/Notifications/notificationsStore.ts'

export default function RootLayout() {
  const lenisRef = useRef<LenisRef>(null)
  const { user } = useAuth()
  // One notifications socket for the whole session; the header's bell and the pop-ups read its store.
  useLiveNotifications(user?.email_verified_at ? user.id : undefined)

  // Drive Lenis from GSAP's ticker so smooth scrolling and GSAP animations share one frame loop.
  useEffect(() => {
    const update = (time: number) => lenisRef.current?.lenis?.raf(time * 1000)
    gsap.ticker.add(update)
    return () => gsap.ticker.remove(update)
  }, [])

  // Keep ScrollTrigger positions in sync with Lenis' virtual scroll.
  useLenis(ScrollTrigger.update)

  return (
    <ReactLenis root ref={lenisRef} options={{ autoRaf: false, lerp: 0.1 }}>
      <Outlet />
      {user?.email_verified_at && <ChatbotWidget key={user.id} userId={user.id} />}
      {user?.email_verified_at && <NotificationPopups />}
      <ScrollRestoration />
    </ReactLenis>
  )
}
