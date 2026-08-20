<?php
declare(strict_types=1);

namespace app\command;

use app\modules\Operations\SystemLifecycle\Infrastructure\SignedReleaseBuilder;
use think\console\Command;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Input;
use think\console\Output;

final class BuildRelease extends Command
{
    protected function configure(): void
    {
        $this->setName('lc:release:build')->setDescription('Build and sign an immutable LoveCards backend Release ZIP')
            ->addArgument('output', Argument::REQUIRED, 'Absolute output ZIP path outside the source tree')
            ->addOption('source', null, Option::VALUE_OPTIONAL, 'Backend source root; defaults to the running release')
            ->addOption('release-id', null, Option::VALUE_REQUIRED, 'Immutable Release ID')
            // ThinkPHP reserves the global --version option; use an explicit
            // release-prefixed name so command registration remains valid.
            ->addOption('release-version', null, Option::VALUE_REQUIRED, 'SemVer release version')
            ->addOption('build', null, Option::VALUE_REQUIRED, 'Build identifier')
            ->addOption('sequence', null, Option::VALUE_REQUIRED, 'Monotonic positive release sequence')
            ->addOption('private-key', null, Option::VALUE_REQUIRED, 'Absolute PEM private-key path outside source')
            ->addOption('key-id', null, Option::VALUE_REQUIRED, 'Trusted public-key ID configured on target instances')
            ->addOption('channel', null, Option::VALUE_OPTIONAL, 'Release channel', 'stable')
            ->addOption('source-schema-min', null, Option::VALUE_OPTIONAL, 'Oldest supported schema ID', '0')
            ->addOption('source-schema-max', null, Option::VALUE_OPTIONAL, 'Newest supported source schema ID')
            ->addOption('minimum-php', null, Option::VALUE_OPTIONAL, 'Minimum PHP version', '8.1')
            ->addOption('minimum-mysql', null, Option::VALUE_OPTIONAL, 'Minimum MySQL version', '5.7')
            ->addOption('required-extensions', null, Option::VALUE_OPTIONAL, 'Comma-separated PHP extensions', '')
            ->addOption('optional-facilities', null, Option::VALUE_OPTIONAL, 'Comma-separated optional facilities', '')
            ->addOption('issued-at', null, Option::VALUE_OPTIONAL, 'RFC3339 issue time; defaults to now')
            ->addOption('expires-at', null, Option::VALUE_OPTIONAL, 'RFC3339 expiry; defaults to issue time + 7 days')
            ->addOption('force', 'f', Option::VALUE_NONE, 'Replace an existing output ZIP');
    }

    protected function execute(Input $input, Output $output): int
    {
        $required = [];
        foreach (['release-id', 'release-version', 'build', 'sequence', 'private-key', 'key-id'] as $name) {
            $value = trim((string) $input->getOption($name));
            if ($value === '') $required[] = '--' . $name;
        }
        if ($required !== []) throw new \InvalidArgumentException('缺少必需选项: ' . implode(', ', $required));

        $sequence = filter_var($input->getOption('sequence'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($sequence === false) throw new \InvalidArgumentException('--sequence 必须是正整数');
        $source = trim((string) ($input->getOption('source') ?? ''));
        if ($source === '') $source = root_path();
        $issuedAt = $this->dateOption($input, 'issued-at');
        $expiresAt = $this->dateOption($input, 'expires-at');
        $passphrase = getenv('LC_RELEASE_SIGNING_PASSPHRASE');

        $result = app(SignedReleaseBuilder::class)->build(
            $source,
            (string) $input->getArgument('output'),
            (string) $input->getOption('private-key'),
            (string) $input->getOption('key-id'),
            (string) $input->getOption('release-id'),
            (string) $input->getOption('release-version'),
            (string) $input->getOption('build'),
            (int) $sequence,
            (string) $input->getOption('source-schema-min'),
            $this->nullableString($input->getOption('source-schema-max')),
            (string) $input->getOption('channel'),
            (string) $input->getOption('minimum-php'),
            (string) $input->getOption('minimum-mysql'),
            $this->csv($input->getOption('required-extensions')),
            $this->csv($input->getOption('optional-facilities')),
            $issuedAt,
            $expiresAt,
            $passphrase === false ? null : $passphrase,
            (bool) $input->getOption('force')
        );
        $output->writeln(json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        return 0;
    }

    private function dateOption(Input $input, string $name): ?\DateTimeImmutable
    {
        $value = $this->nullableString($input->getOption($name));
        if ($value === null) return null;
        try { return new \DateTimeImmutable($value); }
        catch (\Throwable $exception) { throw new \InvalidArgumentException('--' . $name . ' 不是有效 RFC3339 时间', 0, $exception); }
    }

    /** @param mixed $value */
    private function nullableString($value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }

    /** @param mixed $value @return string[] */
    private function csv($value): array
    {
        $items = array_filter(array_map('trim', explode(',', (string) $value)), static fn(string $item): bool => $item !== '');
        return array_values(array_unique($items));
    }
}
