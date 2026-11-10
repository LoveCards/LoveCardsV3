<?php
declare(strict_types=1);

namespace app\modules\Platform\Messaging\Application;

use app\api\ApiException;
use app\modules\Platform\Messaging\Contract\MessageNotifier;
use app\modules\Platform\Messaging\Contract\MessagePermission;
use app\modules\Platform\Messaging\Contract\MessagingRepository;
use app\shared\Transaction\TransactionManager;

final class Messaging
{
    public function __construct(
        private MessagingRepository $repository,
        private MessagePermission $permission,
        private MessageNotifier $notifier,
        private TransactionManager $transactions
    ) {}

    public function conversations(int $userId): array
    {
        $this->assertUser($userId);
        return $this->repository->conversationsForUser($userId);
    }

    public function messages(int $userId, int $conversationId, ?string $cursor = null, int $limit = 30): array
    {
        $this->assertUser($userId);
        $result = $this->repository->messagesForUser($userId, $conversationId, $cursor, max(1, min(100, $limit)));
        $items = $result['items'];
        if ($items !== []) {
            $this->transactions->run(function () use ($userId, $conversationId, $items): void {
                $messageId = (int) $items[0]['id'];
                if ($this->repository->markReadThrough($userId, $conversationId, $messageId)) {
                    $this->notifier->messageRead($userId, $conversationId, $messageId);
                }
            });
        }
        return $result;
    }

    /** @return array{id:int,conversation_id:int} */
    public function send(int $userId, int $recipientId, string $body, ?string $clientMessageId = null): array
    {
        $this->assertUser($userId);
        if ($recipientId <= 0 || $recipientId === $userId) throw ApiException::badRequest('收件人无效');
        $body = trim($body);
        if ($body === '') throw ApiException::badRequest('消息内容不能为空');
        if (mb_strlen($body) > 5000) throw ApiException::badRequest('消息内容过长');
        if (!$this->permission->canSend($userId, $recipientId)) throw ApiException::forbidden('当前无法向该用户发送消息');

        $clientMessageId = trim((string) ($clientMessageId ?? ''));
        if ($clientMessageId === '') $clientMessageId = bin2hex(random_bytes(16));
        if (strlen($clientMessageId) > 128) throw ApiException::badRequest('消息幂等标识过长');

        return $this->transactions->run(function () use ($userId, $recipientId, $body, $clientMessageId): array {
            $existing = $this->repository->findMessageByClientId($userId, $recipientId, $clientMessageId);
            if ($existing !== null) return $existing;
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $conversationId = $this->repository->findOrCreateDirectConversation($userId, $recipientId, $now);
            try {
                $messageId = $this->repository->createMessage($conversationId, $userId, $clientMessageId, $body, $now);
            } catch (\Throwable $exception) {
                $existing = $this->repository->findMessageByClientId($userId, $recipientId, $clientMessageId);
                if ($existing !== null) return $existing;
                throw $exception;
            }
            $this->repository->touchConversation($conversationId, $now);
            $this->notifier->messageSent($recipientId, $userId, $conversationId, $messageId);
            return ['id' => $messageId, 'conversation_id' => $conversationId];
        });
    }

    private function assertUser(int $userId): void
    {
        if ($userId <= 0) throw ApiException::unauthorized('请先登入');
    }
}
