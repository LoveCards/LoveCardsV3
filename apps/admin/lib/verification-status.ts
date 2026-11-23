import type { AuthPolicy } from '@lovecards/sdk'

const emailStatusLabels: Record<string, string> = {
  ready: '邮件渠道配置已就绪，外部投递待测试',
  code_disabled: '验证码服务已关闭',
  mail_channel_required: '当前选择短信渠道，邮件验证码不可用',
  code_driver_unavailable: '验证码驱动不可用',
  sender_missing: '尚未配置 SMTP 邮件渠道',
  sender_incompatible: '当前默认渠道无法发送邮件验证码',
  sender_runtime_unavailable: 'SMTP 发送组件不可用',
  sender_configuration_incomplete: 'SMTP 渠道缺少服务器、有效端口或发件邮箱',
}

export function emailVerificationStatus(policy: AuthPolicy | null): string {
  const registration = policy?.registration
  if (!registration) return '邮件验证码状态暂不可用'
  if (registration.email_verification_status) return emailStatusLabels[registration.email_verification_status] ?? '邮件验证码状态暂不可用'
  return registration.email_verification_available ? '邮件验证码可用，外部投递待测试' : '邮件验证码不可用'
}
