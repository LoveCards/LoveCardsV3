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

// 按 sRGB 相对亮度为站点自选填充色选择前景；透明颜色按当前画布合成。
function accentForeground(hex, canvas) {
  let digits = hex.slice(1);
  if (digits.length <= 4) digits = Array.from(digits, (digit) => digit + digit).join('');
  const alpha = digits.length === 8 ? parseInt(digits.slice(6), 16) / 255 : 1;
  const luminance = [0.2126, 0.7152, 0.0722].reduce((sum, weight, index) => {
    const channel = parseInt(digits.slice(index * 2, index * 2 + 2), 16);
    const value = (channel * alpha + canvas[index] * (1 - alpha)) / 255;
    return sum + weight * (value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4);
  }, 0);
  return 1.05 / (luminance + 0.05) >= (luminance + 0.05) / 0.05 ? '#fff' : '#000';
}

export function applyThemeRuntimeConfig(context = getThemeRuntimeContext()) {
  if (!context || typeof document === 'undefined') return;
  const siteName = typeof context.config.siteName === 'string' ? context.config.siteName.trim() : '';
  const primaryColor = typeof context.config.primaryColor === 'string' ? context.config.primaryColor.trim() : '';
  if (siteName) document.title = siteName;
  // 默认配置交给主题变量，以便暗色模式使用自己的强调色。
  if (/^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i.test(primaryColor) && primaryColor.toLowerCase() !== '#c92c60') {
    document.documentElement.style.setProperty('--lc-custom-accent', primaryColor);
    document.documentElement.style.setProperty('--lc-custom-on-accent-light', accentForeground(primaryColor, [245, 245, 247]));
    document.documentElement.style.setProperty('--lc-custom-on-accent-dark', accentForeground(primaryColor, [16, 18, 22]));
    document.documentElement.style.setProperty('--color-accent', primaryColor);
  }
  document.documentElement.dataset.lcTheme = context.theme;
  document.documentElement.dataset.lcMode = context.mode;
  document.documentElement.dataset.lcRelease = context.release;
  document.documentElement.dataset.lcAssetsBase = context.assetsBase;
}
