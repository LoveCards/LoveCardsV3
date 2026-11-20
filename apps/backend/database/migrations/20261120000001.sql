-- Allow supported card video MIME types on the bundled local channel.
-- Preserve administrator MIME policies that differ from the previous default.
-- @lc-phase expand
UPDATE `configs`
SET `value` = 'image/jpeg,image/png,image/gif,image/webp,application/pdf,video/mp4,video/webm,video/quicktime,video/x-m4v',
    `updated_at` = NOW()
WHERE `group` = 'storage_local'
  AND `key` = 'allow_mime_types'
  AND `value` = 'image/jpeg,image/png,image/gif,image/webp,application/pdf';
