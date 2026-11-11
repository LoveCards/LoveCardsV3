-- 退役未生效的通信参数。历史迁移保持原 checksum；仅清理旧 JSON 键。
-- revision 递增使升级前加载的管理表单必须重新读取，迁移重试不重复递增。
UPDATE `runtime_config_state`
SET `settings_json` = JSON_REMOVE(`settings_json`, '$.polling_backoff_factor', '$.polling_max_interval_seconds', '$.require_supervisor'),
    `revision` = `revision` + 1,
    `updated_at` = UTC_TIMESTAMP(6)
WHERE JSON_VALID(`settings_json`)
  AND JSON_CONTAINS_PATH(`settings_json`, 'one', '$.polling_backoff_factor', '$.polling_max_interval_seconds', '$.require_supervisor');
