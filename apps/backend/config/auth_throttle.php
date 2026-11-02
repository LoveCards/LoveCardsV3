<?php
// 登录与访客铸造节流阈值（计数存 Cache，见 app/api/middleware/LoginThrottle）。
// 通过环境变量覆盖：AUTH_THROTTLE_WINDOW / AUTH_THROTTLE_ACCOUNT_FAIL_LIMIT 等。

return [
    // 失败计数窗口（秒）
    'window' => (int) env('auth_throttle.window', 600),
    // 同 (IP, 账号) 允许的连续口令失败次数，达到后窗口内连正确口令也拒绝
    'account_fail_limit' => (int) env('auth_throttle.account_fail_limit', 5),
    // 同 IP 窗口内失败上限（兜底跨账号爆破）。
    // 注意：反代之后所有访客共享入口 IP（取决于 nginx X-Forwarded-For 透传），
    // 真实用户量大的站点应按出口 IP 规模上调，避免 CGNAT 可用性误伤。
    'ip_fail_limit' => (int) env('auth_throttle.ip_fail_limit', 30),
    // 每 IP 每小时可铸造的访客身份数（同上，反代场景需按规模上调）
    'guest_per_ip_hour' => (int) env('auth_throttle.guest_per_ip_hour', 120),
    // 访客限额窗口（秒）
    'guest_window' => (int) env('auth_throttle.guest_window', 3600),
];
