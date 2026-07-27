-- ============================================================
-- Migration: 20260727000001
-- Date: 2026-07-27
-- Description: DB-SCHEMA-BASELINE-001 (Architect Round 2 fixes)
--   Non-destructive upgrade from legacy schema to current
--   baseline. Adds missing tables, columns, unique keys,
--   indexes; migrates good->likes data; seeds system role
--   capabilities.
--
-- MySQL 5.7 compatible (no SIGNAL CONCAT, SELECT INTO guarded).
-- Safe to run twice (idempotent via information_schema pre-checks).
-- No DROP, TRUNCATE, or REPLACE INTO.
-- Uses SIGNAL SQLSTATE '45000' for real failure termination.
-- Uses migration-specific temporary stored procedures (dropped after use).
--
-- PHASE STRUCTURE:
--   Phase 1 (Preflight): All information_schema checks, conflict detection,
--     role verification. Uses temporary stored procedures (CREATE/DROP
--     PROCEDURE DDL auto-commits, but affects no business tables).
--   Phase 2 (Mutation): CREATE TABLE, ALTER TABLE, INSERT, seed data.
--     Business-table DDL/DML executes only if Phase 1 passed.
--     If Phase 2 fails partway, migration is re-entrant: DROP PROCEDURE
--     at next run cleans up leftover temp objects before retry.
-- ============================================================

-- ============================================================
--  PHASE 1: PREFLIGHT (Read-only checks)
--  All information_schema queries, conflict detection, and
--  data verification happen here before ANY DDL/DML mutation.
--  If any SIGNAL fires, no DDL has been committed.
-- ============================================================

-- 1a. Preflight: Check good/goods conflict (five states A-E)
-- Uses stored procedure for SIGNAL support (MySQL 5.7 limitation)
DELIMITER //
DROP PROCEDURE IF EXISTS `mig_preflight_good_goods`//
CREATE PROCEDURE `mig_preflight_good_goods`()
BEGIN
    DECLARE v_good_exists INT;
    DECLARE v_goods_exists INT;
    DECLARE v_conflict_count INT;
    DECLARE v_null_count INT;
    DECLARE v_msg VARCHAR(255);

    SELECT COUNT(*) INTO v_good_exists FROM information_schema.`COLUMNS`
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cards' AND COLUMN_NAME = 'good';
    SELECT COUNT(*) INTO v_goods_exists FROM information_schema.`COLUMNS`
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cards' AND COLUMN_NAME = 'goods';

    -- State E: both columns exist with conflicting values (NULL-safe comparison)
    IF v_good_exists > 0 AND v_goods_exists > 0 THEN
        -- Use MySQL NULL-safe <=> operator: NULL vs 0 is a conflict (unlike IFNULL which treats them as equal)
        SELECT COUNT(*) INTO v_conflict_count FROM `cards`
            WHERE NOT (`good` <=> `goods`);
        IF v_conflict_count > 0 THEN
            SET v_msg = CONCAT('State E: cards.good and cards.goods conflict (NULL-safe) in ', v_conflict_count, ' rows. Manual resolution required before migration can continue.');
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
        END IF;
    END IF;

    -- State D: both exist, values consistent — proceed
    IF v_good_exists > 0 AND v_goods_exists > 0 THEN
        SELECT 'State D: both columns exist with consistent values' AS preflight;
    END IF;

    -- State C: only goods exists (already migrated) — proceed
    IF v_good_exists = 0 AND v_goods_exists > 0 THEN
        SELECT 'State C: goods already exists, skipping' AS preflight;
    END IF;

    -- State A: both missing — will add goods in Phase 2
    IF v_good_exists = 0 AND v_goods_exists = 0 THEN
        SELECT 'State A: neither good nor goods exists, will create goods' AS preflight;
    END IF;

    -- State B: only good exists — will add goods in Phase 2
    IF v_good_exists > 0 AND v_goods_exists = 0 THEN
        SELECT 'State B: only good exists, will create goods and copy data' AS preflight;
    END IF;
END//
DELIMITER ;

CALL mig_preflight_good_goods();
DROP PROCEDURE IF EXISTS `mig_preflight_good_goods`;

-- 1b. Preflight: Verify role ID 1..4 exist and have correct slug mapping
DELIMITER //
DROP PROCEDURE IF EXISTS `mig_preflight_role_slugs`//
CREATE PROCEDURE `mig_preflight_role_slugs`()
BEGIN
    DECLARE v_role_count INT;
    DECLARE v_slug VARCHAR(50);
    DECLARE v_msg VARCHAR(255);

    -- First verify exactly 4 rows exist with IDs 1,2,3,4
    SELECT COUNT(*) INTO v_role_count FROM `roles` WHERE `id` IN (1, 2, 3, 4);
    IF v_role_count != 4 THEN
        SET v_msg = CONCAT('Expected 4 system roles (ids 1,2,3,4) but found ', v_role_count, ' rows. Cannot set is_system.');
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
    END IF;
    SELECT 'Four system role IDs (1,2,3,4) all present' AS preflight;

    -- Then verify slug mapping
    SELECT IFNULL(slug, '') INTO v_slug FROM `roles` WHERE `id` = 1;
    IF v_slug != 'root' THEN
        SET v_msg = CONCAT('Role id=1 has slug "', v_slug, '", expected "root". Cannot set is_system.');
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
    END IF;

    SELECT IFNULL(slug, '') INTO v_slug FROM `roles` WHERE `id` = 2;
    IF v_slug != 'admin' THEN
        SET v_msg = CONCAT('Role id=2 has slug "', v_slug, '", expected "admin". Cannot set is_system.');
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
    END IF;

    SELECT IFNULL(slug, '') INTO v_slug FROM `roles` WHERE `id` = 3;
    IF v_slug != 'user' THEN
        SET v_msg = CONCAT('Role id=3 has slug "', v_slug, '", expected "user". Cannot set is_system.');
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
    END IF;

    SELECT IFNULL(slug, '') INTO v_slug FROM `roles` WHERE `id` = 4;
    IF v_slug != 'guest' THEN
        SET v_msg = CONCAT('Role id=4 has slug "', v_slug, '", expected "guest". Cannot set is_system.');
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
    END IF;

    SELECT 'All role slug mappings verified: root=1, admin=2, user=3, guest=4' AS preflight;
END//
DELIMITER ;

CALL mig_preflight_role_slugs();
DROP PROCEDURE IF EXISTS `mig_preflight_role_slugs`;

-- 1c. (merged into 1f mig_preflight_configs_structure — NULL checks after column existence check)

-- 1d. Preflight: Detect good (pid, uid) duplicates within good table
DELIMITER //
DROP PROCEDURE IF EXISTS `mig_preflight_good_dupes`//
CREATE PROCEDURE `mig_preflight_good_dupes`()
BEGIN
    DECLARE v_good_exists INT;
    DECLARE v_dup_count INT;
    DECLARE v_msg VARCHAR(255);

    SELECT COUNT(*) INTO v_good_exists FROM information_schema.`TABLES`
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'good';

    IF v_good_exists > 0 THEN
        SELECT COUNT(*) INTO v_dup_count FROM (
            SELECT pid, uid, COUNT(*) AS cnt FROM `good` GROUP BY pid, uid HAVING cnt > 1
        ) t;

        IF v_dup_count > 0 THEN
            SET v_msg = CONCAT('good table has ', v_dup_count, ' (pid, uid) duplicate groups. Manual cleanup required before good->likes migration.');
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
        END IF;
        SELECT 'No (pid, uid) duplicates in good table' AS preflight;
    ELSE
        SELECT 'good table does not exist — duplicate pre-check skipped' AS preflight;
    END IF;
END//
DELIMITER ;

CALL mig_preflight_good_dupes();
DROP PROCEDURE IF EXISTS `mig_preflight_good_dupes`;

-- 1e. Preflight: Detect mismatched values between good and likes
-- Checks legacy fields (aid, pid, uid, ip, created_at) ALWAYS.
-- Checks ref_type/ref_id INDEPENDENTLY per column:
--   Column missing: skip (Phase 2 will create)
--   Column exists and IS NULL: skip (Phase 2 will fill)
--   Column exists and = 'card' (ref_type) or = g.pid (ref_id): normal
--   Column exists and non-NULL and != expected: SIGNAL conflict
-- Uses per-column independent checks to handle partial likes schema
-- (one ref column exists, the other missing).
DELIMITER //
DROP PROCEDURE IF EXISTS `mig_preflight_good_likes_mismatch`//
CREATE PROCEDURE `mig_preflight_good_likes_mismatch`()
BEGIN
    DECLARE v_good_exists INT;
    DECLARE v_likes_exists INT;
    DECLARE v_mismatch_count INT;
    DECLARE v_ref_type_col_exists INT;
    DECLARE v_ref_id_col_exists INT;
    DECLARE v_ref_type_conflict INT;
    DECLARE v_ref_id_conflict INT;
    DECLARE v_msg VARCHAR(255);

    SELECT COUNT(*) INTO v_good_exists FROM information_schema.`TABLES`
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'good';
    SELECT COUNT(*) INTO v_likes_exists FROM information_schema.`TABLES`
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'likes';

    IF v_good_exists > 0 AND v_likes_exists > 0 THEN
        -- 1. ALWAYS check legacy field conflicts (aid, pid, uid, ip, created_at)
        SELECT COUNT(*) INTO v_mismatch_count FROM `good` g
        INNER JOIN `likes` l ON g.pid = l.pid AND g.uid = l.uid
        WHERE (g.aid != l.aid OR g.ip != l.ip OR
               (g.created_at IS NULL AND l.created_at IS NOT NULL) OR
               (g.created_at IS NOT NULL AND l.created_at IS NULL) OR
               g.created_at != l.created_at);

        IF v_mismatch_count > 0 THEN
            SET v_msg = CONCAT('good-likes legacy field mismatch: ', v_mismatch_count, ' records with same (pid,uid) but different aid/ip/created_at. Manual review required.');
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
        END IF;
        SELECT 'Legacy fields (aid, pid, uid, ip, created_at) consistent between good and likes' AS preflight;

        -- 2. INDEPENDENT ref_type check
        SELECT COUNT(*) INTO v_ref_type_col_exists FROM information_schema.`COLUMNS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'likes' AND COLUMN_NAME = 'ref_type';

        IF v_ref_type_col_exists > 0 THEN
            -- Column exists: conflict only when non-NULL AND != 'card'
            SELECT COUNT(*) INTO v_ref_type_conflict FROM `good` g
            INNER JOIN `likes` l ON g.pid = l.pid AND g.uid = l.uid
            WHERE l.ref_type IS NOT NULL AND l.ref_type != 'card';

            IF v_ref_type_conflict > 0 THEN
                SET v_msg = CONCAT('likes.ref_type conflict: ', v_ref_type_conflict, ' matching records have ref_type != ''card''. Manual review required.');
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
            END IF;
            SELECT 'likes.ref_type column exists; all non-NULL values are ''card''' AS preflight;
        ELSE
            SELECT 'likes.ref_type column does not exist — will be created in Phase 2' AS preflight;
        END IF;

        -- 3. INDEPENDENT ref_id check
        SELECT COUNT(*) INTO v_ref_id_col_exists FROM information_schema.`COLUMNS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'likes' AND COLUMN_NAME = 'ref_id';

        IF v_ref_id_col_exists > 0 THEN
            -- Column exists: conflict only when non-NULL AND != g.pid
            SELECT COUNT(*) INTO v_ref_id_conflict FROM `good` g
            INNER JOIN `likes` l ON g.pid = l.pid AND g.uid = l.uid
            WHERE l.ref_id IS NOT NULL AND l.ref_id != g.pid;

            IF v_ref_id_conflict > 0 THEN
                SET v_msg = CONCAT('likes.ref_id conflict: ', v_ref_id_conflict, ' matching records have ref_id != g.pid. Manual review required.');
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
            END IF;
            SELECT 'likes.ref_id column exists; all non-NULL values equal g.pid' AS preflight;
        ELSE
            SELECT 'likes.ref_id column does not exist — will be created in Phase 2' AS preflight;
        END IF;

        SELECT 'good-likes preflight all checks passed' AS preflight;
    ELSE
        SELECT 'good or likes table missing — mismatch pre-check skipped' AS preflight;
    END IF;
