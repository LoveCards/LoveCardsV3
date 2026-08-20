import { describe, expect, it, vi } from 'vitest';
import { buildCardMoreMenuItems } from '../features/feed/CardMoreMenu.jsx';

function menuItems(overrides = {}) {
  return buildCardMoreMenuItems({
    saved: false,
    busy: false,
    isReadOnly: false,
    canReport: true,
    onToggleSave: vi.fn(),
    onCopyLink: vi.fn(),
    onReport: vi.fn(),
    ...overrides,
  });
}

describe('feed card more menu', () => {
  it('groups save, link, and report actions behind one menu', () => {
    const items = menuItems();
    expect(items.map((item) => item.label || item.type)).toEqual(['收藏', '复制链接', 'divider', '举报']);
    expect(items.filter((item) => item.label).every((item) => item.isDisabled === false)).toBe(true);
  });

  it('reflects saved state and disables all preview actions', () => {
    const items = menuItems({ saved: true, isReadOnly: true });
    expect(items[0].label).toBe('取消收藏');
    expect(items.filter((item) => item.label).every((item) => item.isDisabled)).toBe(true);
  });

  it('does not offer reporting on the viewer own card', () => {
    const items = menuItems({ canReport: false });
    expect(items.map((item) => item.label || item.type)).toEqual(['收藏', '复制链接']);
  });
});
