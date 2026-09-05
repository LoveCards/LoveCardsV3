import { Navigate, Route, Routes } from 'react-router-dom';
import { HomePage } from '../pages/home/HomePage.jsx';
import { ExplorePage } from '../pages/explore/ExplorePage.jsx';
import { DetailPage } from '../pages/detail/DetailPage.jsx';
import { MediaPage } from '../pages/detail/MediaPage.jsx';
import { ProfilePage } from '../pages/profile/ProfilePage.jsx';
import { ProfileRelationPage } from '../pages/profile/ProfileSecondaryPage.jsx';
import { ComposePage } from '../pages/compose/ComposePage.jsx';
import { AuthPage } from '../pages/auth/AuthPage.jsx';
import { BlueprintPendingPage } from '../pages/system/BlueprintPendingPage.jsx';
import { RecoveryFlowPage } from '../pages/auth/RecoveryFlowPage.jsx';
import { NotFoundPage } from '../pages/system/NotFoundPage.jsx';
import { SettingsIndexRedirect, SettingsPage } from '../pages/settings/SettingsPage.jsx';
import { NotificationSettingsPage } from '../pages/settings/NotificationSettingsPage.jsx';
import { BlockedUsersPage } from '../pages/settings/BlockedUsersPage.jsx';
import { ProfileSettingsPage } from '../pages/settings/ProfileSettingsPage.jsx';
import { ConversationPage } from '../pages/community/ConversationPage.jsx';
import { DraftsPage } from '../pages/community/DraftsPage.jsx';
import { DraftEditorPage } from '../pages/community/DraftEditorPage.jsx';
import { InboxPage } from '../pages/community/InboxPage.jsx';
import { MessagesPage } from '../pages/community/MessagesPage.jsx';
import { NewMessagePage } from '../pages/community/NewMessagePage.jsx';
import { PrivacyPage } from '../pages/community/PrivacyPage.jsx';
import { PublicProfileView } from '../pages/community/PublicProfileView.jsx';
import { ProtectedRoute } from './auth-routing.jsx';

const pendingRoutes = [
  { path: '/compose/:type', title: '发布类型', description: '针对不同媒体类型提供对应编辑体验。', capability: '分类发布编辑器暂未开放，请使用通用发布页。', backTo: '/compose', backLabel: '返回发布', mobileMode: 'immersive', protected: true },
];

