import { Navigate, createBrowserRouter, useLocation } from 'react-router'
import RootLayout from './layouts/RootLayout.tsx'
import Landing from './pages/Landing/Landing.tsx'
import SignIn from './pages/SignIn/SignIn.tsx'
import SignUp from './pages/SignUp/SignUp.tsx'
import ForgotPassword from './pages/ForgotPassword/ForgotPassword.tsx'
import Home from './pages/Home/Home.tsx'
import Person from './pages/Person/Person.tsx'
import MovieDetails from './pages/MovieDetails/MovieDetails.tsx'
import Discover from './pages/Discover/Discover.tsx'
import ForYou from './pages/ForYou/ForYou.tsx'
import Lists from './pages/Lists/Lists.tsx'
import ListDialog from './pages/Lists/ListDialog.tsx'
import ListDetail from './pages/ListDetail/ListDetail.tsx'
import Watchlist from './pages/Watchlist/Watchlist.tsx'
import Profile from './pages/Profile/Profile.tsx'
import Settings from './pages/Settings/Settings.tsx'
import Cinemas from './pages/Cinemas/Cinemas.tsx'
import AdminLayout from './pages/Admin/AdminLayout.tsx'
import AdminOverview from './pages/Admin/AdminOverview.tsx'
import AdminUsers from './pages/Admin/AdminUsers.tsx'
import AdminReviews from './pages/Admin/AdminReviews.tsx'
import AdminMovies from './pages/Admin/AdminMovies.tsx'
import MovieNights from './pages/MovieNights/MovieNights.tsx'
import NightDetail from './pages/MovieNights/NightDetail.tsx'
import Rooms from './pages/Rooms/Rooms.tsx'
import Notifications from './pages/Notifications/Notifications.tsx'
import Placeholder from './pages/Placeholder/Placeholder.tsx'
import { useAuth } from './contexts/AuthContext.tsx'
import AuthCallback from './pages/AuthCallback/AuthCallback.tsx'

function RequireAuth({ children }: { children: React.ReactNode }) {
  const { user, loading } = useAuth()
  const location = useLocation()
  if (loading) return null
  if (!user) return <Navigate to="/signin" replace state={{ from: location }} />
  return children
}

export const router = createBrowserRouter([
  {
    path: '/',
    element: <RootLayout />,
    children: [
      { index: true, element: <Landing /> },
      { path: 'signin', element: <SignIn /> },
      { path: 'signup', element: <SignUp /> },
      { path: 'auth/callback', element: <AuthCallback /> },
      { path: 'forgot-password', element: <ForgotPassword /> },
      // Signed-in home. Reached by URL until auth is wired to the backend.
      { path: 'Home-Page', element: <RequireAuth><Home /></RequireAuth> },
      { path: 'people/:slug', element: <Person /> },
      { path: 'movies/:slug', element: <MovieDetails /> },
      { path: 'discover', element: <Discover /> },
      // Lives under Discover so the Discover nav link is highlighted, as in the Figma frame.
      { path: 'discover/cinemas', element: <Cinemas /> },
      { path: 'cinemas', element: <Navigate to="/discover/cinemas" replace /> },
      { path: 'for-you', element: <RequireAuth><ForYou /></RequireAuth> },
      {
        path: 'lists',
        element: <RequireAuth><Lists /></RequireAuth>,
        // The Create/Edit List dialog opens over the Lists page, which stays mounted underneath.
        children: [
          { path: 'new', element: <ListDialog mode="create" /> },
          { path: ':slug/edit', element: <ListDialog mode="edit" /> },
        ],
      },
      { path: 'lists/:slug', element: <ListDetail /> },
      // Lives under Lists so the Lists nav link is highlighted, as in the Figma frame.
      { path: 'lists/watchlist', element: <RequireAuth><Watchlist /></RequireAuth> },
      { path: 'watchlist', element: <Navigate to="/lists/watchlist" replace /> },
      // `/profile` is the signed-in user's own profile; `/profile/:handle` is anyone's public profile.
      { path: 'profile', element: <RequireAuth><Profile /></RequireAuth> },
      { path: 'profile/:handle', element: <Profile /> },
      { path: 'settings', element: <RequireAuth><Settings /></RequireAuth> },
      // Admin dashboard. Reached by URL until auth (and an admin role check) is connected.
      {
        path: 'admin',
        element: <RequireAuth><AdminLayout /></RequireAuth>,
        children: [
          { index: true, element: <AdminOverview /> },
          { path: 'users', element: <AdminUsers /> },
          { path: 'reviews', element: <AdminReviews /> },
          { path: 'movies', element: <AdminMovies /> },
        ],
      },
      { path: 'notifications', element: <RequireAuth><Notifications /></RequireAuth> },
      // Screens designed in Figma but not built yet.
      { path: 'terms', element: <Placeholder title="Terms of Service" /> },
      { path: 'privacy', element: <Placeholder title="Privacy Policy" /> },
      { path: 'community', element: <Placeholder title="Community" /> },
      { path: 'movie-nights', element: <RequireAuth><MovieNights /></RequireAuth> },
      { path: 'movie-nights/:slug', element: <RequireAuth><NightDetail /></RequireAuth> },
      // One route with an optional room id, so switching rooms keeps the page (and its chat state) mounted.
      { path: 'rooms/:roomId?', element: <RequireAuth><Rooms /></RequireAuth> },
      { path: 'releases', element: <Placeholder title="Coming soon" /> },
      { path: 'movies/:slug/credits', element: <Placeholder title="Full credits" /> },
      { path: 'movies/:slug/videos', element: <Placeholder title="Videos" /> },
      { path: '*', element: <Placeholder title="Page not found" notFound /> },
    ],
  },
])
