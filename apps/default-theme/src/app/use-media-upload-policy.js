import { useEffect, useState } from 'react';
import { loadUploadPolicy } from '../services/upload-policy.js';

export function useMediaUploadPolicy(enabled) {
  const [policy, setPolicy] = useState(null);
  useEffect(() => {
    if (!enabled) return undefined;
    let active = true;
    loadUploadPolicy().then((value) => { if (active) setPolicy(value); });
    return () => { active = false; };
  }, [enabled]);
  return enabled ? policy : null;
}
