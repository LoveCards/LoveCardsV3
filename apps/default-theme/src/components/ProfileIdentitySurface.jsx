import { useEffect, useLayoutEffect, useRef, useState } from 'react';

const GENDER_LABELS = {
  male: '男',
  female: '女',
  non_binary: '非二元',
  prefer_not_to_say: '不愿透露',
};

export function formatProfileCount(value, fallback = '—') {
  if (value === null || value === undefined || value === '') return fallback;
  const number = Number(value);
  if (!Number.isFinite(number)) return String(value);
  return number >= 10000 ? `${(number / 10000).toFixed(number >= 100000 ? 0 : 1).replace('.0', '')}万` : String(number);
}

export function normalizeProfileUid(value) {
  if (value === null || value === undefined) return '';
  return String(value).trim();
}

function ageFromBirthDate(value, now = new Date()) {
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(typeof value === 'string' ? value.trim() : '');
  if (!match) return '';
  const year = Number(match[1]);
  const month = Number(match[2]);
  const day = Number(match[3]);
  if (month < 1 || month > 12 || day < 1 || day > 31) return '';
  let age = now.getFullYear() - year;
  if (now.getMonth() + 1 < month || (now.getMonth() + 1 === month && now.getDate() < day)) age -= 1;
  return age >= 0 && age <= 120 ? String(age) : '';
}

export function buildProfileMetadata(profile) {
  if (!profile) return [];
  const metadata = [];
  const gender = typeof profile.gender === 'string' ? profile.gender.trim() : '';
  const explicitAge = profile.age === null || profile.age === undefined || profile.age === '' ? '' : String(profile.age).trim();
  const age = explicitAge || ageFromBirthDate(profile.birth_date);
  if (gender && profile.can_view_gender !== false) metadata.push({ key: 'gender', label: '性别', value: GENDER_LABELS[gender] || gender });
  if (age && profile.can_view_age !== false) metadata.push({ key: 'age', label: '年龄', value: age });
  return metadata;
}

export function ProfileIdentitySkeleton({ ariaLabel = '用户资料加载中' } = {}) {
  return <section className="profile-skeleton-hero" aria-hidden="true">
    <i className="profile-skeleton-cover" />
    <div className="profile-skeleton-identity">
      <i className="profile-skeleton-avatar" />
      <div>
        <i className="profile-skeleton-name" />
        <i className="profile-skeleton-handle" />
        <i className="profile-skeleton-bio" />
      </div>
      <div className="profile-skeleton-actions"><i /><i /></div>
    </div>
    <div className="profile-skeleton-stats"><i /><i /><i /></div>
    <span className="sr-only">{ariaLabel}</span>
  </section>;
}

/**
 * Shared identity surface for the signed-in profile and public profiles.
 * Permissions and actions are supplied by the page; geometry and hierarchy
 * stay identical so a profile never feels like a different kind of page.
 */
export function ProfileIdentitySurface({
  mode = 'owner',
  ariaLabel = '用户资料',
  contextLabel = '',
  coverUrl = '',
  coverAction = null,
  avatar,
  name,
  uid,
  location = '',
  bio = '',
  bioContent = null,
  metadata = [],
  menu = null,
  actions = null,
  stats = null,
}) {
  const normalizedUid = uid === null || uid === undefined ? '' : String(uid).trim();
  const normalizedLocation = typeof location === 'string' ? location.trim() : '';
  const normalizedBio = typeof bio === 'string' ? bio.trim() : '';
  const normalizedCover = typeof coverUrl === 'string' ? coverUrl.trim() : '';
  const normalizedContextLabel = typeof contextLabel === 'string' ? contextLabel.trim() : '';
  const normalizedMetadata = Array.isArray(metadata) ? metadata.filter((item) => item && item.value !== null && item.value !== undefined && String(item.value).trim()) : [];
  const hasCustomBio = bioContent !== null && bioContent !== undefined;
  const [bioExpanded, setBioExpanded] = useState(false);
  const [bioExpandable, setBioExpandable] = useState(false);
  const bioRef = useRef(null);

  useEffect(() => {
    setBioExpanded(false);
  }, [normalizedBio]);

  useLayoutEffect(() => {
    const bioNode = bioRef.current;
    if (!bioNode || bioExpanded) return undefined;
    const measure = () => setBioExpandable(bioNode.scrollHeight > bioNode.clientHeight + 1);
    measure();
    if (typeof ResizeObserver === 'undefined') return undefined;
    const observer = new ResizeObserver(measure);
    observer.observe(bioNode);
    return () => observer.disconnect();
  }, [bioExpanded, normalizedBio]);

  return <section className={`profile-identity-surface profile-identity-surface-${mode}`} aria-label={ariaLabel}>
    <div
      className={`profile-identity-cover${normalizedCover ? ' has-image' : ''}`}
      style={normalizedCover ? { backgroundImage: `url(${normalizedCover})` } : undefined}
      aria-hidden={coverAction || menu || normalizedContextLabel ? undefined : 'true'}
    >
      {normalizedContextLabel && <span className="profile-identity-context">{normalizedContextLabel}</span>}
      {(coverAction || menu) && <div className="profile-identity-cover-tools">
        {coverAction}
        {menu && <div className="profile-identity-menu">{menu}</div>}
      </div>}
    </div>
    <div className="profile-identity-row">
      <div className="profile-identity-avatar">{avatar}</div>
      <div className="profile-identity-main">
        <div className="profile-identity-name-row">
          <div className="profile-identity-name">{name}</div>
          <span className="profile-identity-uid">{normalizedUid ? `UID · ${normalizedUid}` : 'UID 暂不可用'}</span>
        </div>
      </div>
      {hasCustomBio && <div className="profile-identity-bio-block is-custom">{bioContent}</div>}
      {!hasCustomBio && normalizedBio && <div className="profile-identity-bio-block">
        <p ref={bioRef} className={`profile-identity-bio${bioExpanded ? ' is-expanded' : ''}`}>{normalizedBio}</p>
        {bioExpandable && <button className="profile-identity-bio-toggle" type="button" aria-expanded={bioExpanded} onClick={() => setBioExpanded((value) => !value)}>{bioExpanded ? '收起' : '展开'}</button>}
      </div>}
      {!hasCustomBio && !normalizedBio && <p className="profile-identity-empty-bio">还没有个人简介</p>}
      {(normalizedLocation || normalizedMetadata.length > 0) && <div className="profile-identity-facts">
        {normalizedLocation && <span className="profile-identity-fact profile-identity-location">{normalizedLocation}</span>}
        {normalizedMetadata.map((item, index) => <span className="profile-identity-fact" key={item.key || `${item.label || 'fact'}-${index}`}>{item.label ? `${item.label} · ` : ''}{String(item.value).trim()}</span>)}
      </div>}
      {!normalizedLocation && normalizedMetadata.length === 0 && <p className="profile-identity-empty-facts">所在地、性别与年龄尚未设置</p>}
      {actions && <div className="profile-identity-actions">{actions}</div>}
    </div>
    {stats && <div className="profile-identity-stats">{stats}</div>}
  </section>;
}
