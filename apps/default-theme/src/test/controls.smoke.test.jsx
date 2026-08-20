import { readFileSync } from 'node:fs';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vitest';
import { AppProviders } from '../app/providers.jsx';
import { Button, FormLayout, IconButton, TextArea, TextInput, ToggleButton } from '../ui/index.js';

describe('Astryx control boundary', () => {
  it('forwards field, loading, disabled, and pressed states to Astryx', () => {
    const html = renderToStaticMarkup(
      <AppProviders initialMode="light">
        <FormLayout>
          <TextInput label="账号" value="" onChange={() => undefined} isDisabled status={{ type: 'error', message: '请输入账号。' }} statusVariant="detached" />
          <TextArea label="简介" value="" onChange={() => undefined} rows={3} />
          <Button label="保存" isLoading />
          <IconButton label="关闭" icon={<span aria-hidden="true">×</span>} />
          <ToggleButton label="喜欢" isPressed icon={<span aria-hidden="true">♡</span>} />
        </FormLayout>
      </AppProviders>,
    );

    expect(html).toContain('astryx-text-input');
    expect(html).toContain('astryx-textarea');
    expect(html).toContain('astryx-button');
    expect(html).toContain('astryx-toggle-button');
    expect(html).toContain('aria-invalid="true"');
    expect(html).toContain('aria-busy="true"');
    expect(html).toContain('aria-pressed="true"');
    expect(html).toContain('请输入账号。');
  });

  it('keeps Auth CSS outside Astryx internal input and button elements', () => {
    const css = readFileSync(new URL('../pages/auth/auth.css', import.meta.url), 'utf8');
    expect(css).not.toMatch(/\.auth-form\s+(?:input|textarea|button)\b/);
    expect(css).toContain('.auth-fields .astryx-text-input');
    expect(css).toContain('.auth-submit.astryx-button');
  });
});
