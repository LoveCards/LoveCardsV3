-- Public user card streams: make handle resolution and latest-card reads index-backed.
-- Additive/idempotent for MySQL 5.7; no destructive rewrite.
-- @lc-phase expand

DROP PROCEDURE IF EXISTS `mig_public_user_cards_20260821`;
DELIMITER //
CREATE PROCEDURE `mig_public_user_cards_20260821`()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND INDEX_NAME = 'idx_users_public_handle'
    ) THEN
        ALTER TABLE `users`
            ADD KEY `idx_users_public_handle` (`username`(191), `status`, `deleted_at`, `id`);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'cards'
          AND INDEX_NAME = 'idx_cards_public_user'
    ) THEN
        ALTER TABLE `cards`
            ADD KEY `idx_cards_public_user` (`user_id`, `status`, `deleted_at`, `created_at`, `id`);
    END IF;
END //
DELIMITER ;

CALL `mig_public_user_cards_20260821`();
DROP PROCEDURE IF EXISTS `mig_public_user_cards_20260821`;
