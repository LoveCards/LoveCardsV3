-- Move comment pinning from a per-comment flag to a card-scoped relationship.
-- The legacy comments.is_top column remains readable during the compatibility window.
-- @lc-phase expand

CREATE TABLE IF NOT EXISTS `card_comment_pins` (
  `card_id` INT(11) NOT NULL,
  `comment_id` INT(11) NOT NULL,
  `pinned_by` INT(11) NOT NULL DEFAULT 0,
  `pinned_at` DATETIME NOT NULL,
  PRIMARY KEY (`card_id`),
  UNIQUE KEY `uq_card_comment_pins_comment` (`comment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `capabilities` (`capability`, `description`, `risk_level`)
SELECT 'comments.pin', '置顶评论', 1
WHERE NOT EXISTS (
  SELECT 1 FROM `capabilities` WHERE `capability` = 'comments.pin'
);

INSERT INTO `role_grants` (`role_id`, `capability`, `scope`, `created_at`)
SELECT 2, 'comments.pin', 'all', NOW()
WHERE EXISTS (SELECT 1 FROM `roles` WHERE `id` = 2 AND `slug` = 'admin' AND `deleted_at` IS NULL)
  AND NOT EXISTS (
    SELECT 1 FROM `role_grants`
    WHERE `role_id` = 2 AND `capability` = 'comments.pin' AND `scope` = 'all'
  );

INSERT INTO `role_grants` (`role_id`, `capability`, `scope`, `created_at`)
SELECT role.`id`, 'comments.pin', 'all', NOW()
FROM `roles` role
LEFT JOIN `role_grants` existing
  ON existing.`role_id` = role.`id`
 AND existing.`capability` = 'comments.pin'
 AND existing.`scope` = 'all'
WHERE role.`slug` IN ('root', 'community_operator')
  AND role.`deleted_at` IS NULL
  AND existing.`role_id` IS NULL;

-- @lc-phase migrate

INSERT INTO `card_comment_pins` (`card_id`, `comment_id`, `pinned_by`, `pinned_at`)
SELECT comment.`pid`, MAX(comment.`id`), 0, NOW()
FROM `comments` comment
JOIN `cards` card
  ON card.`id` = comment.`pid`
 AND card.`status` = 0
 AND card.`deleted_at` IS NULL
LEFT JOIN `card_comment_pins` existing ON existing.`card_id` = comment.`pid`
WHERE comment.`aid` = 1
  AND comment.`parent_id` = 0
  AND comment.`status` = 0
  AND comment.`deleted_at` IS NULL
  AND comment.`is_top` = 1
  AND existing.`card_id` IS NULL
GROUP BY comment.`pid`
;

-- @lc-phase contract
SELECT 1;
