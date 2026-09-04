-- Close the Eventing delivery runtime contract for existing MySQL 5.7 installations.
-- Additive/idempotent; incompatible pre-existing indexes fail closed.
-- @lc-phase expand

DROP PROCEDURE IF EXISTS `mig_eventing_delivery_runtime_20260821`;
DELIMITER //
CREATE PROCEDURE `mig_eventing_delivery_runtime_20260821`()
BEGIN
    DECLARE v_claim_index_parts INT DEFAULT 0;
    DECLARE v_claim_index_valid_parts INT DEFAULT 0;

    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'event_deliveries'
          AND COLUMN_NAME = 'last_error'
          AND CHARACTER_MAXIMUM_LENGTH < 2048
    ) THEN
        ALTER TABLE `event_deliveries`
            MODIFY COLUMN `last_error` VARCHAR(2048) NULL,
            ALGORITHM=INPLACE,
            LOCK=NONE;
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'event_deliveries'
          AND COLUMN_NAME = 'execution_mode'
          AND (COLUMN_DEFAULT IS NULL OR COLUMN_DEFAULT <> 'request_safe')
    ) THEN
        ALTER TABLE `event_deliveries`
            MODIFY COLUMN `execution_mode` VARCHAR(16) NOT NULL DEFAULT 'request_safe',
            ALGORITHM=INPLACE,
            LOCK=NONE;
    END IF;

    -- `worker` was an obsolete database default. Existing rows are daemon-only work.
    UPDATE `event_deliveries`
       SET `execution_mode` = 'daemon_required'
     WHERE `execution_mode` = 'worker';

    SELECT COUNT(*) INTO v_claim_index_parts
      FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'event_deliveries'
       AND INDEX_NAME = 'idx_delivery_claim';

    IF v_claim_index_parts = 0 THEN
        ALTER TABLE `event_deliveries`
            ADD KEY `idx_delivery_claim` (`claim_token`,`status`,`id`),
            ALGORITHM=INPLACE,
            LOCK=NONE;
    ELSE
        SELECT COUNT(*) INTO v_claim_index_valid_parts
          FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'event_deliveries'
           AND INDEX_NAME = 'idx_delivery_claim'
           AND NON_UNIQUE = 1
           AND ((SEQ_IN_INDEX = 1 AND COLUMN_NAME = 'claim_token')
             OR (SEQ_IN_INDEX = 2 AND COLUMN_NAME = 'status')
             OR (SEQ_IN_INDEX = 3 AND COLUMN_NAME = 'id'));
        IF v_claim_index_parts <> 3 OR v_claim_index_valid_parts <> 3 THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'idx_delivery_claim exists with an incompatible definition';
        END IF;
    END IF;
END //
DELIMITER ;

CALL `mig_eventing_delivery_runtime_20260821`();
DROP PROCEDURE IF EXISTS `mig_eventing_delivery_runtime_20260821`;
