import { createContext, useContext, useEffect, useMemo, useState } from 'react';
import { Theme } from '@astryxdesign/core/theme';
import { LayerProvider } from '@astryxdesign/core/Layer';
import { InternationalizationProvider } from '@astryxdesign/core/i18n';
import { neutralTheme } from '@astryxdesign/theme-neutral/built';
import { checkSession, loadCurrentUser, logout } from '../services/auth-service.js';
import { getSdkClient } from './sdk-client.js';

const ThemeModeContext = createContext(null);
const AuthContext = createContext(null);
const toastConfig = {
  position: 'topEnd',
  maxVisible: 3,
  inset: {
    top: 'var(--lc-toast-top-inset)',
    end: 'var(--lc-toast-inline-end)',
  },
};
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

  const [auth, setAuth] = useState({ status: 'checking', session: null, user: null, userStatus: 'idle' });
  useEffect(() => {
    let active = true;
    const refresh = async () => {
      try {
        const session = await checkSession();
        if (!session || session.visitor) {
          if (active) setAuth({ status: 'ready', session, user: null, userStatus: 'idle', error: null });
          return;
        }
        let user = null;
        let userStatus = 'error';
        try {
          user = await loadCurrentUser();
          userStatus = user ? 'ready' : 'error';
        } catch (error) {
          // A rejected current-user request with 401 means the cached session
          // is no longer authenticated. Clear it so private pages render their
          // login boundary instead of a misleading network-error state.
          if (error?.status === 401) getSdkClient()?.clearToken();
          if (error?.status === 401) {
            if (active) setAuth({ status: 'ready', session: null, user: null, userStatus: 'idle', error: null });
            return;
          }
          // Session state remains usable when the profile request is temporarily unavailable.
        }
        if (active) setAuth({ status: 'ready', session, user, userStatus, error: null });
      } catch (error) {
        if (active) setAuth((current) => ({ ...current, status: 'error', error, user: null, userStatus: 'error' }));
      }
    };
    refresh();
    const onChange = () => refresh();
    window.addEventListener('lc-auth-changed', onChange);
    return () => { active = false; window.removeEventListener('lc-auth-changed', onChange); };
  }, []);
  const authValue = useMemo(() => ({
    ...auth,
    hasSession: Boolean(auth.session),
    isVisitor: Boolean(auth.session?.visitor),
    isAuthenticated: Boolean(auth.session && !auth.session.visitor),
    can: (capability) => hasSessionCapability(auth.session, capability),
    refresh: () => window.dispatchEvent(new CustomEvent('lc-auth-changed')),
    logout: async () => { try { await logout(); } finally { setAuth({ status: 'ready', session: null, user: null, userStatus: 'idle' }); } },
  }), [auth]);

  return (
    <ThemeModeContext value={value}>
      <AuthContext value={authValue}>
        <InternationalizationProvider locale="zh-CN" overrides={astryxChinese} dir="ltr">
          <Theme theme={neutralTheme} mode={mode}>
            <LayerProvider toast={toastConfig}>{children}</LayerProvider>
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
