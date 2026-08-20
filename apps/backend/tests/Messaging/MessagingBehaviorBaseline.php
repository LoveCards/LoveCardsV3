<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/app/api/ApiException.php';
require $root . '/app/modules/Platform/Messaging/Contract/MessagingRepository.php';
require $root . '/app/modules/Platform/Messaging/Contract/MessagePermission.php';
require $root . '/app/modules/Platform/Messaging/Contract/MessageNotifier.php';
require $root . '/app/shared/Transaction/TransactionManager.php';
require $root . '/app/modules/Platform/Messaging/Application/Messaging.php';

use app\modules\Platform\Messaging\Application\Messaging;
use app\modules\Platform\Messaging\Contract\MessageNotifier;
use app\modules\Platform\Messaging\Contract\MessagePermission;
use app\modules\Platform\Messaging\Contract\MessagingRepository;
use app\shared\Transaction\TransactionManager;

final class FakeMessagingRepository implements MessagingRepository
{
    public array $messages = [];
    public int $readThrough = 0;
    public function conversationsForUser(int $userId): array { return [['id' => 9, 'unread_count' => 2]]; }
    public function messagesForUser(int $userId, int $conversationId, ?string $cursor, int $limit): array { return ['items' => [['id' => 12]], 'next_cursor' => null, 'has_more' => false]; }
    public function findMessageIdByClientId(int $senderId, int $recipientId, string $clientMessageId): ?int { return $this->messages[$clientMessageId] ?? null; }
    public function findOrCreateDirectConversation(int $firstUserId, int $secondUserId, DateTimeImmutable $at): int { return 9; }
    public function createMessage(int $conversationId, int $senderId, string $clientMessageId, string $body, DateTimeImmutable $at): int { return $this->messages[$clientMessageId] = 21; }
    public function touchConversation(int $conversationId, DateTimeImmutable $at): void {}
    public function markReadThrough(int $userId, int $conversationId, int $messageId): void { $this->readThrough = $messageId; }
}

final class FakeMessagePermission implements MessagePermission
{
    public function __construct(public bool $allowed = true) {}
    public function canSend(int $senderId, int $recipientId): bool { return $this->allowed; }
}

final class FakeMessageNotifier implements MessageNotifier
{
    public int $calls = 0;
    public bool $calledInsideTransaction = false;
    public function __construct(private FakeMessageTransaction $transactions) {}
    public function messageSent(int $recipientId, int $senderId, int $conversationId, int $messageId): void
    {
        $this->calls++;
        $this->calledInsideTransaction = $this->transactions->active;
    }
}

final class FakeMessageTransaction implements TransactionManager
{
    public bool $active = false;
    public function run(callable $callback): mixed
    {
        $this->active = true;
        try { return $callback(); }
        finally { $this->active = false; }
    }
}

$assert = static function (bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); };
$repository = new FakeMessagingRepository();
$permission = new FakeMessagePermission();
$transactions = new FakeMessageTransaction();
$notifier = new FakeMessageNotifier($transactions);
$messaging = new Messaging($repository, $permission, $notifier, $transactions);

$assert($messaging->send(1, 2, ' hello ', 'client-1') === 21, 'message should be created');
$assert($messaging->send(1, 2, 'hello', 'client-1') === 21, 'same client id should be idempotent');
$assert($notifier->calls === 1, 'idempotent retry must not duplicate notification');
$assert($notifier->calledInsideTransaction, 'message notification Outbox write must share the message transaction');
$messaging->messages(1, 9);
$assert($repository->readThrough === 12, 'opening messages should advance read cursor');

$permission->allowed = false;
$denied = false;
try { $messaging->send(1, 2, 'blocked'); } catch (\app\api\ApiException $exception) { $denied = $exception->getHttpStatus() === 403; }
$assert($denied, 'safety permission must block message send');

$notifierSource = file_get_contents($root . '/app/modules/Platform/Messaging/Infrastructure/NotificationMessageNotifier.php');
$assert(str_contains($notifierSource, "'message.sent'"), 'production notifier must publish message.sent');
$assert(!str_contains($notifierSource, 'Notifications::direct'), 'message send must not write notifications synchronously');

$repositorySource = file_get_contents($root . '/app/modules/Platform/Messaging/Infrastructure/ThinkDbMessagingRepository.php');
$insertStart = strpos($repositorySource, "Db::table('conversations')->insertGetId");
$memberStart = strpos($repositorySource, "Db::table('conversation_members')->insertAll");
$catchStart = strpos($repositorySource, '} catch (\\Throwable $exception)', $insertStart ?: 0);
$assert($insertStart !== false && $catchStart !== false && $memberStart !== false && $memberStart > $catchStart, 'member insert failures must not be treated as direct-key races');

fwrite(STDOUT, "PASS messaging behavior baseline\n");
