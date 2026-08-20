-- ============================================================
-- LoveCards — Authorization model migration 20260804000001
-- Add normalized user roles, structured grants, capability
-- catalog mirror and revocable session registry.
-- MySQL 5.7+, additive and idempotent, no legacy data removal.
-- ============================================================

DROP PROCEDURE IF EXISTS `mig_authz_preflight_20260804`;
DELIMITER //
CREATE PROCEDURE `mig_authz_preflight_20260804`()
BEGIN
    DECLARE v_oversized_roles INT DEFAULT 0;
    DECLARE v_invalid_roles INT DEFAULT 0;

    SELECT COUNT(*) INTO v_oversized_roles
    FROM `users`
    WHERE `roles_id` IS NOT NULL AND JSON_LENGTH(`roles_id`) > 32;

    IF v_oversized_roles > 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'users.roles_id contains more than 32 role ids';
    END IF;

    SELECT COUNT(*) INTO v_invalid_roles
    FROM `users` u
    JOIN (
        SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3
        UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7
        UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL SELECT 10 UNION ALL SELECT 11
        UNION ALL SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL SELECT 15
        UNION ALL SELECT 16 UNION ALL SELECT 17 UNION ALL SELECT 18 UNION ALL SELECT 19
        UNION ALL SELECT 20 UNION ALL SELECT 21 UNION ALL SELECT 22 UNION ALL SELECT 23
        UNION ALL SELECT 24 UNION ALL SELECT 25 UNION ALL SELECT 26 UNION ALL SELECT 27
        UNION ALL SELECT 28 UNION ALL SELECT 29 UNION ALL SELECT 30 UNION ALL SELECT 31
    ) numbers ON numbers.n < COALESCE(JSON_LENGTH(u.`roles_id`), 0)
    LEFT JOIN `roles` r ON r.id = CAST(JSON_UNQUOTE(JSON_EXTRACT(u.`roles_id`, CONCAT('$[', numbers.n, ']'))) AS UNSIGNED)
    WHERE r.id IS NULL;

    IF v_invalid_roles > 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'users.roles_id references a missing role';
    END IF;
END //
DELIMITER ;

CALL `mig_authz_preflight_20260804`();
DROP PROCEDURE IF EXISTS `mig_authz_preflight_20260804`;

CREATE TABLE IF NOT EXISTS `user_roles` (
  `user_id` INT(11) NOT NULL,
  `role_id` INT(11) NOT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`user_id`, `role_id`),
  KEY `idx_user_roles_role` (`role_id`),
  CONSTRAINT `fk_user_roles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_roles_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `capabilities` (
  `capability` VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `risk_level` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`capability`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `role_grants` (
  `role_id` INT(11) NOT NULL,
  `capability` VARCHAR(100) NOT NULL,
  `scope` VARCHAR(16) NOT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`role_id`, `capability`, `scope`),
  KEY `idx_role_grants_capability` (`capability`),
  CONSTRAINT `fk_role_grants_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_role_grants_capability` FOREIGN KEY (`capability`) REFERENCES `capabilities` (`capability`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `auth_sessions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `jti_hash` CHAR(64) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `token_expires_at` DATETIME NOT NULL,
  `renewable_until` DATETIME NOT NULL,
  `security_version` INT UNSIGNED NOT NULL,
  `revoked_at` DATETIME DEFAULT NULL,
  `revoked_reason` VARCHAR(64) DEFAULT NULL,
  `rotated_to_session_id` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_auth_sessions_jti` (`jti_hash`),
  KEY `idx_auth_sessions_user` (`user_id`),
  KEY `idx_auth_sessions_expiry` (`token_expires_at`),
  CONSTRAINT `fk_auth_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `user_roles` (`user_id`, `role_id`, `created_at`)
SELECT u.id, r.id, NOW()
FROM `users` u
JOIN (
    SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3
    UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7
    UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL SELECT 10 UNION ALL SELECT 11
    UNION ALL SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL SELECT 15
    UNION ALL SELECT 16 UNION ALL SELECT 17 UNION ALL SELECT 18 UNION ALL SELECT 19
    UNION ALL SELECT 20 UNION ALL SELECT 21 UNION ALL SELECT 22 UNION ALL SELECT 23
    UNION ALL SELECT 24 UNION ALL SELECT 25 UNION ALL SELECT 26 UNION ALL SELECT 27
    UNION ALL SELECT 28 UNION ALL SELECT 29 UNION ALL SELECT 30 UNION ALL SELECT 31
) numbers ON numbers.n < COALESCE(JSON_LENGTH(u.`roles_id`), 0)
JOIN `roles` r ON r.id = CAST(JSON_UNQUOTE(JSON_EXTRACT(u.`roles_id`, CONCAT('$[', numbers.n, ']'))) AS UNSIGNED)
LEFT JOIN `user_roles` existing ON existing.user_id = u.id AND existing.role_id = r.id
WHERE existing.user_id IS NULL;

INSERT INTO `capabilities` (`capability`, `description`, `risk_level`)
SELECT normalized.capability, normalized.capability, 0
FROM (
    SELECT DISTINCT
        CASE
            WHEN RIGHT(rc.capability, 4) = '.all' THEN LEFT(rc.capability, CHAR_LENGTH(rc.capability) - 4)
            ELSE rc.capability
        END AS capability
    FROM `role_capabilities` rc
    UNION SELECT 'admin.access'
) normalized
LEFT JOIN `capabilities` existing ON existing.capability = normalized.capability
WHERE existing.capability IS NULL;

INSERT INTO `role_grants` (`role_id`, `capability`, `scope`, `created_at`)
SELECT normalized.role_id, normalized.capability, normalized.scope, NOW()
FROM (
    SELECT DISTINCT
        rc.role_id,
        CASE
            WHEN RIGHT(rc.capability, 4) = '.all' THEN LEFT(rc.capability, CHAR_LENGTH(rc.capability) - 4)
            ELSE rc.capability
        END AS capability,
        CASE
            WHEN RIGHT(rc.capability, 4) = '.all' THEN 'all'
            WHEN rc.capability REGEXP '^(cards|comments|tags|users|files)\\.(read|update|delete|approve|pin)$' THEN 'own'
            ELSE 'global'
        END AS scope
    FROM `role_capabilities` rc
    UNION SELECT 2, 'admin.access', 'global'
) normalized
LEFT JOIN `role_grants` existing
  ON existing.role_id = normalized.role_id
 AND existing.capability = normalized.capability
 AND existing.scope = normalized.scope
WHERE existing.role_id IS NULL;
