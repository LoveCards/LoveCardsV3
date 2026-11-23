import { Activity, lazy, Suspense, useLayoutEffect, useRef } from 'react';
import { Navigate, Outlet, Route, Routes, useLocation } from 'react-router-dom';
import { HomeFeedRoute, LegacyFeedRoute, ProtectedRoute } from './auth-routing.jsx';
import { HomePage } from '../pages/home/HomePage.jsx';
import { ActivityPage } from '../pages/activity/ActivityPage.jsx';
import { RouteBoundary } from './RouteBoundary.jsx';
import { cardReturnSource, previewBackground } from './card-preview-routing.js';
import { composeModalBackground } from './compose-modal-routing.js';
import { CardPreviewSurface } from '../features/feed/CardPreviewSurface.jsx';
import { ComposePage } from '../pages/compose/ComposePage.jsx';

const ExplorePage = lazy(() => import('../pages/explore/ExplorePage.jsx').then((module) => ({ default: module.ExplorePage })));
const SearchPage = lazy(() => import('../pages/search/SearchPage.jsx').then((module) => ({ default: module.SearchPage })));
const DetailPage = lazy(() => import('../pages/detail/DetailPage.jsx').then((module) => ({ default: module.DetailPage })));
const MediaPage = lazy(() => import('../pages/detail/MediaPage.jsx').then((module) => ({ default: module.MediaPage })));
const MePage = lazy(() => import('../pages/profile/MePage.jsx').then((module) => ({ default: module.MePage })));
const ProfileRelationPage = lazy(() => import('../pages/profile/ProfileSecondaryPage.jsx').then((module) => ({ default: module.ProfileRelationPage })));
const AuthPage = lazy(() => import('../pages/auth/AuthPage.jsx').then((module) => ({ default: module.AuthPage })));
const BlueprintPendingPage = lazy(() => import('../pages/system/BlueprintPendingPage.jsx').then((module) => ({ default: module.BlueprintPendingPage })));
const RecoveryFlowPage = lazy(() => import('../pages/auth/RecoveryFlowPage.jsx').then((module) => ({ default: module.RecoveryFlowPage })));
const NotFoundPage = lazy(() => import('../pages/system/NotFoundPage.jsx').then((module) => ({ default: module.NotFoundPage })));
const SettingsIndexRedirect = lazy(() => import('../pages/settings/SettingsPage.jsx').then((module) => ({ default: module.SettingsIndexRedirect })));
const SettingsPage = lazy(() => import('../pages/settings/SettingsPage.jsx').then((module) => ({ default: module.SettingsPage })));
const NotificationSettingsPage = lazy(() => import('../pages/settings/NotificationSettingsPage.jsx').then((module) => ({ default: module.NotificationSettingsPage })));
const BlockedUsersPage = lazy(() => import('../pages/settings/BlockedUsersPage.jsx').then((module) => ({ default: module.BlockedUsersPage })));
const ProfileSettingsPage = lazy(() => import('../pages/settings/ProfileSettingsPage.jsx').then((module) => ({ default: module.ProfileSettingsPage })));
const ConversationPage = lazy(() => import('../pages/community/ConversationPage.jsx').then((module) => ({ default: module.ConversationPage })));
const DraftsPage = lazy(() => import('../pages/community/DraftsPage.jsx').then((module) => ({ default: module.DraftsPage })));
const DraftEditorPage = lazy(() => import('../pages/community/DraftEditorPage.jsx').then((module) => ({ default: module.DraftEditorPage })));
const InboxPage = lazy(() => import('../pages/community/InboxPage.jsx').then((module) => ({ default: module.InboxPage })));
const MessagesPage = lazy(() => import('../pages/community/MessagesPage.jsx').then((module) => ({ default: module.MessagesPage })));
const NewMessagePage = lazy(() => import('../pages/community/NewMessagePage.jsx').then((module) => ({ default: module.NewMessagePage })));
const PrivacyPage = lazy(() => import('../pages/community/PrivacyPage.jsx').then((module) => ({ default: module.PrivacyPage })));
const PublicProfileView = lazy(() => import('../pages/community/PublicProfileView.jsx').then((module) => ({ default: module.PublicProfileView })));

const pendingRoutes = [
  { path: '/compose/:type', title: '发布类型', description: '针对不同媒体类型提供对应编辑体验。', capability: '分类发布编辑器暂未开放，请使用通用发布页。', backTo: '/compose', backLabel: '返回发布', mobileMode: 'immersive', protected: true },
];

