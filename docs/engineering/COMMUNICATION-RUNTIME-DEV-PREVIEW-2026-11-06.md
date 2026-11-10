# 通信运行时 dev 预览交接（2026-11-06）

> **历史验收快照，不能代表当前 dev 状态。** 本文测试了旧的自动回退行为；当前事件处理与通信契约以 [EVENTING.md](EVENTING.md)、[RUNTIME-ARCHITECTURE.md](RUNTIME-ARCHITECTURE.md) 和 [FRONTEND-SIGNALS.md](FRONTEND-SIGNALS.md) 为准。运行中故障不自动改通信或事件消费模式。

## 入口与边界

- 主题：`http://45.207.204.11:20092/`
- 管理端：`http://45.207.204.11:20092/admin/login`
- 独立预览 worktree：`/www/wwwroot/testtt_chizg_cn/LoveCardsV3-runtime-preview`
- `20092` 是预览工作树内独立 Nginx 进程，分发主题静态文件、`/admin/`、`/api/` 与 `/stream`。未修改宝塔主 Nginx 配置或重启原 dev 服务。
- 预览后端 `127.0.0.1:6002`、Gateway `127.0.0.1:20091`、管理端 `127.0.0.1:20093` 仅供本机反代使用；Event Worker 单独运行。原 `6001/20080/20088/20089` 未重启，生产未触碰。
- 预览 `.env` 的 `LC_REALTIME_GATEWAY_PUBLIC_URL` 指向 `http://45.207.204.11:20092`。密钥、密码和 token 不写入本文件或仓库。

## 验收结果

| 场景 | 结果 |
| --- | --- |
| Polling 默认模式 | 共享 dev 配置已恢复 `desired=polling / effective=polling / ready`，revision 7；原 `20088` 入口亦返回 polling/ready |
| Realtime 健康 | 临时切至 realtime 后，Worker 与 Gateway 均 ready；管理端运行状态页显示 realtime/ready |
| 故障回退 | 分别停止预览 Gateway、Worker，均得到 polling/degraded；各自恢复后回到 realtime/ready |
| 一次性 ticket | 首次 `/stream` 返回 200，同一 ticket 重放返回 401；有效期 60 秒 |
| 浏览器重连 | 预览 Gateway 重启后，主题重新取得 ticket 并建立第二条 200 SSE 连接；无页面脚本错误 |
| 私信与通知 | 两个 dev 用户间发送私信、关注，浏览器分别收到 `messages`、`notifications` 信号；通知写库回执缺口已修复 |
| 通知策略 | 关闭 `message.sent` 通知后，私信仍触发 `messages` 信号且不生成新消息通知；策略已恢复 7 条全启用，revision 12 |
| 并发与权限 | 两个预览后端并发提交相同策略版本得到一次 200、一次 `409/9016`；锁定规则修改 `422/9010` 且版本不变；访客修改策略 `403/1101` |
| 浏览器页面 | 主题深链接登录、管理端运行状态、通知投递、通知策略页均可打开；相关 API 返回 200，无页面脚本错误 |
| CI | PR #50 提交 `2266d1a7` 的 backend、PHP 8.1、PHP 8.2、frontend、integration 五项全绿 |

## 交付状态与复核

- PR #50：`fix/runtime-closeout-candidate` → `feat/runtime-control-plane`（PR #48）；未合并。
- 预览主题单独以 `VITE_DATA_MODE=api VITE_API_URL=/api vite build --base=/` 构建，确保 `/auth/login` 等深链接的资源路径正确。正式主题打包流程不受影响。
- 预览管理端已在独立 worktree 构建，`/api/` 经 20092 反代到 6002；主题和管理端使用同一预览 API。
- 当前预览默认 Polling，避免改变共享 dev 的既有基线。若需演示 Realtime，可在预览管理端“系统运维 → 运行状态”切换；演示结束后恢复 Polling。Gateway 与 Worker 保持运行。
- Scheduler 探测为 stale，是 advisory 能力，不阻断 Realtime。
- `.preview/` 内的 Nginx 配置、日志与构建包为服务器本地预览资产，未入 Git；独立预览进程尚未由 Supervisor/systemd 托管，服务器重启后需重新启动。生产部署和进程托管仍属于后续部署任务。
