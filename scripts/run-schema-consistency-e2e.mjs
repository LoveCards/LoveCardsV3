import { randomBytes } from 'node:crypto'
import process from 'node:process'

// 最终结构必须经过获批的 S11 Contract。复用签名包升级 E2E，
// 由 UpgradeCoordinator 执行；该运行器创建并清理两个隔离数据库。
if (process.env.LOVECARDS_SCHEMA_E2E_ISOLATED !== '1') {
  throw new Error('Refusing schema E2E: set LOVECARDS_SCHEMA_E2E_ISOLATED=1')
}
process.env.LOVECARDS_UPGRADE_E2E_ISOLATED = '1'
process.env.LOVECARDS_E2E_ADMIN_ACCOUNT ||= 'e2e_schema_admin'
process.env.LOVECARDS_E2E_ADMIN_PASSWORD ||= randomBytes(16).toString('hex')

await import('./run-upgrade-e2e.mjs')
