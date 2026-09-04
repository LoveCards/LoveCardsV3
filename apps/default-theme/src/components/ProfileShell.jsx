import { Link } from 'react-router-dom';
import { Icon } from '../ui/icons.jsx';

/**
 * Shared profile canvas for the signed-in profile and public profiles.
 * The mode changes permissions and actions, never the page geometry.
 */
export function ProfileShell({ children, mode = 'owner', title = '我的', ariaLabel, backTo = '/', mobileActions = null }) {
  const publicProfile = mode === 'public';
  return <main className={`profile-page profile-shell profile-shell-${mode}${publicProfile ? ' public-profile-page' : ''}`} aria-label={ariaLabel || title}>
    <div className="profile-mobile-top">
      <Link to={backTo} aria-label={publicProfile ? '返回首页' : '返回首页'}><Icon name="arrow" size={19} /></Link>
      <strong>{title}</strong>
      {mobileActions || <span aria-hidden="true" />}
    </div>
    {children}
  </main>;
}
