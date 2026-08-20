function isRecord(value) {
  return value !== null && typeof value === 'object' && !Array.isArray(value);
}

function requiredString(value, key) {
  if (typeof value[key] !== 'string' || !value[key].trim()) {
    throw new Error(`主题启动配置缺少 ${key}。`);
  }
  return value[key].trim();
}

export function readThemeBootstrap(root = globalThis.document) {
  const node = root?.getElementById?.('lc-bootstrap');
  if (!node) return null;

  let value;
  try {
    value = JSON.parse(node.textContent || '{}');
  } catch {
    throw new Error('主题启动配置不是有效 JSON。');
  }

  if (!isRecord(value) || value.mode !== 'spa') throw new Error('主题启动模式无效。');
  if (!isRecord(value.config)) throw new Error('主题启动配置缺少 config。');

  return Object.freeze({
    apiUrl: requiredString(value, 'apiUrl'),
    theme: requiredString(value, 'theme'),
    mode: 'spa',
    release: requiredString(value, 'release'),
    assetsBase: requiredString(value, 'assetsBase'),
    config: Object.freeze({ ...value.config }),
  });
}

let runtimeContext;

export function getThemeRuntimeContext() {
  if (runtimeContext === undefined) runtimeContext = readThemeBootstrap();
  return runtimeContext;
}

export function getRuntimeDataMode() {
  if (getThemeRuntimeContext()) return 'api';
  return String(import.meta.env.VITE_DATA_MODE || 'api').trim();
}

export function applyThemeRuntimeConfig(context = getThemeRuntimeContext()) {
  if (!context || typeof document === 'undefined') return;
  const siteName = typeof context.config.siteName === 'string' ? context.config.siteName.trim() : '';
  const primaryColor = typeof context.config.primaryColor === 'string' ? context.config.primaryColor.trim() : '';
  if (siteName) document.title = siteName;
  if (/^#[0-9a-f]{3,8}$/i.test(primaryColor)) {
    document.documentElement.style.setProperty('--lc-rose', primaryColor);
    document.documentElement.style.setProperty('--color-accent', primaryColor);
  }
  document.documentElement.dataset.lcTheme = context.theme;
  document.documentElement.dataset.lcMode = context.mode;
  document.documentElement.dataset.lcRelease = context.release;
  document.documentElement.dataset.lcAssetsBase = context.assetsBase;
}
