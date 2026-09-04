import { useLocation, useParams } from 'react-router-dom';
import { AppShell } from '../../layouts/AppShell.jsx';
import { ConversationPanel } from './ConversationPanel.jsx';

export function ConversationPage() {
  const { conversationId } = useParams();
  const location = useLocation();
  return <AppShell mobileMode="task"><main className="community-page conversation-page" aria-label="私信会话"><ConversationPanel key={conversationId} conversationId={conversationId} initialRecipientId={location.state?.recipientId} standalone /></main></AppShell>;
}
