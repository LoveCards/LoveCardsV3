-- 通知策略事件名修复：将 Phase 3 初始种子中的旧别名向前规范化为实际事件名。
-- 历史 policy_revision 与已登记投递不回写；只生成一个新的当前策略版本。

-- @lc-phase preflight

-- @lc-phase backfill
DROP PROCEDURE IF EXISTS `mig_normalize_notification_policy_events_20261107`;
DELIMITER //
CREATE PROCEDURE `mig_normalize_notification_policy_events_20261107`()
proc: BEGIN
    DECLARE current_revision INT UNSIGNED DEFAULT NULL;
    DECLARE next_revision INT UNSIGNED DEFAULT NULL;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    -- 锁住当前版本行，避免管理员在读取版本与晋升规则之间并发写入。
    START TRANSACTION;
    SELECT `policy_revision` INTO current_revision
    FROM `notification_policy_versions`
    ORDER BY `policy_revision` DESC
    LIMIT 1
    FOR UPDATE;

    IF current_revision IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '通知策略版本不存在，无法规范化事件名';
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM `notification_policies`
        WHERE `policy_revision` = current_revision
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '通知策略当前版本没有规则行，无法规范化事件名';
    END IF;

    -- 允许运维在账本恢复后安全重放：本迁移已经生成过的版本不再重复晋升。
    IF EXISTS (
        SELECT 1
        FROM `notification_policy_versions`
        WHERE `source` = 'system' AND `reason` = '规范化通知策略事件名（2026-11-07）'
    ) THEN
        COMMIT;
        LEAVE proc;
    END IF;

    SET next_revision = current_revision + 1;

    -- 同一版本同时存在别名和规范名时无法无损合并，先停下避免覆盖管理员选择。
    IF EXISTS (
        SELECT 1
        FROM `notification_policies` alias_row
        INNER JOIN `notification_policies` canonical_row
            ON canonical_row.`policy_revision` = current_revision
            AND canonical_row.`event_version` = alias_row.`event_version`
            AND canonical_row.`rule_key` = alias_row.`rule_key`
        WHERE alias_row.`policy_revision` = current_revision
          AND (
                (alias_row.`event_type` = 'content.saved' AND canonical_row.`event_type` = 'library.saved')
             OR (alias_row.`event_type` = 'comment.published' AND canonical_row.`event_type` = 'content.comment.published')
             OR (alias_row.`event_type` = 'mentions.added' AND canonical_row.`event_type` = 'content.mentions.added')
          )
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '通知策略同时存在旧事件别名和规范事件名，请先人工合并';
    END IF;

    INSERT INTO `notification_policy_versions`
        (`policy_revision`, `snapshot_json`, `source`, `reason`, `created_by`, `created_at`)
    SELECT
        next_revision,
        REPLACE(
            REPLACE(
                REPLACE(`snapshot_json`, '"content.saved":', '"library.saved":'),
                '"comment.published":', '"content.comment.published":'
            ),
            '"mentions.added":', '"content.mentions.added":'
        ),
        'system',
        '规范化通知策略事件名（2026-11-07）',
        NULL,
        UTC_TIMESTAMP(6)
    FROM `notification_policy_versions`
    WHERE `policy_revision` = current_revision;

    UPDATE `notification_policies`
    SET
        `event_type` = CASE `event_type`
            WHEN 'content.saved' THEN 'library.saved'
            WHEN 'comment.published' THEN 'content.comment.published'
            WHEN 'mentions.added' THEN 'content.mentions.added'
            ELSE `event_type`
        END,
        `policy_revision` = next_revision,
        `updated_at` = UTC_TIMESTAMP(6)
    WHERE `policy_revision` = current_revision;

    IF EXISTS (
        SELECT 1
        FROM `notification_policies`
        WHERE `policy_revision` = next_revision
          AND `event_type` IN ('content.saved', 'comment.published', 'mentions.added')
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '通知策略事件名规范化校验失败';
    END IF;

    COMMIT;
END //
DELIMITER ;
CALL `mig_normalize_notification_policy_events_20261107`();
DROP PROCEDURE IF EXISTS `mig_normalize_notification_policy_events_20261107`;

-- @lc-phase verify
SELECT COUNT(*)
FROM `notification_policies`
WHERE `policy_revision` = (SELECT MAX(`policy_revision`) FROM `notification_policy_versions`)
  AND `event_type` IN ('library.saved', 'content.comment.published', 'content.mentions.added');

-- @lc-phase contract
