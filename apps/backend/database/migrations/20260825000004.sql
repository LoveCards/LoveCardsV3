-- Reconcile denormalized card counters from authoritative fact tables.
-- @lc-phase migrate
UPDATE `cards` c
LEFT JOIN (SELECT `ref_id`, COUNT(*) AS `n` FROM `likes` WHERE `ref_type` = 'card' GROUP BY `ref_id`) l ON l.`ref_id` = c.`id`
LEFT JOIN (SELECT `pid`, COUNT(*) AS `n` FROM `comments` WHERE `deleted_at` IS NULL AND `status` = 0 GROUP BY `pid`) r ON r.`pid` = c.`id`
SET c.`goods` = COALESCE(l.`n`, 0), c.`comments` = COALESCE(r.`n`, 0)
WHERE c.`goods` <> COALESCE(l.`n`, 0) OR c.`comments` <> COALESCE(r.`n`, 0);

-- @lc-phase contract
SELECT 1;
