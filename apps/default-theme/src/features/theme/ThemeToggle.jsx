import { useThemeMode } from '../../app/providers.jsx';
import { Button, Icon } from '../../ui/index.js';

const themeModes = {
  light: { label: '浅色', icon: 'sun', next: 'dark' },
  dark: { label: '深色', icon: 'moon', next: 'system' },
  system: { label: '自动', icon: 'monitor', next: 'light' },
};

export function ThemeToggle() {
  const { preference, setPreference } = useThemeMode();
  const { label, icon } = themeModes[preference];

  return <Button
    className="sidebar-theme"
    type="button"
    onClick={() => setPreference((current) => themeModes[current].next)}
    label={`切换主题 · ${label}`}
    variant="ghost"
    size="lg"
    icon={<Icon name={icon} size={17} />}
  />;
}
