import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { readFileSync, readdirSync } from 'node:fs';
import { extname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { renderRoute } from './renderRoute.jsx';
import { settingsPathFromLegacyHash } from '../pages/settings/SettingsPage.jsx';

function filesBelow(directory, extension) {
  return readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const path = join(directory, entry.name);
    return entry.isDirectory() ? filesBelow(path, extension) : extname(path) === extension ? [path] : [];
  });
}

describe('UI integrity', () => {
  beforeEach(() => vi.stubEnv('VITE_DATA_MODE', 'design-preview'));
  afterEach(() => vi.unstubAllEnvs());

  it('keeps search scopes semantically distinct', () => {
    const posts = renderRoute('/search/posts?q=人类简史');
    const media = renderRoute('/search/media?q=人类简史');
    expect(posts).toContain('最近在读《人类简史》');
    expect(media).not.toContain('最近在读《人类简史》');
    expect(posts).not.toContain('/search/users');
  });

  it('renders compose and keeps the legacy profile edit URL as a redirect', () => {
    const compose = renderRoute('/compose');
    const composeSource = readFileSync(fileURLToPath(new URL('../pages/compose/ComposePage.jsx', import.meta.url)), 'utf8');
    const composeCss = readFileSync(fileURLToPath(new URL('../pages/compose/compose.css', import.meta.url)), 'utf8');
    const composeSpec = readFileSync(fileURLToPath(new URL('../../ui/docs/specifications/COMPOSE.md', import.meta.url)), 'utf8');
    const composeBlueprint = readFileSync(fileURLToPath(new URL('../../ui/blueprints/blueprint-04-compose.html', import.meta.url)), 'utf8');
    expect(compose).toContain('发布新内容');
    expect(compose).toContain('app-shell is-mobile-immersive is-compose-route');
    expect(compose).not.toContain('class="mobile-bottom-nav"');
    expect(composeSource).toContain('<AppShell mobileMode="immersive">');
    expect(compose).toContain('compose-content');
    expect(compose).toContain('compose-editor-surface');
    expect(compose).not.toContain('compose-step-rail');
    expect(compose).not.toContain('实时预览');
    expect(compose).not.toContain('尚无可预览内容');
    expect(compose).toContain('发布类型');
    expect(compose).toContain('data-value="text"');
    expect(compose).toContain('data-value="image"');
    expect(compose).toContain('data-value="video"');
    expect(compose).not.toContain('compose-live-card');
    expect(compose).not.toContain('发布检查');
    expect(compose).toContain('确认发布');
    expect(compose).toContain('所有人可见');
    expect(compose).toContain('纯文字，不包含媒体');
    expect(composeSource).toContain('<ComposeSkeleton />');
    expect(composeSource).toContain('<ScopedBanner');
    expect(composeSource).toContain('<section className="compose-content-field" aria-label="正文编辑">');
    expect(composeSource).toContain('compose-content-control');
    expect(composeSource.indexOf('className="compose-media-workspace"')).toBeLessThan(composeSource.indexOf('className="compose-content-field"'));
    expect(composeSource).toContain('再填写必填正文');
    expect(composeSource).toContain('CARD_CONTENT_MAX_LENGTH');
    expect(composeSource).not.toContain('普通卡片不设置标题');
    expect(composeSource).toContain('content.length >= 800');
    expect(composeSource).toContain('<ChatComposerInput');
    expect(composeSource).toContain('triggers={mentionTrigger ? [mentionTrigger] : undefined}');
    expect(composeSource).toContain('pasteAsToken={false}');
    expect(composeSource).not.toContain('mentioned_user_ids');
    expect(composeSource).not.toContain('compose-media-empty');
    expect(composeSource).not.toContain('compose-author');
    expect(composeSource).not.toContain('compose-skeleton-author');
    expect(composeCss).not.toContain('.compose-author');
    expect(composeSource).toContain('className={`compose-save-state is-${saveState.tone}`} role="status" aria-live="polite"');
    expect(composeSource).toContain("label: '已保存到草稿箱'");
    expect(composeSource).toContain("label: draftId ? '有未保存的修改' : '尚未存入草稿箱'");
    expect(composeSpec).toContain('一条连续编辑轴');
    expect(composeSpec).toContain('正文最多 1000 字');
    expect(composeSpec).toContain('正文紧随其后且必填');
    expect(composeSpec).toContain('不提供标题');
    expect(composeSpec).toContain('不重复展示当前账号身份');
    expect(composeSpec).not.toContain('作者与未保存状态');
    expect(composeBlueprint).toContain('已发布内容继续使用同一编辑轴');
    expect(composeBlueprint).not.toContain('composer-author-row');
    expect(readFileSync(fileURLToPath(new URL('../app/router.jsx', import.meta.url)), 'utf8')).toContain('<Navigate to="/me" replace />');
  });

  it('keeps drafts as a focused recovery list instead of a summary dashboard', () => {
    const html = renderRoute('/drafts');
    const source = readFileSync(fileURLToPath(new URL('../pages/community/DraftsPage.jsx', import.meta.url)), 'utf8');
    const css = readFileSync(fileURLToPath(new URL('../pages/community/community.css', import.meta.url)), 'utf8');
    const spec = readFileSync(fileURLToPath(new URL('../../ui/docs/specifications/COMPOSE.md', import.meta.url)), 'utf8');
    const blueprint = readFileSync(fileURLToPath(new URL('../../ui/blueprints/blueprint-04-compose.html', import.meta.url)), 'utf8');
    expect(html).toContain('drafts-page-head');
    expect(html).toContain('class="drafts-back" href="/compose"');
    expect(html).not.toContain('class="mobile-bottom-nav"');
    expect(source).toContain('<AppShell mobileMode="task">');
    expect(source).toContain('function DraftsSkeleton()');
    expect(source).toContain('className="draft-row-main"');
    expect(source).toContain('className="draft-delete-button"');
    expect(source).toContain('normalizeDraftMedia(payload.media)');
    expect(source).toContain('formatDraftTime(item.updated_at)');
    expect(source).toContain('<ScopedBanner className="drafts-delete-feedback"');
    expect(source).not.toContain('drafts-summary');
    expect(source).not.toContain('云端同步正常');
    expect(source).not.toContain('创作空间');
    expect(css).toContain('.community-page.drafts-page { width: min(860px');
    expect(css).toContain('.drafts-list { border-right: 0; border-left: 0; border-radius: 0; }');
    expect(spec).toContain('不展示没有服务端依据的“云端同步正常”');
    expect(blueprint).toContain('Drafts · Continuous List');
    expect(blueprint).not.toContain('drafts-summary');
  });

  it('keeps the profile surface aligned with the identity and asset blueprint', () => {
    const profile = renderRoute('/me');
    const profileSource = readFileSync(fileURLToPath(new URL('../pages/profile/ProfilePage.jsx', import.meta.url)), 'utf8');
    expect(profile).toContain('profile-mobile-top');
    expect(profile).toContain('分享主页');
    expect(profile).toContain('获赞');
    expect(profile).toContain('profile-post-list');
    expect(profile).toMatch(/>内容\s+128<\/a>/);
    expect(profile).toContain('>喜欢</a>');
    expect(profile).toContain('>收藏</a>');
    expect(profile).toContain('>历史</a>');
    expect(profile).not.toContain('评论记录');
    expect(profile).not.toContain('profile-rail');
    expect(profile).not.toContain('关系摘要');
    expect(profile).not.toContain('设计预览 · 个人内容示例');
    expect(profileSource).toContain('<ProfileContentTabs mode="self" contentCount="—" />');
    expect(profileSource).toContain('ProfileCardFeedSkeleton');
    expect(profileSource).toContain('InlineProfileAvatar');
    expect(profileSource).toContain('InlineProfileName');
    expect(profileSource).toContain('<Icon name="upload" size={16} />更换背景');
    expect(profileSource).toContain('function InlineProfileBio');
    expect(profileSource).toContain('updateProfile({ bio: nextBio })');
    expect(profileSource).toContain('bioContent={<InlineProfileBio');
    expect(profileSource).toContain('canEditIdentity');
    expect(profileSource).toContain('<ProfileIdentitySurface');
    expect(profileSource).toContain('stats={<><span className="profile-stat"');
    expect(profileSource).toContain('uid={profileUser?.uid}');
    expect(profileSource).not.toContain('账号 ${profileHandle ? `@${profileHandle}`');
    expect(profileSource).toContain('const contentCount = data.contentCount;');
    expect(profileSource).not.toContain('data.contentCount ?? data.items.length');
    expect(profileSource).not.toContain('ProfileRelationPanel');
    expect(profileSource).not.toContain('aria-controls="profile-relation-panel"');
    expect(profileSource).not.toContain('profile-relation-skeleton');
    expect(profileSource).not.toContain('>动态</small>');
    expect(profileSource).not.toContain('>收起</button>');
    expect(profileSource).not.toContain('profileBasePath}/following');
    expect(profileSource).not.toContain('profileBasePath}/followers');
    expect(profileSource).not.toContain('profile-preview-action');
    expect(profile).not.toContain('href="/me/edit"');
    expect(profileSource).toContain('clearOpen && <ConfirmActionDialog');
  });

  it('uses one progressive profile identity hierarchy for owner and public pages', () => {
    const identity = readFileSync(fileURLToPath(new URL('../components/ProfileIdentitySurface.jsx', import.meta.url)), 'utf8');
    const owner = readFileSync(fileURLToPath(new URL('../pages/profile/ProfilePage.jsx', import.meta.url)), 'utf8');
    const profileService = readFileSync(fileURLToPath(new URL('../services/profile-service.js', import.meta.url)), 'utf8');
    const publicProfile = readFileSync(fileURLToPath(new URL('../pages/community/PublicProfileView.jsx', import.meta.url)), 'utf8');
    const css = readFileSync(fileURLToPath(new URL('../pages/profile/profile.css', import.meta.url)), 'utf8');
    const patterns = readFileSync(fileURLToPath(new URL('../../ui/docs/registries/PATTERNS.md', import.meta.url)), 'utf8');
    const decision = readFileSync(fileURLToPath(new URL('../../ui/docs/decisions/BLUEPRINT-05.md', import.meta.url)), 'utf8');
    expect(identity).toContain('buildProfileMetadata');
    expect(identity).toContain('profile-identity-menu');
    expect(identity).toContain('profile-identity-context');
    expect(identity).toContain('profile-identity-cover-tools');
    expect(identity).toContain('profile-identity-bio-toggle');
    expect(identity).toContain('aria-expanded={bioExpanded}');
    expect(identity).toContain('scrollHeight > bioNode.clientHeight');
    expect(identity).toContain("ageFromBirthDate(profile.birth_date)");
    expect(identity).toContain('所在地、性别与年龄尚未设置');
    expect(identity).toContain('还没有个人简介');
    expect(profileService).toContain("gender: user.gender || ''");
    expect(profileService).toContain("birth_date: user.birth_date || ''");
    expect(owner).toContain('metadata={buildProfileMetadata(profileUser)}');
    expect(owner).toContain('contextLabel="我的"');
    expect(owner).toContain('menu={mobileActions}');
    expect(publicProfile).toContain('metadata={buildProfileMetadata(profile)}');
    expect(publicProfile).toContain('contextLabel="用户主页"');
    expect(publicProfile).toContain('menu={profileMenu}');
    expect(publicProfile).toContain("return <Navigate to={ownPath} replace />");
    expect(publicProfile).not.toContain('actions={ownProfile ?');
    expect(publicProfile).toContain('label="发私信"');
    expect(publicProfile).toContain('profile-identity-avatar-node');
    expect(publicProfile).toContain('profile?.stats?.received_likes');
    expect(publicProfile.indexOf('获赞数暂未公开')).toBeLessThan(publicProfile.indexOf("relationLink(profile, 'following'"));
    expect(publicProfile.indexOf("relationLink(profile, 'following'")).toBeLessThan(publicProfile.indexOf("relationLink(profile, 'followers'"));
    expect(css).toContain('--profile-identity-cover-height: 188px;');
    expect(css).toContain('--profile-identity-avatar-size: 96px;');
    expect(css).toContain('--profile-identity-avatar-size: 76px;');
    expect(css).toContain('grid-template-columns: var(--profile-identity-avatar-size) minmax(0, 1fr) auto');
    expect(css).toContain('grid-template-columns: var(--profile-identity-avatar-size) minmax(0, 1fr)');
    expect(css).toContain('grid-template-columns: 76px minmax(0, 1fr) auto');
    expect(css).toContain('.profile-identity-actions { grid-column: 3; grid-row: 1 / span 3;');
    expect(css).toContain('.profile-identity-actions { grid-column: 1 / -1; grid-row: auto;');
    expect(css).toContain('-webkit-line-clamp: 4');
    expect(patterns).toContain('mobile is cover → vertically centered avatar/name/UID group → bio → public metadata → actions → stats');
    expect(identity).toContain('!hasCustomBio && !normalizedBio');
    expect(identity).toContain('!normalizedLocation && normalizedMetadata.length === 0');
    expect(decision).toContain('公开身份元信息不展示 IP 地址');
  });

  it('keeps journal card copy visible in the mobile home feed', () => {
    const homeCss = readFileSync(fileURLToPath(new URL('../pages/home/home.css', import.meta.url)), 'utf8');
    expect(homeCss).toContain('.feed-story-family-text .story-copy > p { display: block; }');
    expect(homeCss).not.toContain('.feed-story-family-text .story-copy > p { display: none; }');
  });

  it('connects public profiles to the handle-scoped card feed and explicit states', () => {
    const source = readFileSync(fileURLToPath(new URL('../pages/community/PublicProfileView.jsx', import.meta.url)), 'utf8');
    const service = readFileSync(fileURLToPath(new URL('../services/community-service.js', import.meta.url)), 'utf8');
    expect(source).toContain('listPublicCardsByUser(handle');
    expect(source).toContain('公开内容加载中');
    expect(source).toContain('公开内容不可见');
    expect(source).toContain('还没有公开内容');
    expect(source).toContain('这个用户不存在');
    expect(source).toContain('ProfileContentTabs mode="public"');
    expect(source).toContain('ProfileCardFeed');
    expect(source).toContain('PublicProfileSkeleton');
    expect(source).toContain('<AppShell mobileMode="task"><main className="profile-secondary-page public-profile-relation-page"');
    expect(source).toContain('<AppShell mobileMode="standard"><ProfileShell mode="public"');
    expect(source).toContain('ProfileShell mode="public"');
    expect(source).toContain('ProfileRelationList');
    expect(source).toContain('uid={profile?.uid}');
    expect(source).not.toContain('社区成员');
    expect(source).toContain('ProfileIdentitySurface');
    expect(readFileSync(fileURLToPath(new URL('../components/ProfileRelationList.jsx', import.meta.url)), 'utf8')).toContain('ProfileRelationSkeleton');
    expect(service).toContain('cards.listPublicByUser');
    expect(service).toContain('normalizeSdkCard');
    expect(source).not.toContain('尚未提供按公开用户查询内容的接口');
    expect(renderRoute('/user/demo')).toContain('public-profile-skeleton');
    const relation = renderRoute('/user/demo/followers');
    expect(relation).toContain('profile-secondary-page public-profile-relation-page');
    expect(relation).toContain('profile-secondary-mobile-top');
    expect(relation).toContain('profile-secondary-skeleton');
    expect(relation).not.toContain('public-profile-hero');
    expect(relation).not.toContain('class="mobile-bottom-nav"');
  });

  it('keeps profile URLs on real handles instead of numeric account identifiers', () => {
    const service = readFileSync(fileURLToPath(new URL('../services/profile-service.js', import.meta.url)), 'utf8');
    expect(service).toContain('handle: user.handle || user.username || null');
    expect(service).toContain('collection?.pagination?.totalItems');
    expect(service).not.toContain('user.handle || user.number');
  });

  it('keeps form dialog actions outside the scrolling body', () => {
    const detail = readFileSync(fileURLToPath(new URL('../pages/detail/DetailPage.jsx', import.meta.url)), 'utf8');
    const report = readFileSync(fileURLToPath(new URL('../features/moderation/ReportResourceDialog.jsx', import.meta.url)), 'utf8');
    const interaction = readFileSync(fileURLToPath(new URL('../ui/interaction.jsx', import.meta.url)), 'utf8');
    // Comment body editing was intentionally removed; detail only uses confirm dialogs now.
    expect(detail).not.toContain('<FormDialog');
    expect(report).toContain('<FormDialog');
    expect(interaction).toMatch(/lc-dialog-body[\s\S]*lc-dialog-footer/);
    expect(detail).not.toMatch(/lc-dialog-body[\s\S]*lc-dialog-actions/);
    expect(report).not.toMatch(/lc-dialog-body[\s\S]*lc-dialog-actions/);
  });

  it('routes published-card editing through the complete compose surface', () => {
    const detail = readFileSync(fileURLToPath(new URL('../pages/detail/DetailPage.jsx', import.meta.url)), 'utf8');
    const compose = readFileSync(fileURLToPath(new URL('../pages/compose/ComposePage.jsx', import.meta.url)), 'utf8');
    expect(detail).toContain('navigate(`/compose?edit=${data.card.id}`)');
    expect(detail).not.toContain('detail-edit');
    expect(detail).not.toContain('saveCard');
    expect(compose).toContain("const editQuery = searchParams.get('edit')");
    expect(compose).toContain('loadPublishedCardForEdit(requestedEditId)');
    expect(compose).toContain('updatePublishedCard({');
    expect(compose).toContain("editMode ? '保存修改' : '确认发布'");
    expect(compose).toContain('!editMode && <Button className="compose-draft-save"');
  });

  it('keeps personal content tabs in one stable, swipeable order', () => {
    const labels = ['内容', '喜欢', '收藏', '历史'];
    for (const path of ['/me', '/me?tab=likes', '/me?tab=saved', '/me?tab=history']) {
      const nav = renderRoute(path).match(/<nav class="profile-tabs"[\s\S]*?<\/nav>/)?.[0] || '';
      const positions = labels.map((label) => nav.search(new RegExp(`>${label}(?:\\s+\\d+)?<\\/a>`)));
      expect(nav).toContain('aria-current="page"');
      expect(positions.every((position) => position >= 0)).toBe(true);
      expect(positions).toEqual([...positions].sort((a, b) => a - b));
    }
    expect(renderRoute('/me')).toContain('href="/me?tab=likes"');
    expect(renderRoute('/me')).toContain('href="/me?tab=saved"');
    expect(renderRoute('/me')).toContain('href="/me?tab=history"');
    expect(renderRoute('/me')).toContain('href="/me"');
    expect(renderRoute('/me')).not.toContain('href="/me?tab=content"');
    expect(renderRoute('/me')).not.toContain('href="/me/library/likes"');
  });

  it('moves profile relationships to navigation-level secondary pages', () => {
    const profile = renderRoute('/me');
    const routerSource = readFileSync(fileURLToPath(new URL('../app/router.jsx', import.meta.url)), 'utf8');
    const secondarySource = readFileSync(fileURLToPath(new URL('../pages/profile/ProfileSecondaryPage.jsx', import.meta.url)), 'utf8');
    expect(profile).not.toMatch(/<div class="profile-stats"[\s\S]*>[^<]*动态/);
    expect(profile).toContain('href="/me/following"');
    expect(profile).toContain('href="/me/followers"');
    expect(profile).toContain('class="profile-stat"');
    expect(profile).not.toContain('href="/me/likes-and-saves"');

    for (const path of ['/me/following', '/me/followers']) {
      const html = renderRoute(path);
      expect(html).toContain('profile-secondary-page');
      expect(html).toContain('profile-secondary-mobile-top');
      expect(html).not.toContain('class="mobile-bottom-nav"');
    }
    expect(renderRoute('/me/following')).toContain('>关注</h1>');
    expect(renderRoute('/me/followers')).toContain('>粉丝</h1>');
    expect(routerSource).toContain('<Route path="/me/likes-and-saves" element={<Navigate to="/me" replace />} />');
    expect(secondarySource).toContain('<AppShell mobileMode="task">');
    expect(secondarySource).not.toContain('ProfileEngagementPage');
  });

  it('keeps personal library views in the profile shell and shares message tabs', () => {
    for (const path of ['/me?tab=likes', '/me?tab=saved', '/me?tab=history']) {
      const html = renderRoute(path);
      expect(html).toContain('个人内容分类');
      expect(html).toContain('profile-page');
    }
    const routerSource = readFileSync(fileURLToPath(new URL('../app/router.jsx', import.meta.url)), 'utf8');
    expect(routerSource).toContain('<Route path="/me/library/likes" element={<Navigate to="/me?tab=likes" replace />} />');
    expect(routerSource).toContain('<Route path="/me/library/saved" element={<Navigate to="/me?tab=saved" replace />} />');
    expect(routerSource).toContain('<Route path="/me/library/history" element={<Navigate to="/me?tab=history" replace />} />');
    expect(routerSource).toContain('<Route path="/me/library/comments" element={<Navigate to="/me" replace />} />');
    expect(renderRoute('/me/library/comments')).not.toContain('profile-page');

    const inbox = renderRoute('/inbox');
    const messages = renderRoute('/messages');
    for (const html of [inbox, messages]) {
      const tabs = html.match(/<nav class="community-tabs message-center-tabs"[\s\S]*?<\/nav>/)?.[0] || '';
      expect(tabs).toContain('message-center-tab-label">通知');
      expect(tabs).toContain('message-center-tab-label">私信');
      expect(tabs.indexOf('message-center-tab-label">私信')).toBeLessThan(tabs.indexOf('message-center-tab-label">通知'));
    }
    expect(inbox).toContain('通知范围');
    expect(inbox).not.toContain('通知分类');
    expect(inbox).toContain('互动消息');
    expect(inbox).toContain('系统通知');
    expect(inbox).not.toContain('>全部</a>');
    expect(inbox).not.toContain('>关注</a>');

    const messagesSource = readFileSync(fileURLToPath(new URL('../pages/community/MessagesPage.jsx', import.meta.url)), 'utf8');
    const panelSource = readFileSync(fileURLToPath(new URL('../pages/community/ConversationPanel.jsx', import.meta.url)), 'utf8');
    expect(messagesSource).toContain('message-workspace');
    expect(messagesSource).toContain('message-workspace-desktop-panel');
    expect(panelSource).not.toContain('message-chat-details');
    expect(panelSource).toContain('message-chat-identity');
    expect(panelSource).toContain('ConversationThreadSkeleton');
    expect(messagesSource).toContain('MessageCenterHeader');
    expect(messagesSource).toContain('conversation-row-open');
    expect(messagesSource).toContain('conversationPreviewOf');
    expect(messagesSource).toContain('conversationUnreadCountOf');
    expect(messagesSource).toContain('formatMessageTime(conversationTimeOf(item))');
    expect(messagesSource).not.toContain("item.last_message || item.preview || '打开会话查看消息'");
  });

  it('keeps message surfaces and skeleton geometry on the shared radius system', () => {
    const communityCss = readFileSync(fileURLToPath(new URL('../pages/community/community.css', import.meta.url)), 'utf8');
    const patternSpec = readFileSync(fileURLToPath(new URL('../../ui/docs/registries/PATTERNS.md', import.meta.url)), 'utf8');
    expect(communityCss).toContain('Final cascade guard: the message family shares one surface grammar.');
    expect(communityCss).toContain('--_chat-composer-radius: var(--lc-radius-control) !important;');
    expect(communityCss).toContain('.message-workspace-skeleton .conversation-list-skeleton');
    expect(communityCss).toContain('border-radius: 0; background: var(--lc-surface);');
    expect(patternSpec).toContain('输入器是独立控件，使用 `--lc-radius-control`');
    expect(patternSpec).toContain('ConversationRow 与其 skeleton 必须共享同一层级和几何');
  });

  it('keeps the mobile discovery feed on the page canvas instead of a full-width panel', () => {
    const css = readFileSync(fileURLToPath(new URL('../pages/explore/explore.css', import.meta.url)), 'utf8');
    expect(css).toMatch(/\.discovery-section\s*\{[\s\S]*?background:\s*var\(--lc-canvas\)/);
  });

  it('keeps profile and relation surfaces on the shared mobile feed gutter', () => {
    const tokens = readFileSync(fileURLToPath(new URL('../styles/tokens.css', import.meta.url)), 'utf8');
    const profileCss = readFileSync(fileURLToPath(new URL('../pages/profile/profile.css', import.meta.url)), 'utf8');
    const communityCss = readFileSync(fileURLToPath(new URL('../pages/community/community.css', import.meta.url)), 'utf8');
    const cardSpec = readFileSync(fileURLToPath(new URL('../../ui/standards/CARD-SYSTEM.md', import.meta.url)), 'utf8');
    expect(tokens).toContain('--lc-feed-mobile-gutter: 9px');
    expect(profileCss).toContain('padding: 0 0 calc(88px');
    expect(profileCss).toContain('width: calc(100% - var(--lc-feed-mobile-gutter) - var(--lc-feed-mobile-gutter));');
    expect(profileCss).toContain('margin-inline: var(--lc-feed-mobile-gutter);');
    expect(profileCss).toContain('padding: 10px 0 28px;');
    expect(profileCss).toContain('.profile-feed > .feed-story { width: calc((100% - 9px) / 2); }');
    expect(profileCss).not.toMatch(/\.profile-feed\s*>\s*\.feed-story\s*\{\s*width:\s*100%/);
    expect(profileCss).toContain('padding: 0 0 calc(88px + env(safe-area-inset-bottom));');
    expect(communityCss).not.toContain('.public-profile-page .profile-feed');
    expect(communityCss).toContain('.public-profile-card-state { margin-inline: var(--lc-feed-mobile-gutter); }');
    expect(cardSpec).toContain('禁止卡片直接贴住视口边缘');
    expect(cardSpec).toContain('最窄宽度也不以 `359px` 断点退化为单列大卡');
    expect(cardSpec).toContain('Masonry 容器自身的内容宽度必须能完整容纳两列');
  });

  it('keeps compose as an immersive mobile task above the safe area', () => {
    const composeCss = readFileSync(fileURLToPath(new URL('../pages/compose/compose.css', import.meta.url)), 'utf8');
    expect(composeCss).toContain('border-radius: var(--lc-radius-panel);');
    expect(composeCss).toContain('.app-shell.is-compose-route > .product-header { display: none; }');
    expect(composeCss).toContain('.compose-editor-surface { padding: 0 16px; }');
    expect(composeCss).toContain('.compose-content-control {');
    expect(composeCss).toContain('background: var(--lc-surface-subtle);');
    expect(composeCss).toContain('padding: 0 0 calc(96px + env(safe-area-inset-bottom));');
    expect(composeCss).toContain('scroll-padding-bottom: calc(108px + env(safe-area-inset-bottom));');
    expect(composeCss).toContain('bottom: env(safe-area-inset-bottom);');
    expect(composeCss).not.toContain('bottom: calc(64px + env(safe-area-inset-bottom));');
    expect(composeCss).toContain('grid-template-columns: minmax(0, 0.86fr) minmax(0, 1.14fr);');
  });

  it('keeps avatar upload feedback inside a stable task surface', () => {
    const profileSource = readFileSync(fileURLToPath(new URL('../pages/profile/ProfilePage.jsx', import.meta.url)), 'utf8');
    const profileCss = readFileSync(fileURLToPath(new URL('../pages/profile/profile.css', import.meta.url)), 'utf8');
    const errorSpec = readFileSync(fileURLToPath(new URL('../../ui/docs/specifications/ERROR-FEEDBACK.md', import.meta.url)), 'utf8');
    expect(profileSource).toContain('<FormDialog');
    expect(profileSource).toContain('<FileInput');
    expect(profileSource).toContain('<ScopedBanner');
    expect(profileSource).toContain('statusVariant="detached"');
    expect(profileSource).toContain('正在上传头像');
    expect(profileSource).toContain('头像已更新。');
    expect(profileSource).not.toContain('profile-avatar-feedback');
    expect(profileSource).not.toContain('profile-avatar-hint');
    expect(profileCss).toContain('.avatar-upload-dialog');
    expect(profileCss).not.toContain('.profile-avatar-feedback');
    expect(errorSpec).toContain('状态标题 + 具体原因 + 下一步动作');
  });

  it('reserves the measured suggestion height above mobile search scopes', () => {
    const searchSurface = readFileSync(fileURLToPath(new URL('../features/search/DiscoverySearchSurface.jsx', import.meta.url)), 'utf8');
    const css = readFileSync(fileURLToPath(new URL('../pages/explore/explore.css', import.meta.url)), 'utf8');
    expect(searchSurface).toContain('onOpenChange={setSuggestionsOpen}');
    expect(searchSurface).toContain("--discovery-suggestions-height");
    expect(searchSurface).toContain('new ResizeObserver(updateHeight)');
    expect(css).toMatch(/\.discovery-search-surface\.is-suggestions-open\s*\{[\s\S]*?var\(--discovery-suggestions-height/);
  });

  it('keeps the mobile search subtitle above its input field', () => {
    const css = readFileSync(fileURLToPath(new URL('../styles/components.css', import.meta.url)), 'utf8');
    expect(css).toContain('.mobile-search-dialog-inner .astryx-layout-header');
    expect(css).toMatch(/\.mobile-search-dialog-inner \.astryx-layout-header[\s\S]*?margin-bottom:\s*14px;/);
    expect(css).not.toContain('.mobile-search-dialog-inner .astryx-dialog-header');
    expect(css).toMatch(/\.mobile-search-field \.astryx-typeahead\s*\{[\s\S]*?height:\s*48px[\s\S]*?min-height:\s*48px[\s\S]*?border-radius:\s*10px/);
    expect(css).toContain('.mobile-search-field .astryx-typeahead-dropdown');
    expect(css).toContain(".mobile-search-dialog-inner .astryx-layout-header [role='tooltip']");
    expect(css).toMatch(/\.header-search-field \.astryx-typeahead\s*\{[\s\S]*?height:\s*42px[\s\S]*?min-height:\s*42px[\s\S]*?border-radius:\s*10px/);
  });

  it('keeps settings on one extensible task axis with shared real navigation', () => {
    const overview = renderRoute('/settings');
    const appearance = renderRoute('/settings/appearance');
    const security = renderRoute('/settings/security');
    const session = renderRoute('/settings/session');
    const privacy = renderRoute('/settings/privacy');
    const layoutSource = readFileSync(fileURLToPath(new URL('../pages/settings/SettingsLayout.jsx', import.meta.url)), 'utf8');
    const settingsSource = readFileSync(fileURLToPath(new URL('../pages/settings/SettingsPage.jsx', import.meta.url)), 'utf8');
    const routerSource = readFileSync(fileURLToPath(new URL('../app/router.jsx', import.meta.url)), 'utf8');
    const css = readFileSync(fileURLToPath(new URL('../pages/settings/settings.css', import.meta.url)), 'utf8');
    const spec = readFileSync(fileURLToPath(new URL('../../ui/docs/specifications/SETTINGS.md', import.meta.url)), 'utf8');
    for (const html of [overview, appearance, security, privacy, session]) {
      expect(html).toContain('settings-layout');
      expect(html).toContain('settings-sidebar');
      expect(html).toContain('settings-panel');
      expect(html).not.toContain('settings-mobile-navigation');
      expect(html.match(/aria-current="page"/g)).toHaveLength(1);
      expect(html).not.toContain('class="mobile-bottom-nav"');
    }
    expect(overview).toContain('aria-current="page" href="/settings"');
    expect(overview).toContain('class="settings-overview-list"');
    expect(appearance).toContain('aria-current="page" href="/settings/appearance"');
    expect(appearance).toContain('主题模式');
    expect(appearance).not.toContain('登录密码');
    expect(security).toContain('aria-current="page" href="/settings/security"');
    expect(security).toContain('账号与安全');
    expect(security).not.toContain('主题模式');
    expect(privacy).toContain('aria-current="page" href="/settings/privacy"');
    expect(session).toContain('aria-current="page" href="/settings/session"');
    expect(session).toContain('登录状态');
    expect(session).not.toContain('登录密码');
    expect(settingsSource).toContain("section === 'security'");
    expect(settingsSource).toContain('登录密码');
    expect(settingsSource).toContain("section === 'session'");
    expect(settingsSource).toContain('退出当前账号');
    expect(privacy).not.toContain('settings-community-layout');
    expect(layoutSource).toContain('export const settingsNavigationItems');
    expect(layoutSource).toContain("href: '/settings/appearance'");
    expect(layoutSource).toContain("href: '/settings/security'");
    expect(layoutSource).toContain("href: '/settings/privacy'");
    expect(layoutSource).toContain("href: '/settings/session'");
    expect(layoutSource).not.toContain('#settings-');
    expect(layoutSource).not.toContain('/settings/preferences');
    expect(routerSource).toContain('<Route path="/settings/account" element={<Navigate to="/settings/security" replace />} />');
    expect(settingsPathFromLegacyHash('#settings-appearance')).toBe('/settings/appearance');
    expect(settingsPathFromLegacyHash('#settings-account')).toBe('/settings/security');
    expect(settingsPathFromLegacyHash('#settings-session')).toBe('/settings/session');
    expect(settingsPathFromLegacyHash('#unknown')).toBe('/settings/appearance');
    expect(css).toContain('grid-template-columns: 216px minmax(0, 1fr);');
    expect(css).not.toContain('.settings-mobile-navigation {');
    expect(spec).toContain('新增分类只修改共享导航登记和对应页面');
    expect(spec).toContain('不能伪装成可点击入口');
    expect(spec).toContain('分类导航不混用 hash 定位和页面跳转');
  });

  it('keeps nested routes connected to their immediate parent during pending and loading states', () => {
    expect(renderRoute('/settings')).toContain('class="detail-back" href="/me"');
    expect(renderRoute('/settings/privacy')).toContain('class="detail-back" href="/settings"');
    expect(renderRoute('/settings/security')).toContain('class="detail-back" href="/settings"');
    expect(renderRoute('/compose/photo')).toContain('class="detail-back pending-back" href="/compose"');
    expect(renderRoute('/messages/requests')).toContain('class="detail-back pending-back" href="/messages"');
    const draftRoute = readFileSync(fileURLToPath(new URL('../pages/community/DraftEditorPage.jsx', import.meta.url)), 'utf8');
    expect(draftRoute).toContain("/compose?draft=");
    expect(renderRoute('/drafts')).toContain('class="drafts-back" href="/compose"');
    expect(renderRoute('/drafts')).not.toContain('新建草稿');
    expect(renderRoute('/user/demo/followers')).toContain('class="profile-secondary-back" href="/user/demo"');
    expect(renderRoute('/user/demo/followers')).not.toContain('这里还没有记录');
  });

  it('redirects legacy media routes to the content detail without an intermediate page', () => {
    const mediaPage = readFileSync(fileURLToPath(new URL('../pages/detail/MediaPage.jsx', import.meta.url)), 'utf8');
    expect(mediaPage).toContain('Navigate');
    expect(mediaPage).toContain('to={`/post/${id}`}');
    expect(mediaPage).not.toContain('查看正文');
    expect(mediaPage).not.toContain('media-focus-page');
  });

  it('renders discussion as an ordinary content surface', () => {
    const html = renderRoute('/explore');
    expect(html).toContain('feed-story-discussion');
    expect(html).toContain('story-stat-replies');
    expect(html).not.toContain('story-poll');
    expect(html).not.toContain('投票');
  });

  it('keeps feed mutations behind one more menu and direct likes on detail only', () => {
    const feed = renderRoute('/');
    expect(feed).toContain('story-more-menu');
    expect(feed).toContain('aria-label="更多操作"');
    expect(feed).toContain('story-stat-replies');
    expect(feed).not.toContain('save-card-button');
    expect(feed).not.toContain('story-like-control');
  });

  it('keeps topic labels in detail context instead of repeating them in feeds', () => {
    expect(renderRoute('/')).not.toContain('story-topics');
    expect(renderRoute('/me')).not.toContain('story-topics');
    expect(renderRoute('/user/demo')).not.toContain('story-topics');
    const detailSource = readFileSync(fileURLToPath(new URL('../pages/detail/DetailPage.jsx', import.meta.url)), 'utf8');
    expect(detailSource).toContain('className="detail-topic"');
  });

  it('renders inbox as a product surface without engineering copy', () => {
    const html = renderRoute('/inbox');
    expect(html).toContain('通知');
    expect(html).toContain('全部已读');
    expect(html).not.toContain('Blueprint');
    expect(html).not.toContain('正式 React 页面');
    expect(html).not.toContain('旧工程验证页');
  });

  it('does not reference undefined local CSS custom properties', () => {
    const srcDirectory = fileURLToPath(new URL('../', import.meta.url));
    const css = filesBelow(srcDirectory, '.css').map((path) => readFileSync(path, 'utf8')).join('\n');
    const defined = new Set([...css.matchAll(/(--[a-z0-9-]+)\s*:/gi)].map((match) => match[1]));
    const used = new Set([...css.matchAll(/var\((--[a-z0-9-]+)/gi)].map((match) => match[1]));
    expect([...used].filter((name) => !defined.has(name))).toEqual([]);
  });
});
