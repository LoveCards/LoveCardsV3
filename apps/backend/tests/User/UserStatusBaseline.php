<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/modules/Platform/User/Public/UserStatus.php';

use app\modules\Platform\User\Public\UserStatus;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$assert(UserStatus::canAuthenticate(UserStatus::ACTIVE), 'active users must authenticate');
$assert(UserStatus::canAuthenticate(UserStatus::HIDDEN), 'hidden users must retain account access');
$assert(!UserStatus::canAuthenticate(UserStatus::BANNED), 'banned users must not authenticate');
$assert(!UserStatus::canAuthenticate(UserStatus::PENDING), 'pending users must not authenticate');
$assert(UserStatus::isPublic(UserStatus::ACTIVE), 'only active users are public');
$assert(!UserStatus::isPublic(UserStatus::HIDDEN), 'hidden users must not expose public identity');
$assert(!UserStatus::isKnown(99), 'unknown status must fail closed');

fwrite(STDOUT, "PASS user status baseline\n");