function useFullDetailReturn(location, full) {
  const previous = useRef(null);
  useLayoutEffect(() => {
    const departed = previous.current;
    previous.current = full ? location : null;
    if (full) {
      const back = window.matchMedia('(max-width: 760px)').matches ? '.route-task-heading > a' : '.detail-back';
      document.querySelector(`.detail-page:not(.is-preview) > ${back}`)?.focus({ preventScroll: true });
      window.scrollTo(0, 0);
      return undefined;
    }
    const source = cardReturnSource(departed);
    if (!source || source.pathname !== location.pathname || source.search !== location.search || source.hash !== location.hash) return undefined;

    const { previewCardId, previewTrigger, sourceScrollY } = departed.state;
    const selector = previewTrigger && `[data-card-preview-id="${CSS.escape(previewCardId)}"][data-card-preview-trigger="${CSS.escape(previewTrigger)}"]`;
    // 恢复列表后，数据区域可能先显示骨架；等待真实触发器再还原焦点。
    let frame;
    let focused = false;
    const position = () => window.scrollTo(0, sourceScrollY || 0);
    const resize = new ResizeObserver(() => { if (focused) position(); });
    const restore = () => {
      const main = document.querySelector('main');
      const target = selector && main?.querySelector(selector);
      if (!main || (!target && main.querySelector('.route-loading-state, .is-loading, .feed-story-skeleton'))) return;
      const focus = target || main.querySelector('h1') || main;
      if (!target && !focus.hasAttribute('tabindex')) focus.tabIndex = -1;
      focus.focus({ preventScroll: true });
      focused = true;
      position();
      resize.observe(main);
      observer.disconnect();
    };
    const schedule = () => {
      cancelAnimationFrame(frame);
      frame = requestAnimationFrame(restore);
    };
    const observer = new MutationObserver(schedule);
    observer.observe(document.getElementById('root'), { childList: true, subtree: true });
    const stop = () => {
      cancelAnimationFrame(frame);
      observer.disconnect();
      resize.disconnect();
      for (const event of ['pointerdown', 'wheel', 'keydown']) window.removeEventListener(event, stop, true);
    };
    // 瀑布流测量与媒体就绪可继续改变文档高度；用户接管滚动后立即停止恢复。
    for (const event of ['pointerdown', 'wheel', 'keydown']) window.addEventListener(event, stop, { capture: true, passive: true });
    schedule();
    return stop;
  }, [full, location]);
}

function BackgroundRoutes({ preview }) {
  return <Outlet context={preview} />;
}

export function AppRoutes() {
  const location = useLocation();
  const preview = previewBackground(location);
  const source = preview || cardReturnSource(location);
  const full = Boolean(source && !preview);
  const composeBackground = composeModalBackground(location);
  useFullDetailReturn(location, full);
  const background = source || composeBackground;
  return (
    <Suspense fallback={<RouteBoundary />}>
      <Activity mode={full ? 'hidden' : 'visible'}>
      <Routes location={background || location}>
        <Route element={<BackgroundRoutes preview={preview ? location : null} />}>
        <Route path="/" element={<HomeFeedRoute><HomePage /></HomeFeedRoute>} />
        <Route path="/feed/latest" element={<LegacyFeedRoute to="/explore" />} />
        <Route path="/feed/following" element={<LegacyFeedRoute to="/activity" />} />
        <Route path="/activity" element={<ProtectedRoute><ActivityPage /></ProtectedRoute>} />
        <Route path="/explore" element={<ExplorePage />} />
        <Route path="/search" element={<SearchPage />} />
        <Route path="/search/:scope" element={<SearchPage />} />
        <Route path="/topic/:slug" element={<ExplorePage topic />} />
        <Route path="/post/:id" element={<DetailPage />} />
        <Route path="/post/:id/thread/:commentId" element={<DetailPage />} />
        <Route path="/media/:id" element={<MediaPage />} />
        <Route path="/me" element={<ProtectedRoute allowVisitor><MePage /></ProtectedRoute>} />
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
        <Route path="/settings/session" element={<ProtectedRoute allowVisitor><SettingsPage section="session" /></ProtectedRoute>} />
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
        </Route>
      </Routes>
      </Activity>
      {source && <Routes><Route path="/post/:id" element={full ? <DetailPage /> : <CardPreviewSurface />} /></Routes>}
      {composeBackground && <Routes><Route path="/compose" element={<ProtectedRoute><ComposePage surface="dialog" /></ProtectedRoute>} /></Routes>}
    </Suspense>
  );
}
