-- Card-scoped pin ownership. The relation table remains as a one-release
-- compatibility bridge while all reads/writes converge on cards.pinned_comment_id.
SET @column_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cards' AND COLUMN_NAME = 'pinned_comment_id'
);
SET @stmt = IF(@column_exists = 0,
  'ALTER TABLE `cards` ADD COLUMN `pinned_comment_id` INT NULL DEFAULT NULL COMMENT ''当前卡片置顶的一级评论'' AFTER `comments`',
  'SELECT 1');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

UPDATE `cards` c
JOIN `card_comment_pins` p ON p.`card_id` = c.`id`
JOIN `comments` cm ON cm.`id` = p.`comment_id`
SET c.`pinned_comment_id` = p.`comment_id`
WHERE c.`pinned_comment_id` IS NULL
  AND c.`status` = 0 AND c.`deleted_at` IS NULL
  AND cm.`status` = 0 AND cm.`deleted_at` IS NULL;
