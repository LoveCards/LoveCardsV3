import { getSdkClient, sdkConfigured } from '../app/sdk-client.js';
import { getRuntimeDataMode } from '../app/runtime-context.js';

export function getLoginState() {
  const mode = getRuntimeDataMode();
  if (mode === 'design-preview') return { source: 'preview', canLogin: false, message: '设计预览不连接账号系统。' };
  if (mode !== 'api') return { source: 'configuration-error', canLogin: false, message: '数据模式配置无效。' };
  if (!sdkConfigured()) return { source: 'configuration-error', canLogin: false, message: '尚未配置 API，暂时无法登录。' };
  return { source: 'live', canLogin: true, message: '' };
}

export async function login({ account, password }) {
  const state = getLoginState();
  if (!state.canLogin) throw new Error(state.message);
  const client = getSdkClient();
  const result = await client.session.login({ account: account.trim(), password });
  if (!result || typeof result.token !== 'string' || !result.token) {
    throw new Error('登录接口没有返回有效凭证。');
  }
  client.setToken(result.token);
  if (typeof window !== 'undefined') window.dispatchEvent(new CustomEvent('lc-auth-changed'));
  return result;
}

export async function loadAuthPolicy() {
  const client = getSdkClient();
  if (!client) throw new Error('尚未配置 API，无法读取注册策略。');
  return client.session.policy();
}

export async function sendRegistrationCode(account) {
  const client = getSdkClient();
  if (!client) throw new Error('尚未配置 API，无法发送验证码。');
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(account?.trim() || '')) throw new Error('请输入有效邮箱后再发送验证码。');
  return client.session.captcha({ account: account.trim() });
}

export async function register({ account, password, passwordConfirm, code, verification = {} }) {
  const state = getLoginState();
  if (!state.canLogin) throw new Error(state.message);
  if (!account?.trim() || !password || password !== passwordConfirm) {
    throw new Error('请填写账号，并确认两次密码一致。');
  }
  const client = getSdkClient();
  const result = await client.session.register({ account: account.trim(), password, ...(code ? { code: code.trim() } : {}), ...verification });
  if (!result?.token) throw new Error('注册接口没有返回有效凭证。');
  client.setToken(result.token);
  if (typeof window !== 'undefined') window.dispatchEvent(new CustomEvent('lc-auth-changed'));
  return result;
}

export async function startGuestSession() {
  const state = getLoginState();
  if (!state.canLogin) throw new Error(state.message);
  const client = getSdkClient();
  const result = await client.session.guest();
  if (!result?.token) throw new Error('游客会话接口没有返回有效凭证。');
  client.setToken(result.token);
  if (typeof window !== 'undefined') window.dispatchEvent(new CustomEvent('lc-auth-changed'));
  return result;
}

export async function checkSession() {
  const client = getSdkClient();
  if (!client?.getToken()) return null;
  try {
    return await client.session.check();
  } catch (error) {
    if (error?.status === 401) {
      client.clearToken();
      return null;
    }
    throw error;
  }
}

export async function loadCurrentUser() {
  const client = getSdkClient();
  if (!client?.getToken()) return null;
  const user = await client.users.me();
  if (!user || !/^\d{12}$/.test(String(user.uid || ''))) throw new Error('SDK 契约错误：users.me() 未返回有效 UID。');
  return user;
}

export async function logout() {
  const client = getSdkClient();
  try { if (client?.getToken()) await client.session.logout(); } finally {
    client?.clearToken();
    if (typeof window !== 'undefined') window.dispatchEvent(new CustomEvent('lc-auth-changed'));
  }
}
