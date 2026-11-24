export const registrationContactLabels = { email: '邮箱', phone: '手机号' };

export function registrationContactError(account, method) {
  const value = account?.trim() || '';
  if (method === 'email' && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) return '';
  if (method === 'phone' && /^1[3-9][0-9]{9}$/.test(value)) return '';
  return method === 'phone' ? '请输入有效的中国大陆手机号。' : '请输入有效邮箱。';
}
