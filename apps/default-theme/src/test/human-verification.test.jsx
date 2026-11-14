import { afterEach, describe, expect, it, vi } from 'vitest';
import { verifyGeetest } from '../features/human-verification/useHumanVerification.jsx';

afterEach(() => vi.unstubAllGlobals());

describe('Geetest challenge', () => {
  it('returns only the four fields required by the backend after a successful challenge', async () => {
    const instance = {
      onReady: vi.fn((callback) => { instance.ready = callback; }),
      onSuccess: vi.fn((callback) => { instance.success = callback; }),
      onFail: vi.fn(), onError: vi.fn(), onClose: vi.fn(),
      showCaptcha: vi.fn(), destroy: vi.fn(),
      getValidate: vi.fn().mockReturnValue({ lot_number: 'lot', captcha_output: 'output', pass_token: 'pass', gen_time: '123', ignored: 'value' }),
    };
    const initGeetest4 = vi.fn((_options, callback) => callback(instance));
    vi.stubGlobal('window', { initGeetest4, setTimeout, clearTimeout });
    const result = verifyGeetest('public-id');
    await vi.waitFor(() => expect(instance.onReady).toHaveBeenCalled());
    instance.ready();
    expect(instance.showCaptcha).toHaveBeenCalledOnce();
    instance.success();
    await expect(result).resolves.toEqual({ lot_number: 'lot', captcha_output: 'output', pass_token: 'pass', gen_time: '123' });
    expect(initGeetest4).toHaveBeenCalledWith(expect.objectContaining({ captchaId: 'public-id', product: 'bind' }), expect.any(Function));
    expect(instance.destroy).toHaveBeenCalledOnce();
  });

  it('rejects an incomplete challenge result before the write request', async () => {
    const instance = {
      onReady: vi.fn((callback) => callback()),
      onSuccess: vi.fn((callback) => { instance.success = callback; }),
      onFail: vi.fn(), onError: vi.fn(), onClose: vi.fn(),
      showCaptcha: vi.fn(), destroy: vi.fn(),
      getValidate: vi.fn().mockReturnValue({ lot_number: 'lot' }),
    };
    vi.stubGlobal('window', { initGeetest4: (_options, callback) => callback(instance), setTimeout, clearTimeout });
    const result = verifyGeetest('public-id');
    await vi.waitFor(() => expect(instance.onSuccess).toHaveBeenCalled());
    instance.success();
    await expect(result).rejects.toThrow('验证结果不完整');
    expect(instance.destroy).toHaveBeenCalledOnce();
  });
});
