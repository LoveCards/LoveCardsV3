<?php

namespace app\modules\Platform\Sender\Infrastructure\Driver;

use PHPMailer\PHPMailer\PHPMailer;
use app\modules\Platform\Sender\Infrastructure\Driver\AbstractDriver;
use app\modules\Platform\Sender\Contract\Message;
use app\modules\Platform\Sender\Contract\SendResult;

class SmtpDriver extends AbstractDriver
{
    public static function meta(): array
    {
        return [
            'type'        => 'smtp',
            'channelType' => 'smtp',
            'name'        => 'SMTP 邮件',
            'icon'        => 'mdi-email',
            'fields'      => [
                ['key' => 'host',     'label' => 'SMTP 服务器',  'type' => 'text'],
                ['key' => 'port',     'label' => '端口',         'type' => 'number'],
                ['key' => 'addr',     'label' => '发件邮箱',     'type' => 'text'],
                ['key' => 'pass',     'label' => '邮箱密码',     'type' => 'password'],
                ['key' => 'name',     'label' => '发件人名称',   'type' => 'text'],
                ['key' => 'security', 'label' => '加密方式',     'type' => 'select', 'options' => [
                    ['value' => 'ssl', 'label' => 'SSL'],
                    ['value' => 'tls', 'label' => 'TLS'],
                    ['value' => '',    'label' => '无加密'],
                ]],
            ],
        ];
    }

    public function send(Message $message): SendResult
    {
        return $message->type === 'code'
            ? $this->sendCode($message)
            : $this->sendNotify($message);
    }

    private function sendCode(Message $message): SendResult
    {
        $subject = '验证码';
        $code = htmlspecialchars((string) $message->code, ENT_QUOTES, 'UTF-8');
        $expire = (int) $message->expire;
        $body = "<p>你的验证码为 <strong>{$code}</strong>，{$expire} 分钟内有效。</p>";
        return $this->doSend($message->to, $subject, $body);
    }

    private function sendNotify(Message $message): SendResult
    {
        $subject = $message->vars['subject'] ?? 'LoveCards 通知';
        if ($message->template) {
            $body = $this->renderTemplate($message->template, $message->vars);
        } else {
            $body = $message->body;
        }
        return $this->doSend($message->to, $subject, $body);
    }

    public function configurationStatus(): string
    {
        if (!class_exists(PHPMailer::class)) return 'sender_runtime_unavailable';
        $port = (int) ($this->config['port'] ?? 465);
        $security = (string) ($this->config['security'] ?? PHPMailer::ENCRYPTION_SMTPS);
        if (trim((string) ($this->config['host'] ?? '')) === '' || $port < 1 || $port > 65535
            || !in_array($security, ['', PHPMailer::ENCRYPTION_SMTPS, PHPMailer::ENCRYPTION_STARTTLS], true)
            || !filter_var($this->config['addr'] ?? '', FILTER_VALIDATE_EMAIL)) {
            return 'sender_configuration_incomplete';
        }
        return 'ready';
    }

    private function doSend(string|array $to, string $subject, string $body): SendResult
    {
        if ($this->configurationStatus() !== 'ready') return SendResult::fail('smtp', 'SMTP 渠道配置尚未就绪');
        try {
            $mailer = new PHPMailer(true);
            $mailer->isSMTP();
            $mailer->CharSet = PHPMailer::CHARSET_UTF8;
            $mailer->Host = (string) $this->config['host'];
            $mailer->Port = (int) ($this->config['port'] ?? 465);
            $mailer->Timeout = 10;
            $mailer->Username = (string) $this->config['addr'];
            $mailer->Password = (string) ($this->config['pass'] ?? '');
            $mailer->SMTPAuth = $mailer->Password !== '';
            $mailer->SMTPSecure = (string) ($this->config['security'] ?? PHPMailer::ENCRYPTION_SMTPS);
            $mailer->setFrom($mailer->Username, (string) ($this->config['name'] ?? ''));
            foreach ((array) $to as $recipient) $mailer->addAddress($recipient);
            $mailer->Subject = $subject;
            $mailer->isHTML(true);
            $mailer->Body = $body;
            $mailer->AltBody = strip_tags($body);
            $mailer->send();
            return SendResult::ok('smtp');
        } catch (\Throwable) {
            return SendResult::fail('smtp', 'SMTP 发送失败，请检查服务器、端口、加密设置和登录凭据');
        }
    }
}
