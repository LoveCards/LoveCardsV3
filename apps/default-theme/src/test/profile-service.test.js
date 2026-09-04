import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const sdk = vi.hoisted(() => ({ getSdkClient: vi.fn() }));

vi.mock('../app/sdk-client.js', () => sdk);

import { updateProfile } from '../services/profile-service.js';

beforeEach(() => {
  vi.stubEnv('VITE_DATA_MODE', 'api');
});

afterEach(() => {
  vi.clearAllMocks();
  vi.unstubAllEnvs();
});

describe('inline profile updates', () => {
  it('updates only the username when the name editor saves', async () => {
    const updateMe = vi.fn().mockResolvedValue(undefined);
    sdk.getSdkClient.mockReturnValue({ getToken: () => 'token', users: { updateMe } });

    await expect(updateProfile({ username: '新名字' })).resolves.toEqual({ username: '新名字', avatar: undefined });
    expect(updateMe).toHaveBeenCalledWith({ username: '新名字' });
  });

  it('uploads an avatar and updates only the returned avatar URL', async () => {
    const upload = vi.fn().mockResolvedValue({ url: '/avatars/new.webp' });
    const updateMe = vi.fn().mockResolvedValue(undefined);
    const file = new Blob(['avatar'], { type: 'image/webp' });
    sdk.getSdkClient.mockReturnValue({ getToken: () => 'token', files: { upload }, users: { updateMe } });

    await expect(updateProfile({ file })).resolves.toEqual({ avatar: '/avatars/new.webp' });
    expect(upload).toHaveBeenCalledTimes(1);
    expect(upload.mock.calls[0][0].get('file')).toMatchObject({ type: file.type, size: file.size });
    expect(upload.mock.calls[0][0].get('scene')).toBe('avatar');
    expect(upload.mock.calls[0][0].get('is_public')).toBe('1');
    expect(updateMe).toHaveBeenCalledWith({ avatar: '/avatars/new.webp' });
  });

  it('cleans up an uploaded avatar when the profile update is rejected', async () => {
    const upload = vi.fn().mockResolvedValue({ id: 17, url: '/avatars/orphan.webp' });
    const remove = vi.fn().mockResolvedValue(undefined);
    const updateMe = vi.fn().mockRejectedValue(new Error('用户名已存在'));
    const file = new Blob(['avatar'], { type: 'image/webp' });
    sdk.getSdkClient.mockReturnValue({ getToken: () => 'token', files: { upload, delete: remove }, users: { updateMe } });

    await expect(updateProfile({ file })).rejects.toThrow('用户名已存在');
    expect(remove).toHaveBeenCalledWith(17);
  });
});
