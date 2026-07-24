# Auth 行为基线

本文件记录 Auth 垂直切片开始前必须保持的行为。可执行基线位于
`apps/backend/tests/Auth/BehaviorBaseline.php`，通过以下命令运行：

```bash
cd apps/backend
composer test:auth
```

## 已锁定行为

| 场景 | 当前行为 |
| --- | --- |
| 用户不存在 | 登录失败，业务码 `1001` |
| 用户状态不是 `0` 或 `2` | 登录失败，HTTP 403，业务码 `1002` |
| 密码不匹配 | 登录失败，业务码 `1003` |
| 登录成功 | Token payload 包含用户 `uid` |
| 注册密码为空 | 注册失败，业务码 `9002` |
| 邮箱或手机号已存在 | 注册失败，业务码 `1004` |
| 注册成功 | 分配普通用户角色，状态为 `0` |
| 同一 IP 在同一小时重复访客登录 | 复用既有访客账号 |
| 访客账号不存在 | 创建访客角色账号，状态为 `0` |
| 无 Token 且访客模式关闭 | HTTP 401，消息为“请先登入” |
| 无 Token 且访客模式开启 | 注入 `uid=0`、访客角色及访客能力 |
| 无效 Token 且访客模式关闭 | HTTP 401，并保留 Token 校验错误消息 |
| 无效 Token 且访客模式开启 | 降级为访客上下文 |
| Token 对应用户不存在 | HTTP 401，消息为“用户不存在” |
| 认证成功 | 注入包含 uid、用户、角色、能力及续期 Token 的 `AuthContext` |
| 访客认证 | 注入 `isVisitor=true` 的 `AuthContext` |
| capability 任一匹配 | 放行并将能力列表注入请求 |
| capability 全部不匹配 | HTTP 403，消息为“权限不足” |

## 续期契约

过期 Token 只有在其续期凭据仍存在时才能续期。续期成功返回原认证数据和新 Token：

```php
['uid' => $uid, '_new_token' => $newToken]
```

续期会消费旧凭据，并由中间件在响应的 `X-New-Token` Header 中返回新 Token。续期凭据的
缓存时间为 Token 有效期加配置的续期宽限期；宽限期结束后，过期 Token 返回“token已失效”。
该行为由 `tests/Auth/JwtRenewal.php` 独立覆盖。
