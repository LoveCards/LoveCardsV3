import { Link } from 'react-router-dom';
import { Avatar } from '../ui/icons.jsx';

export function normalizeAuthorHandle(value) {
  const handle = typeof value === 'string' ? value.trim() : '';
  if (!handle || /^(?:已注销|注销用户|deleted|unknown|null|undefined)$/i.test(handle)) return null;
  return handle;
}

export function normalizeAuthorUid(value) {
  const uid = typeof value === 'string' ? value.trim() : '';
  return /^\d{12}$/.test(uid) ? uid : null;
}

export function AuthorIdentity({ author, uid, handle: _handle, meta, avatarUrl, tone = 'rose', size = 'sm', variant = 'compact', className = '', id }) {
  const label = typeof author === 'string' && author.trim() ? author.trim() : '未知用户';
  const validUid = normalizeAuthorUid(uid);
  const legacyHandle = validUid ? null : normalizeAuthorHandle(_handle);
  const avatar = <Avatar label={label} tone={tone} size={size} src={avatarUrl} />;
  const content = <>{avatar}<span className="author-identity-copy"><b>{label}</b>{meta && <small>{meta}</small>}</span></>;
  const classes = `author-identity author-identity-${variant} ${className}`.trim();

  return validUid || legacyHandle
    ? <Link id={id} className={classes} to={`/user/${encodeURIComponent(validUid || legacyHandle)}`} aria-label={`查看 ${label} 的主页`} onClick={(event) => event.stopPropagation()}>{content}</Link>
    : <span id={id} className={classes}>{content}</span>;
}
