<?php
declare(strict_types=1);

namespace app\api
{
    final class ApiException extends \RuntimeException
    {
        public static function notFound(string $message = ''): self { return new self($message, 404); }
        public static function forbidden(string $message = ''): self { return new self($message, 403); }
    }
}

namespace tests\Authorization
{
    final class FakeRecord
    {
        public function __construct(
            public int $id = 0,
            public int $user_id = 0,
            public bool $deleted = false,
            private bool $empty = false
        ) {}

        public static function empty(): self { return new self(empty: true); }
        public function isEmpty(): bool { return $this->empty; }
    }

    final class FakeModel
    {
        private bool $includeTrashed;
        private array $ids = [];
        private ?int $ownerNotEqual = null;
        /** @var array<int, FakeRecord> */
        private static array $records = [];

        public function __construct(bool $includeTrashed = false) { $this->includeTrashed = $includeTrashed; }

        public static function seed(): void
        {
            self::$records = [
                1 => new FakeRecord(1, 10),
                2 => new FakeRecord(2, 20),
                3 => new FakeRecord(3, 10, true),
            ];
        }

        public static function withTrashed(): self { return new self(true); }

        public function where(string $field, $operator, $value = null): self
        {
            if ($field === 'id') $this->ids = [(int) $operator];
            if ($field === 'user_id' && $operator === '<>') $this->ownerNotEqual = (int) $value;
            return $this;
        }

        public function whereIn(string $field, array $values): self
        {
            if ($field === 'id') $this->ids = array_map('intval', $values);
            return $this;
        }

        public function findOrEmpty(): FakeRecord
        {
            return $this->rows()[0] ?? FakeRecord::empty();
        }

        public function column(string $field): array
        {
            return array_map(static fn(FakeRecord $record) => $record->$field, $this->rows());
        }

        public function field(string $fields): self { return $this; }

        public function select(): object
        {
            $rows = array_map(static fn(FakeRecord $record) => [
                'id' => $record->id,
                'user_id' => $record->user_id,
            ], $this->rows());
            return new class($rows) {
                public function __construct(private array $rows) {}
                public function toArray(): array { return $this->rows; }
            };
        }

        /** @return FakeRecord[] */
        private function rows(): array
        {
            return array_values(array_filter(
                self::$records,
                fn(FakeRecord $record): bool => ($this->ids === [] || in_array($record->id, $this->ids, true))
                    && ($this->includeTrashed || !$record->deleted)
                    && ($this->ownerNotEqual === null || $record->user_id !== $this->ownerNotEqual)
            ));
        }
    }
}

namespace
{
    require __DIR__ . '/../../app/shared/Authorization/Scope.php';
    require __DIR__ . '/../../app/shared/Authorization/Grant.php';
    require __DIR__ . '/../../app/shared/Authorization/GrantSet.php';
    require __DIR__ . '/../../app/shared/Authorization/OwnedResourcePolicy.php';

    final class OwnershipGuardHarness
    {
        public static function one(int $id, int $uid, string $scope, bool $includeTrashed = false): void
        {
            $grants = new \app\shared\Authorization\GrantSet([
                new \app\shared\Authorization\Grant('cards.update', $scope),
            ]);
            \app\shared\Authorization\OwnedResourcePolicy::assertOne(
                \tests\Authorization\FakeModel::class, 'user_id', $grants, 'cards.update', $id, $uid, $includeTrashed
            );
        }

        public static function batch(array $ids, int $uid, string $scope, bool $includeTrashed = false): void
        {
            $grants = new \app\shared\Authorization\GrantSet([
                new \app\shared\Authorization\Grant('cards.update', $scope),
            ]);
            \app\shared\Authorization\OwnedResourcePolicy::assertMany(
                \tests\Authorization\FakeModel::class, 'user_id', $grants, 'cards.update', $ids, $uid, $includeTrashed
            );
        }
    }

    \tests\Authorization\FakeModel::seed();
    $tests = [];
    $test = static function (string $name, \Closure $callback) use (&$tests): void { $tests[$name] = $callback; };
    $assertCode = static function (int $code, \Closure $callback): void {
        try {
            $callback();
        } catch (\Throwable $exception) {
            if ($exception->getCode() === $code) return;
            throw new \RuntimeException("expected {$code}, got {$exception->getCode()}");
        }
        throw new \RuntimeException("expected exception {$code}");
    };

    $test('all scope checks active resource existence', static function () use ($assertCode): void {
        OwnershipGuardHarness::one(1, 99, \app\shared\Authorization\Scope::ALL);
        $assertCode(404, static fn() => OwnershipGuardHarness::one(999, 99, \app\shared\Authorization\Scope::ALL));
    });

    $test('own scope enforces owner', static function () use ($assertCode): void {
        OwnershipGuardHarness::one(1, 10, \app\shared\Authorization\Scope::OWN);
        $assertCode(403, static fn() => OwnershipGuardHarness::one(2, 10, \app\shared\Authorization\Scope::OWN));
    });

    $test('trashed resource is only visible when requested', static function () use ($assertCode): void {
        $assertCode(404, static fn() => OwnershipGuardHarness::one(3, 10, \app\shared\Authorization\Scope::OWN));
        OwnershipGuardHarness::one(3, 10, \app\shared\Authorization\Scope::OWN, true);
    });

    $test('batch scope validates existence and ownership', static function () use ($assertCode): void {
        OwnershipGuardHarness::batch([1], 10, \app\shared\Authorization\Scope::OWN);
        $assertCode(404, static fn() => OwnershipGuardHarness::batch([1, 999], 10, \app\shared\Authorization\Scope::OWN));
        $assertCode(403, static fn() => OwnershipGuardHarness::batch([1, 2], 10, \app\shared\Authorization\Scope::OWN));
        OwnershipGuardHarness::batch([1, 3], 10, \app\shared\Authorization\Scope::OWN, true);
    });

    $failures = 0;
    foreach ($tests as $name => $callback) {
        try {
            $callback();
            fwrite(STDOUT, "PASS {$name}\n");
        } catch (\Throwable $exception) {
            $failures++;
            fwrite(STDERR, "FAIL {$name}: {$exception->getMessage()}\n");
        }
    }

    if ($failures > 0) exit(1);
    fwrite(STDOUT, count($tests) . " OwnedResourcePolicy baseline tests passed.\n");
}
