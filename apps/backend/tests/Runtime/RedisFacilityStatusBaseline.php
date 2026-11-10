<?php
declare(strict_types=1);

namespace app\modules\Platform\Config\Public {
    final class RedisConnectionSettings
    {
        public static bool $enabled = false;
        public static array $result = ['ready' => false, 'reason_code' => 'redis-disabled'];
        public static int $testCalls = 0;

        public static function current(): array { return ['enabled' => self::$enabled]; }
        public static function test(): array { self::$testCalls++; return self::$result; }
    }
}

namespace {
    require dirname(__DIR__, 2) . '/vendor/autoload.php';

    use app\modules\Operations\Runtime\Application\ResolveFacilities;
    use app\modules\Operations\Runtime\Infrastructure\ConfiguredFacilityProbe;
    use app\modules\Platform\Config\Public\RedisConnectionSettings;
    use app\shared\Runtime\FacilityConfig;
    use app\shared\Runtime\FacilityResolver;
    use app\shared\Runtime\NativeFacilityProbe;

    $assert = static function (bool $ok, string $message): void {
        if (!$ok) throw new \RuntimeException($message);
    };
    $resolver = new ResolveFacilities(new FacilityResolver(), new ConfiguredFacilityProbe(new NativeFacilityProbe()));
    $config = new FacilityConfig('eventing.wakeup', 'auto', false, 'redis', 'mysql-poll');

    $disabled = $resolver->execute([$config])['eventing.wakeup']->toArray();
    $assert(!$disabled['configured'] && !$disabled['healthy'], 'disabled Redis must not report healthy');
    $assert(!$disabled['enabled'] && $disabled['effective_provider'] === null, 'polling must not be mislabeled as Redis fallback');
    $assert(RedisConnectionSettings::$testCalls === 0, 'disabled Redis must not be probed');

    RedisConnectionSettings::$enabled = true;
    RedisConnectionSettings::$result = ['ready' => true, 'reason_code' => null];
    $healthy = $resolver->execute([$config])['eventing.wakeup']->toArray();
    $assert($healthy['configured'] && $healthy['healthy'] && $healthy['effective_provider'] === 'redis', 'configured Redis must use shared connection test');

    RedisConnectionSettings::$result = ['ready' => false, 'reason_code' => 'redis-auth-failed'];
    $failed = $resolver->execute([$config])['eventing.wakeup']->toArray();
    $assert(!$failed['healthy'] && $failed['reason_code'] === 'redis-auth-failed', 'authentication failure must not report healthy');
    $assert($failed['effective_provider'] === null, 'failed Redis must not advertise a fallback provider');

    echo "Redis facility status baseline passed.\n";
}
