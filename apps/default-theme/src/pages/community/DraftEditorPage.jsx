import { Navigate, useParams } from 'react-router-dom';

// Keep old bookmarks working while making the publish page the only draft editor.
export function DraftEditorPage() {
  const { id } = useParams();
  return <Navigate replace to={id === 'new' ? '/compose' : `/compose?draft=${encodeURIComponent(id || '')}`} />;
}
