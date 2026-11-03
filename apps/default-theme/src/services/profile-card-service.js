import { getSdkClient } from '../app/sdk-client.js';
import { normalizeSdkCard } from './feed-service.js';

export function cardReferenceId(item) {
  if (!item || typeof item !== 'object') return null;
  const refType = String(item.ref_type ?? '').trim().toLowerCase();
  const resourceType = String(item.resource_type ?? '').trim().toLowerCase();
  if ((refType && refType !== 'card') || (resourceType && resourceType !== 'card')) return null;
  const candidates = [
    item.ref_type === 'card' ? item.ref_id : null,
    item.resource_type === 'card' ? item.resource_id : null,
    item.resource_id,
    item.card_id,
    item.pid,
    item.ref_id,
    // Legacy likes use aid as a content channel id (often 1), so it must
    // remain the last fallback after the real card references above.
    item.aid,
  ];
  for (const candidate of candidates) {
    const value = Number(candidate);
    if (Number.isInteger(value) && value > 0) return value;
  }
  return null;
}

export async function hydrateCardReference(item) {
  const id = cardReferenceId(item);
  if (!id) return { ...item, resource: null, unsupported: true };
  try {
    const resource = normalizeSdkCard(await getSdkClient().cards.get(id));
    const meta = item?.viewed_at || item?.created_at;
    return { ...item, resource: meta ? { ...resource, meta } : resource, unsupported: false };
  } catch (error) {
    const status = Number(error?.status ?? error?.response?.status ?? 0);
    return { ...item, resource: null, deleted: status === 404, unavailable: status !== 404 };
  }
}

export function hydrateCardReferences(items) {
  return Promise.all((Array.isArray(items) ? items : []).map(hydrateCardReference));
}
