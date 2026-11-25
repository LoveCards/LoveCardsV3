<?php

namespace app\modules\Platform\Captcha\Infrastructure\Driver;

use app\api\ApiException;
use app\modules\Platform\Sender\Public\Sender;
use app\shared\Infrastructure\Cache\CacheManager;
use app\shared\Infrastructure\Concurrency\NamedLock;

class CodeDriver extends AbstractDriver
{
    private const MAX_ATTEMPTS = 5;

    public static function type(): string
    {
        return 'code';
    }

    public static function meta(): array
    {
        return [
            'slug' => 'smtp_code',
            'type' => 'code',
            'name' => '邮箱/短信验证码',
            'icon' => 'mdi-email-check-outline',
            'fields' => [],
        ];
    }

    public function generate(array $params): array
    {
        $to = $params['to'];
        $key = $this->key($to, $params['scene'] ?? 'default');
        $ttl = $params['ttl'] ?? 300;
        $channel = $params['channel'] ?? 'smtp';

        return NamedLock::run($key, function () use ($params, $to, $key, $ttl, $channel): array {
            $previous = CacheManager::get('captcha', $key);
            $wait = (int) ($previous['resend_at'] ?? 0) - time();
            if ($wait > 0) {
                throw ApiException::tooManyRequests('验证码已发送，请稍后重试', 'captcha_rate_limited', $wait);
            }
            if (isset($params['ip'])) {
                $this->reserveSend('ip:' . $params['ip'], 10, 600);
                $this->reserveSend('recipient:' . strtolower($to), 5, 3600);
            }

            $code = $channel === 'sms' ? sprintf('%06d', random_int(0, 999999)) : $this->createCode(6);
            $result = Sender::code($channel, $to, $code, (int) ceil($ttl / 60));
            if (!$result->success) return ['status' => false, 'msg' => '验证码发送失败，请稍后重试'];

            $now = time();
            $this->save($key, [
                'hash' => password_hash($code, PASSWORD_DEFAULT),
                'expires_at' => $now + $ttl,
                'resend_at' => $now + ($params['resend_after'] ?? $ttl),
                'attempts' => 0,
            ], $ttl);
            return ['status' => true, 'msg' => '验证码已发送'];
        });
    }

    public function verify(array $params): bool
    {
        return $this->verifyDetailed($params) === 'valid';
    }

    /** @return 'valid'|'expired'|'invalid' */
    public function verifyDetailed(array $params): string
    {
        $key = $this->key($params['key'], $params['scene'] ?? 'default');
        $code = strtoupper(trim((string) $params['code']));
        return NamedLock::run($key, function () use ($key, $code): string {
            $state = CacheManager::get('captcha', $key);
            $remaining = (int) ($state['expires_at'] ?? 0) - time();
            if ($remaining <= 0 || empty($state['hash']) || $state['attempts'] >= self::MAX_ATTEMPTS) return 'expired';

            $matches = password_verify($code, $state['hash']);
            if ($matches) $state['hash'] = null;
            else $state['attempts']++;
            $this->save($key, $state, $remaining);
            return $matches ? 'valid' : 'invalid';
        });
    }

    public function delete(string $key, string $scene = 'default'): bool
    {
        $cacheKey = $this->key($key, $scene);
        return NamedLock::run($cacheKey, static function () use ($cacheKey): bool {
            CacheManager::delete($cacheKey);
            return true;
        });
    }

    private function key(string $recipient, string $scene): string
    {
        return 'verification-code:' . hash('sha256', $scene . ':' . strtolower($recipient));
    }

    private function reserveSend(string $subject, int $limit, int $window): void
    {
        $key = 'verification-quota:' . hash('sha256', $subject);
        NamedLock::run($key, function () use ($key, $limit, $window): void {
            $state = CacheManager::get('captcha', $key);
            $now = time();
            if ((int) ($state['until'] ?? 0) <= $now) $state = ['until' => $now + $window, 'count' => 0];
            if ($state['count'] >= $limit) {
                throw ApiException::tooManyRequests('验证码请求过于频繁，请稍后重试', 'code-send-quota', $state['until'] - $now);
            }
            $state['count']++;
            $this->save($key, $state, $state['until'] - $now);
        });
    }

    private function save(string $key, array $state, int $ttl): void
    {
        CacheManager::set('captcha', $key, $state, $ttl);
        if (CacheManager::get('captcha', $key) !== $state) {
            throw ApiException::capabilityUnavailable('验证码服务暂时不可用', 'verification-state-unavailable');
        }
    }

    private function createCode(int $length): string
    {
        $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $code;
    }
}
