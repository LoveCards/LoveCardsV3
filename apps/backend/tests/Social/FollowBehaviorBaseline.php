<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/app/shared/Transport/Http/CursorPage.php';
require $root . '/app/modules/Platform/Social/Domain/FollowPolicy.php';
require $root . '/app/modules/Platform/Social/Contract/SocialGraphRepository.php';
require $root . '/app/modules/Platform/Social/Application/FollowUser.php';
require $root . '/app/modules/Platform/Social/Application/ListRelations.php';

use app\modules\Platform\Social\Application\FollowUser;
use app\modules\Platform\Social\Application\ListRelations;
use app\modules\Platform\Social\Contract\SocialGraphRepository;

final class FakeSocialGraphRepository implements SocialGraphRepository
{
    public array $relations = [];
    public function profileByHandle(string $handle): ?array { return null; }
    public function userExists(int $userId): bool { return $userId === 2; }
    public function follow(int $actorId, int $targetId): bool { $key = "{$actorId}:{$targetId}"; if (isset($this->relations[$key])) return false; return $this->relations[$key] = true; }
    public function unfollow(int $actorId, int $targetId): bool { $key = "{$actorId}:{$targetId}"; if (!isset($this->relations[$key])) return false; unset($this->relations[$key]); return true; }
    public function isFollowing(int $actorId, int $targetId): bool { return isset($this->relations["{$actorId}:{$targetId}"]); }
    public function listFollowers(int $userId, ?string $cursor, int $limit): array { return ['items' => [['id' => 1]], 'next_cursor' => '10', 'has_more' => true]; }
    public function listFollowing(int $userId, ?string $cursor, int $limit): array { return ['items' => [['id' => 2]], 'next_cursor' => null, 'has_more' => false]; }
    public function counts(int $userId): array { return ['followers' => 1, 'following' => 1]; }
}

$assert = static function (bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); };
$repository = new FakeSocialGraphRepository();
$useCase = new FollowUser($repository);

$assert($useCase->follow(1, 2), 'first follow should create relation');
$assert(!$useCase->follow(1, 2), 'repeated follow should be idempotent');
$assert($useCase->unfollow(1, 2), 'unfollow should remove relation');
$assert(!$useCase->unfollow(1, 2), 'repeated unfollow should be idempotent');

$selfRejected = false;
try { $useCase->follow(1, 1); } catch (DomainException) { $selfRejected = true; }
$assert($selfRejected, 'self follow must be rejected');

$missingRejected = false;
try { $useCase->follow(1, 3); } catch (DomainException) { $missingRejected = true; }
$assert($missingRejected, 'missing target must be rejected');

$page = (new ListRelations($repository))->followers(2, null, 500);
$assert($page['next_cursor'] === '10' && $page['has_more'], 'relation page should preserve cursor contract');

fwrite(STDOUT, "PASS social follow behavior baseline\n");
