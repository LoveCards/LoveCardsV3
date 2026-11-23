import { useEffect, useRef, useState } from 'react';
import { normalizeAuthorUid } from '../../components/AuthorIdentity.jsx';
import { getUserFacingError } from '../../app/user-facing-error.js';
import { followUser, getUserRelation } from '../../services/community-service.js';
import { Button, useToast } from '../../ui/index.js';

export function DetailFollowButton({ authorUid, viewerUid, isAuthenticated, source, onRequireLogin, onFollowed }) {
  const uid = normalizeAuthorUid(authorUid);
  const eligible = source === 'live' && uid && uid !== viewerUid;
  const [relation, setRelation] = useState({ uid: null, viewerUid: null, status: 'idle' });
  const inFlight = useRef(false);
  const toast = useToast();

  useEffect(() => {
    if (!eligible || !isAuthenticated) return undefined;
    let active = true;
    setRelation({ uid, viewerUid, status: 'loading' });
    getUserRelation(uid).then((result) => {
      if (active) setRelation({ uid, viewerUid, status: result?.following || result?.blocked_by_me ? 'hidden' : 'available' });
    }).catch(() => {
      if (active) setRelation({ uid, viewerUid, status: 'error' });
    });
    return () => { active = false; };
  }, [eligible, isAuthenticated, uid, viewerUid]);

  if (!eligible || (isAuthenticated && (relation.uid !== uid || relation.viewerUid !== viewerUid || !['available', 'submitting'].includes(relation.status)))) return null;

  const follow = async () => {
    if (!isAuthenticated) { onRequireLogin(); return; }
    if (inFlight.current) return;
    inFlight.current = true;
    setRelation({ uid, viewerUid, status: 'submitting' });
    try {
      await followUser(uid);
      setRelation({ uid, viewerUid, status: 'hidden' });
      onFollowed?.();
      toast({ body: '已关注', uniqueID: `detail-follow-${uid}`, collisionBehavior: 'overwrite' });
    } catch (error) {
      setRelation({ uid, viewerUid, status: 'available' });
      toast({ body: getUserFacingError(error, { fallback: '暂时无法关注，请稍后重试。' }), type: 'error', uniqueID: `detail-follow-${uid}`, collisionBehavior: 'overwrite' });
    } finally {
      inFlight.current = false;
    }
  };

  return <Button className="detail-follow-button" type="button" label="+ 关注" variant="ghost" size="sm" isDisabled={relation.status === 'submitting'} isLoading={relation.status === 'submitting'} onClick={follow} />;
}
