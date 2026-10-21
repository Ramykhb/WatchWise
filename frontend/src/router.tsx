import { createBrowserRouter } from 'react-router'
import RootLayout from './layouts/RootLayout.tsx'
import Landing from './pages/Landing/Landing.tsx'
import SignIn from './pages/SignIn/SignIn.tsx'
import SignUp from './pages/SignUp/SignUp.tsx'
import Placeholder from './pages/Placeholder/Placeholder.tsx'

export const router = createBrowserRouter([
  {
    path: '/',
    element: <RootLayout />,
    children: [
      { index: true, element: <Landing /> },
      { path: 'signin', element: <SignIn /> },
      { path: 'signup', element: <SignUp /> },
      // Screens designed in Figma but not built yet.
      { path: 'forgot-password', element: <Placeholder title="Forgot password" /> },
      { path: 'terms', element: <Placeholder title="Terms of Service" /> },
      { path: 'privacy', element: <Placeholder title="Privacy Policy" /> },
      { path: 'discover', element: <Placeholder title="Discover" /> },
      { path: 'lists', element: <Placeholder title="Lists" /> },
      { path: 'community', element: <Placeholder title="Community" /> },
      { path: '*', element: <Placeholder title="Page not found" notFound /> },
    ],
  },
])
