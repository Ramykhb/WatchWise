import { createBrowserRouter } from 'react-router'
import RootLayout from './layouts/RootLayout.tsx'
import Landing from './pages/Landing/Landing.tsx'
import SignIn from './pages/SignIn/SignIn.tsx'
import SignUp from './pages/SignUp/SignUp.tsx'
import ForgotPassword from './pages/ForgotPassword/ForgotPassword.tsx'
import Home from './pages/Home/Home.tsx'
import Person from './pages/Person/Person.tsx'
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
      // Screens designed in Figma but not built yet.
      { path: 'terms', element: <Placeholder title="Terms of Service" /> },
      { path: 'privacy', element: <Placeholder title="Privacy Policy" /> },
      { path: 'discover', element: <Placeholder title="Discover" /> },
      { path: 'lists', element: <Placeholder title="Lists" /> },
      { path: 'community', element: <Placeholder title="Community" /> },
      { path: 'for-you', element: <Placeholder title="For You" /> },
      { path: 'movie-nights', element: <Placeholder title="Movie Nights" /> },
      { path: 'rooms', element: <Placeholder title="Discussion Rooms" /> },
      { path: 'releases', element: <Placeholder title="Coming soon" /> },
      { path: 'profile', element: <Placeholder title="Profile" /> },
      { path: 'movies/:slug', element: <Placeholder title="Movie details" /> },
      { path: '*', element: <Placeholder title="Page not found" notFound /> },
    ],
  },
])
