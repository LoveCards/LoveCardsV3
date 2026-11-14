import { useEffect, useMemo, useState } from 'react';
import { Link, Navigate, useLocation, useNavigate } from 'react-router-dom';
import { Brand } from '../../components/Brand.jsx';
import { getLoginState, loadAuthPolicy, login, register, sendRegistrationCode, startGuestSession } from '../../services/auth-service.js';
import { useHumanVerification } from '../../features/human-verification/useHumanVerification.jsx';
import { useAuth } from '../../app/providers.jsx';
import { getUserFacingError } from '../../app/user-facing-error.js';
import { Button, FormLayout, Icon, TextInput } from '../../ui/index.js';
import { safeReturnPath } from '../../app/auth-routing.jsx';

export function AuthPage({ mode = 'login' }) {
  const navigate = useNavigate();
  const location = useLocation();
  const { isAuthenticated, status: authStatus } = useAuth();
  const isRegister = mode === 'register';
  const capability = useMemo(() => getLoginState(), []);
  const [account, setAccount] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirm, setPasswordConfirm] = useState('');
  const [emailCode, setEmailCode] = useState('');
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

  const registrationBlocked = Boolean(isRegister && (policyStatus !== 'ready' || !policy?.registration?.enabled || (policy.registration?.email_verification_required && !policy.registration?.email_verification_available) || (policy.human_verification?.enabled && !policy.human_verification?.available)));
  const registrationMessage = !isRegister ? ''
    : policyStatus === 'loading' ? '正在确认注册策略…'
      : policyStatus === 'error' ? '暂时无法确认注册策略，请重试。'
        : !policy?.registration?.enabled ? '当前暂未开放新账号注册。'
          : policy.registration?.email_verification_required && !policy.registration?.email_verification_available ? '邮箱验证码服务尚未就绪，请稍后重试。'
            : policy.human_verification?.enabled && !policy.human_verification?.available ? '人机验证服务尚未就绪，请稍后重试。'
              : '';
  const guestEnabled = capability.canLogin && policy?.guest?.enabled === true;

  const submit = async (event) => {
    event.preventDefault();
    const nextErrors = {};
    if (!account.trim()) nextErrors.account = '请输入手机号、邮箱或用户名。';
    if (!password) nextErrors.password = '请输入密码。';
    if (isRegister && password !== passwordConfirm) nextErrors.passwordConfirm = '两次输入的密码不一致。';
    if (isRegister && policy?.registration?.email_verification_required && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(account.trim())) nextErrors.account = '请输入有效邮箱。';
    if (isRegister && policy?.registration?.email_verification_required && !emailCode.trim()) nextErrors.emailCode = '请输入邮箱验证码。';
    if (Object.keys(nextErrors).length > 0) {
      setFieldErrors(nextErrors);
      setStatus('error');
      setMessage('');
      return;
    }

    setFieldErrors({});
    setPendingAction('submit');
    setStatus('loading');
    setMessage('');
    try {
      if (isRegister) {
        const verification = await verify(policy);
        await register({ account, password, passwordConfirm, code: emailCode, verification });
      }
      else await login({ account, password });
      setStatus('success');
      setMessage(isRegister ? '账号已创建，正在进入你的主页。' : '登录成功，正在进入你的主页。');
      navigate(returnTo, { replace: true });
    } catch (error) {
      setStatus('error');
      setMessage(getUserFacingError(error, { fallback: isRegister ? '账号暂时没有创建，请稍后重试。' : '登录没有完成，请检查账号和密码后重试。', validationFallback: isRegister ? '注册信息不符合要求，请检查后重试。' : '账号或密码格式不符合要求，请检查后重试。' }));
    } finally {
      setPendingAction('');
    }
  };

  const sendCode = async () => {
    setPendingAction('email-code');
    setStatus('loading');
    setMessage('');
    try {
      await sendRegistrationCode(account);
      setStatus('success');
      setMessage('验证码已发送，请查收邮箱。');
    } catch (error) {
      setStatus('error');
      setMessage(getUserFacingError(error, { fallback: '验证码暂时没有发送，请稍后重试。' }));
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
            <TextInput
              autoComplete={isRegister && policy?.registration?.email_verification_required ? 'email' : 'username'}
              htmlName="account"
              isDisabled={!capability.canLogin || registrationBlocked || Boolean(pendingAction)}
              isRequired
              label={isRegister && policy?.registration?.email_verification_required ? '邮箱' : '手机号、邮箱或用户名'}
              onChange={(value) => { setAccount(value); setFieldErrors((current) => current.account ? { ...current, account: '' } : current); }}
              placeholder={isRegister && policy?.registration?.email_verification_required ? 'name@example.com' : '输入你的账号'}
              size="lg"
              status={fieldErrors.account ? { type: 'error', message: fieldErrors.account } : undefined}
              statusVariant="detached"
              value={account}
              width="100%"
            />
            <TextInput
              autoComplete={isRegister ? 'new-password' : 'current-password'}
              htmlName="password"
              isDisabled={!capability.canLogin || registrationBlocked || Boolean(pendingAction)}
              isRequired
              label="密码"
              minLength={isRegister ? 6 : undefined}
              onChange={(value) => { setPassword(value); setFieldErrors((current) => current.password ? { ...current, password: '' } : current); }}
              placeholder="输入密码"
              size="lg"
              status={fieldErrors.password ? { type: 'error', message: fieldErrors.password } : undefined}
              statusVariant="detached"
              type="password"
              value={password}
              width="100%"
            />
            {isRegister && <TextInput
              autoComplete="new-password"
              isDisabled={!capability.canLogin || registrationBlocked || Boolean(pendingAction)}
              isRequired
              label="确认密码"
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
            {isRegister && policy?.registration?.email_verification_required && <div className="auth-email-code">
              <TextInput label="邮箱验证码" value={emailCode} onChange={(value) => { setEmailCode(value); setFieldErrors((current) => current.emailCode ? { ...current, emailCode: '' } : current); }} isDisabled={registrationBlocked || Boolean(pendingAction)} isRequired status={fieldErrors.emailCode ? { type: 'error', message: fieldErrors.emailCode } : undefined} width="100%" />
              <Button type="button" label="发送验证码" variant="secondary" size="lg" isDisabled={registrationBlocked || Boolean(pendingAction)} isLoading={pendingAction === 'email-code'} clickAction={sendCode} />
            </div>}
          </FormLayout>
          <p className={`auth-message is-${status}`} aria-live="polite">{message}</p>
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
