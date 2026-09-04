import { Link } from 'react-router-dom';

function safePath(value) {
  const path = typeof value === 'string' ? value.trim() : '';
  return path.startsWith('/') && !path.startsWith('//') ? path : null;
}

function codePointOffset(value, offset) {
  const points = Array.from(value);
  const index = Number(offset);
  if (!Number.isInteger(index) || index < 0 || index > points.length) return null;
  return points.slice(0, index).join('').length;
}

/**
 * Convert server-authenticated mention ranges into renderable segments.
 * The client never discovers mentions from raw text; absent/invalid ranges
 * simply fall back to the original text.
 */
export function mentionSegments(value, mentions) {
  const text = String(value ?? '');
  if (!Array.isArray(mentions) || mentions.length === 0) return [{ type: 'text', value: text }];
  const ranges = mentions.map((mention) => {
    const start = codePointOffset(text, mention?.start);
    const end = codePointOffset(text, mention?.end);
    if (start === null || end === null || end <= start || !text.slice(start, end).startsWith('@')) return null;
    return { start, end, mention, actionUrl: safePath(mention?.action_url) };
  }).filter(Boolean).sort((left, right) => left.start - right.start);
  const segments = [];
  let cursor = 0;
  for (const range of ranges) {
    if (range.start < cursor) continue;
    if (range.start > cursor) segments.push({ type: 'text', value: text.slice(cursor, range.start) });
    segments.push({ type: 'mention', value: text.slice(range.start, range.end), mention: range.mention, actionUrl: range.actionUrl });
    cursor = range.end;
  }
  if (cursor < text.length) segments.push({ type: 'text', value: text.slice(cursor) });
  return segments.length ? segments : [{ type: 'text', value: text }];
}

export function MentionText({ text, mentions, className = '', renderText }) {
  const segments = mentionSegments(text, mentions);
  return (
    <span className={className || undefined}>
      {segments.map((segment, index) => {
        if (segment.type === 'text') {
          return typeof renderText === 'function'
            ? renderText(segment.value, `mention-text-${index}`)
            : <span key={`mention-text-${index}`}>{segment.value}</span>;
        }
        const unavailable = segment.mention?.state === 'unavailable' || !segment.actionUrl;
        if (unavailable) return <span className="mention-text-unavailable" key={`mention-${index}`}>{segment.value}</span>;
        return <Link className="mention-text-link" to={segment.actionUrl} key={`mention-${index}`} onClick={(event) => event.stopPropagation()}>{segment.value}</Link>;
      })}
    </span>
  );
}
