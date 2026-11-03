import { AuthorIdentity } from './AuthorIdentity.jsx';

export function ProfileRelationSkeleton({ title }) {
  return <div className="profile-secondary-skeleton" role="status" aria-label={`${title}加载中`}>
    <span className="sr-only">{title}加载中</span>
    {[0, 1, 2, 3].map((item) => <div className="profile-secondary-skeleton-row" key={item} aria-hidden="true"><i /><span><i /><i /></span></div>)}
  </div>;
}

export function ProfileRelationList({ items = [], emptyLabel = '用户', className = '' }) {
  return <div className={`profile-secondary-list${className ? ` ${className}` : ''}`} role="list" aria-label="关系用户列表">
    {items.map((row, index) => {
      const user = row.user || row;
      const uid = user.uid || row.uid || null;
      const handle = user.handle || user.username || row.handle || row.username || '';
      const author = user.display_name || user.username || row.display_name || row.username || row.name || row.number || `${emptyLabel} ${index + 1}`;
      return <article className="profile-secondary-row" role="listitem" key={user.id || row.id || uid || handle || index}>
        <AuthorIdentity author={author} uid={uid} handle={handle} avatarUrl={user.avatar || row.avatar} meta={uid ? `UID · ${uid}` : 'UID 暂不可用'} size="md" variant="list" />
      </article>;
    })}
  </div>;
}
