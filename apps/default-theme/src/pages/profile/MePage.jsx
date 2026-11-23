import { Navigate, useLocation } from 'react-router-dom';
import { useAuth } from '../../app/providers.jsx';
import { GuestMePage } from './GuestMePage.jsx';
import { ProfilePage } from './ProfilePage.jsx';

export function MePage() {
  const { isVisitor } = useAuth();
  const location = useLocation();
  const params = new URLSearchParams(location.search);
  const tab = params.get('tab');
  if (params.has('tab') && (isVisitor || !['likes', 'saved', 'history'].includes(tab))) {
    params.delete('tab');
    const search = params.toString();
    return <Navigate replace to={{ pathname: '/me', search: search ? `?${search}` : '', hash: location.hash }} />;
  }
  return isVisitor ? <GuestMePage /> : <ProfilePage />;
}
