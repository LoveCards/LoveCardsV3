const genericMessages = new Set([
  '参数错误',
  '请求参数错误',
  '参数验证失败',
  '未知错误',
  '网络错误',
  '权限不足',
  '资源不存在',
  '系统异常',
  '服务器内部错误',
  '文件大小超出限制',
  '文件类型不允许',
  '文件上传失败',
]);

function cleanText(value) {
  if (typeof value !== 'string') return '';
  return value.trim().replace(/\s+/g, ' ').slice(0, 240);
}

export function getErrorDetail(details, depth = 0) {
  if (depth > 3) return '';
  const direct = cleanText(details);
  if (direct) return direct;
  if (Array.isArray(details)) {
    for (const item of details) {
      const result = getErrorDetail(item, depth + 1);
      if (result) return result;
    }
    return '';
  }
  if (details && typeof details === 'object') {
    for (const item of Object.values(details)) {
      const result = getErrorDetail(item, depth + 1);
      if (result) return result;
    }
  }
  return '';
}

function getSafeValidationDetail(details) {
  const detail = getErrorDetail(details);
  if (!detail || detail.length > 120) return '';
  if (/[\\/]|\.(?:php|js|ts|sql)\b|\b(?:select|insert|update|delete|exception|stack|trace)\b|[{}[\]]/i.test(detail)) return '';
  return /不能为空|不得为空|格式|不正确|至少|最多|不能超过|超出|需要|必须|已存在|无效|长度|不一致|不支持|不允许|过长|过短/.test(detail) ? detail : '';
}

function errorNumber(error, key) {
  const value = Number(error?.[key]);
  return Number.isFinite(value) ? value : 0;
}

function isNetworkFailure(error) {
  const code = errorNumber(error, 'code');
  const message = cleanText(error?.message).toLowerCase();
  const hasStatus = error != null && Object.prototype.hasOwnProperty.call(error, 'status');
  return (hasStatus && errorNumber(error, 'status') === 0)
    || code === -2
    || code === -3
    || message.includes('network')
    || message.includes('failed to fetch')
    || message === '网络错误'
    || message.includes('网络连接')
    || message.includes('请求超时');
}

function isValidationFailure(error) {
  const status = errorNumber(error, 'status');
  const code = errorNumber(error, 'code');
  return code === 9002 || status === 400 || status === 422;
}

function isTechnicalMessage(message) {
  return /SDK\s*契约|接口没有返回|VITE_|\b(?:axios|fetch|contract|ECONN|ERR_[A-Z_]+)\b|\.(?:php|js|ts)\b|[\\/]/i.test(message);
}

export function getUserFacingError(error, {
  fallback = '暂时没有完成，请稍后重试。',
  validationFallback = '输入内容不符合要求，请检查后重试。',
} = {}) {
  const reason = String(error?.reason_code || error?.reason || error?.details?.reason_code || '').trim();
  const reasonMessages = {
    blocked_relationship: '你与该用户的关系受到限制，暂时无法执行此操作。',
    current_password_invalid: '当前密码不正确，请重新输入。',
    email_invalid: '请输入有效的邮箱地址。',
    captcha_invalid: '验证码不正确，请检查后重试。',
    captcha_expired: '验证码已过期，请重新获取。',
    captcha_rate_limited: '验证码发送过于频繁，请稍后再试。',
    captcha_delivery_failed: '验证码暂时发送失败，请稍后再试。',
  };
  if (reasonMessages[reason]) return reasonMessages[reason];
  const status = errorNumber(error, 'status');
  const message = cleanText(error?.message);
  const detail = getSafeValidationDetail(error?.details);

  if (isValidationFailure(error)) return detail || validationFallback;
  if (isNetworkFailure(error)) return '网络连接不稳定，请检查网络后重试。';
  if (status === 401) return '登录状态已失效，请重新登录后再试。';
  if (status === 403) return '当前账号没有执行此操作的权限。';
  if (status === 413 || errorNumber(error, 'code') === 6001) return '文件超过上传大小限制，请选择更小的文件。';
  if (status === 415 || errorNumber(error, 'code') === 6002) return '文件格式不受支持，请重新选择。';
  if (status === 429) {
    const retryAfter = Number(error?.retry_after ?? error?.retryAfter ?? error?.headers?.['retry-after'] ?? error?.headers?.['Retry-After']);
    return Number.isFinite(retryAfter) && retryAfter > 0 ? `操作太频繁，请在 ${Math.ceil(retryAfter)} 秒后再试。` : '操作太频繁，请稍等一会再试。';
  }
  if (status >= 500) return fallback;
  if (message && !genericMessages.has(message) && !isTechnicalMessage(message) && !/^请求失败\s*\(\d+\)$/.test(message)) return message;
  if (status === 404) return '相关内容不存在或已不可用。';
  if (status === 409) return '当前状态已发生变化，请刷新后重试。';
  return fallback;
}

