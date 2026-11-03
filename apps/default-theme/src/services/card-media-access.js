// 只消费 SDK 签发的地址；关注、拉黑与会话权限均由服务端决定。
export function installCardMediaAccess(client, apiUrl) {
  if (!client?.cards?.mediaAccess) return;
  const cards = client.cards;
  const hydrate = async (card) => {
    if (!card || !['followers', 'private'].includes(card.visibility)) return card;
    if (!card.media?.length && !card.cover) return card;
    const absolute = (url) => url ? new URL(url, new URL(apiUrl, window.location.origin)).href : null;
    let pending;
    const loadAccess = () => {
      pending ||= cards.mediaAccess(card.id).finally(() => { pending = null; });
      return pending;
    };
    const access = await loadAccess();
    const urls = new Map(access.media.map((item) => [item.file_id, absolute(item.url)]));
    const media = (card.media || []).map((item) => ({
      ...item, url: urls.get(item.file_id) || '', poster_url: null,
      renewUrl: async () => absolute((await loadAccess()).media.find((file) => file.file_id === item.file_id)?.url),
    })).filter((item) => item.url);
    return { ...card, media, pictures: media.map((item) => item.url), cover: absolute(access.cover_url) };
  };
  for (const name of ['get', 'getOwn', 'list', 'listOwn', 'hot', 'search', 'listFollowing', 'listPublicByUser', 'publicByHandle']) {
    if (typeof cards[name] !== 'function') continue;
    const original = cards[name].bind(cards);
    cards[name] = async (...args) => {
      const result = await original(...args);
      if (Array.isArray(result)) return Promise.all(result.map(hydrate));
      if (Array.isArray(result?.data)) return { ...result, data: await Promise.all(result.data.map(hydrate)) };
      return hydrate(result);
    };
  }
}
