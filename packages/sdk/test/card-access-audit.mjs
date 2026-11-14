// 仅由已经通过隔离环境检查的 SDK 集成套件调用。
export async function cardAccessAudit({ admin, user, anonymous, cardId, mediaUrl, origin, test, assert, assertApiError, fetchComplete }) {
  await test('audit: restricted card denies anonymous/stranger reads and writes', async () => {
    const owner = await admin.users.me()
    const reader = await user.users.me()
    assert(owner.uid !== reader.uid, 'matrix requires two distinct active users')
    await admin.cards.update(cardId, { visibility: 'private' })
    assert((await admin.cards.getOwn(cardId)).visibility === 'private', 'owner must retain real visibility')
    for (const client of [anonymous, user]) {
      await assertApiError(() => client.cards.get(cardId), 404)
      await assertApiError(() => client.comments.cardList(cardId), 404)
    }
    await assertApiError(() => user.cards.like(cardId), 404)
    await assertApiError(() => user.comments.create(cardId, { content: 'must not persist' }), 404)
    await assertApiError(() => user.library.save({ resource_type: 'card', resource_id: String(cardId) }), 404)
    assert((await fetchComplete(origin + mediaUrl)).status === 404, 'old public hash must be revoked')
    const grant = await admin.cards.mediaAccess(cardId)
    assert(grant.media.length === 1 && grant.expires_in === 300, 'typed grant should contain real media')
    assert(grant.cover_url === grant.media[0].url, 'covered media should use the same protected grant URL')
    const url = origin + grant.media[0].url
    const response = await fetchComplete(url)
    assert(response.status === 200 && response.headers.get('cache-control').includes('no-store'), 'owner private media must stream without cache')
    assert((await fetchComplete(url, { method: 'HEAD' })).status === 200, 'authorized HEAD')
    const range = await fetchComplete(url, { headers: { Range: 'bytes=0-3' } })
    assert(range.status === 206 && range.byteLength === 4, 'authorized range')
    assert((await fetchComplete(url.replace(/grant=.*/, 'grant=invalid'))).status === 404, 'bad grant must not read media')
    await admin.cards.update(cardId, { visibility: 'followers' })
    await assertApiError(() => user.cards.get(cardId), 404)
    await user.social.follow(owner.uid)
    assert((await user.cards.get(cardId)).id === cardId, 'real follower must read card')
    const followerGrant = await user.cards.mediaAccess(cardId)
    const followerUrl = origin + followerGrant.media[0].url
    const followerResponse = await fetchComplete(followerUrl)
    assert(followerResponse.status === 200, 'real follower must read media')
    await user.social.unfollow(owner.uid)
    assert((await fetchComplete(followerUrl, { headers: { 'If-None-Match': followerResponse.headers.get('etag') } })).status === 404, 'revocation must precede 304')
    assert((await fetchComplete(followerUrl, { headers: { Range: 'bytes=0-3' } })).status === 404, 'revocation must precede range')
    await admin.cards.update(cardId, { visibility: 'public' })
    await user.safety.block(owner.uid)
    await assertApiError(() => user.cards.get(cardId), 404)
    await assertApiError(() => user.comments.cardList(cardId), 404)
    await user.safety.unblock(owner.uid)
    assert((await user.cards.get(cardId)).id === cardId, 'unblocked public card must be readable')
    await assertApiError(() => user.comments.cardList(2147483647), 404)
    const original = await admin.safety.preferences()
    await admin.safety.preferences_update({ profile_visibility: 'private' })
    try {
      assert((await anonymous.cards.get(cardId)).id === cardId, 'card public audience is independent of profile')
      assert((await user.cards.get(cardId)).id === cardId, 'authenticated public audience matches anonymous')
      await assertApiError(() => user.social.profile(owner.uid), 403)
    } finally { await admin.safety.preferences_update(original) }

    const publicProfile = () => user.social.profileByHandle(owner.username)
    const publicLikesBefore = Number((await publicProfile()).stats?.received_likes || 0)
    const ownerLikesBefore = Number((await admin.users.me()).stats?.received_likes || 0)
    await user.cards.like(cardId)
    try {
      assert(Number((await publicProfile()).stats?.received_likes) === publicLikesBefore + 1, 'public card like should increment public stats')
      assert(Number((await admin.users.me()).stats?.received_likes) === ownerLikesBefore + 1, 'public card like should increment owner stats')
      await admin.cards.update(cardId, { visibility: 'private' })
      assert(Number((await publicProfile()).stats?.received_likes) === publicLikesBefore, 'private card like must disappear from public stats')
      assert(Number((await admin.users.me()).stats?.received_likes) === ownerLikesBefore + 1, 'owner stats must retain a non-deleted restricted card like')
    } finally {
      await admin.cards.update(cardId, { visibility: 'public' })
      await user.cards.unlike(cardId)
    }
    return true
  })

  await test('audit: pin replacement and hidden comment cleanup use card pointer', async () => {
    const first = Number((await user.comments.create(cardId, { content: 'audit first pin' })).id)
    const second = Number((await user.comments.create(cardId, { content: 'audit second pin' })).id)
    await admin.comments.moderateManaged(first, { status: 0 })
    await admin.comments.moderateManaged(second, { status: 0 })
    await admin.comments.pin(cardId, { comment_id: first })
    let page = await anonymous.comments.cardList(cardId)
    assert(page.data[0].id === first && page.data[0].is_pinned, 'first pin must be first')
    await admin.comments.pin(cardId, { comment_id: second })
    page = await anonymous.comments.cardList(cardId)
    assert(page.data[0].id === second && page.data.filter(c => c.is_pinned).length === 1, 'replacement must be unique')
    await assertApiError(() => user.comments.pin(cardId, { comment_id: first }), 403)
    await admin.comments.moderateManaged(second, { status: 2 })
    page = await anonymous.comments.cardList(cardId)
    assert(!page.data.some(c => c.is_pinned), 'hidden pin must clear')
    await admin.comments.unpin(cardId)
    await admin.comments.unpin(cardId)
    return true
  })

  await test('audit: library and history cursor pages skip restricted cards without truncating later readable entries', async () => {
    const ids = []
    for (let index = 0; index < 6; index += 1) {
      const created = await admin.cards.create({ content: `SDK cursor audit ${Date.now()} ${index}` })
      const id = Number(created.id)
      assert(Number.isInteger(id) && id > 0, 'cursor fixture card must have an id')
      ids.push(id)
      await admin.cards.updateManaged(id, { status: 0, visibility: 'public' })
      await user.library.save({ resource_type: 'card', resource_id: String(id) })
      await user.library.history_record({ resource_type: 'card', resource_id: String(id) })
    }
    const restrictedIds = new Set(ids.filter((_, index) => index % 2 === 0))
    const readableIds = new Set(ids.filter((id) => !restrictedIds.has(id)))
    try {
      for (const id of restrictedIds) await admin.cards.update(id, { visibility: 'private' })

      const collect = async (readPage) => {
        const found = []
        const cursors = new Set()
        let cursor
        for (let index = 0; index < 1000; index += 1) {
          const page = await readPage({ cursor, limit: 1 })
          assert(Array.isArray(page.data), 'cursor response must include data')
          found.push(...page.data.map((item) => Number(item.resource_id)))
          if (!page.has_more) return found
          assert(typeof page.next_cursor === 'string' && page.next_cursor.length > 0, 'non-terminal page must include a cursor')
          assert(!cursors.has(page.next_cursor), 'cursor must advance without cycles')
          cursors.add(page.next_cursor)
          cursor = page.next_cursor
        }
        throw new Error('cursor pagination exceeded the safety bound')
      }
      const libraryIds = await collect((params) => user.library.list(params))
      const historyIds = await collect((params) => user.library.history(params))
      for (const [label, observed] of [['library', libraryIds], ['history', historyIds]]) {
        const fixtureMatches = observed.filter((id) => ids.includes(id))
        assert(new Set(fixtureMatches).size === fixtureMatches.length, `${label} must not duplicate fixture cards across cursor pages`)
        assert(fixtureMatches.every((id) => readableIds.has(id)), `${label} must exclude every restricted fixture card`)
        assert(readableIds.size === fixtureMatches.length && [...readableIds].every((id) => fixtureMatches.includes(id)), `${label} must continue scanning after restricted entries`)
      }
    } finally {
      for (const id of ids) await admin.cards.update(id, { visibility: 'public' })
    }
    return true
  })

  await test('audit: a shared media file remains public while another reference is public', async () => {
    const card = await anonymous.cards.get(cardId)
    const fileId = Number(card.media?.[0]?.file_id)
    assert(Number.isInteger(fileId) && fileId > 0, 'shared-media fixture needs a real media file')
    const sibling = await admin.cards.create({ content: `SDK shared media ${Date.now()}`, media_file_ids: [fileId], cover_file_id: fileId })
    const siblingId = Number(sibling.id)
    assert(Number.isInteger(siblingId) && siblingId > 0, 'shared-media sibling must have an id')
    await admin.cards.updateManaged(siblingId, { status: 0, visibility: 'public' })
    try {
      await admin.cards.update(cardId, { visibility: 'private' })
      assert((await fetchComplete(origin + mediaUrl)).status === 200, 'public sibling reference must keep shared media public')
      await admin.cards.update(siblingId, { visibility: 'private' })
      assert((await fetchComplete(origin + mediaUrl)).status === 404, 'shared media must be revoked when every reference is restricted')
    } finally {
      await admin.cards.update(cardId, { visibility: 'public' })
      try {
        await admin.cards.update(siblingId, { visibility: 'public' })
      } finally {
        await admin.cards.deleteManaged(siblingId, { reason: 'SDK shared-media audit fixture cleanup' })
      }
    }
    return true
  })
}
