import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { Brand } from '../../components/Brand.jsx';
import { requestAccountRecovery, resetAccountRecovery } from '../../services/community-service.js';
import { Button, FormLayout, TextInput } from '../../ui/index.js';

const stepNumber = { identify: 1, verify: 2, reset: 3, done: 4 };

export function RecoveryFlowPage() {
  const [step, setStep] = useState('identify');
  const [account, setAccount] = useState('');
  const [challengeId, setChallengeId] = useState(null);
  const [code, setCode] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirm, setPasswordConfirm] = useState('');
  const [expiresAt, setExpiresAt] = useState(0);
  const [now, setNow] = useState(Date.now());
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState('');
  const remaining = Math.max(0, Math.ceil((expiresAt - now) / 1000));
  const expired = step !== 'identify' && step !== 'done' && expiresAt > 0 && remaining === 0;
  useEffect(() => {
    if (!expiresAt || step === 'identify' || step === 'done') return undefined;
    const interval = window.setInterval(() => setNow(Date.now()), 1000);
    return () => window.clearInterval(interval);
  }, [expiresAt, step]);
  const remainingText = useMemo(() => `${Math.floor(remaining / 60)}:${String(remaining % 60).padStart(2, '0')}`, [remaining]);
  const request = async (event) => {
    event.preventDefault();
    if (!account.trim() || busy) return;
    setBusy(true); setMessage('');
    try { const result = await requestAccountRecovery({ account: account.trim() }); setChallengeId(result?.challenge_id ?? null); const seconds = Math.max(60, Number(result?.expires_in) || 600); setExpiresAt(Date.now() + seconds * 1000); setNow(Date.now()); setStep('verify'); setMessage('如果账号可恢复，验证码已经发送。'); }
    catch (error) { setMessage(error?.message || '暂时无法发起账号恢复。'); }
    finally { setBusy(false); }
  };
  const reset = async (event) => {
    event.preventDefault();
    if (busy || expired || !code.trim() || password.length < 8 || password !== passwordConfirm) return;
    setBusy(true); setMessage('');
    try { await resetAccountRecovery({ challenge_id: challengeId, code: code.trim(), password }); setStep('done'); setMessage('密码已重置，旧会话已经失效。'); }
    catch (error) { setMessage(error?.message || '验证或重置失败，输入内容已保留。'); }
    finally { setBusy(false); }
  };
  const restart = () => { setStep('identify'); setChallengeId(null); setCode(''); setPassword(''); setPasswordConfirm(''); setExpiresAt(0); setMessage(''); };
  return <main className="auth-page recovery-page" aria-labelledby="recovery-title"><section className="auth-panel recovery-panel"><Brand /><div className="recovery-progress" aria-label={`账号恢复步骤 ${stepNumber[step]} / 4`}><span style={{ '--recovery-progress': `${stepNumber[step] * 25}%` }} /></div><header className="auth-heading"><p>步骤 {stepNumber[step]} / 4</p><h1 id="recovery-title">{step === 'identify' ? '找回你的账号' : step === 'verify' ? '输入验证码' : step === 'reset' ? '设置新密码' : '恢复完成'}</h1><p>{step === 'identify' ? '输入注册时使用的手机号、邮箱或用户名。' : step === 'verify' ? '为了保护账号隐私，无论账号是否存在都会显示相同结果。' : step === 'reset' ? '新密码至少 8 位；失败时不会清空验证码或密码。' : '现在可以使用新密码重新登录。'}</p></header>
    {step === 'identify' && <form className="auth-form" onSubmit={request}><FormLayout><TextInput autoComplete="username" label="账号信息" value={account} onChange={setAccount} placeholder="手机号、邮箱或用户名" size="lg" width="100%" isDisabled={busy} /></FormLayout><Button type="submit" label="继续" variant="primary" size="lg" width="100%" isDisabled={!account.trim() || busy} isLoading={busy} /></form>}
    {step === 'verify' && <form className="auth-form" onSubmit={(event) => { event.preventDefault(); if (code.trim() && !expired) { setMessage(''); setStep('reset'); } }}><FormLayout><TextInput autoComplete="one-time-code" label="验证码" value={code} onChange={setCode} placeholder="输入收到的验证码" size="lg" width="100%" isDisabled={expired} /></FormLayout><p className="recovery-expiry">验证码有效期：{remainingText}</p>{expired ? <Button type="button" label="重新申请" variant="primary" size="lg" width="100%" onClick={restart} /> : <Button type="submit" label="下一步" variant="primary" size="lg" width="100%" isDisabled={!code.trim()} />}</form>}
    {step === 'reset' && <form className="auth-form" onSubmit={reset}><FormLayout><TextInput autoComplete="new-password" label="新密码" type="password" value={password} onChange={setPassword} minLength={8} size="lg" width="100%" isDisabled={busy || expired} /><TextInput autoComplete="new-password" label="确认新密码" type="password" value={passwordConfirm} onChange={setPasswordConfirm} minLength={8} size="lg" width="100%" isDisabled={busy || expired} status={passwordConfirm && password !== passwordConfirm ? { type: 'error', message: '两次输入的密码不一致。' } : undefined} statusVariant="detached" /></FormLayout><p className="recovery-expiry">验证码有效期：{remainingText}</p>{expired ? <Button type="button" label="重新申请" variant="primary" size="lg" width="100%" onClick={restart} /> : <Button type="submit" label="重置密码" variant="primary" size="lg" width="100%" isDisabled={busy || password.length < 8 || password !== passwordConfirm} isLoading={busy} />}</form>}
    {step === 'done' && <div className="recovery-done"><span aria-hidden="true">✓</span><Link className="action-link action-link-primary" to="/auth/login">返回登录</Link></div>}
    <p className={`auth-message${message && step !== 'done' ? ' is-error' : ''}`} aria-live="polite">{message}</p>{step !== 'done' && <div className="auth-links"><Link to="/auth/login">← 返回登录</Link>{step !== 'identify' && <button type="button" onClick={restart}>重新开始</button>}</div>}
  </section></main>;
}
