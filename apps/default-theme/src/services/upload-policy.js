import { getSdkClient } from '../app/sdk-client.js';

export async function loadUploadPolicy(client = getSdkClient()) {
  try {
    const policy = await client?.files?.uploadPolicy?.();
    return policy?.scenes ? policy : null;
  } catch {
    return null;
  }
}

export function uploadAccept(rule, fallback) {
  return rule ? rule.extensions.map((extension) => '.' + extension).join(',') : fallback;
}

export function uploadRuleDescription(rule, fallback) {
  if (!rule) return fallback;
  const limit = (rule.max_bytes / 1048576).toLocaleString('zh-CN', { maximumFractionDigits: 2 });
  return rule.extensions.join('、') + '，单文件最多 ' + limit + ' MiB';
}

export function validateSceneFile(file, rule) {
  if (!rule) return;
  if (!rule.available) throw new Error('当前存储渠道不支持此上传方式。');
  const extension = String(file.name || '').split('.').at(-1).toLowerCase();
  if (!rule.extensions.includes(extension)) throw new Error('当前上传规则允许这些文件格式 ' + rule.extensions.join('、'));
  const mime = String(file.type || '').toLowerCase();
  if (mime && mime !== 'application/octet-stream' && rule.mime_patterns.length
    && !rule.mime_patterns.some((pattern) => pattern === mime || (pattern.endsWith('/*') && mime.startsWith(pattern.slice(0, -1))))) {
    throw new Error('当前存储渠道不接受此媒体类型。');
  }
  if (file.size > rule.max_bytes) {
    const source = rule.storage_max_bytes !== null && rule.storage_max_bytes < rule.site_max_bytes ? '存储渠道' : '站点';
    const size = (rule.max_bytes / 1048576).toLocaleString('zh-CN', { maximumFractionDigits: 2 });
    throw new Error('文件超出' + source + '的 ' + size + ' MiB 上传上限。');
  }
}
