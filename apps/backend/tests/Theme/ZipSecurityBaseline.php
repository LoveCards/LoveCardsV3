<?php

namespace {
    function root_path(): string { return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lovecards-theme-zip-root'; }
    require_once dirname(__DIR__, 2) . '/app/shared/SystemPaths/SystemPaths.php';
    require_once dirname(__DIR__, 2) . '/app/shared/SystemPaths/SystemPathsResolver.php';
    require_once dirname(__DIR__, 2) . '/app/shared/SystemPaths/LocalStoragePath.php';
    require_once dirname(__DIR__, 2) . '/app/modules/Theme/Domain/ThemeManifest.php';
    require_once dirname(__DIR__, 2) . '/app/modules/Theme/Infrastructure/FilesystemThemeCatalog.php';
    require_once dirname(__DIR__, 2) . '/app/modules/Theme/Infrastructure/SecureThemeInstaller.php';

    $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lovecards-theme-zip-' . bin2hex(random_bytes(6));
    mkdir($root, 0700, true);
    $makeZip = static function (string $name, array $entries) use ($root): string {
        $path = $root . DIRECTORY_SEPARATOR . $name;
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) throw new \RuntimeException('cannot create zip');
        foreach ($entries as $entry => $content) $zip->addFromString($entry, $content);
        $zip->close();
        return $path;
    };
    $makeSymlinkZip = static function (string $name, string $manifest) use ($root): string {
        $path = $root . DIRECTORY_SEPARATOR . $name;
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) throw new \RuntimeException('cannot create zip');
        $zip->addFromString('theme.json', $manifest);
        $zip->addFromString('dist/index.html', '<html></html>');
        $zip->addFromString('dist/link.js', 'target');
        if (method_exists($zip, 'setExternalAttributesName')) $zip->setExternalAttributesName('dist/link.js', \ZipArchive::OPSYS_UNIX, 0120777 << 16);
        $zip->close();
        return $path;
    };
    $manifest = json_encode(['schemaVersion' => 1, 'id' => 'safe-theme', 'name' => 'Safe', 'version' => '1.0.0-test-' . bin2hex(random_bytes(3)), 'mode' => 'spa', 'entry' => 'dist/index.html', 'config' => []], JSON_UNESCAPED_SLASHES);
    $html = '<!doctype html><html><head></head><body><div id="app"></div></body></html>';
    $expectReject = static function (string $zipPath) use ($root): void {
        try { (new \app\modules\Theme\Infrastructure\SecureThemeInstaller())->install($zipPath); throw new \RuntimeException('malicious zip accepted'); }
        catch (\Throwable $e) { if (is_file($root . '/escape.php')) throw new \RuntimeException('path escaped'); }
    };
    $tests = [
        'valid SPA manifest installs as immutable release' => static function () use ($makeZip, $manifest, $html): void {
            $zip = $makeZip('valid.zip', ['theme.json' => $manifest, 'dist/index.html' => $html, 'dist/assets/main.js' => 'console.log(1);']);
            $result = (new \app\modules\Theme\Infrastructure\SecureThemeInstaller())->install($zip);
            if ($result['id'] !== 'safe-theme' || $result['mode'] !== 'spa' || $result['release'] === 'source') throw new \RuntimeException('invalid install result');
        },
        'nested theme root rejected' => static function () use ($makeZip, $expectReject, $manifest, $html): void { $expectReject($makeZip('nested.zip', ['safe-theme/theme.json' => $manifest, 'safe-theme/dist/index.html' => $html])); },
        'invalid mode rejected' => static function () use ($makeZip, $expectReject, $manifest): void { $bad = json_decode($manifest, true); $bad['mode'] = 'html'; $expectReject($makeZip('mode.zip', ['theme.json' => json_encode($bad), 'dist/index.html' => '<html></html>'])); },
        'entry traversal rejected' => static function () use ($makeZip, $expectReject, $manifest): void { $bad = json_decode($manifest, true); $bad['entry'] = '../index.html'; $expectReject($makeZip('entry.zip', ['theme.json' => json_encode($bad), 'dist/index.html' => '<html></html>'])); },
        'missing version rejected' => static function () use ($makeZip, $expectReject, $manifest): void { $bad = json_decode($manifest, true); unset($bad['version']); $expectReject($makeZip('version.zip', ['theme.json' => json_encode($bad), 'dist/index.html' => '<html></html>'])); },
        'invalid SemVer rejected' => static function () use ($makeZip, $expectReject, $manifest): void { $bad = json_decode($manifest, true); $bad['version'] = '01.0.0'; $expectReject($makeZip('semver.zip', ['theme.json' => json_encode($bad), 'dist/index.html' => '<html></html>'])); },
        'valid prerelease SemVer accepted' => static function () use ($makeZip, $manifest): void { $valid = json_decode($manifest, true); $valid['version'] = '1.2.3-alpha.1+build.7'; $zip = $makeZip('semver-valid.zip', ['theme.json' => json_encode($valid), 'dist/index.html' => '<html></html>']); $result = (new \app\modules\Theme\Infrastructure\SecureThemeInstaller())->install($zip); if (strpos($result['release'], '1.2.3-alpha.1+build.7-') !== 0) throw new RuntimeException('valid prerelease SemVer was not preserved'); },
        'symlink entry rejected' => static function () use ($makeSymlinkZip, $expectReject, $manifest): void { $expectReject($makeSymlinkZip('symlink.zip', $manifest)); },
        'parent traversal rejected' => static function () use ($makeZip, $expectReject, $manifest): void { $expectReject($makeZip('parent.zip', ['theme.json' => $manifest, '../escape.php' => 'x'])); },
        'backslash traversal rejected' => static function () use ($makeZip, $expectReject, $manifest): void { $expectReject($makeZip('backslash.zip', ['theme.json' => $manifest, '..\\escape.php' => 'x'])); },
        'absolute path rejected' => static function () use ($makeZip, $expectReject, $manifest): void { $expectReject($makeZip('absolute.zip', ['theme.json' => $manifest, 'C:/escape.php' => 'x'])); },
    ];
    $failures = 0;
    try { foreach ($tests as $name => $test) { try { $test(); fwrite(STDOUT, "PASS {$name}\n"); } catch (\Throwable $e) { $failures++; fwrite(STDERR, "FAIL {$name}: {$e->getMessage()}\n"); } } }
    finally { if (is_dir($root)) { $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \RecursiveDirectoryIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST); foreach ($it as $file) $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname()); @rmdir($root); } }
    if ($failures) exit(1);
    fwrite(STDOUT, count($tests) . " Theme ZIP security tests passed.\n");
}