export function getAvatarUploadError(error) {
  return getAvatarUploadFeedback(error).message;
}

export function getAvatarUploadFeedback(error) {
  const status = errorNumber(error, 'status');
  const code = errorNumber(error, 'code');
  const detail = getErrorDetail(error?.details);
  const evidence = `${cleanText(error?.message)} ${detail}`;

  if (code === 6001 || status === 413 || /大小|超出上传限制|fileSize/i.test(evidence)) {
    return {
      scope: 'field',
      message: '头像超过 10MB，请选择更小的图片。',
      fieldStatus: '图片太大。请选择不超过 10MB 的图片。',
    };
  }
  if (code === 6002 || status === 415 || /格式|类型|扩展名|fileExt|mime/i.test(evidence)) {
    return {
      scope: 'field',
      message: '头像格式不支持，请选择 JPG、PNG、GIF 或 WebP 图片。',
      fieldStatus: '图片格式不支持。请选择 JPG、PNG、GIF 或 WebP。',
    };
  }
  if (/文件不得为空|无法读取|损坏/i.test(evidence)) {
    return {
      scope: 'field',
      message: '无法读取这张图片，请重新导出或选择其他图片。',
      fieldStatus: '图片无法读取。请重新导出或选择其他图片。',
    };
  }
  if (isValidationFailure(error)) {
    return {
      scope: 'field',
      message: '头像不符合上传要求，请检查图片后重新选择。',
      fieldStatus: '图片不符合要求。请检查格式和大小后重新选择。',
    };
  }
  if (isNetworkFailure(error)) {
    return {
      scope: 'task',
      message: '头像没有上传，请检查网络后重试。',
      title: '头像还没有上传',
      description: '网络连接不稳定，已保留你选择的图片。',
      action: 'retry',
    };
  }
  if (status === 401) {
    return {
      scope: 'task',
      message: '登录状态已失效，请重新登录后再上传头像。',
      title: '登录状态已失效',
      description: '重新登录后可以继续更换头像。',
      action: 'login',
    };
  }
  if (status === 403) {
    return {
      scope: 'task',
      message: '当前账号不能上传头像。',
      title: '当前账号不能更换头像',
      description: '这次选择没有上传，你可以关闭任务返回主页。',
      action: 'close',
    };
  }
  if (status === 429) {
    return {
      scope: 'task',
      message: '操作太频繁，请稍等一会再试。',
      title: '头像还没有上传',
      description: '操作太频繁，请稍等一会再重试。',
      action: 'retry',
    };
  }
  const message = getUserFacingError(error, {
    fallback: '头像暂时没有保存，请稍后重试。',
    validationFallback: '头像不符合上传要求，请检查图片后重新选择。',
  });
  return {
    scope: 'task',
    message,
    title: '头像暂时没有保存',
    description: '已保留你选择的图片，可以直接重试上传。',
    action: 'retry',
  };
}
