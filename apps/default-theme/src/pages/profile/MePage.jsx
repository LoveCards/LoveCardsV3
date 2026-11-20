import { useAuth } from '../../app/providers.jsx';
import { GuestMePage } from './GuestMePage.jsx';
import { ProfilePage } from './ProfilePage.jsx';

export function MePage() {
  const { isVisitor } = useAuth();
  return isVisitor ? <GuestMePage /> : <ProfilePage />;
}
