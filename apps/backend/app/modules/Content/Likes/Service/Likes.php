<?php

namespace app\modules\Content\Likes\Service;

use think\facade\Db;
use app\modules\Content\Infrastructure\Persistence\Model\Likes as LikesModel;
use app\modules\Content\Infrastructure\Persistence\Model\Cards as CardsModel;
use app\modules\Content\Infrastructure\Persistence\Model\Comments as CommentsModel;
use app\modules\Content\Cards\Public\CardAccess;
use app\modules\Platform\Eventing\Public\DomainEventBus;
use app\api\ApiException;

class Likes
{
    const TYPES = ['card', 'comment'];

    public static function like(string $refType, int $refId, int $uid, string $ip): int
    {
        self::assertSupportedType($refType);

        Db::startTrans();
        try {
            $target = self::likeTarget($refType, $refId, $uid);

            $exists = LikesModel::where('ref_type', $refType)
                ->where('ref_id', $refId)
                ->where('uid', $uid)
                ->find();

            if ($exists) {
                throw ApiException::badRequest('请勿重复点赞');
            }

            LikesModel::create([
                'aid' => 1,
                'pid' => $refId,
                'ref_type' => $refType,
                'ref_id' => $refId,
                'uid' => $uid,
                'ip' => $ip,
            ]);

            self::incCounter($refType, $refId);

            $payload = [
                'actor_id' => $uid,
                'target_type' => $refType,
                'target_id' => $refId,
                'owner_id' => $target['owner_id'],
                'card_id' => $target['card_id'],
            ];
            if ($target['comment_id'] !== null) {
                $payload['comment_id'] = $target['comment_id'];
            }
            app(DomainEventBus::class)->publish(
                'content.liked',
                $refType,
                (string) $refId,
                $payload,
                $uid
            );

            Db::commit();

            return self::count($refType, $refId);
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    public static function unlike(string $refType, int $refId, int $uid): void
    {
        self::assertSupportedType($refType);

        Db::startTrans();
        try {
            $like = LikesModel::where('ref_type', $refType)
                ->where('ref_id', $refId)
                ->where('uid', $uid)
                ->find();

            if (!$like) {
                throw ApiException::notFound('点赞记录不存在', ApiException::CODE_RESOURCE_NOT_FOUND);
            }

            $like->delete();

            self::decCounter($refType, $refId);

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    public static function getUserLikes(int $uid, ?string $refType = null): array
    {
        if ($refType !== null) {
            self::assertSupportedType($refType);
        }
        $query = LikesModel::where('uid', $uid);
        if ($refType !== null) $query->where('ref_type', $refType);
        return $query->select()->toArray();
    }

    public static function count(string $refType, int $refId): int
    {
        self::assertSupportedType($refType);
        return (int) LikesModel::where('ref_type', $refType)->where('ref_id', $refId)->count();
    }

    private static function incCounter(string $refType, int $refId): void
    {
        self::counterQuery($refType, $refId)->inc('goods')->update();
    }

    private static function decCounter(string $refType, int $refId): void
    {
        self::counterQuery($refType, $refId)->where('goods', '>', 0)->dec('goods')->update();
    }

    /** @return array{owner_id:int,card_id:int,comment_id:?int} */
    private static function likeTarget(string $refType, int $refId, int $viewerId = 0): array
    {
        $resource = match ($refType) {
            'card' => CardsModel::where('id', $refId)->where('status', 0)->lock(true)->findOrEmpty(),
            'comment' => CommentsModel::where('id', $refId)->where('status', 0)->lock(true)->findOrEmpty(),
        };
        if ($resource->isEmpty()) throw ApiException::notFound('内容不存在或不可点赞');

        $ownerId = (int) $resource->user_id;
        $cardId = $refType === 'card' ? $refId : (int) $resource->pid;
        if ($ownerId < 1 || $cardId < 1) {
            throw new \UnexpectedValueException('点赞目标缺少作者或卡片标识');
        }
        CardAccess::assertReadable($cardId, $viewerId);

        return [
            'owner_id' => $ownerId,
            'card_id' => $cardId,
            'comment_id' => $refType === 'comment' ? $refId : null,
        ];
    }

    private static function counterQuery(string $refType, int $refId)
    {
        return match ($refType) {
            'card' => CardsModel::where('id', $refId),
            'comment' => CommentsModel::where('id', $refId),
        };
    }

    private static function assertSupportedType(string $refType): void
    {
        if (!in_array($refType, self::TYPES, true)) {
            throw ApiException::badRequest('不支持的内容类型', ApiException::CODE_PARAM_INVALID);
        }
    }
}
