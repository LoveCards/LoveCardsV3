import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { Theme } from '@astryxdesign/core/theme';
import { LayerProvider } from '@astryxdesign/core/Layer';
import { InternationalizationProvider } from '@astryxdesign/core/i18n';
import { neutralTheme } from '@astryxdesign/theme-neutral/built';
import { checkSession, loadCurrentUser, logout } from '../services/auth-service.js';
import { getSdkClient } from './sdk-client.js';
import { toastConfig } from './toast-config.js';
import { FeedLayoutProvider } from '../features/feed/FeedLayout.jsx';

const ThemeModeContext = createContext(null);
const AuthContext = createContext(null);
const astryxChinese = {
  'zh-CN': {
    '@astryx.moreMenu.label': '更多选项',
    '@astryx.dropdownMenu.label': '选项菜单',
    '@astryx.toast.dismiss': '关闭通知',
    '@astryx.toast.viewport': '通知',
    '@astryx.dialog.close': '关闭对话框',
    '@astryx.popover.close': '关闭浮层',
    '@astryx.button.loading': '处理中',
    '@astryx.fileInput.clearLabel': '清除 {label}',
    '@astryx.fileInput.required': '必填',
    '@astryx.fileInput.triggerWithFiles': '{label}，已选择 {fileNames}',
    '@astryx.field.optional': '选填',
    '@astryx.field.required': '必填',
    '@astryx.selector.clearLabel': '清除 {label}',
    '@astryx.selector.placeholder': '请选择…',
    '@astryx.selector.searchOptions': '搜索选项',
    '@astryx.selector.searchPlaceholder': '搜索…',
    '@astryx.input.statusButton.error': '查看错误详情',
    '@astryx.input.statusButton.success': '查看成功详情',
    '@astryx.input.statusButton.warning': '查看警告详情',
  },
};

export function hasSessionCapability(session, capability) {
  if (!capability || !Array.isArray(session?.capabilities)) return false;
  return session.capabilities.some((value) => value === capability || (typeof value === 'string' && value.startsWith(`${capability}.`)));
}

function resolveInitialPreference(initialMode) {
  if (initialMode) return initialMode;
  if (typeof window === 'undefined') return 'light';
  const saved = window.localStorage.getItem('lc-theme');
  if (saved === 'light' || saved === 'dark' || saved === 'system') return saved;
  return 'system';
}

export function AppProviders({ children, initialMode }) {
  const [preference, setPreference] = useState(() => resolveInitialPreference(initialMode));
  const [systemMode, setSystemMode] = useState(() => typeof window !== 'undefined' && window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
  const [reduceMotion, setReduceMotion] = useState(() => typeof window !== 'undefined' && window.localStorage.getItem('lc-reduce-motion') === 'true');
  const mode = preference === 'system' ? systemMode : preference;
  useEffect(() => {
    window.localStorage.setItem('lc-theme', preference);
  }, [preference]);
  useEffect(() => {
    const media = window.matchMedia?.('(prefers-color-scheme: dark)');
    const update = () => setSystemMode(media?.matches ? 'dark' : 'light');
    media?.addEventListener?.('change', update);
    return () => media?.removeEventListener?.('change', update);
  }, []);
  useEffect(() => {
    window.localStorage.setItem('lc-reduce-motion', String(reduceMotion));
    document.documentElement.dataset.reduceMotion = String(reduceMotion);
  }, [reduceMotion]);
  const value = useMemo(
    () => ({
      mode,
      preference,
      setPreference,
      setMode: setPreference,
      reduceMotion,
      setReduceMotion,
      toggleMode: () => setPreference(mode === 'dark' ? 'light' : 'dark'),
    }),
    [mode, preference, reduceMotion],
  );

  const [auth, setAuth] = useState({ revision: 0, status: 'checking', session: null, user: null, userStatus: 'idle' });
  const sequence = useRef(0);
  const pendingCheck = useRef(null);
  const refresh = useCallback(() => {
    const client = getSdkClient();
    const token = client?.getToken() || null;
    if (pendingCheck.current?.token === token) return pendingCheck.current.revision;
    const revision = ++sequence.current;
    pendingCheck.current = { revision, token };
    const current = () => sequence.current === revision;
    const run = async () => {
      try {
        const session = await checkSession();
        if (!current()) return;
        if (!session || session.visitor) {
          setAuth({ revision, status: 'ready', session, user: null, userStatus: 'idle', error: null });
          return;
        }
        let user = null;
        let userStatus = 'error';
        try {
          user = await loadCurrentUser();
          userStatus = user ? 'ready' : 'error';
        } catch (error) {
          if (!current()) return;
          if (error?.status === 401) {
            if (client?.getToken() === token) client?.clearToken();
            setAuth({ revision, status: 'ready', session: null, user: null, userStatus: 'idle', error: null });
            return;
          }
          // 资料暂不可用时，已验证的会话仍可用于账号动作。
        }
        if (current()) setAuth({ revision, status: 'ready', session, user, userStatus, error: null });
      } catch (error) {
        if (current()) setAuth((previous) => ({ ...previous, revision, status: 'error', error, user: null, userStatus: 'error' }));
      } finally {
        if (current()) pendingCheck.current = null;
      }
    };
    void run();
    return revision;
  }, []);
  useEffect(() => {
    refresh();
    window.addEventListener('lc-auth-changed', refresh);
    return () => {
      sequence.current++;
      pendingCheck.current = null;
      window.removeEventListener('lc-auth-changed', refresh);
    };
  }, [refresh]);
  const authValue = useMemo(() => ({
    ...auth,
    hasSession: Boolean(auth.session),
    isVisitor: Boolean(auth.session?.visitor),
    isAuthenticated: Boolean(auth.session && !auth.session.visitor),
    can: (capability) => hasSessionCapability(auth.session, capability),
    refresh,
    logout,
  }), [auth, refresh]);

  return (
    <ThemeModeContext value={value}>
      <AuthContext value={authValue}>
        <InternationalizationProvider locale="zh-CN" overrides={astryxChinese} dir="ltr">
          <Theme theme={neutralTheme} mode={mode}>
            <LayerProvider toast={toastConfig}><FeedLayoutProvider>{children}</FeedLayoutProvider></LayerProvider>
          </Theme>
        </InternationalizationProvider>
      </AuthContext>
    </ThemeModeContext>
  );
}

export function useAuth() {
  const value = useContext(AuthContext);
  if (!value) throw new Error('useAuth must be used inside AppProviders');
  return value;
}

export function useThemeMode() {
  const value = useContext(ThemeModeContext);
  if (!value) {
    throw new Error('useThemeMode must be used inside AppProviders');
  }
  return value;
}
