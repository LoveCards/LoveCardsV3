import { Link, useNavigate } from 'react-router-dom';
import { Icon } from '../ui/icons.jsx';

export function TaskPageHeader({ title, back, onBack }) {
  const navigate = useNavigate();
  const handleBack = (event) => {
    if (onBack) {
      onBack(event);
      return;
    }
    if (back.delta === undefined || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    event.preventDefault();
    navigate(back.delta);
  };
  return <header className="route-task-heading">
    <Link to={back.to} state={back.state} aria-label={back.label} onClick={handleBack}><Icon name="arrowLeft" size={19} /></Link>
    <strong>{title}</strong>
  </header>;
}
