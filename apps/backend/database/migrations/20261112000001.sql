-- S11：只退役与受审原始种子完全一致的四张旧表。
-- 自定义配置或授权必须先明确转换或归档，不能猜测后删除。
-- @lc-requires-upgrade-contract
-- @lc-phase preflight
-- 仅重建由本迁移创建的校验过程；同名自定义过程交给 CREATE 拒绝。
SET @lc_s11_owned = (
    SELECT BINARY `ROUTINE_COMMENT` = BINARY 'LoveCards:20261112000001:legacy-seed-v1'
    FROM information_schema.ROUTINES
    WHERE `ROUTINE_SCHEMA` = DATABASE()
      AND `ROUTINE_NAME` = 'lc_s11_assert_legacy_seed'
      AND `ROUTINE_TYPE` = 'PROCEDURE'
    LIMIT 1
);
SET @lc_s11_reset = IF(COALESCE(@lc_s11_owned, 0) = 1,
    'DROP PROCEDURE `lc_s11_assert_legacy_seed`', 'SELECT 1');
PREPARE lc_s11_reset_stmt FROM @lc_s11_reset;
EXECUTE lc_s11_reset_stmt;
DEALLOCATE PREPARE lc_s11_reset_stmt;
DELIMITER //
CREATE PROCEDURE `lc_s11_assert_legacy_seed`()
COMMENT 'LoveCards:20261112000001:legacy-seed-v1'
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        DROP TEMPORARY TABLE IF EXISTS `lc_s11_expected_system`;
        DROP TEMPORARY TABLE IF EXISTS `lc_s11_expected_permissions`;
        DROP TEMPORARY TABLE IF EXISTS `lc_s11_expected_role_permissions`;
        DROP TEMPORARY TABLE IF EXISTS `lc_s11_expected_role_capabilities`;
        RESIGNAL;
    END;

    DROP TEMPORARY TABLE IF EXISTS `lc_s11_expected_system`;
    CREATE TEMPORARY TABLE `lc_s11_expected_system` LIKE `system`;
    INSERT INTO `lc_s11_expected_system` (`id`, `name`, `value`) VALUES
(1, 'siteUrl', 'lovecards.cn'),
(2, 'siteName', 'LoveCardsV2.4'),
(3, 'siteICPId', ''),
(4, 'siteKeywords', ''),
(5, 'siteDes', ''),
(10, 'siteFooter', ''),
(11, 'LCEAPI', ''),
(12, 'siteCopyright', ''),
(13, 'siteTitle', 'LoveCards'),
(14, 'smtpSecure', ''),
(15, 'smtpName', '');

    DROP TEMPORARY TABLE IF EXISTS `lc_s11_expected_permissions`;
    CREATE TEMPORARY TABLE `lc_s11_expected_permissions` LIKE `permissions`;
    INSERT INTO `lc_s11_expected_permissions` (`id`, `name`, `slug`, `path`, `method`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, '系统更新检查', 'system-update', '/api/system/updata', 'GET', '检查系统更新', '2026-01-19 04:01:18', '2026-01-19 04:01:18', NULL),
