import { useEffect, useRef, useState } from 'react';
import { login, getLoginState } from '../../services/auth-service.js';
import { useAuth } from '../../app/providers.jsx';
import { getUserFacingError } from '../../app/user-facing-error.js';
import { Button, FormLayout } from '../../ui/controls.jsx';
import { CredentialFields, validateCredentials } from './CredentialFields.jsx';

export function ContextualLoginForm({ description, onSuccess, onCancel, onFullPage, onBusyChange }) {
  const [account, setAccount] = useState('');
  const [password, setPassword] = useState('');
  const [errors, setErrors] = useState({});
  const [message, setMessage] = useState('');
  const [busy, setBusy] = useState(false);
  const [awaitingSession, setAwaitingSession] = useState(false);
  const successHandled = useRef(false);
  const { isAuthenticated, status: authStatus } = useAuth();
  const capability = getLoginState();

  useEffect(() => { onBusyChange?.(busy || awaitingSession); }, [awaitingSession, busy, onBusyChange]);

  useEffect(() => {
    if (awaitingSession && isAuthenticated && authStatus === 'ready' && !successHandled.current) {
      successHandled.current = true;
      onSuccess?.();
    }
  }, [authStatus, awaitingSession, isAuthenticated, onSuccess]);

  const submit = async (event) => {
    event.preventDefault();
    if (busy) return;
    const next = validateCredentials(account, password);
    setErrors(next);
    if (Object.keys(next).length) return;
    setBusy(true);
    setMessage('');
    try {
      await login({ account, password });
      setPassword('');
      setAwaitingSession(true);
    } catch (error) {
      setMessage(getUserFacingError(error, { fallback: '登录没有完成，请检查账号和密码后重试。' }));
    } finally {
      setBusy(false);
    }
  };

  return <form className="lc-context-login" onSubmit={submit}>
    {description && <p>{description}</p>}
    <FormLayout>
      <CredentialFields account={account} password={password} onAccountChange={(value) => { setAccount(value); setErrors((current) => ({ ...current, account: '' })); }} onPasswordChange={(value) => { setPassword(value); setErrors((current) => ({ ...current, password: '' })); }} errors={errors} disabled={busy || awaitingSession || !capability.canLogin} />
    </FormLayout>
    {(message || !capability.canLogin) && <p className="lc-context-login-error" role="alert">{message || capability.message}</p>}
    {awaitingSession && <p className="lc-context-login-status" role="status">正在更新登录状态…</p>}
    <div className="lc-context-login-actions">
      <Button type="button" label="取消" variant="secondary" size="lg" onClick={onCancel} isDisabled={busy || awaitingSession} />
      <Button type="submit" label="登录" variant="primary" size="lg" isLoading={busy || awaitingSession} isDisabled={busy || awaitingSession || !capability.canLogin} />
    </div>
    <button className="lc-context-login-full" type="button" onClick={onFullPage} disabled={busy || awaitingSession}>前往完整登录页</button>
  </form>;
}
