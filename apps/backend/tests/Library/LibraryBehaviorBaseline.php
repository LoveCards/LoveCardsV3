<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/app/shared/Resource/ResourceRef.php';
require $root . '/app/shared/Transport/Http/CursorPage.php';
require $root . '/app/modules/Platform/Library/Contract/LibraryRepository.php';
require $root . '/app/modules/Platform/Library/Application/ManageLibrary.php';

use app\modules\Platform\Library\Application\ManageLibrary;
use app\modules\Platform\Library\Contract\LibraryRepository;
use app\shared\Resource\ResourceRef;

final class FakeLibraryRepository implements LibraryRepository
{
    public array $saved = [];
    public int $views = 0;
    public function save(int $userId, ResourceRef $resource): bool { $key = "{$userId}:{$resource->key()}"; if (isset($this->saved[$key])) return false; return $this->saved[$key] = true; }
    public function remove(int $userId, ResourceRef $resource): bool { $key = "{$userId}:{$resource->key()}"; if (!isset($this->saved[$key])) return false; unset($this->saved[$key]); return true; }
    public function recordView(int $userId, ResourceRef $resource): void { $this->views++; }
    public function listSaved(int $userId, ?string $cursor, int $limit): array { return ['items' => [['id' => 2]], 'next_cursor' => null, 'has_more' => false]; }
    public function listHistory(int $userId, ?string $cursor, int $limit): array { return ['items' => [['id' => 3]], 'next_cursor' => '3', 'has_more' => true]; }
    public function clearHistory(int $userId): int { $count = $this->views; $this->views = 0; return $count; }
}

$assert = static function (bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); };
$repository = new FakeLibraryRepository();
$library = new ManageLibrary($repository);
$resource = new ResourceRef('card', 42);

$assert($library->save(1, $resource), 'first save should create item');
$assert(!$library->save(1, $resource), 'repeated save should be idempotent');
$assert($library->remove(1, $resource), 'remove should delete saved item');
$assert(!$library->remove(1, $resource), 'repeated remove should be idempotent');

$library->recordView(1, $resource);
$library->recordView(1, $resource);
$assert($repository->views === 2, 'views should be recorded');
$assert($library->history(1)['next_cursor'] === '3', 'history should use cursor response');
$assert($library->clearHistory(1) === 2, 'clear should report removed history');

fwrite(STDOUT, "PASS library behavior baseline\n");
