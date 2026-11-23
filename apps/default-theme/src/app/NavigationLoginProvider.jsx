import { createContext, useContext, useEffect, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { AuthPromptDialog } from '../ui/interaction.jsx';
import { composeModalState } from './compose-modal-routing.js';

const NavigationLoginContext = createContext(null);
const loginActions = {
  '/activity': '查看动态',
  '/compose': '发布内容',
  '/messages': '查看消息',
  '/me': '查看我的主页',
};

export function NavigationLoginProvider({ children }) {
  const location = useLocation();
  const navigate = useNavigate();
  const [request, setRequest] = useState(null);
  const active = request?.key === location.key ? request : null;
  useEffect(() => {
    if (request && !active) setRequest(null);
  }, [request, active]);

  const openLogin = (target = null) => setRequest({ target, key: location.key });
  const continueAfterLogin = () => {
    if (!active?.target) return;
    const composeDialog = active.target === '/compose' && window.matchMedia('(min-width: 761px)').matches;
    navigate(active.target, composeDialog ? { state: composeModalState(location) } : undefined);
  };
  const openFullLogin = () => {
    const from = active?.target || `${location.pathname}${location.search}${location.hash}`;
    setRequest(null);
    navigate('/auth/login', { state: { from } });
  };

  return <NavigationLoginContext value={openLogin}>
    {children}
    <AuthPromptDialog
      isOpen={Boolean(active)}
      action={loginActions[active?.target]}
      onOpenChange={(open) => { if (!open) setRequest(null); }}
      onSuccess={continueAfterLogin}
      onLogin={openFullLogin}
    />
  </NavigationLoginContext>;
}

export function useNavigationLogin() {
  return useContext(NavigationLoginContext);
}
