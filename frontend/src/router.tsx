import { Navigate, createBrowserRouter } from 'react-router'
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
import { RequireAuth, RequireGuest } from './components/RouteGuards/RouteGuards.tsx'
import AuthCallback from './pages/AuthCallback/AuthCallback.tsx'

export const router = createBrowserRouter([
  {
    path: '/',
    element: <RootLayout />,
    children: [
      { index: true, element: <RequireGuest><Landing /></RequireGuest> },
      { path: 'signin', element: <RequireGuest><SignIn /></RequireGuest> },
      { path: 'signup', element: <RequireGuest><SignUp /></RequireGuest> },
      { path: 'forgot-password', element: <RequireGuest><ForgotPassword /></RequireGuest> },
      { path: 'auth/callback', element: <RequireGuest><AuthCallback /></RequireGuest> },
      {
        element: <RequireAuth />,
        children: [
          { path: 'home-page', element: <Home /> },
          { path: 'people/:slug', element: <Person /> },
          { path: 'movies/:slug', element: <MovieDetails /> },
          { path: 'discover', element: <Discover /> },
          // Lives under Discover so its navigation link stays highlighted.
          { path: 'discover/cinemas', element: <Cinemas /> },
          { path: 'cinemas', element: <Navigate to="/discover/cinemas" replace /> },
          { path: 'for-you', element: <ForYou /> },
          {
            path: 'lists',
            element: <Lists />,
            // The Create/Edit List dialog opens over the Lists page, which stays mounted underneath.
            children: [
              { path: 'new', element: <ListDialog mode="create" /> },
              { path: ':slug/edit', element: <ListDialog mode="edit" /> },
            ],
          },
          { path: 'lists/:slug', element: <ListDetail /> },
          { path: 'lists/watchlist', element: <Watchlist /> },
          { path: 'watchlist', element: <Navigate to="/lists/watchlist" replace /> },
          { path: 'profile', element: <Profile /> },
          { path: 'profile/:handle', element: <Profile /> },
          { path: 'settings', element: <Settings /> },
          {
            path: 'admin',
            element: <AdminLayout />,
            children: [
              { index: true, element: <AdminOverview /> },
              { path: 'users', element: <AdminUsers /> },
              { path: 'reviews', element: <AdminReviews /> },
              { path: 'movies', element: <AdminMovies /> },
            ],
          },
          { path: 'notifications', element: <Notifications /> },
          { path: 'terms', element: <Placeholder title="Terms of Service" /> },
          { path: 'privacy', element: <Placeholder title="Privacy Policy" /> },
          { path: 'community', element: <Placeholder title="Community" /> },
          { path: 'movie-nights', element: <MovieNights /> },
          { path: 'movie-nights/:slug', element: <NightDetail /> },
          // One optional room id keeps the page mounted when switching rooms.
          { path: 'rooms/:roomId?', element: <Rooms /> },
          { path: 'releases', element: <Placeholder title="Coming soon" /> },
          { path: 'movies/:slug/credits', element: <Placeholder title="Full credits" /> },
          { path: 'movies/:slug/videos', element: <Placeholder title="Videos" /> },
          { path: '*', element: <Placeholder title="Page not found" notFound /> },
        ],
      },
    ],
  },
])
