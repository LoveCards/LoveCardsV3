import { describe, expect, it } from 'vitest';
import { getAvatarUploadError, getAvatarUploadFeedback, getErrorDetail, getUserFacingError } from '../app/user-facing-error.js';

describe('user-facing errors', () => {
  it('prefers a concrete validation detail over a generic parameter message', () => {
    expect(getUserFacingError({ code: 9002, status: 400, message: '参数错误', details: ['用户名需要 3–12 个字符'] })).toBe('用户名需要 3–12 个字符');
  });

  it('reads the first safe text from nested validation details', () => {
    expect(getErrorDetail({ avatar: { errors: ['文件格式错误'] } })).toBe('文件格式错误');
  });

  it('uses the surface validation fallback when details are absent', () => {
    expect(getUserFacingError({ code: 9002, status: 400, message: '参数错误' }, { validationFallback: '请检查资料格式。' })).toBe('请检查资料格式。');
  });

  it('does not expose technical validation details', () => {
    expect(getUserFacingError({ code: 9002, status: 400, message: '参数错误', details: 'SQLSTATE in C:\\app\\Profile.php' }, { validationFallback: '请检查资料格式。' })).toBe('请检查资料格式。');
  });

  it('does not expose SDK contract diagnostics', () => {
    expect(getUserFacingError(new Error('SDK 契约错误：接口没有返回有效编号。'), { fallback: '草稿暂时没有保存。' })).toBe('草稿暂时没有保存。');
  });

  it('turns legacy upload validation details into exact avatar guidance', () => {
    expect(getAvatarUploadError({ code: 9002, status: 400, message: '参数错误', details: '文件格式错误' })).toBe('头像格式不支持，请选择 JPG、PNG、GIF 或 WebP 图片。');
    expect(getAvatarUploadError({ code: 6001, status: 413, message: '文件大小超出限制' })).toBe('头像超过 10MB，请选择更小的图片。');
  });

  it('separates avatar file errors from task-wide upload failures', () => {
    expect(getAvatarUploadFeedback({ status: 415, message: '文件类型不允许' })).toMatchObject({
      scope: 'field',
      fieldStatus: '图片格式不支持。请选择 JPG、PNG、GIF 或 WebP。',
    });
    expect(getAvatarUploadFeedback({ status: 0, code: -3, message: '网络连接失败' })).toMatchObject({
      scope: 'task',
      title: '头像还没有上传',
      action: 'retry',
    });
    expect(getAvatarUploadFeedback({ status: 403, message: '权限不足' })).toMatchObject({
      scope: 'task',
      title: '当前账号不能更换头像',
      action: 'close',
    });
  });

  it('distinguishes network, permission, rate limit and server failures', () => {
    expect(getUserFacingError({ code: -3, status: 0, message: '网络连接失败' })).toBe('网络连接不稳定，请检查网络后重试。');
    expect(getUserFacingError({ status: 403, message: '权限不足' })).toBe('当前账号没有执行此操作的权限。');
    expect(getUserFacingError({ status: 429, message: '请求过于频繁' })).toBe('操作太频繁，请稍等一会再试。');
    expect(getUserFacingError({ status: 500, message: '服务器内部错误' }, { fallback: '头像暂时没有保存，请稍后重试。' })).toBe('头像暂时没有保存，请稍后重试。');
  });
});
