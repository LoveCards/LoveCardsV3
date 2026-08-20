CREATE TABLE IF NOT EXISTS `theme_bindings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `hostname` VARCHAR(253) NOT NULL,
  `theme_id` VARCHAR(64) NOT NULL,
  `release_key` VARCHAR(128) NOT NULL,
  `config_overrides` JSON NULL,
  `enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_theme_bindings_hostname` (`hostname`),
  KEY `idx_theme_bindings_theme` (`theme_id`, `release_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE `theme_bindings` SET `enabled` = 0 WHERE `release_key` = 'source';
