import { useThemeMode } from '../../app/providers.jsx';
import { Button, Icon, IconButton } from '../../ui/index.js';

export function ThemeToggle({ showLabel = false }) {
  const { mode, toggleMode } = useThemeMode();
  const nextLabel = mode === 'dark' ? '切换到亮色模式' : '切换到暗色模式';

  const icon = <Icon name={mode === 'dark' ? 'sun' : 'moon'} size={18} />;
  return showLabel
    ? <Button className="theme-toggle" type="button" onClick={toggleMode} label={mode === 'dark' ? '亮色' : '暗色'} variant="ghost" size="lg" icon={icon} />
    : <IconButton className="theme-toggle" type="button" onClick={toggleMode} label={nextLabel} tooltip={nextLabel} variant="ghost" size="lg" icon={icon} />;
}
