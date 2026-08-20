import { describe, expect, it } from 'vitest';
import { renderRoute } from './renderRoute.jsx';

function count(markup, pattern) {
  return [...markup.matchAll(pattern)].length;
}

function buttonsWithoutNames(markup) {
  return [...markup.matchAll(/<button\b([^>]*)>([\s\S]*?)<\/button>/g)].filter(([, attributes, content]) => {
    const hasAriaName = /aria-label=("[^"]+"|'[^']+')/.test(attributes);
    const text = content.replace(/<[^>]+>/g, '').replace(/&[^;]+;/g, '').trim();
    return !hasAriaName && text.length === 0;
  });
}

describe('accessibility smoke', () => {
  for (const path of ['/', '/explore', '/missing']) {
    it(`${path} has one main landmark and one h1`, () => {
      const html = renderRoute(path);
      expect(count(html, /<main\b/g)).toBe(1);
      expect(count(html, /<h1\b/g)).toBe(1);
      expect(buttonsWithoutNames(html)).toHaveLength(0);
    });
  }

  it('preserves dark theme semantics in server markup', () => {
    const html = renderRoute('/', 'dark');
    expect(html).toContain('data-theme="dark"');
    expect(html).toContain('data-astryx-theme="neutral"');
  });
});
