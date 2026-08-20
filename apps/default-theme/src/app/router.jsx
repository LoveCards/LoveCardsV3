import { Route, Routes } from 'react-router-dom';
import { HomePage } from '../pages/home/HomePage.jsx';
import { ExplorePage } from '../pages/explore/ExplorePage.jsx';
import { DetailPage } from '../pages/detail/DetailPage.jsx';
import { MediaPage } from '../pages/detail/MediaPage.jsx';
import { ProfilePage } from '../pages/profile/ProfilePage.jsx';
import { EditProfilePage } from '../pages/profile/EditProfilePage.jsx';
import { ComposePage } from '../pages/compose/ComposePage.jsx';
import { AuthPage } from '../pages/auth/AuthPage.jsx';
import { BlueprintPendingPage } from '../pages/system/BlueprintPendingPage.jsx';
import { RecoveryFlowPage } from '../pages/auth/RecoveryFlowPage.jsx';
import { NotFoundPage } from '../pages/system/NotFoundPage.jsx';
import { SettingsPage } from '../pages/settings/SettingsPage.jsx';
import { LibraryPage } from '../pages/profile/LibraryPage.jsx';
import { ConversationPage } from '../pages/community/ConversationPage.jsx';
import { DraftsPage } from '../pages/community/DraftsPage.jsx';
import { DraftEditorPage } from '../pages/community/DraftEditorPage.jsx';
import { InboxPage } from '../pages/community/InboxPage.jsx';
import { MessagesPage } from '../pages/community/MessagesPage.jsx';
import { LibraryAssetsPage } from '../pages/community/LibraryAssetsPage.jsx';
import { PrivacyPage } from '../pages/community/PrivacyPage.jsx';
import { PublicProfileView } from '../pages/community/PublicProfileView.jsx';

const pendingRoutes = [
  { path: '/settings/:section', title: '设置分区', description: '按主题分组管理账户与偏好。', capability: '独立设置分区暂未开放，请使用设置首页。', backTo: '/settings', backLabel: '返回设置' },
  { path: '/compose/:type', title: '发布类型', description: '针对不同媒体类型提供对应编辑体验。', capability: '分类发布编辑器暂未开放，请使用通用发布页。', backTo: '/compose', backLabel: '返回发布' },
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
      <Route path="/me" element={<ProfilePage />} />
      <Route path="/me/edit" element={<EditProfilePage />} />
      <Route path="/settings" element={<SettingsPage />} />
      <Route path="/me/library/:section" element={<LibraryPage />} />
      <Route path="/compose" element={<ComposePage />} />
      <Route path="/auth/login" element={<AuthPage mode="login" />} />
      <Route path="/auth/register" element={<AuthPage mode="register" />} />
      <Route path="/drafts" element={<DraftsPage />} />
      <Route path="/drafts/:id" element={<DraftEditorPage />} />
      <Route path="/inbox" element={<InboxPage />} />
      <Route path="/inbox/:type" element={<InboxPage />} />
      <Route path="/messages" element={<MessagesPage />} />
      <Route path="/messages/requests" element={<BlueprintPendingPage title="消息请求" description="陌生人消息请求与正式会话分开管理。" capability="消息请求分类暂未开放；现有私信会话可以正常使用。" backTo="/messages" backLabel="返回私信" />} />
      <Route path="/messages/:conversationId" element={<ConversationPage />} />
      <Route path="/me/library/saved" element={<LibraryAssetsPage section="saved" />} />
      <Route path="/me/library/history" element={<LibraryAssetsPage section="history" />} />
      <Route path="/settings/privacy" element={<PrivacyPage />} />
      <Route path="/auth/recovery" element={<RecoveryFlowPage />} />
      <Route path="/user/:handle/followers" element={<PublicProfileView relation="followers" />} />
      <Route path="/user/:handle/following" element={<PublicProfileView relation="following" />} />
      <Route path="/user/:handle" element={<PublicProfileView />} />
      {pendingRoutes.map((route) => (
        <Route
          key={route.path}
          path={route.path}
          element={<BlueprintPendingPage title={route.title} description={route.description} capability={route.capability} backTo={route.backTo} backLabel={route.backLabel} />}
        />
      ))}
      <Route path="*" element={<NotFoundPage />} />
    </Routes>
  );
}
