<?php

namespace think\facade {
    final class ProfileLikesQuery
    {
        public function __construct(private array $rows) {}
        public function alias(string $alias): self { return $this; }
        public function join(string $table, string $condition): self { return $this; }
        public function where(string $field, mixed $operator, mixed $value = null): self { return $this; }
        public function whereNull(string $field): self { return $this; }
        public function count(): int
        {
            return count(array_filter($this->rows, static fn(array $row): bool =>
                $row['aid'] === 1
                && $row['ref_type'] === 'card'
                && $row['card_user_id'] === $row['target_user_id']
                && $row['card_status'] === 0
                && $row['card_deleted_at'] === null
            ));
        }
    }

    final class Db
    {
        public static array $rows = [];
        public static function table(string $table): ProfileLikesQuery
        {
            if ($table !== 'likes') throw new \RuntimeException('unexpected table');
            return new ProfileLikesQuery(self::$rows);
        }
    }
}

namespace {

require_once dirname(__DIR__, 2) . '/app/modules/Platform/User/Public/UserProfileStats.php';
require_once dirname(__DIR__, 2) . '/app/modules/Platform/User/Service/Profile.php';

use app\modules\Platform\User\Service\Profile;
use think\facade\Db;

$source = file_get_contents(dirname(__DIR__, 2) . '/app/modules/Platform/User/Service/Profile.php');
$statsSource = file_get_contents(dirname(__DIR__, 2) . '/app/modules/Platform/User/Public/UserProfileStats.php');
$socialSource = file_get_contents(dirname(__DIR__, 2) . '/app/modules/Platform/Social/Public/Social.php');
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$assert(str_contains($source, "['received_likes' => UserProfileStats::receivedLikes(") , 'users.me must expose received_likes');
$assert(substr_count($socialSource, "['received_likes' => UserProfileStats::receivedLikes(") === 2, 'uid and handle public profiles must share received likes');
$assert(str_contains($statsSource, "where('l.ref_type', 'card')"), 'received likes must only use card likes');
$assert(str_contains($statsSource, "where('c.status', 0)"), 'received likes must exclude unavailable cards');
$assert(str_contains($statsSource, "whereNull('c.deleted_at')"), 'received likes must exclude deleted cards');
$assert(!str_contains($statsSource, "likes_and_saves"), 'backend profile stats must not expose likes and saves aggregate');

Db::$rows = [
    ['aid' => 1, 'ref_type' => 'card', 'card_user_id' => 7, 'target_user_id' => 7, 'card_status' => 0, 'card_deleted_at' => null],
    ['aid' => 1, 'ref_type' => 'card', 'card_user_id' => 7, 'target_user_id' => 7, 'card_status' => 0, 'card_deleted_at' => null],
    ['aid' => 1, 'ref_type' => 'card', 'card_user_id' => 7, 'target_user_id' => 7, 'card_status' => 0, 'card_deleted_at' => null],
    ['aid' => 1, 'ref_type' => 'comment', 'card_user_id' => 7, 'target_user_id' => 7, 'card_status' => 0, 'card_deleted_at' => null],
    ['aid' => 1, 'ref_type' => 'card', 'card_user_id' => 7, 'target_user_id' => 7, 'card_status' => 2, 'card_deleted_at' => null],
    ['aid' => 1, 'ref_type' => 'card', 'card_user_id' => 7, 'target_user_id' => 7, 'card_status' => 0, 'card_deleted_at' => '2026-01-01'],
    ['aid' => 1, 'ref_type' => 'card', 'card_user_id' => 8, 'target_user_id' => 7, 'card_status' => 0, 'card_deleted_at' => null],
];
$assert(Profile::receivedLikes(7) === 3, 'received likes must aggregate visible cards only');
Db::$rows = [];
$assert(Profile::receivedLikes(7) === 0, 'users without content must return zero');

fwrite(STDOUT, "Profile stats baseline passed.\n");
}
