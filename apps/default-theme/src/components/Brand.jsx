import { Link } from 'react-router-dom';
import { getThemeRuntimeContext } from '../app/runtime-context.js';

export function Brand() {
  const configuredName = getThemeRuntimeContext()?.config?.siteName;
  const siteName = typeof configuredName === 'string' && configuredName.trim() ? configuredName.trim() : 'LoveCards';
  return (
    <Link className="brand" to="/" aria-label={`${siteName} 首页`}>
      <span className="brand-mark" aria-hidden="true">L</span>
      <span>{siteName}</span>
    </Link>
  );
}
