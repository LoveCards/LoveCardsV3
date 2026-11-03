-- ResourceRef compatibility migration.
-- Resource identifiers are public stable strings, not necessarily integers.
-- Existing numeric values are widened to VARCHAR without data loss.

ALTER TABLE `library_items`
    MODIFY COLUMN `resource_id` VARCHAR(128) NOT NULL;
ALTER TABLE `view_history`
    MODIFY COLUMN `resource_id` VARCHAR(128) NOT NULL;
ALTER TABLE `notifications`
    MODIFY COLUMN `resource_id` VARCHAR(128) DEFAULT NULL;
ALTER TABLE `reports`
    MODIFY COLUMN `target_id` VARCHAR(128) NOT NULL;