(2, '获取主题列表', 'system-themes', '/api/system/themes', 'GET', '获取可用主题列表', '2026-01-19 04:01:18', '2026-01-19 04:01:18', NULL),
(3, '获取系统配置', 'system-config-get', '/api/system/config', 'GET', '获取系统配置信息', '2026-01-19 04:01:18', '2026-01-19 04:01:18', NULL),
(4, '设置系统配置', 'system-config-set', '/api/system/config', 'POST', '设置系统配置', '2026-01-19 04:01:18', '2026-01-19 04:01:18', NULL),
(5, '系统站点设置', 'system-site', '/api/system/site', 'POST', '系统站点设置', '2026-01-19 04:01:18', '2026-01-19 04:01:18', NULL),
(6, '系统邮箱设置', 'system-email', '/api/system/email', 'PATCH', '系统邮箱设置', '2026-01-19 04:01:18', '2026-01-19 04:01:18', NULL),
(7, '设置主题配置', 'system-theme-config', '/api/system/theme-config', 'POST', '设置主题配置', '2026-01-19 04:01:18', '2026-01-19 04:01:18', NULL),
(8, '设置主题', 'system-set-theme', '/api/system/set-theme', 'POST', '设置当前主题', '2026-01-19 04:01:18', '2026-01-19 04:01:18', NULL),
(9, '获取用户列表', 'admin-users-list', '/api/admin/users', 'GET', '获取用户列表', '2026-01-19 04:01:18', '2026-01-19 04:01:18', NULL),
(10, '更新用户', 'admin-user-update', '/api/admin/user', 'PATCH', '更新用户信息', '2026-01-19 04:01:18', '2026-01-19 04:01:18', NULL),
(11, '删除用户', 'admin-user-delete', '/api/admin/user', 'DELETE', '删除用户', '2026-01-19 04:01:18', '2026-01-19 04:01:18', NULL),
(12, '批量操作用户', 'admin-users-batch', '/api/admin/users/batch-operate', 'POST', '批量操作用户', '2026-01-19 04:01:18', '2026-01-19 04:01:18', NULL),
(13, '获取单个卡片', 'admin-card-get', '/api/admin/card', 'GET', '获取单个卡片详情', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(14, '获取卡片列表', 'admin-cards-list', '/api/admin/cards', 'GET', '获取卡片列表', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(15, '更新卡片', 'admin-card-update', '/api/admin/card', 'PATCH', '更新卡片信息', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(16, '删除卡片', 'admin-cards-delete', '/api/admin/cards', 'DELETE', '删除卡片', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(17, '批量操作卡片', 'admin-cards-batch', '/api/admin/cards/batch-operate', 'POST', '批量操作卡片', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(18, '获取评论列表', 'admin-comments-list', '/api/admin/comments', 'GET', '获取评论列表', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(19, '更新评论', 'admin-comment-update', '/api/admin/comment', 'PATCH', '更新评论信息', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(20, '删除评论', 'admin-comment-delete', '/api/admin/comment', 'DELETE', '删除评论', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(21, '批量操作评论', 'admin-comments-batch', '/api/admin/comments/batch-operate', 'POST', '批量操作评论', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(22, '获取标签列表', 'admin-tags-list', '/api/admin/tags', 'GET', '获取标签列表', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(23, '创建标签', 'admin-tag-create', '/api/admin/tag', 'POST', '创建标签', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(24, '更新标签', 'admin-tag-update', '/api/admin/tag', 'PATCH', '更新标签信息', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(25, '删除标签', 'admin-tag-delete', '/api/admin/tag', 'DELETE', '删除标签', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(26, '批量操作标签', 'admin-tags-batch', '/api/admin/tags/batch-operate', 'POST', '批量操作标签', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(27, '控制台', 'admin-dashboard', '/api/admin/dashboard', 'GET', '访问控制台', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(28, '获取角色列表', 'admin-roles-list', '/api/admin/roles', 'GET', '获取角色列表', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(29, '获取单个角色', 'admin-role-get', '/api/admin/role', 'GET', '获取单个角色详情', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(30, '创建角色', 'admin-role-create', '/api/admin/role', 'POST', '创建角色', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(31, '更新角色', 'admin-role-update', '/api/admin/role', 'PATCH', '更新角色信息', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(32, '删除角色', 'admin-role-delete', '/api/admin/role', 'DELETE', '删除角色', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(33, '分配权限', 'admin-role-assign', '/api/admin/role/assign-permissions', 'POST', '为角色分配权限', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(34, '获取角色权限', 'admin-role-permissions', '/api/admin/role/permissions', 'GET', '获取角色的权限列表', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(35, '获取权限列表', 'admin-permissions-list', '/api/admin/permissions', 'GET', '获取权限列表', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(36, '获取单个权限', 'admin-permission-get', '/api/admin/permission', 'GET', '获取单个权限详情', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(37, '创建权限', 'admin-permission-create', '/api/admin/permission', 'POST', '创建权限', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(38, '更新权限', 'admin-permission-update', '/api/admin/permission', 'PATCH', '更新权限信息', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(39, '删除权限', 'admin-permission-delete', '/api/admin/permission', 'DELETE', '删除权限', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(40, '获取所有权限', 'admin-permissions-all', '/api/admin/permissions/all', 'GET', '获取所有权限（不分页）', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(41, '添加角色权限', 'admin-role-permission-add', '/api/admin/role-permission', 'POST', '为角色添加权限', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(42, '移除角色权限', 'admin-role-permission-remove', '/api/admin/role-permission', 'DELETE', '移除角色权限', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(43, '批量添加角色权限', 'admin-role-permissions-batch-add', '/api/admin/role-permissions/batch-add', 'POST', '批量添加角色权限', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(44, '批量移除角色权限', 'admin-role-permissions-batch-remove', '/api/admin/role-permissions/batch-remove', 'POST', '批量移除角色权限', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(45, '获取标签列表', 'user-tags-list', '/api/tags', 'GET', '获取标签列表', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(46, '获取卡片图集', 'user-card-images', '/api/card/images', 'GET', '获取卡片图集', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(47, '获取卡片列表', 'user-cards-list', '/api/cards', 'GET', '获取卡片列表', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(48, '喜欢卡片', 'user-card-like', '/api/card/like', 'POST', '喜欢卡片', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(49, '创建评论', 'user-card-comment', '/api/card/comment', 'POST', '创建评论', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(50, '创建卡片', 'user-card-create', '/api/card', 'POST', '创建卡片', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(51, '删除卡片', 'user-card-delete', '/api/card', 'DELETE', '删除卡片', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(52, '删除评论', 'user-comment-delete', '/api/comment', 'DELETE', '删除评论', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(53, '取消喜欢', 'user-like-delete', '/api/like', 'DELETE', '取消喜欢', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(54, '获取评论列表', 'user-comments-list', '/api/comments', 'GET', '获取评论列表', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(55, '获取喜欢列表', 'user-likes-list', '/api/likes', 'GET', '获取喜欢列表', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(56, '更新用户信息', 'user-info-update', '/api/user/info', 'PATCH', '更新用户信息', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(57, '获取用户信息', 'user-info-get', '/api/user/info', 'GET', '获取用户信息', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(58, '修改密码', 'user-password', '/api/user/password', 'POST', '修改密码', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(59, '绑定邮箱', 'user-email', '/api/user/email', 'POST', '绑定邮箱', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(60, '获取邮箱验证码', 'user-email-captcha', '/api/user/email-captcha', 'POST', '获取邮箱验证码', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(61, '上传用户图片', 'upload-user-images', '/api/upload/user-images', 'POST', '上传用户图片', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(62, '访客-获取卡片列表', 'guest-cards-list', '/api/cards', 'GET', '访客获取卡片列表', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(63, '访客-获取评论列表', 'guest-comments-list', '/api/comments', 'GET', '访客获取评论列表', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(64, '访客-获取喜欢列表', 'guest-likes-list', '/api/likes', 'GET', '访客获取喜欢列表', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(65, '访客-获取用户信息', 'guest-user-info', '/api/user/info', 'GET', '访客获取用户信息', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(66, '访客-获取标签列表', 'guest-tags-list', '/api/tags', 'GET', '访客获取标签列表', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL);

    DROP TEMPORARY TABLE IF EXISTS `lc_s11_expected_role_permissions`;
    CREATE TEMPORARY TABLE `lc_s11_expected_role_permissions` LIKE `role_permissions`;
    INSERT INTO `lc_s11_expected_role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES
(1, 1, 1, '2026-01-18 20:01:19'),
(2, 1, 2, '2026-01-18 20:01:19'),
(3, 1, 3, '2026-01-18 20:01:19'),
(4, 1, 4, '2026-01-18 20:01:19'),
(5, 1, 5, '2026-01-18 20:01:19'),
(6, 1, 6, '2026-01-18 20:01:19'),
(7, 1, 7, '2026-01-18 20:01:19'),
(8, 1, 8, '2026-01-18 20:01:19'),
(9, 1, 9, '2026-01-18 20:01:19'),
(10, 1, 10, '2026-01-18 20:01:19'),
(11, 1, 11, '2026-01-18 20:01:19'),
(12, 1, 12, '2026-01-18 20:01:19'),
(13, 1, 13, '2026-01-18 20:01:19'),
(14, 1, 14, '2026-01-18 20:01:19'),
(15, 1, 15, '2026-01-18 20:01:19'),
(16, 1, 16, '2026-01-18 20:01:19'),
(17, 1, 17, '2026-01-18 20:01:19'),
(18, 1, 18, '2026-01-18 20:01:19'),
(19, 1, 19, '2026-01-18 20:01:19'),
(20, 1, 20, '2026-01-18 20:01:19'),
(21, 1, 21, '2026-01-18 20:01:19'),
(22, 1, 22, '2026-01-18 20:01:19'),
(23, 1, 23, '2026-01-18 20:01:19'),
(24, 1, 24, '2026-01-18 20:01:19'),
(25, 1, 25, '2026-01-18 20:01:19'),
(26, 1, 26, '2026-01-18 20:01:19'),
(27, 1, 27, '2026-01-18 20:01:19'),
(28, 1, 28, '2026-01-18 20:01:19'),
(29, 1, 29, '2026-01-18 20:01:19'),
(30, 1, 30, '2026-01-18 20:01:19'),
(31, 1, 31, '2026-01-18 20:01:19'),
(32, 1, 32, '2026-01-18 20:01:19'),
(33, 1, 33, '2026-01-18 20:01:19'),
(34, 1, 34, '2026-01-18 20:01:19'),
(35, 1, 35, '2026-01-18 20:01:19'),
(36, 1, 36, '2026-01-18 20:01:19'),
(37, 1, 37, '2026-01-18 20:01:19'),
(38, 1, 38, '2026-01-18 20:01:19'),
(39, 1, 39, '2026-01-18 20:01:19'),
(40, 1, 40, '2026-01-18 20:01:19'),
(41, 1, 41, '2026-01-18 20:01:19'),
(42, 1, 42, '2026-01-18 20:01:19'),
(43, 1, 43, '2026-01-18 20:01:19'),
(44, 1, 44, '2026-01-18 20:01:19'),
(45, 1, 45, '2026-01-18 20:01:19'),
(46, 1, 46, '2026-01-18 20:01:19'),
(47, 1, 47, '2026-01-18 20:01:19'),
(48, 1, 48, '2026-01-18 20:01:19'),
(49, 1, 49, '2026-01-18 20:01:19'),
(50, 1, 50, '2026-01-18 20:01:19'),
(51, 1, 51, '2026-01-18 20:01:19'),
(52, 1, 52, '2026-01-18 20:01:19'),
(53, 1, 53, '2026-01-18 20:01:19'),
(54, 1, 54, '2026-01-18 20:01:19'),
(55, 1, 55, '2026-01-18 20:01:19'),
(56, 1, 56, '2026-01-18 20:01:19'),
(57, 1, 57, '2026-01-18 20:01:19'),
(58, 1, 58, '2026-01-18 20:01:19'),
(59, 1, 59, '2026-01-18 20:01:19'),
(60, 1, 60, '2026-01-18 20:01:19'),
(61, 1, 61, '2026-01-18 20:01:19'),
(62, 1, 62, '2026-01-18 20:01:19'),
(63, 1, 63, '2026-01-18 20:01:19'),
(64, 1, 64, '2026-01-18 20:01:19'),
(65, 1, 65, '2026-01-18 20:01:19'),
(66, 1, 66, '2026-01-18 20:01:19'),
(67, 1, 67, '2026-01-18 20:01:19'),
(68, 1, 68, '2026-01-18 20:01:19'),
(69, 1, 69, '2026-01-18 20:01:19'),
(70, 1, 70, '2026-01-18 20:01:19'),
(71, 1, 71, '2026-01-18 20:01:19'),
(72, 1, 72, '2026-01-18 20:01:19'),
(73, 1, 73, '2026-01-18 20:01:19'),
(74, 1, 74, '2026-01-18 20:01:19'),
(75, 1, 75, '2026-01-18 20:01:19'),
(76, 1, 76, '2026-01-18 20:01:19'),
(77, 1, 77, '2026-01-18 20:01:19'),
(78, 1, 78, '2026-01-18 20:01:19'),
(79, 1, 79, '2026-01-18 20:01:19'),
(80, 1, 80, '2026-01-18 20:01:19'),
(128, 2, 13, '2026-01-18 20:01:19'),
(129, 2, 15, '2026-01-18 20:01:19'),
(130, 2, 17, '2026-01-18 20:01:19'),
(131, 2, 16, '2026-01-18 20:01:19'),
(132, 2, 14, '2026-01-18 20:01:19'),
(133, 2, 20, '2026-01-18 20:01:19'),
(134, 2, 19, '2026-01-18 20:01:19'),
(135, 2, 21, '2026-01-18 20:01:19'),
(136, 2, 18, '2026-01-18 20:01:19'),
(137, 2, 27, '2026-01-18 20:01:19'),
(138, 2, 23, '2026-01-18 20:01:19'),
(139, 2, 25, '2026-01-18 20:01:19'),
(140, 2, 24, '2026-01-18 20:01:19'),
(141, 2, 26, '2026-01-18 20:01:19'),
(142, 2, 22, '2026-01-18 20:01:19'),
(143, 3, 61, '2026-01-18 20:01:19'),
(144, 3, 49, '2026-01-18 20:01:19'),
(145, 3, 50, '2026-01-18 20:01:19'),
(146, 3, 51, '2026-01-18 20:01:19'),
(147, 3, 46, '2026-01-18 20:01:19'),
(148, 3, 48, '2026-01-18 20:01:19'),
(149, 3, 47, '2026-01-18 20:01:19'),
(150, 3, 52, '2026-01-18 20:01:19'),
(151, 3, 54, '2026-01-18 20:01:19'),
(152, 3, 59, '2026-01-18 20:01:19'),
(153, 3, 60, '2026-01-18 20:01:19'),
(154, 3, 57, '2026-01-18 20:01:19'),
(155, 3, 56, '2026-01-18 20:01:19'),
(156, 3, 53, '2026-01-18 20:01:19'),
(157, 3, 55, '2026-01-18 20:01:19'),
(158, 3, 58, '2026-01-18 20:01:19'),
(159, 3, 45, '2026-01-18 20:01:19'),
(174, 4, 62, '2026-01-18 20:01:19'),
(175, 4, 63, '2026-01-18 20:01:19'),
(176, 4, 64, '2026-01-18 20:01:19'),
(177, 4, 66, '2026-01-18 20:01:19'),
(178, 4, 65, '2026-01-18 20:01:19');

    DROP TEMPORARY TABLE IF EXISTS `lc_s11_expected_role_capabilities`;
    CREATE TEMPORARY TABLE `lc_s11_expected_role_capabilities` LIKE `role_capabilities`;
    INSERT INTO `lc_s11_expected_role_capabilities` (`role_id`, `capability`) VALUES
(1, 'admin.access'),
(1, 'captcha.install'),
(1, 'captcha.read'),
(1, 'cards.approve'),
(1, 'cards.approve.all'),
(1, 'cards.create'),
(1, 'cards.delete'),
(1, 'cards.delete.all'),
(1, 'cards.pin'),
(1, 'cards.pin.all'),
(1, 'cards.read'),
(1, 'cards.read.all'),
(1, 'cards.update'),
(1, 'cards.update.all'),
(1, 'comments.create'),
(1, 'comments.delete'),
(1, 'comments.delete.all'),
(1, 'comments.read'),
(1, 'comments.read.all'),
(1, 'comments.update'),
(1, 'comments.update.all'),
(1, 'config.deleteKey'),
(1, 'config.init'),
(1, 'config.read'),
(1, 'config.register'),
(1, 'config.reload'),
(1, 'config.update'),
(1, 'dashboard.read'),
(1, 'files.delete'),
(1, 'files.delete.all'),
(1, 'files.read'),
(1, 'files.read.all'),
(1, 'files.update'),
(1, 'files.update.all'),
(1, 'files.upload'),
(1, 'likes.create'),
(1, 'likes.delete'),
(1, 'likes.read'),
(1, 'permissions.read'),
(1, 'roles.assign'),
(1, 'roles.create'),
(1, 'roles.delete'),
(1, 'roles.read'),
(1, 'roles.update'),
(1, 'sender.install'),
(1, 'sender.read'),
(1, 'sender.test'),
(1, 'session.captcha'),
(1, 'session.check'),
(1, 'session.guest'),
(1, 'session.login'),
(1, 'session.logout'),
(1, 'session.register'),
(1, 'storage.install'),
(1, 'storage.read'),
(1, 'storage.test'),
(1, 'system.update'),
(1, 'tags.create'),
(1, 'tags.delete'),
(1, 'tags.delete.all'),
(1, 'tags.read'),
(1, 'tags.read.all'),
(1, 'tags.update'),
(1, 'tags.update.all'),
(1, 'theme.activate'),
(1, 'theme.delete'),
(1, 'theme.read'),
(1, 'theme.update'),
(1, 'theme.upload'),
(1, 'users.delete'),
(1, 'users.delete.all'),
(1, 'users.read'),
(1, 'users.read.all'),
(1, 'users.update'),
(1, 'users.update.all'),
(2, 'admin.access'),
(2, 'captcha.install'),
(2, 'captcha.read'),
(2, 'cards.approve'),
(2, 'cards.approve.all'),
(2, 'cards.create'),
(2, 'cards.delete.all'),
(2, 'cards.pin.all'),
(2, 'cards.read'),
(2, 'cards.read.all'),
(2, 'cards.update.all'),
(2, 'comments.delete.all'),
(2, 'comments.read'),
(2, 'comments.read.all'),
(2, 'comments.update.all'),
(2, 'config.deleteKey'),
(2, 'config.init'),
(2, 'config.read'),
(2, 'config.register'),
(2, 'config.reload'),
(2, 'config.update'),
(2, 'dashboard.read'),
(2, 'files.delete'),
(2, 'files.delete.all'),
(2, 'files.read'),
(2, 'files.read.all'),
(2, 'files.update.all'),
(2, 'files.upload'),
(2, 'likes.create'),
(2, 'likes.delete'),
(2, 'likes.read'),
(2, 'permissions.read'),
(2, 'roles.assign'),
(2, 'roles.create'),
(2, 'roles.delete'),
(2, 'roles.read'),
(2, 'roles.update'),
(2, 'sender.install'),
(2, 'sender.read'),
(2, 'sender.test'),
(2, 'storage.install'),
(2, 'storage.read'),
(2, 'storage.test'),
(2, 'tags.create'),
(2, 'tags.delete.all'),
(2, 'tags.read'),
(2, 'tags.read.all'),
(2, 'tags.update.all'),
(2, 'theme.activate'),
(2, 'theme.delete'),
(2, 'theme.read'),
(2, 'theme.update'),
(2, 'theme.upload'),
(2, 'users.delete.all'),
(2, 'users.read'),
(2, 'users.read.all'),
(2, 'users.update.all'),
(3, 'cards.create'),
(3, 'cards.read'),
(3, 'comments.create'),
(3, 'comments.read'),
(3, 'files.read'),
(3, 'files.upload'),
(3, 'likes.create'),
(3, 'likes.delete'),
(3, 'likes.read'),
(3, 'tags.create'),
(3, 'tags.read'),
(3, 'users.read'),
(3, 'users.update'),
(4, 'cards.read'),
(4, 'comments.read'),
(4, 'files.read'),
(4, 'likes.create'),
(4, 'likes.delete'),
(4, 'likes.read'),
(4, 'tags.read');

    IF (SELECT COUNT(*) FROM `system`) <> 11
       OR EXISTS (
           SELECT 1 FROM `system` actual
           LEFT JOIN `lc_s11_expected_system` expected ON actual.`id` = expected.`id`
           WHERE expected.`id` IS NULL OR NOT ((actual.`id` <=> expected.`id`) AND (BINARY actual.`name` <=> BINARY expected.`name`) AND (BINARY actual.`value` <=> BINARY expected.`value`))
       ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'S11 system 包含未映射历史数据，禁止删除';
    END IF;

    IF (SELECT COUNT(*) FROM `permissions`) <> 66
       OR EXISTS (
           SELECT 1 FROM `permissions` actual
           LEFT JOIN `lc_s11_expected_permissions` expected ON actual.`id` = expected.`id`
           WHERE expected.`id` IS NULL OR NOT ((actual.`id` <=> expected.`id`) AND (BINARY actual.`name` <=> BINARY expected.`name`) AND (BINARY actual.`slug` <=> BINARY expected.`slug`) AND (BINARY actual.`path` <=> BINARY expected.`path`) AND (BINARY actual.`method` <=> BINARY expected.`method`) AND (BINARY actual.`description` <=> BINARY expected.`description`) AND (actual.`created_at` <=> expected.`created_at`) AND (actual.`updated_at` <=> expected.`updated_at`) AND (actual.`deleted_at` <=> expected.`deleted_at`))
       ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'S11 permissions 包含未映射历史数据，禁止删除';
    END IF;

    IF (SELECT COUNT(*) FROM `role_permissions`) <> 117
       OR EXISTS (
           SELECT 1 FROM `role_permissions` actual
           LEFT JOIN `lc_s11_expected_role_permissions` expected ON actual.`id` = expected.`id`
           WHERE expected.`id` IS NULL OR NOT ((actual.`id` <=> expected.`id`) AND (actual.`role_id` <=> expected.`role_id`) AND (actual.`permission_id` <=> expected.`permission_id`) AND (actual.`created_at` <=> expected.`created_at`))
       ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'S11 role_permissions 包含未映射历史数据，禁止删除';
    END IF;

    IF (SELECT COUNT(*) FROM `role_capabilities`) <> 152
       OR EXISTS (
           SELECT 1 FROM `role_capabilities` actual
           LEFT JOIN `lc_s11_expected_role_capabilities` expected ON actual.`role_id` = expected.`role_id` AND BINARY actual.`capability` = BINARY expected.`capability`
           WHERE expected.`role_id` IS NULL OR NOT ((actual.`role_id` <=> expected.`role_id`) AND (BINARY actual.`capability` <=> BINARY expected.`capability`))
       ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'S11 role_capabilities 包含未映射历史数据，禁止删除';
    END IF;

    DROP TEMPORARY TABLE IF EXISTS `lc_s11_expected_role_capabilities`;
    DROP TEMPORARY TABLE IF EXISTS `lc_s11_expected_role_permissions`;
    DROP TEMPORARY TABLE IF EXISTS `lc_s11_expected_permissions`;
    DROP TEMPORARY TABLE IF EXISTS `lc_s11_expected_system`;
END //
DELIMITER ;
CALL `lc_s11_assert_legacy_seed`();

-- @lc-phase expand
-- @lc-phase backfill
-- @lc-phase verify
DROP TEMPORARY TABLE IF EXISTS `lc_s11_owner_guard`;
CREATE TEMPORARY TABLE `lc_s11_owner_guard` (`id` tinyint NOT NULL PRIMARY KEY);
INSERT INTO `lc_s11_owner_guard` (`id`) VALUES (1);
INSERT INTO `lc_s11_owner_guard` (`id`)
SELECT IF(EXISTS (
    SELECT 1 FROM information_schema.ROUTINES
    WHERE `ROUTINE_SCHEMA` = DATABASE()
      AND `ROUTINE_NAME` = 'lc_s11_assert_legacy_seed'
      AND `ROUTINE_TYPE` = 'PROCEDURE'
      AND BINARY `ROUTINE_COMMENT` = BINARY 'LoveCards:20261112000001:legacy-seed-v1'
), 2, 1);
DROP TEMPORARY TABLE `lc_s11_owner_guard`;
CALL `lc_s11_assert_legacy_seed`();

-- @lc-phase contract
-- Contract 前再次核对，避免审批窗口内旧数据改变。
DROP TEMPORARY TABLE IF EXISTS `lc_s11_owner_guard`;
CREATE TEMPORARY TABLE `lc_s11_owner_guard` (`id` tinyint NOT NULL PRIMARY KEY);
INSERT INTO `lc_s11_owner_guard` (`id`) VALUES (1);
INSERT INTO `lc_s11_owner_guard` (`id`)
SELECT IF(EXISTS (
    SELECT 1 FROM information_schema.ROUTINES
    WHERE `ROUTINE_SCHEMA` = DATABASE()
      AND `ROUTINE_NAME` = 'lc_s11_assert_legacy_seed'
      AND `ROUTINE_TYPE` = 'PROCEDURE'
      AND BINARY `ROUTINE_COMMENT` = BINARY 'LoveCards:20261112000001:legacy-seed-v1'
), 2, 1);
DROP TEMPORARY TABLE `lc_s11_owner_guard`;
CALL `lc_s11_assert_legacy_seed`();
DROP TABLE `role_permissions`, `permissions`, `role_capabilities`, `system`;