END//
DELIMITER ;

CALL mig_preflight_good_likes_mismatch();
DROP PROCEDURE IF EXISTS `mig_preflight_good_likes_mismatch`;

-- 1f. Preflight: Check configs table structure (column types, nullability, unique key)
-- Also checks duplicate (group, key) data (Phase 1 check)
DELIMITER //
DROP PROCEDURE IF EXISTS `mig_preflight_configs_structure`//
CREATE PROCEDURE `mig_preflight_configs_structure`()
BEGIN
    DECLARE v_table_exists INT;
    DECLARE v_col_count INT;
    DECLARE v_group_type VARCHAR(64);
    DECLARE v_key_type VARCHAR(64);
    DECLARE v_group_nullable VARCHAR(3);
    DECLARE v_key_nullable VARCHAR(3);
    DECLARE v_uk_exists INT;
    DECLARE v_uk_seq_match INT;
    DECLARE v_uk_total INT;
    DECLARE v_uk_extra INT;
    DECLARE v_uk_non_unique INT;
    DECLARE v_dup_count INT;
    DECLARE v_group_nulls INT;
    DECLARE v_key_nulls INT;
    DECLARE v_prefix INT;
    DECLARE v_msg VARCHAR(255);

    SELECT COUNT(*) INTO v_table_exists FROM information_schema.`TABLES`
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configs';

    IF v_table_exists > 0 THEN
        -- Check `group` column exists
        SELECT COUNT(*) INTO v_col_count FROM information_schema.`COLUMNS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configs' AND COLUMN_NAME = 'group';
        IF v_col_count = 0 THEN
            SET v_msg = 'CRITICAL: configs.group column is missing. Manual schema repair required.';
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
        END IF;

        -- Check `key` column exists
        SELECT COUNT(*) INTO v_col_count FROM information_schema.`COLUMNS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configs' AND COLUMN_NAME = 'key';
        IF v_col_count = 0 THEN
            SET v_msg = 'CRITICAL: configs.key column is missing. Manual schema repair required.';
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
        END IF;

        -- Check `created_at` column exists
        SELECT COUNT(*) INTO v_col_count FROM information_schema.`COLUMNS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configs' AND COLUMN_NAME = 'created_at';
        IF v_col_count = 0 THEN
            SET v_msg = 'CRITICAL: configs.created_at column is missing. Manual schema repair required.';
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
        END IF;

        -- Check `updated_at` column exists
        SELECT COUNT(*) INTO v_col_count FROM information_schema.`COLUMNS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configs' AND COLUMN_NAME = 'updated_at';
        IF v_col_count = 0 THEN
            SET v_msg = 'CRITICAL: configs.updated_at column is missing. Manual schema repair required.';
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
        END IF;

        -- NULL value checks (merged from standalone mig_preflight_configs_nulls)
        SELECT COUNT(*) INTO v_group_nulls FROM `configs` WHERE `group` IS NULL;
        IF v_group_nulls > 0 THEN
            SET v_msg = CONCAT('configs has ', v_group_nulls, ' NULL group rows; cannot set NOT NULL. Manual cleanup required.');
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
        END IF;
        SELECT COUNT(*) INTO v_key_nulls FROM `configs` WHERE `key` IS NULL;
        IF v_key_nulls > 0 THEN
            SET v_msg = CONCAT('configs has ', v_key_nulls, ' NULL key rows; cannot set NOT NULL. Manual cleanup required.');
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
        END IF;

        -- Three-state check for uk_configs_group_key: missing / exact / conflicting
        SELECT COUNT(*) INTO v_uk_total FROM information_schema.`STATISTICS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configs' AND INDEX_NAME = 'uk_configs_group_key';
        IF v_uk_total > 0 THEN
            -- Verify no prefix index (SUB_PART must be NULL for exact match)
            SELECT COUNT(*) INTO v_prefix FROM information_schema.`STATISTICS`
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configs' AND INDEX_NAME = 'uk_configs_group_key'
                  AND SUB_PART IS NOT NULL;
            IF v_prefix > 0 THEN
                SET v_msg = 'conflicting index uk_configs_group_key on configs — has prefix (SUB_PART not null)';
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
            END IF;
            SELECT COUNT(*) INTO v_uk_seq_match FROM information_schema.`STATISTICS`
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configs' AND INDEX_NAME = 'uk_configs_group_key'
                  AND ((COLUMN_NAME = 'group' AND SEQ_IN_INDEX = 1) OR (COLUMN_NAME = 'key' AND SEQ_IN_INDEX = 2));
            SELECT COUNT(*) INTO v_uk_extra FROM information_schema.`STATISTICS`
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configs' AND INDEX_NAME = 'uk_configs_group_key'
                  AND COLUMN_NAME NOT IN ('group', 'key');
            SELECT MIN(NON_UNIQUE) INTO v_uk_non_unique FROM information_schema.`STATISTICS`
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configs' AND INDEX_NAME = 'uk_configs_group_key';

            IF v_uk_seq_match = 2 AND v_uk_extra = 0 AND v_uk_non_unique = 0 THEN
                SELECT 'uk_configs_group_key exact match on configs (unique, correct columns)' AS preflight;
            ELSE
                SET v_msg = 'conflicting index uk_configs_group_key on configs — wrong columns/order/extra/NON_UNIQUE';
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
            END IF;
        ELSE
            SELECT 'uk_configs_group_key missing on configs — will be added in Phase 2' AS preflight;
        END IF;

        -- Duplicate data check (Phase 1 preflight)
        SELECT COUNT(*) INTO v_dup_count FROM (
            SELECT `group`, `key`, COUNT(*) AS cnt FROM `configs` GROUP BY `group`, `key` HAVING cnt > 1
        ) t;
        IF v_dup_count > 0 THEN
            SET v_msg = CONCAT('configs has ', v_dup_count, ' duplicate (group, key) rows; manual cleanup required');
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
        END IF;
        SELECT 'No duplicate (group, key) data in configs' AS preflight;

        SELECT 'configs structure preflight passed' AS preflight;
    ELSE
        SELECT 'configs table does not exist — will be created in Phase 2' AS preflight;
    END IF;
END//
DELIMITER ;

CALL mig_preflight_configs_structure();
DROP PROCEDURE IF EXISTS `mig_preflight_configs_structure`;

-- 1g. Preflight: Check files table structure (critical columns, indexes)
-- Three-state index checks (missing/exact/conflicting), duplicate hash check,
-- and missing column detection (ref_type, ref_id, upload_status, expire_at)
DELIMITER //
DROP PROCEDURE IF EXISTS `mig_preflight_files_structure`//
CREATE PROCEDURE `mig_preflight_files_structure`()
BEGIN
    DECLARE v_table_exists INT;
    DECLARE v_col_count INT;
    DECLARE v_hash_type VARCHAR(64);
    DECLARE v_hash_len INT;
    DECLARE v_hash_nullable VARCHAR(3);
    DECLARE v_uk_total INT;
    DECLARE v_uk_seq_match INT;
    DECLARE v_uk_extra INT;
    DECLARE v_uk_non_unique INT;
    DECLARE v_idx_seq INT;
    DECLARE v_idx_cols INT;
    DECLARE v_idx_extra INT;
    DECLARE v_dup_count INT;
    DECLARE v_prefix INT;
    DECLARE v_ref_type_exists INT;
    DECLARE v_ref_id_exists INT;
    DECLARE v_upload_status_exists INT;
    DECLARE v_expire_at_exists INT;
    DECLARE v_msg VARCHAR(255);

    SELECT COUNT(*) INTO v_table_exists FROM information_schema.`TABLES`
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files';

    IF v_table_exists > 0 THEN
        -- Check hash column exists
        SELECT COUNT(*) INTO v_col_count FROM information_schema.`COLUMNS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files' AND COLUMN_NAME = 'hash';
        IF v_col_count = 0 THEN
            SET v_msg = 'CRITICAL: files.hash column is missing. Manual schema repair required.';
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
        END IF;

        -- Three-state check for uk_files_hash
        SELECT COUNT(*) INTO v_uk_total FROM information_schema.`STATISTICS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files' AND INDEX_NAME = 'uk_files_hash';
        IF v_uk_total > 0 THEN
            -- Verify no prefix index (SUB_PART must be NULL for exact match)
            SELECT COUNT(*) INTO v_prefix FROM information_schema.`STATISTICS`
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files' AND INDEX_NAME = 'uk_files_hash'
                  AND SUB_PART IS NOT NULL;
            IF v_prefix > 0 THEN
                SET v_msg = 'conflicting index uk_files_hash on files — has prefix (SUB_PART not null)';
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
            END IF;
            SELECT COUNT(*) INTO v_uk_seq_match FROM information_schema.`STATISTICS`
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files' AND INDEX_NAME = 'uk_files_hash'
                  AND COLUMN_NAME = 'hash' AND SEQ_IN_INDEX = 1;
            SELECT COUNT(*) INTO v_uk_extra FROM information_schema.`STATISTICS`
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files' AND INDEX_NAME = 'uk_files_hash'
                  AND COLUMN_NAME NOT IN ('hash');
            SELECT MIN(NON_UNIQUE) INTO v_uk_non_unique FROM information_schema.`STATISTICS`
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files' AND INDEX_NAME = 'uk_files_hash';

            IF v_uk_seq_match = 1 AND v_uk_extra = 0 AND v_uk_non_unique = 0 THEN
                SELECT 'uk_files_hash exact match on files (unique, hash only)' AS preflight;
            ELSE
                SET v_msg = 'conflicting index uk_files_hash on files — wrong columns/order/extra/NON_UNIQUE';
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
            END IF;
        ELSE
            SELECT 'uk_files_hash missing on files — will be added in Phase 2' AS preflight;
        END IF;

        -- Duplicate hash data check (Phase 1 preflight)
        SELECT COUNT(*) INTO v_dup_count FROM (
            SELECT hash, COUNT(*) AS cnt FROM `files` GROUP BY hash HAVING cnt > 1
        ) t;
        IF v_dup_count > 0 THEN
            SET v_msg = CONCAT('files has ', v_dup_count, ' duplicate hash values; manual cleanup required');
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
        END IF;
        SELECT 'No duplicate hash data in files' AS preflight;

        -- Three-state check for idx_user_id
        SELECT COUNT(*) INTO v_uk_total FROM information_schema.`STATISTICS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files' AND INDEX_NAME = 'idx_user_id';
        IF v_uk_total > 0 THEN
            SELECT COUNT(*) INTO v_uk_seq_match FROM information_schema.`STATISTICS`
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files' AND INDEX_NAME = 'idx_user_id'
                  AND COLUMN_NAME = 'user_id' AND SEQ_IN_INDEX = 1;
            SELECT COUNT(*) INTO v_uk_extra FROM information_schema.`STATISTICS`
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files' AND INDEX_NAME = 'idx_user_id'
                  AND COLUMN_NAME NOT IN ('user_id');
            IF v_uk_seq_match = 1 AND v_uk_extra = 0 THEN
                SELECT 'idx_user_id exact match on files' AS preflight;
            ELSE
                SET v_msg = 'conflicting index idx_user_id on files — wrong column/extra columns';
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
            END IF;
        ELSE
            SELECT 'idx_user_id missing on files — will be added in Phase 2' AS preflight;
        END IF;

        -- Three-state check for idx_scene
        SELECT COUNT(*) INTO v_uk_total FROM information_schema.`STATISTICS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files' AND INDEX_NAME = 'idx_scene';
        IF v_uk_total > 0 THEN
            SELECT COUNT(*) INTO v_uk_seq_match FROM information_schema.`STATISTICS`
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files' AND INDEX_NAME = 'idx_scene'
                  AND COLUMN_NAME = 'scene' AND SEQ_IN_INDEX = 1;
            SELECT COUNT(*) INTO v_uk_extra FROM information_schema.`STATISTICS`
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files' AND INDEX_NAME = 'idx_scene'
                  AND COLUMN_NAME NOT IN ('scene');
            IF v_uk_seq_match = 1 AND v_uk_extra = 0 THEN
                SELECT 'idx_scene exact match on files' AS preflight;
            ELSE
                SET v_msg = 'conflicting index idx_scene on files — wrong column/extra columns';
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
            END IF;
        ELSE
            SELECT 'idx_scene missing on files — will be added in Phase 2' AS preflight;
        END IF;

        -- Three-state check for idx_ref (composite: ref_type SEQ=1, ref_id SEQ=2)
        SELECT COUNT(*) INTO v_uk_total FROM information_schema.`STATISTICS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files' AND INDEX_NAME = 'idx_ref';
        IF v_uk_total > 0 THEN
            SELECT COUNT(*) INTO v_uk_seq_match FROM information_schema.`STATISTICS`
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files' AND INDEX_NAME = 'idx_ref'
                  AND ((COLUMN_NAME = 'ref_type' AND SEQ_IN_INDEX = 1) OR (COLUMN_NAME = 'ref_id' AND SEQ_IN_INDEX = 2));
            SELECT COUNT(*) INTO v_uk_extra FROM information_schema.`STATISTICS`
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files' AND INDEX_NAME = 'idx_ref'
                  AND COLUMN_NAME NOT IN ('ref_type', 'ref_id');
            IF v_uk_seq_match = 2 AND v_uk_extra = 0 THEN
                SELECT 'idx_ref exact match on files (ref_type, ref_id)' AS preflight;
            ELSE
                SET v_msg = 'conflicting index idx_ref on files — wrong columns/order/extra';
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
            END IF;
        ELSE
            SELECT 'idx_ref missing on files — will be added in Phase 2' AS preflight;
        END IF;

        -- Three-state check for idx_pending_expire (composite: upload_status SEQ=1, expire_at SEQ=2)
        SELECT COUNT(*) INTO v_uk_total FROM information_schema.`STATISTICS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files' AND INDEX_NAME = 'idx_pending_expire';
        IF v_uk_total > 0 THEN
            SELECT COUNT(*) INTO v_uk_seq_match FROM information_schema.`STATISTICS`
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files' AND INDEX_NAME = 'idx_pending_expire'
                  AND ((COLUMN_NAME = 'upload_status' AND SEQ_IN_INDEX = 1) OR (COLUMN_NAME = 'expire_at' AND SEQ_IN_INDEX = 2));
            SELECT COUNT(*) INTO v_uk_extra FROM information_schema.`STATISTICS`
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files' AND INDEX_NAME = 'idx_pending_expire'
                  AND COLUMN_NAME NOT IN ('upload_status', 'expire_at');
            IF v_uk_seq_match = 2 AND v_uk_extra = 0 THEN
                SELECT 'idx_pending_expire exact match on files (upload_status, expire_at)' AS preflight;
            ELSE
                SET v_msg = 'conflicting index idx_pending_expire on files — wrong columns/order/extra';
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
            END IF;
        ELSE
            SELECT 'idx_pending_expire missing on files — will be added in Phase 2' AS preflight;
        END IF;

        -- Check migration columns: ref_type, ref_id, upload_status, expire_at
        -- These are nullable/default columns, safe to add in Phase 2 if missing
        SELECT COUNT(*) INTO v_ref_type_exists FROM information_schema.`COLUMNS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files' AND COLUMN_NAME = 'ref_type';
        SELECT COUNT(*) INTO v_ref_id_exists FROM information_schema.`COLUMNS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files' AND COLUMN_NAME = 'ref_id';
        SELECT COUNT(*) INTO v_upload_status_exists FROM information_schema.`COLUMNS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files' AND COLUMN_NAME = 'upload_status';
        SELECT COUNT(*) INTO v_expire_at_exists FROM information_schema.`COLUMNS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files' AND COLUMN_NAME = 'expire_at';

        IF v_ref_type_exists = 0 THEN
            SELECT 'files.ref_type missing — will be added in Phase 2' AS preflight;
        END IF;
        IF v_ref_id_exists = 0 THEN
            SELECT 'files.ref_id missing — will be added in Phase 2' AS preflight;
        END IF;
        IF v_upload_status_exists = 0 THEN
            SELECT 'files.upload_status missing — will be added in Phase 2' AS preflight;
        END IF;
        IF v_expire_at_exists = 0 THEN
            SELECT 'files.expire_at missing — will be added in Phase 2' AS preflight;
        END IF;
        IF v_ref_type_exists > 0 AND v_ref_id_exists > 0 AND v_upload_status_exists > 0 AND v_expire_at_exists > 0 THEN
            SELECT 'All files migration columns (ref_type, ref_id, upload_status, expire_at) present' AS preflight;
        END IF;

        SELECT 'files structure preflight passed' AS preflight;
    ELSE
        SELECT 'files table does not exist — will be created in Phase 2' AS preflight;
    END IF;
END//
DELIMITER ;

CALL mig_preflight_files_structure();
DROP PROCEDURE IF EXISTS `mig_preflight_files_structure`;

-- 1h. Preflight: Check likes table structure
-- Three-state uk_likes_pid_uid check, duplicate data check, missing column detection
DELIMITER //
DROP PROCEDURE IF EXISTS `mig_preflight_likes_structure`//
CREATE PROCEDURE `mig_preflight_likes_structure`()
BEGIN
    DECLARE v_table_exists INT;
    DECLARE v_col_count INT;
    DECLARE v_pid_type VARCHAR(64);
    DECLARE v_uid_type VARCHAR(64);
    DECLARE v_ip_type VARCHAR(64);
    DECLARE v_ip_len INT;
    DECLARE v_uk_total INT;
    DECLARE v_uk_seq_match INT;
    DECLARE v_uk_extra INT;
    DECLARE v_uk_non_unique INT;
    DECLARE v_dup_count INT;
    DECLARE v_ref_type_exists INT;
    DECLARE v_ref_id_exists INT;
    DECLARE v_prefix INT;
    DECLARE v_msg VARCHAR(255);

    SELECT COUNT(*) INTO v_table_exists FROM information_schema.`TABLES`
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'likes';

    IF v_table_exists > 0 THEN
        -- Check pid column exists
        SELECT COUNT(*) INTO v_col_count FROM information_schema.`COLUMNS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'likes' AND COLUMN_NAME = 'pid';
        IF v_col_count = 0 THEN
            SET v_msg = 'CRITICAL: likes.pid column is missing. Manual schema repair required.';
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
        END IF;

        -- Check uid column exists
        SELECT COUNT(*) INTO v_col_count FROM information_schema.`COLUMNS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'likes' AND COLUMN_NAME = 'uid';
        IF v_col_count = 0 THEN
            SET v_msg = 'CRITICAL: likes.uid column is missing. Manual schema repair required.';
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
        END IF;

        -- Three-state check for uk_likes_pid_uid: missing / exact / conflicting
        SELECT COUNT(*) INTO v_uk_total FROM information_schema.`STATISTICS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'likes' AND INDEX_NAME = 'uk_likes_pid_uid';
        IF v_uk_total > 0 THEN
            -- Verify no prefix index (SUB_PART must be NULL for exact match)
            SELECT COUNT(*) INTO v_prefix FROM information_schema.`STATISTICS`
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'likes' AND INDEX_NAME = 'uk_likes_pid_uid'
                  AND SUB_PART IS NOT NULL;
            IF v_prefix > 0 THEN
                SET v_msg = 'conflicting index uk_likes_pid_uid on likes — has prefix (SUB_PART not null)';
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
            END IF;
            SELECT COUNT(*) INTO v_uk_seq_match FROM information_schema.`STATISTICS`
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'likes' AND INDEX_NAME = 'uk_likes_pid_uid'
                  AND ((COLUMN_NAME = 'pid' AND SEQ_IN_INDEX = 1) OR (COLUMN_NAME = 'uid' AND SEQ_IN_INDEX = 2));
            SELECT COUNT(*) INTO v_uk_extra FROM information_schema.`STATISTICS`
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'likes' AND INDEX_NAME = 'uk_likes_pid_uid'
                  AND COLUMN_NAME NOT IN ('pid', 'uid');
            SELECT MIN(NON_UNIQUE) INTO v_uk_non_unique FROM information_schema.`STATISTICS`
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'likes' AND INDEX_NAME = 'uk_likes_pid_uid';

            IF v_uk_seq_match = 2 AND v_uk_extra = 0 AND v_uk_non_unique = 0 THEN
                SELECT 'uk_likes_pid_uid exact match on likes (unique, correct columns)' AS preflight;
            ELSE
                SET v_msg = 'conflicting index uk_likes_pid_uid on likes — wrong columns/order/extra/NON_UNIQUE';
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
            END IF;
        ELSE
            SELECT 'uk_likes_pid_uid missing on likes — will be added in Phase 2' AS preflight;
        END IF;

        -- Duplicate (pid, uid) data check (Phase 1 preflight)
        SELECT COUNT(*) INTO v_dup_count FROM (
            SELECT pid, uid, COUNT(*) AS cnt FROM `likes` GROUP BY pid, uid HAVING cnt > 1
        ) t;
        IF v_dup_count > 0 THEN
            SET v_msg = CONCAT('likes has ', v_dup_count, ' duplicate (pid, uid) rows; manual cleanup required');
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
        END IF;
        SELECT 'No duplicate (pid, uid) data in likes' AS preflight;

        -- Check migration columns: ref_type, ref_id
        SELECT COUNT(*) INTO v_ref_type_exists FROM information_schema.`COLUMNS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'likes' AND COLUMN_NAME = 'ref_type';
        SELECT COUNT(*) INTO v_ref_id_exists FROM information_schema.`COLUMNS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'likes' AND COLUMN_NAME = 'ref_id';

        IF v_ref_type_exists = 0 THEN
            SELECT 'likes.ref_type missing — will be added in Phase 2' AS preflight;
        END IF;
        IF v_ref_id_exists = 0 THEN
            SELECT 'likes.ref_id missing — will be added in Phase 2' AS preflight;
        END IF;
        IF v_ref_type_exists > 0 AND v_ref_id_exists > 0 THEN
            SELECT 'All likes migration columns (ref_type, ref_id) present' AS preflight;
        END IF;

        SELECT 'likes structure preflight passed' AS preflight;
    ELSE
        SELECT 'likes table does not exist — will be created in Phase 2' AS preflight;
    END IF;
