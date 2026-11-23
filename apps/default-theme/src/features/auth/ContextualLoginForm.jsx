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
  const [requestedRevision, setRequestedRevision] = useState(null);
  const successHandled = useRef(false);
  const { isAuthenticated, status: authStatus, revision, refresh } = useAuth();
  const capability = getLoginState();
  const verifying = requestedRevision !== null && revision < requestedRevision;
  const checked = requestedRevision !== null && !verifying;
  const needsRetry = checked && authStatus === 'error';
  const expired = checked && authStatus === 'ready' && !isAuthenticated;
  const sessionMessage = needsRetry ? '登录状态暂时无法确认，请重新检查。' : expired ? '会话已失效，请重新输入密码登录。' : '';

  useEffect(() => { onBusyChange?.(busy || verifying); }, [verifying, busy, onBusyChange]);

  useEffect(() => {
    if (checked && isAuthenticated && authStatus === 'ready' && !successHandled.current) {
      successHandled.current = true;
      onSuccess?.();
    }
  }, [authStatus, checked, isAuthenticated, onSuccess]);

  const submit = async (event) => {
    event.preventDefault();
    if (busy || verifying) return;
    if (needsRetry) {
      setRequestedRevision(refresh());
      return;
    }
    const next = validateCredentials(account, password);
    setErrors(next);
    if (Object.keys(next).length) return;
    setBusy(true);
    setMessage('');
    setRequestedRevision(null);
    try {
      await login({ account, password });
      setPassword('');
      setRequestedRevision(refresh());
    } catch (error) {
      setMessage(getUserFacingError(error, { fallback: '登录没有完成，请检查账号和密码后重试。' }));
    } finally {
      setBusy(false);
    }
  };

  return <form className="lc-context-login" onSubmit={submit}>
    {description && <p>{description}</p>}
    <FormLayout>
      <CredentialFields account={account} password={password} onAccountChange={(value) => { setAccount(value); setErrors((current) => ({ ...current, account: '' })); }} onPasswordChange={(value) => { setPassword(value); setErrors((current) => ({ ...current, password: '' })); }} errors={errors} disabled={busy || verifying || needsRetry || !capability.canLogin} />
    </FormLayout>
    {(sessionMessage || message || !capability.canLogin) && <p className="lc-context-login-error" role="alert">{sessionMessage || message || capability.message}</p>}
    {verifying && <p className="lc-context-login-status" role="status">正在更新登录状态…</p>}
    <div className="lc-context-login-actions">
      <Button type="button" label="取消" variant="secondary" size="lg" onClick={onCancel} isDisabled={busy || verifying} />
      <Button type="submit" label={needsRetry ? '重新检查登录状态' : '登录'} variant="primary" size="lg" isLoading={busy || verifying} isDisabled={busy || verifying || !capability.canLogin} />
    </div>
    <button className="lc-context-login-full" type="button" onClick={onFullPage} disabled={busy || verifying}>前往完整登录页</button>
  </form>;
}
