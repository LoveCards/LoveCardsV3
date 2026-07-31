<?php

namespace app\api { class ApiException extends \RuntimeException {} }

namespace {
    require_once dirname(__DIR__, 2) . '/app/frontend/service/ThemeEngine.php';
    use app\frontend\service\ThemeEngine;

    $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lovecards-theme-zip-' . bin2hex(random_bytes(6));
    mkdir($root, 0700, true);
    $tests = [];
    $makeZip = static function (string $name, array $entries, ?callable $configure = null) use ($root): string {
        $path = $root . DIRECTORY_SEPARATOR . $name;
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) throw new \RuntimeException('无法创建测试 ZIP');
        foreach ($entries as $entry => $content) $zip->addFromString($entry, $content);
        if ($configure) $configure($zip);
        $zip->close();
        return $path;
    };
    $expectReject = static function (string $zipPath) use ($root): void {
        try {
            ThemeEngine::installTheme($zipPath, $root . DIRECTORY_SEPARATOR . 'themes');
            throw new \RuntimeException('恶意 ZIP 未被拒绝');
        } catch (\app\api\ApiException $e) {
            if (is_file($root . DIRECTORY_SEPARATOR . 'escape.php')) throw new \RuntimeException('ZIP 写出了目标目录');
            $staging = glob($root . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . '.install-*');
            if ($staging) throw new \RuntimeException('失败后留下临时主题目录');
        }
    };

    $tests['valid root theme installs'] = static function () use ($makeZip, $root): void {
        $zip = $makeZip('valid.zip', [
            'theme.json' => json_encode(['name' => 'safe-theme', 'version' => '1.0.0']),
            'assets/app.css' => 'body{}',
        ]);
        $result = ThemeEngine::installTheme($zip, $root . DIRECTORY_SEPARATOR . 'themes');
        if ($result['name'] !== 'safe-theme' || !is_file($root . '/themes/safe-theme/assets/app.css')) throw new \RuntimeException('合法主题安装结果错误');
    };
    $tests['existing theme is replaced without backup residue'] = static function () use ($makeZip, $root): void {
        $themePath = $root . '/themes/replace-theme';
        mkdir($themePath, 0700, true);
        file_put_contents($themePath . '/old.txt', 'old');
        $zip = $makeZip('replace.zip', [
            'theme.json' => '{"name":"replace-theme","version":"2.0.0"}',
            'new.txt' => 'new',
        ]);
        ThemeEngine::installTheme($zip, $root . DIRECTORY_SEPARATOR . 'themes');
        if (is_file($themePath . '/old.txt') || !is_file($themePath . '/new.txt')) throw new \RuntimeException('主题没有完整替换');
        if (glob($themePath . '.backup-*')) throw new \RuntimeException('主题替换留下备份目录');
    };
    $tests['parent traversal rejected'] = static function () use ($makeZip, $expectReject): void {
        $expectReject($makeZip('parent.zip', ['theme.json' => '{"name":"bad-parent"}', '../escape.php' => 'x']));
    };
    $tests['backslash traversal rejected'] = static function () use ($makeZip, $expectReject): void {
        $expectReject($makeZip('backslash.zip', ['theme.json' => '{"name":"bad-backslash"}', '..\\escape.php' => 'x']));
    };
    $tests['absolute path rejected'] = static function () use ($makeZip, $expectReject): void {
        $expectReject($makeZip('absolute.zip', ['theme.json' => '{"name":"bad-absolute"}', 'C:/escape.php' => 'x']));
    };
    $tests['Windows reserved path rejected'] = static function () use ($makeZip, $expectReject): void {
        $expectReject($makeZip('reserved.zip', ['theme.json' => '{"name":"bad-reserved"}', 'assets/CON.txt' => 'x']));
    };
    $tests['nested manifest rejected'] = static function () use ($makeZip, $expectReject): void {
        $expectReject($makeZip('nested.zip', ['nested/theme.json' => '{"name":"bad-nested"}']));
    };
    $tests['symlink entry rejected'] = static function () use ($makeZip, $expectReject): void {
        $zip = $makeZip('symlink.zip', ['theme.json' => '{"name":"bad-link"}', 'link' => '../outside'], static function (\ZipArchive $archive): void {
            $archive->setExternalAttributesName('link', \ZipArchive::OPSYS_UNIX, 0120777 << 16);
        });
        $expectReject($zip);
    };
    $tests['entry limit rejected'] = static function () use ($makeZip, $expectReject): void {
        $entries = ['theme.json' => '{"name":"too-many"}'];
        for ($i = 0; $i < 1000; $i++) $entries['files/' . $i . '.txt'] = 'x';
        $expectReject($makeZip('entries.zip', $entries));
    };

    $failures = 0;
    try {
        foreach ($tests as $name => $test) {
            try { $test(); fwrite(STDOUT, "PASS {$name}\n"); }
            catch (\Throwable $e) { $failures++; fwrite(STDERR, "FAIL {$name}: {$e->getMessage()}\n"); }
        }
    } finally {
        $remove = static function (string $dir) use (&$remove): void {
            if (!is_dir($dir)) return;
            foreach (array_diff(scandir($dir), ['.', '..']) as $item) {
                $path = $dir . DIRECTORY_SEPARATOR . $item;
                is_dir($path) ? $remove($path) : @unlink($path);
            }
            @rmdir($dir);
        };
        $remove($root);
    }
    if ($failures) exit(1);
    fwrite(STDOUT, count($tests) . " Theme ZIP security tests passed.\n");
}
