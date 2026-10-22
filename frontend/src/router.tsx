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
import ListDetail from './pages/ListDetail/ListDetail.tsx'
import Watchlist from './pages/Watchlist/Watchlist.tsx'
import Placeholder from './pages/Placeholder/Placeholder.tsx'

export const router = createBrowserRouter([
  {
    path: '/',
    element: <RootLayout />,
    children: [
      { index: true, element: <Landing /> },
      { path: 'signin', element: <SignIn /> },
      { path: 'signup', element: <SignUp /> },
      { path: 'forgot-password', element: <ForgotPassword /> },
      // Signed-in home. Reached by URL until auth is wired to the backend.
      { path: 'Home-Page', element: <Home /> },
      { path: 'people/:slug', element: <Person /> },
      { path: 'movies/:slug', element: <MovieDetails /> },
      { path: 'discover', element: <Discover /> },
      { path: 'for-you', element: <ForYou /> },
      { path: 'lists', element: <Lists /> },
      { path: 'lists/:slug', element: <ListDetail /> },
      // Lives under Lists so the Lists nav link is highlighted, as in the Figma frame.
      { path: 'lists/watchlist', element: <Watchlist /> },
      { path: 'watchlist', element: <Navigate to="/lists/watchlist" replace /> },
      // Screens designed in Figma but not built yet.
      { path: 'terms', element: <Placeholder title="Terms of Service" /> },
      { path: 'privacy', element: <Placeholder title="Privacy Policy" /> },
      // Create/Edit List modal (Figma 32:1944) is the next screen.
      { path: 'lists/new', element: <Placeholder title="Create a list" /> },
      { path: 'lists/:slug/edit', element: <Placeholder title="Edit list" /> },
      { path: 'community', element: <Placeholder title="Community" /> },
      { path: 'movie-nights', element: <Placeholder title="Movie Nights" /> },
      { path: 'rooms', element: <Placeholder title="Discussion Rooms" /> },
      { path: 'releases', element: <Placeholder title="Coming soon" /> },
      { path: 'profile', element: <Placeholder title="Profile" /> },
      { path: 'movies/:slug/credits', element: <Placeholder title="Full credits" /> },
      { path: 'movies/:slug/videos', element: <Placeholder title="Videos" /> },
      { path: 'cinemas', element: <Placeholder title="Nearby cinemas" /> },
      { path: '*', element: <Placeholder title="Page not found" notFound /> },
    ],
  },
])
