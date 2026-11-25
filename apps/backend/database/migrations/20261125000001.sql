-- 空注册方式保留旧站的有效策略，第一次保存时写入明确选择。
INSERT INTO `configs` (`group`, `key`, `value`, `type`, `description`, `created_at`, `updated_at`)
VALUES ('user', 'registration_mode', '', 'string', '允许的注册方式', UTC_TIMESTAMP(), UTC_TIMESTAMP()),
       ('user', 'registration_sms_verification', '0', 'bool', '注册短信验证', UTC_TIMESTAMP(), UTC_TIMESTAMP()),
       ('user', '__revision', '1', 'int', '内部配置组版本', UTC_TIMESTAMP(), UTC_TIMESTAMP())
ON DUPLICATE KEY UPDATE `key` = `configs`.`key`;
