import { beforeEach, describe, expect, it, vi } from 'vitest';

const sdk = vi.hoisted(() => ({
  getSdkClient: vi.fn(),
  sdkConfigured: vi.fn(),
}));
const runtime = vi.hoisted(() => ({ getRuntimeDataMode: vi.fn() }));

vi.mock('../app/sdk-client.js', () => sdk);
vi.mock('../app/runtime-context.js', () => runtime);

import { checkSession, getLoginState, login, register, sendRegistrationCode } from '../services/auth-service.js';
import { hasSessionCapability } from '../app/providers.jsx';

describe('login SDK integration', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    runtime.getRuntimeDataMode.mockReturnValue('api');
    sdk.sdkConfigured.mockReturnValue(true);
  });

  it('stores the SDK token after a successful login', async () => {
    const client = {
      session: { login: vi.fn().mockResolvedValue({ token: 'token-123' }) },
      setToken: vi.fn(),
    };
    sdk.getSdkClient.mockReturnValue(client);

    await expect(login({ account: ' demo ', password: 'secret' })).resolves.toEqual({ token: 'token-123' });
    expect(client.session.login).toHaveBeenCalledWith({ account: 'demo', password: 'secret' });
    expect(client.setToken).toHaveBeenCalledWith('token-123');
  });

  it('keeps login disabled in design preview', () => {
    runtime.getRuntimeDataMode.mockReturnValue('design-preview');
    expect(getLoginState()).toMatchObject({ canLogin: false, source: 'preview' });
  });

  it('uses the registration endpoint and stores its token', async () => {
    const client = {
      session: { register: vi.fn().mockResolvedValue({ token: 'registered-token' }) },
      setToken: vi.fn(),
    };
    sdk.getSdkClient.mockReturnValue(client);
    await register({ account: ' new-user ', password: 'secret1', passwordConfirm: 'secret1' });
    expect(client.session.register).toHaveBeenCalledWith({ account: 'new-user', password: 'secret1' });
    expect(client.setToken).toHaveBeenCalledWith('registered-token');
  });

  it('passes email and human verification results to registration', async () => {
    const client = {
      session: { captcha: vi.fn().mockResolvedValue(undefined), register: vi.fn().mockResolvedValue({ token: 'registered-token' }) },
      setToken: vi.fn(),
    };
    sdk.getSdkClient.mockReturnValue(client);
    await sendRegistrationCode(' user@example.com ');
    await register({ account: ' user@example.com ', password: 'secret1', passwordConfirm: 'secret1', code: ' 123456 ', verification: { lot_number: 'lot', captcha_output: 'output', pass_token: 'pass', gen_time: '123' } });
    expect(client.session.captcha).toHaveBeenCalledWith({ account: 'user@example.com' });
    expect(client.session.register).toHaveBeenCalledWith({ account: 'user@example.com', password: 'secret1', code: '123456', lot_number: 'lot', captcha_output: 'output', pass_token: 'pass', gen_time: '123' });
  });

  it('rejects invalid registration email before sending a code', async () => {
    const client = { session: { captcha: vi.fn() } };
    sdk.getSdkClient.mockReturnValue(client);
    await expect(sendRegistrationCode('not-an-email')).rejects.toThrow('请输入有效邮箱');
    expect(client.session.captcha).not.toHaveBeenCalled();
  });

  it('only clears a stored token when session check returns 401', async () => {
    const client = {
      getToken: vi.fn().mockReturnValue('token'),
      clearToken: vi.fn(),
      session: { check: vi.fn().mockRejectedValue({ status: 0, message: 'offline' }) },
    };
    sdk.getSdkClient.mockReturnValue(client);
    await expect(checkSession()).rejects.toMatchObject({ status: 0 });
    expect(client.clearToken).not.toHaveBeenCalled();

    client.session.check.mockRejectedValue({ status: 401 });
    await expect(checkSession()).resolves.toBeNull();
    expect(client.clearToken).toHaveBeenCalledOnce();
  });

  it('treats scoped grants as satisfying their base capability', () => {
    const session = { capabilities: ['users.read.all', 'cards.update.own', 'theme.activate'] };
    expect(hasSessionCapability(session, 'users.read')).toBe(true);
    expect(hasSessionCapability(session, 'cards.update')).toBe(true);
    expect(hasSessionCapability(session, 'theme.activate')).toBe(true);
    expect(hasSessionCapability(session, 'users.delete')).toBe(false);
  });
});
