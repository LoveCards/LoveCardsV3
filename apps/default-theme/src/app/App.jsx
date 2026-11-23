import { createBrowserRouter, RouterProvider } from 'react-router-dom';
import { AppRoutes } from './router.jsx';
import { NavigationLoginProvider } from './NavigationLoginProvider.jsx';

const router = createBrowserRouter([{ path: '*', element: <NavigationLoginProvider><AppRoutes /></NavigationLoginProvider> }]);

export default function App() {
  return <RouterProvider router={router} />;
}
