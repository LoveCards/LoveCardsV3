-- 追加运行时通信配置能力（Phase 1 控制面）。幂等，可对已装实例重放。
INSERT INTO `capabilities` (`capability`, `description`, `risk_level`)
SELECT source.capability, source.description, source.risk_level
FROM (
  SELECT 'operations.runtime.configure' capability, '变更运行时通信模式与运行参数' description, 3 risk_level
) source
LEFT JOIN `capabilities` existing ON existing.capability = source.capability
WHERE existing.capability IS NULL;
