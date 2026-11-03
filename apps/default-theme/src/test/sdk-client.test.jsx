import { beforeEach, describe, expect, it, vi } from 'vitest';

const createClient = vi.hoisted(() => vi.fn(() => ({ session: {} })));
const runtime = vi.hoisted(() => ({
  getThemeRuntimeContext: vi.fn(),
}));

vi.mock('@lovecards/sdk', () => ({ createClient }));
vi.mock('../app/runtime-context.js', () => runtime);

import { getSdkClient, resolveApiUrl, sdkConfigured } from '../app/sdk-client.js';

describe('SDK runtime configuration', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.stubEnv('VITE_API_URL', 'https://build-time.invalid/api');
    runtime.getThemeRuntimeContext.mockReturnValue({ apiUrl: '/api' });
  });

  it('uses the backend bootstrap apiUrl before build-time values', () => {
    expect(resolveApiUrl()).toBe('/api');
    expect(sdkConfigured()).toBe(true);
    getSdkClient();
    expect(createClient).toHaveBeenCalledWith(expect.objectContaining({ apiUrl: '/api' }));
  });
});
