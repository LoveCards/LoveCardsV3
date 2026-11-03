<?php
declare(strict_types=1);

namespace app\modules\Platform\Auth\Public;

use app\api\ApiException;
use app\modules\Platform\Auth\Contract\SessionRegistry;
use app\modules\Platform\Auth\Contract\UserRepository;
use think\facade\Cache;

/** 只允许读取指定卡片媒体的短期凭据，不能用于 API 登录。 */
final class MediaGrants
{
    public const TTL = 300;

    public function __construct(private SessionRegistry $sessions, private UserRepository $users) {}

    public function issue(int $userId, string $sessionId, int $cardId): string
    {
        $session = $this->sessions->findForUserByPublicId($userId, $sessionId);
        $hash = (string) ($session['jti_hash'] ?? '');
        if ($hash === '' || $this->sessions->findActive($hash, new \DateTimeImmutable()) === null) {
            throw ApiException::unauthorized('请重新登录');
        }
        $token = bin2hex(random_bytes(32));
        $saved = Cache::set('media_grant:' . hash('sha256', $token), [
            'user_id' => $userId, 'jti_hash' => $hash, 'card_id' => $cardId, 'expires_at' => time() + self::TTL,
        ], self::TTL);
        if (!$saved) throw ApiException::error('暂时无法创建媒体访问凭据');
        return $token;
    }

    public function viewer(string $token, int $cardId): int
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $token) !== 1) throw ApiException::notFound('媒体不存在');
        $grant = Cache::get('media_grant:' . hash('sha256', $token));
        if (!is_array($grant) || ($grant['card_id'] ?? 0) !== $cardId || ($grant['expires_at'] ?? 0) <= time()) {
            throw ApiException::notFound('媒体不存在');
        }
        $session = $this->sessions->findActive((string) $grant['jti_hash'], new \DateTimeImmutable());
        $userId = (int) $grant['user_id'];
        $user = $this->users->findById($userId);
        if ($session === null || $session->userId() !== $userId || $user === null || $user->status() !== 0) throw ApiException::notFound('媒体不存在');
        return $userId;
    }
}
