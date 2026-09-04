<?php

declare(strict_types=1);

namespace app\modules\Platform\Notifications\Infrastructure;

use app\modules\Platform\Eventing\Public\DeliveryContext;
use app\modules\Platform\Eventing\Public\EventEnvelope;
use app\modules\Platform\Eventing\Public\EventHandler;
use app\modules\Platform\Notifications\Public\Notifications;
use app\shared\Resource\ResourceRef;
use think\facade\Db;

/** Projects one visible comment into its primary and mention notifications. */
final class ContentCommentPublishedNotificationHandler implements EventHandler
{
    public function handle(EventEnvelope $event, DeliveryContext $context): void
    {
        $payload = $event->payload();
        $actorId = (int) ($payload['actor_id'] ?? 0);
        $cardId = (int) ($payload['card_id'] ?? 0);
        $commentId = (int) ($payload['comment_id'] ?? 0);
        $parentId = (int) ($payload['parent_comment_id'] ?? 0);
        $cardAuthorId = (int) ($payload['card_author_id'] ?? 0);
        $parentAuthorId = (int) ($payload['parent_author_id'] ?? 0);
        if ($actorId < 1 || $cardId < 1 || $commentId < 1 || $cardAuthorId < 1
            || ($parentId > 0 && $parentAuthorId < 1)) {
            throw new \UnexpectedValueException('content.comment.published payload 不完整');
        }

        $mentions = $this->mentions($payload['mentions'] ?? []);
        $primaryRecipientId = $parentId > 0 ? $parentAuthorId : $cardAuthorId;
        $candidateIds = array_values(array_unique(array_merge(
            [$primaryRecipientId],
            array_column($mentions, 'user_id')
        )));
        $active = $this->activeRecipients($candidateIds);
        $resource = new ResourceRef('comment', (string) $commentId);

        if ($primaryRecipientId !== $actorId && isset($active[$primaryRecipientId])) {
            if ($parentId > 0) {
                Notifications::direct(
                    $primaryRecipientId,
                    "reply:{$commentId}:parent:{$parentId}",
                    'content.replied',
                    $actorId,
                    $resource,
                    ['card_id' => $cardId, 'comment_id' => $commentId, 'parent_comment_id' => $parentId]
                );
            } else {
                Notifications::direct(
                    $primaryRecipientId,
                    "comment:{$commentId}:card-owner",
                    'content.commented',
                    $actorId,
                    $resource,
                    ['card_id' => $cardId, 'comment_id' => $commentId]
                );
            }
        }

        foreach ($mentions as $mention) {
            $recipientId = $mention['user_id'];
            // The primary reply/comment notification is more specific than a mention.
            if ($recipientId === $actorId || $recipientId === $primaryRecipientId || !isset($active[$recipientId])) continue;
            Notifications::direct(
                $recipientId,
                "mention:comment:{$commentId}:user:{$recipientId}",
                'content.mentioned',
                $actorId,
                $resource,
                [
                    'card_id' => $cardId,
                    'comment_id' => $commentId,
                    'mentioned_username' => $mention['username'],
                ]
            );
        }
    }

    /** @return array<int, array{user_id:int,username:string}> */
    private function mentions($value): array
    {
        if (!is_array($value)) return [];
        $mentions = [];
        foreach ($value as $row) {
            if (!is_array($row)) continue;
            $userId = (int) ($row['user_id'] ?? 0);
            $username = trim((string) ($row['username'] ?? ''));
            if ($userId > 0 && $username !== '') $mentions[$userId] = ['user_id' => $userId, 'username' => $username];
        }
        return array_values($mentions);
    }

    /** @return array<int, true> */
    private function activeRecipients(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
        if ($ids === []) return [];
        return array_fill_keys(Db::table('users')
            ->whereIn('id', $ids)
            ->where('status', 0)
            ->whereNull('deleted_at')
            ->column('id'), true);
    }
}
