-- ============================================================
-- LoveCardsV3 — Migration 20260728000001
-- Add files.update / files.update.all capabilities
-- Idempotent: uses WHERE NOT EXISTS for each insert
-- Safe for MySQL 5.7+
-- ============================================================

--  PHASE 1: Role anchor verification
--  Verify system role anchors before any writes using explicit COUNT(*) checks.
--  If any mismatch is found, SIGNAL stops execution BEFORE any INSERT.

DROP PROCEDURE IF EXISTS `mig_verify_role_anchors_28000001`;
DELIMITER //
CREATE PROCEDURE `mig_verify_role_anchors_28000001`()
BEGIN
    DECLARE v_root_ok INT DEFAULT 0;
    DECLARE v_admin_ok INT DEFAULT 0;

    SELECT COUNT(*) INTO v_root_ok FROM `roles` WHERE id=1 AND slug='root' AND is_system=1;
    SELECT COUNT(*) INTO v_admin_ok FROM `roles` WHERE id=2 AND slug='admin' AND is_system=1;

    IF v_root_ok <> 1 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Root role (id=1) not found or slug/is_system mismatch';
    END IF;

    IF v_admin_ok <> 1 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Admin role (id=2) not found or slug/is_system mismatch';
    END IF;
END //
DELIMITER ;

CALL mig_verify_role_anchors_28000001();
DROP PROCEDURE IF EXISTS `mig_verify_role_anchors_28000001`;

--  PHASE 2: INSERT new role_capabilities (idempotent)

-- Admin role (id=2): add files.update.all
INSERT INTO `role_capabilities` (`role_id`, `capability`)
SELECT 2, c.capability FROM (
    SELECT 'files.update.all' AS capability
) c
WHERE NOT EXISTS (
    SELECT 1 FROM `role_capabilities`
    WHERE role_id = 2 AND capability = c.capability
);

-- Root role (id=1): add files.update and files.update.all
INSERT INTO `role_capabilities` (`role_id`, `capability`)
SELECT 1, c.capability FROM (
    SELECT 'files.update' AS capability
    UNION
    SELECT 'files.update.all'
) c
WHERE NOT EXISTS (
    SELECT 1 FROM `role_capabilities`
    WHERE role_id = 1 AND capability = c.capability
);
