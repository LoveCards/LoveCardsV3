import { BrowserRouter } from 'react-router-dom';
import { AppRoutes } from './router.jsx';

export default function App() {
  return (
    <BrowserRouter>
      <AppRoutes />
    </BrowserRouter>
  );
}
