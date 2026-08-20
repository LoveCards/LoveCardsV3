import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { readFileSync, readdirSync } from 'node:fs';
import { extname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { renderRoute } from './renderRoute.jsx';

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

  it('renders compose and profile editing surfaces', () => {
    const compose = renderRoute('/compose');
    expect(compose).toContain('发布新内容');
    expect(compose).toContain('compose-content');
    expect(compose).toContain('编辑内容');
    expect(compose).not.toContain('实时预览');
    expect(compose).not.toContain('尚无可预览内容');
    expect(compose).toContain('发布类型');
    expect(compose).toContain('data-value="text"');
    expect(compose).toContain('data-value="image"');
    expect(compose).toContain('data-value="video"');
    expect(compose).not.toContain('compose-live-card');
    expect(compose).toContain('发布检查');
    expect(compose).toContain('发布类型：随笔');
    expect(compose).toContain('纯文字，不包含媒体');
    expect(renderRoute('/me/edit')).toContain('编辑资料');
  });

  it('keeps the profile surface aligned with the identity and asset blueprint', () => {
    const profile = renderRoute('/me');
    expect(profile).toContain('profile-mobile-top');
    expect(profile).toContain('分享主页');
    expect(profile).toContain('获赞与收藏');
    expect(profile).toContain('profile-post-list');
    expect(profile).toContain('草稿箱');
    expect(profile).toContain('关系摘要');
    expect(profile).not.toContain('设计预览 · 个人内容示例');
  });

  it('keeps form dialog actions outside the scrolling body', () => {
    const detail = readFileSync(fileURLToPath(new URL('../pages/detail/DetailPage.jsx', import.meta.url)), 'utf8');
    const report = readFileSync(fileURLToPath(new URL('../features/moderation/ReportResourceDialog.jsx', import.meta.url)), 'utf8');
    const interaction = readFileSync(fileURLToPath(new URL('../ui/interaction.jsx', import.meta.url)), 'utf8');
    expect(detail).toContain('<FormDialog');
    expect(report).toContain('<FormDialog');
    expect(interaction).toMatch(/lc-dialog-body[\s\S]*lc-dialog-footer/);
    expect(detail).not.toMatch(/lc-dialog-body[\s\S]*lc-dialog-actions/);
    expect(report).not.toMatch(/lc-dialog-body[\s\S]*lc-dialog-actions/);
  });

  it('keeps personal content tabs in one stable, swipeable order', () => {
    const labels = ['我的内容', '喜欢', '评论', '收藏', '历史'];
    for (const path of ['/me', '/me/library/likes', '/me/library/comments', '/me/library/saved', '/me/library/history']) {
      const nav = renderRoute(path).match(/<nav class="profile-tabs"[\s\S]*?<\/nav>/)?.[0] || '';
      const positions = labels.map((label) => nav.indexOf(`>${label}</a>`));
      expect(nav).toContain('aria-current="page"');
      expect(positions.every((position) => position >= 0)).toBe(true);
      expect(positions).toEqual([...positions].sort((a, b) => a - b));
    }
  });

  it('keeps personal library pages connected to the profile and shares message tabs', () => {
    for (const path of ['/me/library/likes', '/me/library/comments', '/me/library/saved', '/me/library/history']) {
      const html = renderRoute(path);
      expect(html).toContain('class="detail-back" href="/me"');
      expect(html).toContain('个人内容分类');
    }

    const inbox = renderRoute('/inbox');
    const messages = renderRoute('/messages');
    for (const html of [inbox, messages]) {
      const tabs = html.match(/<nav class="community-tabs message-center-tabs"[\s\S]*?<\/nav>/)?.[0] || '';
      expect(tabs).toContain('>通知</a>');
      expect(tabs).toContain('>私信</a>');
      expect(tabs.indexOf('>通知</a>')).toBeLessThan(tabs.indexOf('>私信</a>'));
    }
    expect(inbox).toContain('通知筛选');
    expect(inbox).toContain('全部');
  });

  it('keeps the mobile discovery feed on the page canvas instead of a full-width panel', () => {
    const css = readFileSync(fileURLToPath(new URL('../pages/explore/explore.css', import.meta.url)), 'utf8');
    expect(css).toMatch(/\.discovery-section\s*\{[\s\S]*?background:\s*var\(--lc-canvas\)/);
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

  it('keeps nested routes connected to their immediate parent during pending and loading states', () => {
    expect(renderRoute('/settings/privacy')).toContain('class="detail-back" href="/settings"');
    expect(renderRoute('/settings/account')).toContain('class="detail-back pending-back" href="/settings"');
    expect(renderRoute('/compose/photo')).toContain('class="detail-back pending-back" href="/compose"');
    expect(renderRoute('/messages/requests')).toContain('class="detail-back pending-back" href="/messages"');
    const draftRoute = readFileSync(fileURLToPath(new URL('../pages/community/DraftEditorPage.jsx', import.meta.url)), 'utf8');
    expect(draftRoute).toContain("/compose?draft=");
    expect(renderRoute('/drafts')).toContain('class="detail-back" href="/compose"');
    expect(renderRoute('/drafts')).not.toContain('新建草稿');
    expect(renderRoute('/user/demo/followers')).toContain('class="detail-back" href="/user/demo"');
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