END//
DELIMITER ;

CALL mig_preflight_likes_structure();
DROP PROCEDURE IF EXISTS `mig_preflight_likes_structure`;

-- 1i. Preflight: Check role_capabilities table structure
-- 1i. Preflight: Check role_capabilities table structure
-- Three-state uk_role_cap check and duplicate data check
DELIMITER //
DROP PROCEDURE IF EXISTS `mig_preflight_role_cap_structure`//
CREATE PROCEDURE `mig_preflight_role_cap_structure`()
BEGIN
    DECLARE v_table_exists INT;
    DECLARE v_col_count INT;
    DECLARE v_role_id_type VARCHAR(64);
    DECLARE v_cap_type VARCHAR(64);
    DECLARE v_cap_len INT;
    DECLARE v_uk_total INT;
    DECLARE v_uk_seq_match INT;
    DECLARE v_uk_extra INT;
    DECLARE v_uk_non_unique INT;
    DECLARE v_dup_count INT;
    DECLARE v_prefix INT;
    DECLARE v_msg VARCHAR(255);

    SELECT COUNT(*) INTO v_table_exists FROM information_schema.`TABLES`
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'role_capabilities';

    IF v_table_exists > 0 THEN
        -- Check role_id column exists
        SELECT COUNT(*) INTO v_col_count FROM information_schema.`COLUMNS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'role_capabilities' AND COLUMN_NAME = 'role_id';
        IF v_col_count = 0 THEN
            SET v_msg = 'CRITICAL: role_capabilities.role_id column is missing. Manual schema repair required.';
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
        END IF;

        -- Check capability column exists
        SELECT COUNT(*) INTO v_col_count FROM information_schema.`COLUMNS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'role_capabilities' AND COLUMN_NAME = 'capability';
        IF v_col_count = 0 THEN
            SET v_msg = 'CRITICAL: role_capabilities.capability column is missing. Manual schema repair required.';
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
        END IF;

        -- Three-state check for uk_role_cap: missing / exact / conflicting
        SELECT COUNT(*) INTO v_uk_total FROM information_schema.`STATISTICS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'role_capabilities'
            AND INDEX_NAME = 'uk_role_cap';
        IF v_uk_total > 0 THEN
            -- Verify no prefix index (SUB_PART must be NULL for exact match)
            SELECT COUNT(*) INTO v_prefix FROM information_schema.`STATISTICS`
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'role_capabilities'
                AND INDEX_NAME = 'uk_role_cap' AND SUB_PART IS NOT NULL;
            IF v_prefix > 0 THEN
                SET v_msg = 'conflicting index uk_role_cap on role_capabilities — has prefix (SUB_PART not null)';
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
            END IF;
            SELECT COUNT(*) INTO v_uk_seq_match FROM information_schema.`STATISTICS`
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'role_capabilities'
                AND INDEX_NAME = 'uk_role_cap'
                AND ((COLUMN_NAME = 'role_id' AND SEQ_IN_INDEX = 1) OR (COLUMN_NAME = 'capability' AND SEQ_IN_INDEX = 2));
            SELECT COUNT(*) INTO v_uk_extra FROM information_schema.`STATISTICS`
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'role_capabilities'
                AND INDEX_NAME = 'uk_role_cap'
                AND COLUMN_NAME NOT IN ('role_id', 'capability');
            SELECT MIN(NON_UNIQUE) INTO v_uk_non_unique FROM information_schema.`STATISTICS`
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'role_capabilities'
                AND INDEX_NAME = 'uk_role_cap';

            IF v_uk_seq_match = 2 AND v_uk_extra = 0 AND v_uk_non_unique = 0 THEN
                SELECT 'uk_role_cap exact match on role_capabilities (unique, correct columns)' AS preflight;
            ELSE
                SET v_msg = 'conflicting index uk_role_cap on role_capabilities — wrong columns/order/extra/NON_UNIQUE';
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
            END IF;
        ELSE
            SELECT 'uk_role_cap missing on role_capabilities — will be added in Phase 2' AS preflight;
        END IF;

        -- Duplicate (role_id, capability) data check (Phase 1 preflight)
        SELECT COUNT(*) INTO v_dup_count FROM (
            SELECT role_id, capability, COUNT(*) AS cnt FROM `role_capabilities` GROUP BY role_id, capability HAVING cnt > 1
        ) t;
        IF v_dup_count > 0 THEN
            SET v_msg = CONCAT('role_capabilities has ', v_dup_count, ' duplicate (role_id, capability) rows; manual cleanup required');
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
        END IF;
        SELECT 'No duplicate (role_id, capability) data in role_capabilities' AS preflight;

        SELECT 'role_capabilities structure preflight passed' AS preflight;
    ELSE
        SELECT 'role_capabilities table does not exist — will be created in Phase 2' AS preflight;
    END IF;
END//
DELIMITER ;

CALL mig_preflight_role_cap_structure();
DROP PROCEDURE IF EXISTS `mig_preflight_role_cap_structure`;

-- ============================================================
--  PHASE 2: MUTATION
--  All CREATE TABLE, ALTER TABLE, INSERT, UPDATE operations.
--  Phase 1 preflight passed — it is safe to proceed.
--
--  NOTE: Phase 1 already verified all structural checks (column
--  existence, types, nullability, unique keys, indexes). Phase 2
--  procedures only contain INFO messages for structural items.
--  The only SIGNAL statements in Phase 2 are for runtime data
--  validation (good->likes data conflicts) which cannot be
--  predicted in Phase 1.
-- ============================================================

-- ============================================================
--  Section 2: Create missing tables (configs, files, likes,
--             role_capabilities) if they do not exist.
-- ============================================================

-- 2a. Create configs table if missing
CREATE TABLE IF NOT EXISTS `configs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `group` VARCHAR(50) NOT NULL COMMENT '分组',
  `key` VARCHAR(100) NOT NULL COMMENT '配置键',
  `value` TEXT COMMENT '配置值',
  `type` VARCHAR(20) DEFAULT 'string' COMMENT '类型',
  `description` VARCHAR(255) DEFAULT NULL COMMENT '配置说明',
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_configs_group_key` (`group`, `key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2b. Create files table if missing
CREATE TABLE IF NOT EXISTS `files` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `channel_slug` VARCHAR(64) DEFAULT NULL,
  `user_id` INT(11) NOT NULL DEFAULT 0,
  `is_public` TINYINT(1) NOT NULL DEFAULT 0,
  `scene` VARCHAR(64) DEFAULT NULL,
  `ref_type` VARCHAR(64) DEFAULT NULL,
  `ref_id` INT(11) DEFAULT NULL,
  `original_name` VARCHAR(255) DEFAULT NULL,
  `file_path` VARCHAR(512) DEFAULT NULL,
  `file_url` VARCHAR(512) DEFAULT NULL,
  `file_size` INT(11) DEFAULT 0,
  `file_ext` VARCHAR(32) DEFAULT NULL,
  `mime_type` VARCHAR(128) DEFAULT NULL,
  `driver_path` VARCHAR(512) DEFAULT NULL,
  `hash` VARCHAR(64) NOT NULL,
  `metadata` JSON DEFAULT NULL,
  `status` TINYINT(1) NOT NULL DEFAULT 0,
  `upload_status` VARCHAR(32) DEFAULT NULL,
  `expire_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_files_hash` (`hash`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_scene` (`scene`),
  KEY `idx_ref` (`ref_type`, `ref_id`),
  KEY `idx_pending_expire` (`upload_status`, `expire_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2c. Create likes table if missing
CREATE TABLE IF NOT EXISTS `likes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `aid` INT(11) NOT NULL COMMENT '应用ID (legacy)',
  `pid` INT(11) NOT NULL COMMENT '条目ID (legacy)',
  `ref_type` VARCHAR(32) DEFAULT NULL COMMENT '内容类型: card, comment',
  `ref_id` INT(11) DEFAULT NULL COMMENT '内容ID',
  `uid` INT(11) NOT NULL,
  `ip` VARCHAR(32) NOT NULL COMMENT '发布IP',
  `created_at` TIMESTAMP NULL DEFAULT NULL COMMENT '发布时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_likes_pid_uid` (`pid`, `uid`),
  KEY `idx_uid` (`uid`),
  KEY `idx_ref` (`ref_type`, `ref_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2d. Create role_capabilities table if missing
CREATE TABLE IF NOT EXISTS `role_capabilities` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `role_id` INT(11) NOT NULL,
  `capability` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_role_cap` (`role_id`, `capability`),
  KEY `idx_role_id` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  Section 3: Verify and fix existing table structure
--  Uses stored procedures for conditional logic and SIGNAL.
--  Each procedure first does COUNT(*) to confirm column exists
--  before SELECT INTO. Composite indexes check SEQ_IN_INDEX.
--  AFTER anchor columns are verified to exist before ADD COLUMN.
-- ============================================================

-- 3a. Check configs table structure and fix columns/keys
DELIMITER //
DROP PROCEDURE IF EXISTS `mig_fix_configs`//
CREATE PROCEDURE `mig_fix_configs`()
BEGIN
    DECLARE v_table_exists INT;
    DECLARE v_col_count INT;
    DECLARE v_group_type VARCHAR(64);
    DECLARE v_key_type VARCHAR(64);
    DECLARE v_created_at_type VARCHAR(64);
    DECLARE v_update_type VARCHAR(64);
    DECLARE v_uk_exists INT;
    DECLARE v_uk_seq_match INT;
    DECLARE v_dup_count INT;
    DECLARE v_after_exists INT;
    DECLARE v_after_col VARCHAR(64);

    SELECT COUNT(*) INTO v_table_exists FROM information_schema.`TABLES`
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configs';

    IF v_table_exists = 0 THEN
        SELECT 'configs table does not exist; handled by CREATE TABLE IF NOT EXISTS above' AS status;
    ELSE
        -- Check `group` column type and null (preflight already confirmed no NULLs)
        SELECT COUNT(*) INTO v_col_count FROM information_schema.`COLUMNS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configs' AND COLUMN_NAME = 'group';
        IF v_col_count > 0 THEN
            SELECT IFNULL(DATA_TYPE, '') INTO v_group_type FROM information_schema.`COLUMNS`
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configs' AND COLUMN_NAME = 'group';
            IF v_group_type != 'varchar' THEN
                SELECT CONCAT('INFO: configs.group type is ', v_group_type, '; expected VARCHAR. Skipping type conversion.') AS info;
            END IF;
        END IF;

        -- Ensure group is NOT NULL
        SELECT COUNT(*) INTO v_col_count FROM information_schema.`COLUMNS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configs' AND COLUMN_NAME = 'group' AND IS_NULLABLE = 'YES';
        IF v_col_count > 0 THEN
            ALTER TABLE `configs` MODIFY COLUMN `group` VARCHAR(50) NOT NULL COMMENT '分组';
            SELECT 'configs.group changed to NOT NULL' AS status;
        END IF;

        -- Check `key` column
        SELECT COUNT(*) INTO v_col_count FROM information_schema.`COLUMNS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configs' AND COLUMN_NAME = 'key' AND IS_NULLABLE = 'YES';
        IF v_col_count > 0 THEN
            ALTER TABLE `configs` MODIFY COLUMN `key` VARCHAR(100) NOT NULL COMMENT '配置键';
            SELECT 'configs.key changed to NOT NULL' AS status;
        END IF;

        -- Check created_at/updated_at types (informational only)
        SELECT COUNT(*) INTO v_col_count FROM information_schema.`COLUMNS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configs' AND COLUMN_NAME = 'created_at';
        IF v_col_count > 0 THEN
            SELECT IFNULL(DATA_TYPE, '') INTO v_created_at_type FROM information_schema.`COLUMNS`
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configs' AND COLUMN_NAME = 'created_at';
            IF v_created_at_type != 'datetime' THEN
                SELECT CONCAT('INFO: configs.created_at type is ', v_created_at_type, ' (expected DATETIME). Skipping.') AS info;
            END IF;
        END IF;

        SELECT COUNT(*) INTO v_col_count FROM information_schema.`COLUMNS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configs' AND COLUMN_NAME = 'updated_at';
        IF v_col_count > 0 THEN
            SELECT IFNULL(DATA_TYPE, '') INTO v_update_type FROM information_schema.`COLUMNS`
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configs' AND COLUMN_NAME = 'updated_at';
            IF v_update_type != 'datetime' THEN
                SELECT CONCAT('INFO: configs.updated_at type is ', v_update_type, ' (expected DATETIME). Skipping.') AS info;
            END IF;
        END IF;

        -- Check uk_configs_group_key with SEQ_IN_INDEX verification
        SELECT COUNT(*) INTO v_uk_exists FROM information_schema.`STATISTICS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configs' AND INDEX_NAME = 'uk_configs_group_key'
              AND COLUMN_NAME IN ('group', 'key');
        -- Verify column order: group (SEQ_IN_INDEX=1), key (SEQ_IN_INDEX=2)
        SELECT COUNT(*) INTO v_uk_seq_match FROM information_schema.`STATISTICS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configs' AND INDEX_NAME = 'uk_configs_group_key'
              AND ((COLUMN_NAME = 'group' AND SEQ_IN_INDEX = 1) OR (COLUMN_NAME = 'key' AND SEQ_IN_INDEX = 2));

        IF v_uk_exists < 2 OR v_uk_seq_match < 2 THEN
            SELECT COUNT(*) INTO v_dup_count FROM (
                SELECT `group`, `key`, COUNT(*) AS cnt FROM `configs` GROUP BY `group`, `key` HAVING cnt > 1
            ) t;
            IF v_dup_count > 0 THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'duplicate (group, key) in configs should have been caught in Phase 1 preflight; manual cleanup required';
            ELSE
                ALTER TABLE `configs` ADD UNIQUE KEY `uk_configs_group_key` (`group`, `key`);
                SELECT 'uk_configs_group_key added to configs' AS status;
            END IF;
        ELSE
            SELECT 'uk_configs_group_key exists with correct column order' AS status;
        END IF;

        SELECT 'configs table structure check complete' AS status;
    END IF;
END//
DELIMITER ;

CALL mig_fix_configs();
DROP PROCEDURE IF EXISTS `mig_fix_configs`;

-- 3b. Check files table structure
-- Fixes IF/END IF imbalance, uses SIGNAL on duplicate data,
-- adds missing migration columns (ref_type, ref_id, upload_status, expire_at)
DELIMITER //
DROP PROCEDURE IF EXISTS `mig_fix_files`//
CREATE PROCEDURE `mig_fix_files`()
BEGIN
    DECLARE v_table_exists INT;
    DECLARE v_col_count INT;
    DECLARE v_hash_nullable VARCHAR(3);
    DECLARE v_hash_type VARCHAR(64);
    DECLARE v_hash_len INT;
    DECLARE v_metadata_type VARCHAR(64);
    DECLARE v_uk_exists INT;
    DECLARE v_uk_seq_match INT;
    DECLARE v_idx_user_id INT;
    DECLARE v_idx_scene INT;
    DECLARE v_idx_ref_cols INT;
    DECLARE v_idx_ref_seq INT;
    DECLARE v_idx_pending_cols INT;
    DECLARE v_idx_pending_seq INT;
    DECLARE v_dup_count INT;
    DECLARE v_after_exists INT;
    DECLARE v_ref_type_exists INT;
    DECLARE v_ref_id_exists INT;
    DECLARE v_upload_status_exists INT;
    DECLARE v_expire_at_exists INT;
    DECLARE v_msg VARCHAR(255);

    SELECT COUNT(*) INTO v_table_exists FROM information_schema.`TABLES`
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files';

    IF v_table_exists = 0 THEN
        SELECT 'files table does not exist; handled by CREATE TABLE IF NOT EXISTS above' AS status;
    ELSE
        -- Check hash column
        SELECT COUNT(*) INTO v_col_count FROM information_schema.`COLUMNS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files' AND COLUMN_NAME = 'hash';
        IF v_col_count > 0 THEN
            SELECT IFNULL(IS_NULLABLE, ''), IFNULL(DATA_TYPE, ''), IFNULL(CHARACTER_MAXIMUM_LENGTH, 0)
                INTO v_hash_nullable, v_hash_type, v_hash_len
                FROM information_schema.`COLUMNS`
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files' AND COLUMN_NAME = 'hash';
            IF v_hash_nullable = 'YES' OR v_hash_type != 'varchar' OR v_hash_len < 64 THEN
                SELECT CONCAT('files.hash must be VARCHAR(64) NOT NULL; Phase 1 preflight detected this. Manual fix may be required.') AS info;
            END IF;
        END IF;

        -- Check metadata column
        SELECT COUNT(*) INTO v_col_count FROM information_schema.`COLUMNS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files' AND COLUMN_NAME = 'metadata';
        IF v_col_count > 0 THEN
            SELECT IFNULL(DATA_TYPE, '') INTO v_metadata_type FROM information_schema.`COLUMNS`
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files' AND COLUMN_NAME = 'metadata';
            IF v_metadata_type != 'json' AND v_metadata_type != 'longtext' THEN
                SELECT CONCAT('files.metadata type is ', v_metadata_type, '; expected JSON. Skipping.') AS status;
            END IF;
        END IF;

        -- Check uk_files_hash — [Fix 1/4] Properly terminated IF/END IF
        SELECT COUNT(*) INTO v_uk_exists FROM information_schema.`STATISTICS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files' AND INDEX_NAME = 'uk_files_hash';
        SELECT COUNT(*) INTO v_uk_seq_match FROM information_schema.`STATISTICS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files' AND INDEX_NAME = 'uk_files_hash'
              AND COLUMN_NAME = 'hash' AND SEQ_IN_INDEX = 1;

        IF v_uk_exists = 0 OR v_uk_seq_match = 0 THEN
            SELECT COUNT(*) INTO v_dup_count FROM (
                SELECT hash, COUNT(*) AS cnt FROM `files` GROUP BY hash HAVING cnt > 1
            ) t;
            IF v_dup_count > 0 THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'duplicate hash values in files should have been caught in Phase 1 preflight; manual cleanup required';
            ELSE
                ALTER TABLE `files` ADD UNIQUE KEY `uk_files_hash` (`hash`);
                SELECT 'uk_files_hash added to files' AS status;
            END IF;
        END IF;  -- correctly closes IF v_uk_exists

        -- Check idx_user_id
        SELECT COUNT(*) INTO v_idx_user_id FROM information_schema.`STATISTICS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files' AND INDEX_NAME = 'idx_user_id'
              AND COLUMN_NAME = 'user_id' AND SEQ_IN_INDEX = 1;
        IF v_idx_user_id = 0 THEN
            ALTER TABLE `files` ADD KEY `idx_user_id` (`user_id`);
            SELECT 'idx_user_id added to files' AS status;
        END IF;

        -- Check idx_scene (single column, SEQ_IN_INDEX=1)
        SELECT COUNT(*) INTO v_idx_scene FROM information_schema.`STATISTICS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files' AND INDEX_NAME = 'idx_scene'
              AND COLUMN_NAME = 'scene' AND SEQ_IN_INDEX = 1;
        IF v_idx_scene = 0 THEN
            ALTER TABLE `files` ADD KEY `idx_scene` (`scene`);
            SELECT 'idx_scene added to files' AS status;
        END IF;

        -- [COLUMNS FIRST] Add missing migration columns BEFORE dependent indexes
        -- Fix: ref_type/ref_id/upload_status/expire_at must exist before idx_ref and idx_pending_expire
        SELECT COUNT(*) INTO v_ref_type_exists FROM information_schema.`COLUMNS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files' AND COLUMN_NAME = 'ref_type';
        SELECT COUNT(*) INTO v_ref_id_exists FROM information_schema.`COLUMNS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files' AND COLUMN_NAME = 'ref_id';
        SELECT COUNT(*) INTO v_upload_status_exists FROM information_schema.`COLUMNS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files' AND COLUMN_NAME = 'upload_status';
        SELECT COUNT(*) INTO v_expire_at_exists FROM information_schema.`COLUMNS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files' AND COLUMN_NAME = 'expire_at';

        IF v_ref_type_exists = 0 THEN
            SELECT COUNT(*) INTO v_after_exists FROM information_schema.`COLUMNS`
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files' AND COLUMN_NAME = 'scene';
            IF v_after_exists > 0 THEN
                ALTER TABLE `files` ADD COLUMN `ref_type` VARCHAR(64) DEFAULT NULL COMMENT 'ref type' AFTER `scene`;
            ELSE
                ALTER TABLE `files` ADD COLUMN `ref_type` VARCHAR(64) DEFAULT NULL COMMENT 'ref type';
            END IF;
            SELECT 'ref_type column added to files' AS status;
        END IF;

        IF v_ref_id_exists = 0 THEN
            SELECT COUNT(*) INTO v_after_exists FROM information_schema.`COLUMNS`
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files' AND COLUMN_NAME = 'ref_type';
            IF v_after_exists > 0 THEN
                ALTER TABLE `files` ADD COLUMN `ref_id` INT(11) DEFAULT NULL COMMENT 'ref id' AFTER `ref_type`;
            ELSE
                ALTER TABLE `files` ADD COLUMN `ref_id` INT(11) DEFAULT NULL COMMENT 'ref id';
            END IF;
            SELECT 'ref_id column added to files' AS status;
        END IF;

        IF v_upload_status_exists = 0 THEN
            SELECT COUNT(*) INTO v_after_exists FROM information_schema.`COLUMNS`
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files' AND COLUMN_NAME = 'status';
            IF v_after_exists > 0 THEN
                ALTER TABLE `files` ADD COLUMN `upload_status` VARCHAR(32) DEFAULT NULL COMMENT 'upload status' AFTER `status`;
            ELSE
                ALTER TABLE `files` ADD COLUMN `upload_status` VARCHAR(32) DEFAULT NULL COMMENT 'upload status';
            END IF;
            SELECT 'upload_status column added to files' AS status;
        END IF;

        IF v_expire_at_exists = 0 THEN
            SELECT COUNT(*) INTO v_after_exists FROM information_schema.`COLUMNS`
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files' AND COLUMN_NAME = 'upload_status';
            IF v_after_exists > 0 THEN
                ALTER TABLE `files` ADD COLUMN `expire_at` DATETIME DEFAULT NULL COMMENT 'expire at' AFTER `upload_status`;
            ELSE
                ALTER TABLE `files` ADD COLUMN `expire_at` DATETIME DEFAULT NULL COMMENT 'expire at';
            END IF;
            SELECT 'expire_at column added to files' AS status;
        END IF;

        -- [INDEXES AFTER COLUMNS] idx_ref and idx_pending_expire depend on columns above
        -- Check idx_ref composite: ref_type (SEQ=1), ref_id (SEQ=2)
        SELECT COUNT(*) INTO v_idx_ref_cols FROM information_schema.`STATISTICS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files' AND INDEX_NAME = 'idx_ref'
              AND COLUMN_NAME IN ('ref_type', 'ref_id');
        SELECT COUNT(*) INTO v_idx_ref_seq FROM information_schema.`STATISTICS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files' AND INDEX_NAME = 'idx_ref'
              AND ((COLUMN_NAME = 'ref_type' AND SEQ_IN_INDEX = 1) OR (COLUMN_NAME = 'ref_id' AND SEQ_IN_INDEX = 2));
        IF v_idx_ref_cols < 2 OR v_idx_ref_seq < 2 THEN
            ALTER TABLE `files` ADD KEY `idx_ref` (`ref_type`, `ref_id`);
            SELECT 'idx_ref added to files' AS status;
        END IF;

        -- Check idx_pending_expire composite: upload_status (SEQ=1), expire_at (SEQ=2)
        SELECT COUNT(*) INTO v_idx_pending_cols FROM information_schema.`STATISTICS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files' AND INDEX_NAME = 'idx_pending_expire'
              AND COLUMN_NAME IN ('upload_status', 'expire_at');
        SELECT COUNT(*) INTO v_idx_pending_seq FROM information_schema.`STATISTICS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files' AND INDEX_NAME = 'idx_pending_expire'
              AND ((COLUMN_NAME = 'upload_status' AND SEQ_IN_INDEX = 1) OR (COLUMN_NAME = 'expire_at' AND SEQ_IN_INDEX = 2));
        IF v_idx_pending_cols < 2 OR v_idx_pending_seq < 2 THEN
            ALTER TABLE `files` ADD KEY `idx_pending_expire` (`upload_status`, `expire_at`);
            SELECT 'idx_pending_expire added to files' AS status;
        END IF;

        SELECT 'files table structure check complete' AS status;
    END IF;
END//
DELIMITER ;

CALL mig_fix_files();
DROP PROCEDURE IF EXISTS `mig_fix_files`;

-- 3c. Check likes table structure
-- Fixes duplicate ADD UNIQUE KEY (BLOCKER), uses SIGNAL on duplicate data,
-- adds missing migration columns (ref_type, ref_id)
DELIMITER //
DROP PROCEDURE IF EXISTS `mig_fix_likes`//
CREATE PROCEDURE `mig_fix_likes`()
BEGIN
    DECLARE v_table_exists INT;
    DECLARE v_col_count INT;
    DECLARE v_pid_type VARCHAR(64);
    DECLARE v_uid_type VARCHAR(64);
    DECLARE v_ip_type VARCHAR(64);
    DECLARE v_ip_len INT;
    DECLARE v_uk_exists INT;
    DECLARE v_uk_seq_match INT;
    DECLARE v_dup_count INT;
    DECLARE v_ct_type VARCHAR(64);
    DECLARE v_after_exists INT;
    DECLARE v_ref_type_exists INT;
    DECLARE v_ref_id_exists INT;

    SELECT COUNT(*) INTO v_table_exists FROM information_schema.`TABLES`
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'likes';

    IF v_table_exists = 0 THEN
        SELECT 'likes table does not exist; handled by CREATE TABLE IF NOT EXISTS above' AS status;
    ELSE
        -- Check pid type (Phase 1 preflight already confirmed column exists)
        SELECT COUNT(*) INTO v_col_count FROM information_schema.`COLUMNS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'likes' AND COLUMN_NAME = 'pid';
        IF v_col_count > 0 THEN
            SELECT IFNULL(DATA_TYPE, '') INTO v_pid_type FROM information_schema.`COLUMNS`
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'likes' AND COLUMN_NAME = 'pid';
            IF v_pid_type != 'int' THEN
                SELECT CONCAT('INFO: likes.pid type is ', v_pid_type, '; expected INT. Skipping type conversion.') AS info;
            END IF;
        END IF;

        -- Check uid type
        SELECT COUNT(*) INTO v_col_count FROM information_schema.`COLUMNS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'likes' AND COLUMN_NAME = 'uid';
        IF v_col_count > 0 THEN
            SELECT IFNULL(DATA_TYPE, '') INTO v_uid_type FROM information_schema.`COLUMNS`
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'likes' AND COLUMN_NAME = 'uid';
            IF v_uid_type != 'int' THEN
                SELECT CONCAT('INFO: likes.uid type is ', v_uid_type, '; expected INT. Skipping type conversion.') AS info;
            END IF;
        END IF;

        -- Check ip type and length
        SELECT COUNT(*) INTO v_col_count FROM information_schema.`COLUMNS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'likes' AND COLUMN_NAME = 'ip';
        IF v_col_count > 0 THEN
            SELECT IFNULL(DATA_TYPE, ''), IFNULL(CHARACTER_MAXIMUM_LENGTH, 0)
                INTO v_ip_type, v_ip_len
                FROM information_schema.`COLUMNS`
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'likes' AND COLUMN_NAME = 'ip';
            IF v_ip_type != 'varchar' OR v_ip_len < 32 THEN
                SELECT CONCAT('INFO: likes.ip type is ', v_ip_type, '(', v_ip_len, '); expected VARCHAR(32). Skipping.') AS info;
            END IF;
        END IF;

        -- Check uk_likes_pid_uid with SEQ_IN_INDEX verification
        -- [Fix 3] Removed duplicate ADD UNIQUE KEY that always executed unconditionally
        SELECT COUNT(*) INTO v_uk_exists FROM information_schema.`STATISTICS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'likes' AND INDEX_NAME = 'uk_likes_pid_uid'
              AND COLUMN_NAME IN ('pid', 'uid');
        SELECT COUNT(*) INTO v_uk_seq_match FROM information_schema.`STATISTICS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'likes' AND INDEX_NAME = 'uk_likes_pid_uid'
              AND ((COLUMN_NAME = 'pid' AND SEQ_IN_INDEX = 1) OR (COLUMN_NAME = 'uid' AND SEQ_IN_INDEX = 2));
        IF v_uk_exists < 2 OR v_uk_seq_match < 2 THEN
            SELECT COUNT(*) INTO v_dup_count FROM (
                SELECT pid, uid, COUNT(*) AS cnt FROM `likes` GROUP BY pid, uid HAVING cnt > 1
            ) t;
            IF v_dup_count > 0 THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'duplicate (pid, uid) in likes should have been caught in Phase 1 preflight; manual cleanup required';
            ELSE
                ALTER TABLE `likes` ADD UNIQUE KEY `uk_likes_pid_uid` (`pid`, `uid`);
                SELECT 'uk_likes_pid_uid added to likes' AS status;
            END IF;
        END IF;

        -- [Fix 6] Add missing migration columns for likes (ref_type, ref_id)
        SELECT COUNT(*) INTO v_ref_type_exists FROM information_schema.`COLUMNS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'likes' AND COLUMN_NAME = 'ref_type';
        SELECT COUNT(*) INTO v_ref_id_exists FROM information_schema.`COLUMNS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'likes' AND COLUMN_NAME = 'ref_id';

        IF v_ref_type_exists = 0 THEN
            SELECT COUNT(*) INTO v_after_exists FROM information_schema.`COLUMNS`
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'likes' AND COLUMN_NAME = 'uid';
            IF v_after_exists > 0 THEN
                ALTER TABLE `likes` ADD COLUMN `ref_type` VARCHAR(32) DEFAULT NULL COMMENT '内容类型: card, comment' AFTER `uid`;
            ELSE
                ALTER TABLE `likes` ADD COLUMN `ref_type` VARCHAR(32) DEFAULT NULL COMMENT '内容类型: card, comment';
            END IF;
            SELECT 'ref_type column added to likes' AS status;
        END IF;

        IF v_ref_id_exists = 0 THEN
            SELECT COUNT(*) INTO v_after_exists FROM information_schema.`COLUMNS`
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'likes' AND COLUMN_NAME = 'ref_type';
            IF v_after_exists > 0 THEN
                ALTER TABLE `likes` ADD COLUMN `ref_id` INT(11) DEFAULT NULL COMMENT '内容ID' AFTER `ref_type`;
            ELSE
                ALTER TABLE `likes` ADD COLUMN `ref_id` INT(11) DEFAULT NULL COMMENT '内容ID';
            END IF;
            SELECT 'ref_id column added to likes' AS status;
        END IF;

        -- Check created_at column exists, add if missing AFTER `ip`
        SELECT COUNT(*) INTO v_col_count FROM information_schema.`COLUMNS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'likes' AND COLUMN_NAME = 'created_at';
        IF v_col_count = 0 THEN
            -- Verify anchor column `ip` exists before ADD COLUMN AFTER
            SELECT COUNT(*) INTO v_after_exists FROM information_schema.`COLUMNS`
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'likes' AND COLUMN_NAME = 'ip';
            IF v_after_exists > 0 THEN
                ALTER TABLE `likes` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL COMMENT '发布时间' AFTER `ip`;
                SELECT 'created_at column added to likes' AS status;
            ELSE
                ALTER TABLE `likes` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL COMMENT '发布时间';
                SELECT 'created_at column added to likes (no anchor — ip column not found)' AS status;
            END IF;
        END IF;

        SELECT 'likes table structure check complete' AS status;
    END IF;
END//
DELIMITER ;

CALL mig_fix_likes();
DROP PROCEDURE IF EXISTS `mig_fix_likes`;

-- 3d. Check role_capabilities table structure
-- Fixes IF/END IF imbalance, uses SIGNAL on duplicate data
DELIMITER //
DROP PROCEDURE IF EXISTS `mig_fix_role_cap`//
CREATE PROCEDURE `mig_fix_role_cap`()
BEGIN
    DECLARE v_table_exists INT;
    DECLARE v_col_count INT;
    DECLARE v_role_id_type VARCHAR(64);
    DECLARE v_cap_type VARCHAR(64);
    DECLARE v_cap_len INT;
    DECLARE v_uk_exists INT;
    DECLARE v_uk_seq_match INT;
    DECLARE v_dup_count INT;
    DECLARE v_msg VARCHAR(255);

    SELECT COUNT(*) INTO v_table_exists FROM information_schema.`TABLES`
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'role_capabilities';

    IF v_table_exists = 0 THEN
        SELECT 'role_capabilities table does not exist; handled by CREATE TABLE IF NOT EXISTS above' AS status;
    ELSE
        -- Check role_id type (Phase 1 preflight already confirmed column exists)
        SELECT COUNT(*) INTO v_col_count FROM information_schema.`COLUMNS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'role_capabilities' AND COLUMN_NAME = 'role_id';
        IF v_col_count > 0 THEN
            SELECT IFNULL(DATA_TYPE, '') INTO v_role_id_type FROM information_schema.`COLUMNS`
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'role_capabilities' AND COLUMN_NAME = 'role_id';
            IF v_role_id_type != 'int' THEN
                SELECT CONCAT('INFO: role_capabilities.role_id type is ', v_role_id_type, '; expected INT. Skipping.') AS info;
            END IF;
        END IF;

        -- Check capability type and length
        SELECT COUNT(*) INTO v_col_count FROM information_schema.`COLUMNS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'role_capabilities' AND COLUMN_NAME = 'capability';
        IF v_col_count > 0 THEN
            SELECT IFNULL(DATA_TYPE, ''), IFNULL(CHARACTER_MAXIMUM_LENGTH, 0)
                INTO v_cap_type, v_cap_len
                FROM information_schema.`COLUMNS`
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'role_capabilities' AND COLUMN_NAME = 'capability';
            IF v_cap_type != 'varchar' OR v_cap_len < 100 THEN
                SELECT CONCAT('INFO: role_capabilities.capability type is ', v_cap_type, '(', v_cap_len, '); expected VARCHAR(100). Skipping.') AS info;
            END IF;
        END IF;

        -- Check exact uk_role_cap (role_id, capability) with SEQ_IN_INDEX
        -- [Fix 2] Properly terminated IF/END IF structure
        SELECT COUNT(*) INTO v_uk_exists FROM information_schema.`TABLE_CONSTRAINTS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'role_capabilities'
            AND CONSTRAINT_TYPE = 'UNIQUE' AND CONSTRAINT_NAME = 'uk_role_cap';
        SELECT COUNT(*) INTO v_uk_seq_match FROM information_schema.`STATISTICS`
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'role_capabilities'
            AND INDEX_NAME = 'uk_role_cap'
            AND ((COLUMN_NAME = 'role_id' AND SEQ_IN_INDEX = 1) OR (COLUMN_NAME = 'capability' AND SEQ_IN_INDEX = 2));

        IF v_uk_exists = 0 OR v_uk_seq_match < 2 THEN
            SELECT COUNT(*) INTO v_dup_count FROM (
                SELECT role_id, capability, COUNT(*) AS cnt FROM `role_capabilities` GROUP BY role_id, capability HAVING cnt > 1
            ) t;
            IF v_dup_count > 0 THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'duplicate (role_id, capability) in role_capabilities should have been caught in Phase 1 preflight; manual cleanup required';
            ELSE
                ALTER TABLE `role_capabilities` ADD UNIQUE KEY `uk_role_cap` (`role_id`, `capability`);
                SELECT 'uk_role_cap added to role_capabilities' AS status;
            END IF;
        END IF;  -- closes outer IF v_uk_exists — was previously missing!

        SELECT 'role_capabilities table structure check complete' AS status;
    END IF;
END//
DELIMITER ;

CALL mig_fix_role_cap();
DROP PROCEDURE IF EXISTS `mig_fix_role_cap`;

-- ============================================================
--  Section 4: Add missing columns (with pre-checks)
-- ============================================================

-- 4a. Add cards.pictures JSON if missing (verify AFTER anchor exists)
SET @cards_pictures_exists = (SELECT COUNT(*) FROM information_schema.`COLUMNS` WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cards' AND COLUMN_NAME = 'pictures');
SET @pictures_after_exists = (SELECT COUNT(*) FROM information_schema.`COLUMNS` WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cards' AND COLUMN_NAME = 'tags');
SET @stmt = IF(@cards_pictures_exists = 0 AND @pictures_after_exists > 0,
    'ALTER TABLE `cards` ADD COLUMN `pictures` JSON DEFAULT NULL AFTER `tags`', IF(@cards_pictures_exists = 0,
        'ALTER TABLE `cards` ADD COLUMN `pictures` JSON DEFAULT NULL',
        'SELECT ''pictures already exists'' AS status'));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4b. Add cards.goods INT with preflight-confirmed state (preflight already done in Phase 1)
-- States A/B: add goods column (with AFTER anchor check)
SET @cards_goods_exists = (SELECT COUNT(*) FROM information_schema.`COLUMNS` WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cards' AND COLUMN_NAME = 'goods');
SET @cards_good_exists = (SELECT COUNT(*) FROM information_schema.`COLUMNS` WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cards' AND COLUMN_NAME = 'good');

-- State A: both missing
SET @stmt = IF(@cards_good_exists = 0 AND @cards_goods_exists = 0, IF((SELECT COUNT(*) FROM information_schema.`COLUMNS` WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cards' AND COLUMN_NAME = 'tags') > 0,
        'ALTER TABLE `cards` ADD COLUMN `goods` INT(11) NOT NULL DEFAULT 0 AFTER `tags`',
        'ALTER TABLE `cards` ADD COLUMN `goods` INT(11) NOT NULL DEFAULT 0'),
    'SELECT ''goods already handled'' AS status');
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- State B: only good exists -> ADD COLUMN goods, copy data
SET @stmt = IF(@cards_good_exists > 0 AND @cards_goods_exists = 0, IF((SELECT COUNT(*) FROM information_schema.`COLUMNS` WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cards' AND COLUMN_NAME = 'good') > 0,
        'ALTER TABLE `cards` ADD COLUMN `goods` INT(11) NOT NULL DEFAULT 0 AFTER `good`',
        'ALTER TABLE `cards` ADD COLUMN `goods` INT(11) NOT NULL DEFAULT 0'),
    'SELECT ''goods already handled'' AS status');
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Copy data from good to goods if applicable (state B only)
-- Must use prepared statement: MySQL parses column refs at statement-prepare time,
-- and `good` column may not exist in the target schema (state C/D/E).
SET @stmt = IF(@cards_good_exists > 0 AND @cards_goods_exists = 0,
    'UPDATE `cards` SET `goods` = `good` WHERE `good` != 0',
    'SET @dummy = 0');
PREPARE stmt FROM @stmt;
EXECUTE stmt;
-- Capture ROW_COUNT before DEALLOCATE resets it
SET @good_to_goods_rows = ROW_COUNT();
DEALLOCATE PREPARE stmt;

-- Report audit count
SET @msg = IF(@cards_good_exists > 0 AND @cards_goods_exists = 0,
    CONCAT('State B: goods column added, copied ', @good_to_goods_rows, ' rows from good'),
    'State not B: good-to-goods copy skipped');
SELECT @msg AS status;

-- 4c. Add roles.is_system TINYINT if missing (verify AFTER anchor exists)
SET @roles_is_system_exists = (SELECT COUNT(*) FROM information_schema.`COLUMNS` WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'roles' AND COLUMN_NAME = 'is_system');
SET @is_system_after_exists = (SELECT COUNT(*) FROM information_schema.`COLUMNS` WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'roles' AND COLUMN_NAME = 'slug');
SET @stmt = IF(@roles_is_system_exists = 0 AND @is_system_after_exists > 0,
    'ALTER TABLE `roles` ADD COLUMN `is_system` TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''系统角色标记'' AFTER `slug`', IF(@roles_is_system_exists = 0,
        'ALTER TABLE `roles` ADD COLUMN `is_system` TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''系统角色标记''',
        'SELECT ''is_system already exists'' AS status'));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4d. Add tags_map.status INT if missing (verify AFTER anchor exists)
SET @tags_map_status_exists = (SELECT COUNT(*) FROM information_schema.`COLUMNS` WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tags_map' AND COLUMN_NAME = 'status');
SET @status_after_exists = (SELECT COUNT(*) FROM information_schema.`COLUMNS` WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tags_map' AND COLUMN_NAME = 'tag_id');
SET @stmt = IF(@tags_map_status_exists = 0 AND @status_after_exists > 0,
    'ALTER TABLE `tags_map` ADD COLUMN `status` INT(11) NOT NULL DEFAULT 0 COMMENT ''标签映射状态'' AFTER `tag_id`', IF(@tags_map_status_exists = 0,
        'ALTER TABLE `tags_map` ADD COLUMN `status` INT(11) NOT NULL DEFAULT 0 COMMENT ''标签映射状态''',
        'SELECT ''status already exists'' AS status'));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================
--  Section 5: Verify role ID -> slug mapping before setting
--  is_system. Preflight already done in Phase 1.
-- ============================================================

-- Update is_system for verified system roles (only if not already set)
UPDATE `roles` SET `is_system` = 1 WHERE `id` IN (1, 2, 3, 4) AND (`is_system` IS NULL OR `is_system` = 0);
SELECT CONCAT('Updated is_system for ', ROW_COUNT(), ' system roles') AS status;

-- ============================================================
--  Section 6: Index validation is fully handled by mig_fix_files
--  stored procedure in Section 3. The following inline checks
--  are redundant and have been removed to maintain a single
--  source of truth for index management.
-- ============================================================

-- ============================================================
--  Section 7: Migrate good -> likes data (three-phase)
--  Phase A: detect (pid, uid) duplicates within good table (preflight in 1d)
--  Phase B: detect mismatched values between good and likes (preflight in 1e)
--  Phase C: insert only completely new records with WHERE NOT EXISTS
--  No INSERT IGNORE used. Uses NOT EXISTS and checks ref_type='card' + ref_id=pid.
-- ============================================================

-- Only proceed if good table exists
DELIMITER //
DROP PROCEDURE IF EXISTS `mig_migrate_likes`//
CREATE PROCEDURE `mig_migrate_likes`()
BEGIN
    DECLARE v_good_exists INT;
    DECLARE v_likes_exists INT;
    DECLARE v_source_count INT DEFAULT 0;
    DECLARE v_already_equal INT DEFAULT 0;
    DECLARE v_inserted INT DEFAULT 0;
    DECLARE v_fix_ref_type INT DEFAULT 0;
    DECLARE v_fix_ref_id INT DEFAULT 0;
    DECLARE v_msg VARCHAR(255);

    SELECT COUNT(*) INTO v_good_exists FROM information_schema.`TABLES`
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'good';
    SELECT COUNT(*) INTO v_likes_exists FROM information_schema.`TABLES`
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'likes';

    IF v_good_exists = 0 THEN
        SELECT 'good table does not exist; skipping good->likes migration' AS status;
    ELSE
        -- Count records already migrated with identical values
        -- Must check ref_type='card' AND ref_id=pid for exact match
        SELECT COUNT(*) INTO v_already_equal FROM `likes` l
        WHERE l.ref_type = 'card' AND l.ref_id = l.pid
        AND EXISTS (
            SELECT 1 FROM `good` g
            WHERE g.pid = l.pid AND g.uid = l.uid
              AND g.aid = l.aid AND g.ip = l.ip
              AND ((g.created_at = l.created_at) OR (g.created_at IS NULL AND l.created_at IS NULL))
        );

        SELECT COUNT(*) INTO v_source_count FROM `good`;

        -- Fill NULL ref_type for existing matching likes only (Phase 1 preflight confirmed no non-NULL conflicts)
        UPDATE `likes` l
        INNER JOIN `good` g ON g.pid = l.pid AND g.uid = l.uid
        SET l.ref_type = 'card'
        WHERE l.ref_type IS NULL;
        SET v_fix_ref_type = ROW_COUNT();

        -- Fill NULL ref_id for existing matching likes only (Phase 1 preflight confirmed no non-NULL conflicts)
        UPDATE `likes` l
        INNER JOIN `good` g ON g.pid = l.pid AND g.uid = l.uid
        SET l.ref_id = g.pid
        WHERE l.ref_id IS NULL;
        SET v_fix_ref_id = ROW_COUNT();

        -- Insert records that don't exist in likes at all (by pid, uid)
        -- Uses NOT EXISTS for safe idempotent insert
        INSERT INTO `likes` (`aid`, `pid`, `ref_type`, `ref_id`, `uid`, `ip`, `created_at`)
        SELECT g.`aid`, g.`pid`, 'card', g.`pid`, g.`uid`, g.`ip`, g.`created_at`
        FROM `good` g
        WHERE NOT EXISTS (
            SELECT 1 FROM `likes` l WHERE l.pid = g.pid AND l.uid = g.uid
        );
        SET v_inserted = ROW_COUNT();

        SELECT v_source_count AS source_count, v_already_equal AS already_equal, v_fix_ref_type AS fix_ref_type, v_fix_ref_id AS fix_ref_id, v_inserted AS inserted;
        SET v_msg = CONCAT('Migration complete: ', v_source_count, ' source, ', v_already_equal, ' already equal, ref_type fixed: ', v_fix_ref_type, ', ref_id fixed: ', v_fix_ref_id, ', inserted: ', v_inserted);
        SELECT v_msg AS status;
    END IF;
END//
DELIMITER ;

-- Check if good table exists (for inline idempotent guard on SP call)
SET @good_table_exists = (SELECT COUNT(*) FROM information_schema.`TABLES` WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'good');
SET @stmt = IF(@good_table_exists > 0,
    'CALL mig_migrate_likes()',
    'SELECT ''good table does not exist; skipping likes migration'' AS status');
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

DROP PROCEDURE IF EXISTS `mig_migrate_likes`;

-- ============================================================
--  Section 8: Seed role_capabilities for system roles
--  Non-destructive initialization: only inserts missing entries,
--  never deletes existing capabilities.
--
--  Capability matrix derived from Roles::reseed() in
--  apps/backend/app/api/service/Rbac/Roles.php.
--  System roles: root(1), admin(2), user(3), guest(4).
--  All capabilities come from RBAC::getAllCapabilities() in
--  apps/backend/app/api/service/Rbac/RBAC.php.
--
--  IMPORTANT: When updating the capability matrix in PHP code,
--  the corresponding INSERT statements below must be kept in sync.
-- ============================================================

-- Helper: insert single capability idempotently via INSERT WHERE NOT EXISTS
-- Root (id=1): all capabilities from getAllCapabilities()
INSERT INTO `role_capabilities` (`role_id`, `capability`)
SELECT 1, c.capability FROM (
  SELECT 'cards.read' AS capability UNION SELECT 'cards.read.all' UNION SELECT 'cards.create' UNION SELECT 'cards.update' UNION SELECT 'cards.update.all' UNION SELECT 'cards.delete' UNION SELECT 'cards.delete.all' UNION SELECT 'cards.approve' UNION SELECT 'cards.approve.all' UNION SELECT 'cards.pin' UNION SELECT 'cards.pin.all'
  UNION SELECT 'comments.read' UNION SELECT 'comments.read.all' UNION SELECT 'comments.create' UNION SELECT 'comments.update' UNION SELECT 'comments.update.all' UNION SELECT 'comments.delete' UNION SELECT 'comments.delete.all'
  UNION SELECT 'tags.read' UNION SELECT 'tags.read.all' UNION SELECT 'tags.create' UNION SELECT 'tags.update' UNION SELECT 'tags.update.all' UNION SELECT 'tags.delete' UNION SELECT 'tags.delete.all'
  UNION SELECT 'users.read' UNION SELECT 'users.read.all' UNION SELECT 'users.update' UNION SELECT 'users.update.all' UNION SELECT 'users.delete' UNION SELECT 'users.delete.all'
  UNION SELECT 'files.upload' UNION SELECT 'files.read' UNION SELECT 'files.read.all' UNION SELECT 'files.delete' UNION SELECT 'files.delete.all'
  UNION SELECT 'likes.create' UNION SELECT 'likes.read' UNION SELECT 'likes.delete'
  UNION SELECT 'roles.read' UNION SELECT 'roles.create' UNION SELECT 'roles.update' UNION SELECT 'roles.delete' UNION SELECT 'roles.assign'
  UNION SELECT 'permissions.read'
  UNION SELECT 'config.read' UNION SELECT 'config.update' UNION SELECT 'config.init' UNION SELECT 'config.reload' UNION SELECT 'config.register' UNION SELECT 'config.deleteKey'
  UNION SELECT 'storage.read' UNION SELECT 'storage.install' UNION SELECT 'storage.test'
  UNION SELECT 'sender.read' UNION SELECT 'sender.install' UNION SELECT 'sender.test'
  UNION SELECT 'captcha.read' UNION SELECT 'captcha.install'
  UNION SELECT 'theme.read' UNION SELECT 'theme.update' UNION SELECT 'theme.upload' UNION SELECT 'theme.delete' UNION SELECT 'theme.freeze' UNION SELECT 'theme.activate'
  UNION SELECT 'dashboard.read'
  UNION SELECT 'system.update'
  UNION SELECT 'session.login' UNION SELECT 'session.register' UNION SELECT 'session.guest' UNION SELECT 'session.logout' UNION SELECT 'session.check' UNION SELECT 'session.captcha'
) c
WHERE NOT EXISTS (SELECT 1 FROM `role_capabilities` rc WHERE rc.role_id = 1 AND rc.capability = c.capability);
SELECT CONCAT('Root capabilities seeded: ', ROW_COUNT(), ' rows inserted') AS status;

-- Admin (id=2): subset of capabilities
INSERT INTO `role_capabilities` (`role_id`, `capability`)
SELECT 2, c.capability FROM (
  SELECT 'cards.read' AS capability UNION SELECT 'cards.read.all' UNION SELECT 'cards.create' UNION SELECT 'cards.update.all' UNION SELECT 'cards.delete.all' UNION SELECT 'cards.approve' UNION SELECT 'cards.approve.all' UNION SELECT 'cards.pin.all'
  UNION SELECT 'comments.read' UNION SELECT 'comments.read.all' UNION SELECT 'comments.update.all' UNION SELECT 'comments.delete.all'
  UNION SELECT 'tags.read' UNION SELECT 'tags.read.all' UNION SELECT 'tags.create' UNION SELECT 'tags.update.all' UNION SELECT 'tags.delete.all'
  UNION SELECT 'users.read' UNION SELECT 'users.read.all' UNION SELECT 'users.update.all' UNION SELECT 'users.delete.all'
  UNION SELECT 'files.upload' UNION SELECT 'files.read' UNION SELECT 'files.read.all' UNION SELECT 'files.delete' UNION SELECT 'files.delete.all'
  UNION SELECT 'likes.create' UNION SELECT 'likes.read' UNION SELECT 'likes.delete'
  UNION SELECT 'dashboard.read'
  UNION SELECT 'config.read' UNION SELECT 'config.update' UNION SELECT 'config.init' UNION SELECT 'config.reload' UNION SELECT 'config.register' UNION SELECT 'config.deleteKey'
  UNION SELECT 'storage.read' UNION SELECT 'storage.install' UNION SELECT 'storage.test'
  UNION SELECT 'sender.read' UNION SELECT 'sender.install' UNION SELECT 'sender.test'
  UNION SELECT 'captcha.read' UNION SELECT 'captcha.install'
  UNION SELECT 'theme.read' UNION SELECT 'theme.update' UNION SELECT 'theme.upload' UNION SELECT 'theme.delete' UNION SELECT 'theme.freeze' UNION SELECT 'theme.activate'
  UNION SELECT 'permissions.read'
  UNION SELECT 'roles.read' UNION SELECT 'roles.create' UNION SELECT 'roles.update' UNION SELECT 'roles.delete' UNION SELECT 'roles.assign'
) c
WHERE NOT EXISTS (SELECT 1 FROM `role_capabilities` rc WHERE rc.role_id = 2 AND rc.capability = c.capability);
SELECT CONCAT('Admin capabilities seeded: ', ROW_COUNT(), ' rows inserted') AS status;

-- User (id=3): limited user capabilities
INSERT INTO `role_capabilities` (`role_id`, `capability`)
SELECT 3, c.capability FROM (
  SELECT 'cards.read' AS capability UNION SELECT 'cards.create'
  UNION SELECT 'comments.read' UNION SELECT 'comments.create'
  UNION SELECT 'tags.read' UNION SELECT 'tags.create'
  UNION SELECT 'users.read' UNION SELECT 'users.update'
  UNION SELECT 'files.upload' UNION SELECT 'files.read'
  UNION SELECT 'likes.create' UNION SELECT 'likes.read' UNION SELECT 'likes.delete'
) c
WHERE NOT EXISTS (SELECT 1 FROM `role_capabilities` rc WHERE rc.role_id = 3 AND rc.capability = c.capability);
SELECT CONCAT('User capabilities seeded: ', ROW_COUNT(), ' rows inserted') AS status;

-- Guest (id=4): minimal read-only capabilities
INSERT INTO `role_capabilities` (`role_id`, `capability`)
SELECT 4, c.capability FROM (
  SELECT 'cards.read' AS capability
  UNION SELECT 'comments.read'
  UNION SELECT 'tags.read'
  UNION SELECT 'files.read'
  UNION SELECT 'likes.create' UNION SELECT 'likes.read' UNION SELECT 'likes.delete'
) c
WHERE NOT EXISTS (SELECT 1 FROM `role_capabilities` rc WHERE rc.role_id = 4 AND rc.capability = c.capability);
SELECT CONCAT('Guest capabilities seeded: ', ROW_COUNT(), ' rows inserted') AS status;

-- ============================================================
--  Summary
-- ============================================================
SELECT 'Migration 20260727000001 completed successfully' AS result;
