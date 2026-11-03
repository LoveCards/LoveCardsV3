-- Card-level audience visibility.  Status remains the moderation/lifecycle state.
-- @lc-phase expand
DROP PROCEDURE IF EXISTS `mig_cards_visibility_20260904`;
DELIMITER //
CREATE PROCEDURE `mig_cards_visibility_20260904`()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cards' AND COLUMN_NAME = 'visibility'
    ) THEN
        ALTER TABLE `cards`
            ADD COLUMN `visibility` VARCHAR(16) NOT NULL DEFAULT 'public' AFTER `status`;
    END IF;
END //
DELIMITER ;
CALL `mig_cards_visibility_20260904`();
DROP PROCEDURE IF EXISTS `mig_cards_visibility_20260904`;

-- @lc-phase verify
DROP PROCEDURE IF EXISTS `mig_cards_visibility_verify_20260904`;
DELIMITER //
CREATE PROCEDURE `mig_cards_visibility_verify_20260904`()
BEGIN
    IF EXISTS (
        SELECT 1 FROM `cards`
        WHERE `visibility` NOT IN ('public', 'followers', 'private')
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'cards.visibility 包含非法值';
    END IF;
END //
DELIMITER ;
CALL `mig_cards_visibility_verify_20260904`();
DROP PROCEDURE IF EXISTS `mig_cards_visibility_verify_20260904`;
