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
                    ['value' => '',    'label' => '默认（SSL）'],
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

    public function isConfigured(): bool
    {
        return trim((string) ($this->config['host'] ?? '')) !== ''
            && (int) ($this->config['port'] ?? 0) > 0
            && filter_var($this->config['addr'] ?? '', FILTER_VALIDATE_EMAIL) !== false
            && (string) ($this->config['pass'] ?? '') !== '';
    }

    private function sendCode(Message $message): SendResult
    {
        $subject = '验证码';
        $body = $this->renderTemplate('code', [
            'code'   => $message->code,
            'expire' => $message->expire,
        ]);
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

    private function doSend(string|array $to, string $subject, string $body): SendResult
    {
        if (!$this->isConfigured()) return SendResult::fail('smtp', '邮件渠道配置不完整');
        try {
            $mailer = new PHPMailer(true);
            $mailer->isSMTP();
            $mailer->Host = $this->config['host'];
            $mailer->Port = (int) $this->config['port'];
            $mailer->SMTPAuth = true;
            $mailer->Username = $this->config['addr'];
            $mailer->Password = $this->config['pass'];
            $mailer->SMTPSecure = ($this->config['security'] ?? '') ?: PHPMailer::ENCRYPTION_SMTPS;
            $mailer->Timeout = 5;
            $mailer->getSMTPInstance()->Timelimit = 5;
            $mailer->CharSet = PHPMailer::CHARSET_UTF8;
            $mailer->setFrom($this->config['addr'], (string) ($this->config['name'] ?? ''));
            foreach ((array) $to as $address) $mailer->addAddress($address);
            $mailer->isHTML();
            $mailer->Subject = $subject;
            $mailer->Body = $body;
            $mailer->send();
            return SendResult::ok('smtp');
        } catch (\PHPMailer\PHPMailer\Exception) {
            return SendResult::fail('smtp', '邮件发送失败，请检查配置与服务连通性');
        }
    }
}
