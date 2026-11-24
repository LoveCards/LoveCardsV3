import { useEffect, useMemo, useState } from 'react';
import { Link, Navigate, useLocation, useNavigate } from 'react-router-dom';
import { Brand } from '../../components/Brand.jsx';
import { getLoginState, loadAuthPolicy, login, register, sendRegistrationCode, startGuestSession } from '../../services/auth-service.js';
import { useHumanVerification } from '../../features/human-verification/useHumanVerification.jsx';
import { useAuth } from '../../app/providers.jsx';
import { getUserFacingError } from '../../app/user-facing-error.js';
import { Button, FormLayout, Icon, SegmentedControl, SegmentedControlItem, TextInput } from '../../ui/index.js';
import { registrationContactError, registrationContactLabels } from '../../features/auth/registration-contact.js';
import { safeReturnPath } from '../../app/auth-routing.jsx';
import { CredentialFields, validateCredentials } from '../../features/auth/CredentialFields.jsx';

export function AuthPage({ mode = 'login' }) {
  const navigate = useNavigate();
  const location = useLocation();
  const { isAuthenticated, status: authStatus } = useAuth();
  const isRegister = mode === 'register';
  const capability = useMemo(() => getLoginState(), []);
  const [account, setAccount] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirm, setPasswordConfirm] = useState('');
  const [selectedMethod, setSelectedMethod] = useState(null);
  const [code, setCode] = useState('');
  const [resendAt, setResendAt] = useState(0);
  const [now, setNow] = useState(Date.now);
  const [status, setStatus] = useState('idle');
  const [message, setMessage] = useState(capability.message);
  const [policy, setPolicy] = useState(null);
  const [policyStatus, setPolicyStatus] = useState(capability.canLogin ? 'loading' : 'unavailable');
  const [policyRetry, setPolicyRetry] = useState(0);
  const [pendingAction, setPendingAction] = useState('');
  const [fieldErrors, setFieldErrors] = useState({});
  const { verify, dialog: humanVerificationDialog } = useHumanVerification();
  const returnTo = safeReturnPath(location.state?.from) || '/me';

  useEffect(() => {
    let active = true;
    if (!capability.canLogin) return () => { active = false; };
    setPolicyStatus('loading');
    loadAuthPolicy()
      .then((value) => { if (active) { setPolicy(value); setPolicyStatus('ready'); } })
      .catch(() => { if (active) { setPolicy(null); setPolicyStatus('error'); } });
    return () => { active = false; };
  }, [capability.canLogin, policyRetry]);

  const registration = policy?.registration;
  const methods = (registration?.account_types ?? []).filter((type) => type in registrationContactLabels);
  const canUseMethod = (type) => type === 'email'
    ? !registration?.email_verification_required || registration.email_verification_available
    : !registration?.sms_verification_required || registration.sms_verification_available;
  const method = methods.includes(selectedMethod) ? selectedMethod : methods.find(canUseMethod) ?? methods[0];
  const codeRequired = isRegister && Boolean(method === 'phone' ? registration?.sms_verification_required : registration?.email_verification_required);
  const codeLabel = method === 'phone' ? '短信验证码' : '邮箱验证码';
  const resendIn = Math.max(0, Math.ceil((resendAt - now) / 1000));
  const registrationBlocked = isRegister && (policyStatus !== 'ready' || !registration?.enabled || !method || !canUseMethod(method) || Boolean(policy.human_verification?.enabled && !policy.human_verification?.available));
  const registrationMessage = policyStatus === 'loading' ? '正在确认注册策略…'
    : policyStatus === 'error' ? '暂时无法确认注册策略，请重试。'
      : !registration?.enabled || !method ? '当前暂未开放新账号注册。'
        : !canUseMethod(method) ? codeLabel + '服务尚未就绪，请稍后重试。'
          : '人机验证服务尚未就绪，请稍后重试。';

  useEffect(() => {
    if (resendIn === 0) return undefined;
    const timer = setTimeout(() => setNow(Date.now()), 1000);
    return () => clearTimeout(timer);
  }, [resendIn, now]);

  const changeAccount = (value) => {
    setAccount(value);
    setCode('');
    setResendAt(0);
    setFieldErrors((current) => ({ ...current, account: '', code: '' }));
    setMessage('');
  };

  const changeMethod = (value) => {
    if (!methods.includes(value) || value === method) return;
    setSelectedMethod(value);
    changeAccount('');
  };

  const refreshChangedPolicy = (error) => {
    const reason = error?.reason_code || error?.reason || error?.details?.reason_code;
    if (!['registration-method-not-allowed', 'registration-code-not-required', 'registration-code-unavailable'].includes(reason)) return false;
    setCode('');
    setResendAt(0);
    setPolicyRetry((value) => value + 1);
    return true;
  };

  const guestEnabled = capability.canLogin && policy?.guest?.enabled === true;

  const submit = async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    if (pendingAction || registrationBlocked) return;
    const nextErrors = validateCredentials(account, password);
    if (isRegister) {
      const contactError = registrationContactError(account, method);
      if (contactError) nextErrors.account = contactError;
      if (password !== passwordConfirm) nextErrors.passwordConfirm = '两次输入的密码不一致。';
      if (codeRequired && !code.trim()) nextErrors.code = '请输入' + codeLabel + '。';
    }
    if (Object.keys(nextErrors).length > 0) {
      setFieldErrors(nextErrors);
      setStatus('error');
      setMessage('');
      const firstInvalid = Object.keys(nextErrors)[0];
      requestAnimationFrame(() => form.querySelector(`[name="${firstInvalid}"]`)?.focus({ preventScroll: false }));
      return;
    }

    setFieldErrors({});
    setPendingAction('submit');
    setStatus('loading');
    setMessage('');
    try {
      if (isRegister) {
        const verification = await verify(policy);
        await register({ account, password, passwordConfirm, code: codeRequired ? code : undefined, verification });
      }
      else await login({ account, password });
      setStatus('success');
      setMessage(isRegister ? '账号已创建，正在进入你的主页。' : '登录成功，正在进入你的主页。');
      navigate(returnTo, { replace: true });
    } catch (error) {
      setStatus('error');
      setMessage(isRegister && refreshChangedPolicy(error) ? '注册策略有更新，请检查当前注册方式。' : getUserFacingError(error, { fallback: isRegister ? '账号暂时没有创建，请稍后重试。' : '登录没有完成，请检查账号和密码后重试。', validationFallback: isRegister ? '注册信息不符合要求，请检查后重试。' : '账号或密码格式不符合要求，请检查后重试。' }));
    } finally {
      setPendingAction('');
    }
  };

  const sendCode = async () => {
    if (pendingAction || resendIn > 0 || registrationBlocked || !codeRequired) return;
    const invalid = registrationContactError(account, method);
    if (invalid) {
      setFieldErrors((current) => ({ ...current, account: invalid }));
      return;
    }
    setPendingAction('code');
    setStatus('loading');
    setMessage('');
    try {
      await sendRegistrationCode(account, method);
      const sentAt = Date.now();
      setNow(sentAt);
      setResendAt(sentAt + (registration.resend_after || 60) * 1000);
      setCode('');
      setStatus('success');
      setMessage(method === 'phone' ? '验证码已发送，请查收短信。' : '验证码已发送，请查收邮箱。');
    } catch (error) {
      setStatus('error');
      setMessage(refreshChangedPolicy(error) ? '注册策略有更新，请检查当前注册方式。' : getUserFacingError(error, { fallback: '验证码暂时没有发送，请稍后重试。' }));
    } finally {
      setPendingAction('');
    }
  };

  const enterAsGuest = async () => {
    setPendingAction('guest');
    setStatus('loading');
    setMessage('');
    try {
      await startGuestSession();
      navigate('/');
    } catch (error) {
      setStatus('error');
      setMessage(getUserFacingError(error, { fallback: '游客会话暂不可用，请稍后重试。' }));
    } finally {
      setPendingAction('');
    }
  };

  if (authStatus === 'ready' && isAuthenticated) return <Navigate to={returnTo} replace />;

  return (
    <main className="auth-page" aria-labelledby="auth-title">
      <section className="auth-panel">
        <div className="auth-panel-topbar">
          <Brand />
          <Link className="auth-home-link" to="/" aria-label="返回首页">
            <Icon name="arrow" size={16} />
            <span>返回首页</span>
          </Link>
        </div>
        <header className="auth-heading">
          <h1 id="auth-title">{isRegister ? '创建你的空间。' : '欢迎回来。'}</h1>
          <p>{isRegister ? '加入 LoveCards，分享你愿意留下的每个瞬间。' : '登录后继续收藏、分享和与你在意的人保持联系。'}</p>
        </header>
        <form className="auth-form" onSubmit={submit}>
          <FormLayout className="auth-fields">
            {isRegister && methods.length > 1 && <SegmentedControl value={method} onChange={changeMethod} label="注册方式" layout="fill" size="lg" isDisabled={Boolean(pendingAction)}>
              {methods.map((type) => <SegmentedControlItem key={type} value={type} label={registrationContactLabels[type] + '注册'} />)}
            </SegmentedControl>}
            <CredentialFields account={account} password={password} onAccountChange={changeAccount} onPasswordChange={(value) => { setPassword(value); setFieldErrors((current) => current.password ? { ...current, password: '' } : current); }} errors={fieldErrors} disabled={!capability.canLogin || registrationBlocked || Boolean(pendingAction)} register={isRegister} method={method} />
            {isRegister && <TextInput
              autoComplete="new-password"
              isDisabled={!capability.canLogin || registrationBlocked || Boolean(pendingAction)}
              isRequired
              label="确认密码"
              htmlName="passwordConfirm"
              minLength={6}
              onChange={(value) => { setPasswordConfirm(value); setFieldErrors((current) => current.passwordConfirm ? { ...current, passwordConfirm: '' } : current); }}
              placeholder="再次输入密码"
              size="lg"
              status={fieldErrors.passwordConfirm ? { type: 'error', message: fieldErrors.passwordConfirm } : undefined}
              statusVariant="detached"
              type="password"
              value={passwordConfirm}
              width="100%"
            />}
            {codeRequired && <div className="auth-email-code">
              <TextInput label={codeLabel} autoComplete="one-time-code" inputMode={method === 'phone' ? 'numeric' : 'text'} htmlName="code" value={code} onChange={(value) => { setCode(value); setFieldErrors((current) => ({ ...current, code: '' })); }} isDisabled={registrationBlocked || Boolean(pendingAction)} isRequired status={fieldErrors.code ? { type: 'error', message: fieldErrors.code } : undefined} width="100%" />
              <Button type="button" label={resendIn > 0 ? resendIn + ' 秒后重发' : '发送验证码'} variant="secondary" size="lg" isDisabled={registrationBlocked || Boolean(pendingAction) || resendIn > 0} isLoading={pendingAction === 'code'} clickAction={sendCode} />
            </div>}
          </FormLayout>
          {message && <p className={`auth-message is-${status}`} aria-live="polite">{message}</p>}
          {registrationBlocked && <div className="auth-policy-status" role="status"><p className={policyStatus === 'loading' ? 'auth-message' : 'auth-message is-error'}>{registrationMessage}</p>{policyStatus === 'error' && <Button type="button" label="重试策略检查" variant="ghost" size="sm" onClick={() => setPolicyRetry((value) => value + 1)} />}</div>}
          <Button className="auth-submit" type="submit" label={isRegister ? '创建账号' : '登录'} variant="primary" size="lg" width="100%" isDisabled={!capability.canLogin || registrationBlocked || Boolean(pendingAction)} isLoading={pendingAction === 'submit'} />
        </form>
        <div className="auth-links">
          {!isRegister && <Link to="/auth/recovery">忘记密码？</Link>}
          {isRegister && <Link to="/auth/login">已有账号？登录</Link>}
          {guestEnabled && <Button className="auth-guest" type="button" label="游客体验" variant="ghost" size="lg" isDisabled={Boolean(pendingAction)} isLoading={pendingAction === 'guest'} clickAction={enterAsGuest} />}
          {capability.canLogin && policyStatus === 'ready' && !guestEnabled && <span className="auth-guest-unavailable">游客体验暂未开放</span>}
        </div>
        {!isRegister && <p className="auth-register">没有账号？ <Link to="/auth/register">创建 LoveCards 账号</Link></p>}
      </section>
      {humanVerificationDialog}
    </main>
  );
}
