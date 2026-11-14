<?php
declare(strict_types=1);

namespace app\modules\Platform\Auth\Public;

use app\modules\Platform\Auth\Contract\SessionRegistry;
use app\modules\Platform\Auth\Contract\UserRepository;
use app\modules\Platform\User\Public\UserStatus;
use DateTimeImmutable;

/** 对外只提供会话剩余有效期，不泄露 JTI、会话记录或 Token。 */
final class SessionAccess
{
    public function __construct(private SessionRegistry $sessions, private UserRepository $users) {}

    public function remainingSeconds(int $uid, string $publicId): int
    {
        if ($uid < 1 || preg_match('/^[a-f0-9]{32}$/D', $publicId) !== 1) return 0;
        $row = $this->sessions->findForUserByPublicId($uid, $publicId);
        if ($row === null || !is_string($row['jti_hash'] ?? null)) return 0;
        $now = new DateTimeImmutable('now', new \DateTimeZone('UTC'));
        // 复用鉴权的撤销、安全版本、到期和软删除检查。
        $session = $this->sessions->findActive($row['jti_hash'], $now);
        if ($session === null || $session->userId() !== $uid || $session->publicId() !== $publicId
            || !$session->isActiveAt($now)) return 0;
        $user = $this->users->findById($uid);
        if ($user === null || !UserStatus::canAuthenticate($user->status())) return 0;
        // 向下取整真实剩余秒数，不能因 time() 截断把授权延长近一秒。
        return max(0, (int) floor($session->tokenExpiresAt()->getTimestamp() - microtime(true)));
    }
}
