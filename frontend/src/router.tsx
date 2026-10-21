import { createBrowserRouter } from 'react-router'
import RootLayout from './layouts/RootLayout.tsx'
import Landing from './pages/Landing/Landing.tsx'
import Placeholder from './pages/Placeholder/Placeholder.tsx'

export const router = createBrowserRouter([
  {
    path: '/',
    element: <RootLayout />,
    children: [
      { index: true, element: <Landing /> },
      // Screens designed in Figma but not built yet.
      { path: 'signin', element: <Placeholder title="Sign in" /> },
      { path: 'signup', element: <Placeholder title="Sign up" /> },
      { path: 'discover', element: <Placeholder title="Discover" /> },
      { path: 'lists', element: <Placeholder title="Lists" /> },
      { path: 'community', element: <Placeholder title="Community" /> },
      { path: '*', element: <Placeholder title="Page not found" notFound /> },
    ],
  },
])
