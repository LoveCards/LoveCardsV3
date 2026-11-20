export async function endCurrentSession(logout, navigate, destination) {
  // 先离开受保护页面，避免凭证清除后的路由守卫抢先跳转登录页。
  navigate(destination, { replace: true, state: null });
  try {
    await logout();
  } catch {
    navigate(destination, { replace: true, state: { logoutUnconfirmed: true } });
  }
}
