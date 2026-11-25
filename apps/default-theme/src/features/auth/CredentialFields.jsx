import { TextInput } from '../../ui/controls.jsx';

export function validateCredentials(account, password) {
  const errors = {};
  if (!account.trim()) errors.account = '请输入手机号、邮箱或用户名。';
  if (!password) errors.password = '请输入密码。';
  return errors;
}

export function CredentialFields({ account, password, onAccountChange, onPasswordChange, errors = {}, disabled = false, register = false, method }) {
  const accountLabel = register ? method === 'phone' ? '手机号' : '邮箱' : '手机号、邮箱或用户名';
  return <>
    <TextInput
      autoComplete={register ? method === 'phone' ? 'tel-national' : 'email' : 'username'}
      htmlName="account"
      isDisabled={disabled}
      isRequired
      label={accountLabel}
      onChange={onAccountChange}
      placeholder={register ? method === 'phone' ? '中国大陆 11 位手机号' : 'name@example.com' : '输入你的账号'}
      size="lg"
      status={errors.account ? { type: 'error', message: errors.account } : undefined}
      statusVariant="detached"
      type={register ? method === 'phone' ? 'tel' : 'email' : 'text'}
      value={account}
      width="100%"
    />
    <TextInput
      autoComplete={register ? 'new-password' : 'current-password'}
      htmlName="password"
      isDisabled={disabled}
      isRequired
      label="密码"
      minLength={register ? 6 : undefined}
      onChange={onPasswordChange}
      placeholder="输入密码"
      size="lg"
      status={errors.password ? { type: 'error', message: errors.password } : undefined}
      statusVariant="detached"
      type="password"
      value={password}
      width="100%"
    />
  </>;
}