export function AppRoutes() {
  return (
    <Routes>
      <Route path="/" element={<HomePage />} />
      <Route path="/explore" element={<ExplorePage />} />
      <Route path="/search" element={<ExplorePage search />} />
      <Route path="/search/:scope" element={<ExplorePage search />} />
      <Route path="/topic/:slug" element={<ExplorePage topic />} />
      <Route path="/post/:id" element={<DetailPage />} />
      <Route path="/post/:id/thread/:commentId" element={<DetailPage />} />
      <Route path="/media/:id" element={<MediaPage />} />
      <Route path="/me" element={<ProtectedRoute><ProfilePage /></ProtectedRoute>} />
      <Route path="/me/following" element={<ProtectedRoute><ProfileRelationPage relation="following" /></ProtectedRoute>} />
      <Route path="/me/followers" element={<ProtectedRoute><ProfileRelationPage relation="followers" /></ProtectedRoute>} />
      {/* Legacy deep links remain safe, but received likes are display-only and have no detail page. */}
      <Route path="/me/likes-and-saves" element={<Navigate to="/me" replace />} />
      <Route path="/me/library/likes" element={<Navigate to="/me?tab=likes" replace />} />
      <Route path="/me/library/saved" element={<Navigate to="/me?tab=saved" replace />} />
      <Route path="/me/library/history" element={<Navigate to="/me?tab=history" replace />} />
      {/* Legacy comments-record links no longer have a user-center page. */}
      <Route path="/me/library/comments" element={<Navigate to="/me" replace />} />
      {/* Legacy deep links resolve to the new inline identity editor. */}
      <Route path="/me/edit" element={<Navigate to="/settings/profile" replace />} />
      <Route path="/settings" element={<SettingsIndexRedirect />} />
      <Route path="/settings/profile" element={<ProtectedRoute><ProfileSettingsPage /></ProtectedRoute>} />
      <Route path="/settings/appearance" element={<SettingsPage section="appearance" />} />
      <Route path="/settings/security" element={<ProtectedRoute><SettingsPage section="security" /></ProtectedRoute>} />
      <Route path="/settings/account" element={<Navigate to="/settings/security" replace />} />
      <Route path="/settings/session" element={<ProtectedRoute><SettingsPage section="session" /></ProtectedRoute>} />
      <Route path="/settings/notifications" element={<ProtectedRoute><NotificationSettingsPage /></ProtectedRoute>} />
      <Route path="/compose" element={<ProtectedRoute><ComposePage /></ProtectedRoute>} />
      <Route path="/auth/login" element={<AuthPage mode="login" />} />
      <Route path="/auth/register" element={<AuthPage mode="register" />} />
      <Route path="/drafts" element={<ProtectedRoute><DraftsPage /></ProtectedRoute>} />
      <Route path="/drafts/:id" element={<ProtectedRoute><DraftEditorPage /></ProtectedRoute>} />
      <Route path="/inbox" element={<ProtectedRoute><InboxPage /></ProtectedRoute>} />
      <Route path="/inbox/system" element={<ProtectedRoute><InboxPage /></ProtectedRoute>} />
      {/* Legacy notification filters now resolve to the unified engagement list. */}
      <Route path="/inbox/all" element={<Navigate to="/inbox" replace />} />
      <Route path="/inbox/engagement" element={<Navigate to="/inbox" replace />} />
      <Route path="/inbox/message" element={<Navigate to="/inbox" replace />} />
      <Route path="/inbox/social" element={<Navigate to="/inbox" replace />} />
      <Route path="/inbox/interaction" element={<Navigate to="/inbox" replace />} />
      <Route path="/messages" element={<ProtectedRoute><MessagesPage /></ProtectedRoute>} />
      <Route path="/messages/search" element={<ProtectedRoute><MessagesPage /></ProtectedRoute>} />
      <Route path="/messages/new/:uid" element={<ProtectedRoute><NewMessagePage /></ProtectedRoute>} />
      <Route path="/messages/requests" element={<BlueprintPendingPage title="消息请求" description="陌生人消息请求与正式会话分开管理。" capability="消息请求分类暂未开放；现有私信会话可以正常使用。" backTo="/messages" backLabel="返回私信" mobileMode="task" />} />
      <Route path="/messages/:conversationId" element={<ProtectedRoute><ConversationPage /></ProtectedRoute>} />
      <Route path="/settings/privacy" element={<ProtectedRoute><PrivacyPage /></ProtectedRoute>} />
      <Route path="/settings/blocked" element={<ProtectedRoute><BlockedUsersPage /></ProtectedRoute>} />
      <Route path="/auth/recovery" element={<RecoveryFlowPage />} />
      <Route path="/user/:uid/followers" element={<PublicProfileView relation="followers" />} />
      <Route path="/user/:uid/following" element={<PublicProfileView relation="following" />} />
      <Route path="/user/:uid" element={<PublicProfileView />} />
      {pendingRoutes.map((route) => (
        <Route
          key={route.path}
          path={route.path}
          element={route.protected ? <ProtectedRoute><BlueprintPendingPage title={route.title} description={route.description} capability={route.capability} backTo={route.backTo} backLabel={route.backLabel} mobileMode={route.mobileMode} /></ProtectedRoute> : <BlueprintPendingPage title={route.title} description={route.description} capability={route.capability} backTo={route.backTo} backLabel={route.backLabel} mobileMode={route.mobileMode} />}
        />
      ))}
      <Route path="*" element={<NotFoundPage />} />
    </Routes>
  );
}
