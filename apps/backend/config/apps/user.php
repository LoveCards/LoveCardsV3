<?php
return [
    'captcha' => ['type' => 'bool', 'default' => false, 'description' => '注册邮箱验证'],
    'registration_mode' => ['type' => 'string', 'default' => '', 'description' => '允许的注册方式'],
    'registration_sms_verification' => ['type' => 'bool', 'default' => false, 'description' => '注册短信验证'],
];
