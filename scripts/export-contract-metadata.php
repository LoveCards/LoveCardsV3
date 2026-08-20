<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$backend = $root . DIRECTORY_SEPARATOR . 'apps' . DIRECTORY_SEPARATOR . 'backend';
require $backend . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

function phpFiles(string $root): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') $files[] = $file->getPathname();
    }
    return $files;
}

function classMap(string $root): array
{
    $map = [];
    foreach (phpFiles($root) as $file) {
        $source = file_get_contents($file);
        if ($source === false || !preg_match('/namespace\s+([^;]+);/m', $source, $namespace)) continue;
        if (!preg_match('/(?:final\s+|abstract\s+)?class\s+(\w+)\s+extends\s+([^\s{]+)/m', $source, $class)) continue;
        $map[$class[1]][] = [trim($namespace[1]) . '\\' . $class[1], $file];
    }
    return $map;
}

$map = classMap($backend . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'modules');
foreach (classMap($backend . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'shared') as $shortName => $candidates) {
    $map[$shortName] = array_merge($map[$shortName] ?? [], $candidates);
}
$resources = [];
$validators = [];

foreach ($map as $shortName => $candidates) {
    foreach ($candidates as [$class, $file]) {
        try {
            if (is_subclass_of($class, 'app\\shared\\Transport\\Http\\Resource')) {
                $schema = method_exists($class, 'schema') ? $class::schema() : [];
                $resources[$class] = ['shortName' => $shortName, 'file' => str_replace($root . DIRECTORY_SEPARATOR, '', $file), 'schema' => $schema];
            }
            if (is_subclass_of($class, 'think\\Validate')) {
                $reflection = new ReflectionClass($class);
                $defaults = $reflection->getDefaultProperties();
                $rules = $defaults['rule'] ?? [];
                $messages = $defaults['message'] ?? [];
                $scenes = $defaults['scene'] ?? [];
                $allScene = $reflection->hasProperty('all_scene') ? $reflection->getStaticPropertyValue('all_scene') : [];
                $validators[$class] = [
                    'shortName' => $shortName,
                    'file' => str_replace($root . DIRECTORY_SEPARATOR, '', $file),
                    'rules' => $rules,
                    'messages' => $messages,
                    'scenes' => $scenes,
                    'allScenes' => $allScene,
                    'requestSchemas' => method_exists($class, 'requestSchemas') ? $class::requestSchemas() : [],
                ];
            }
        } catch (Throwable $exception) {
            fwrite(STDERR, "Metadata export failed for {$class}: {$exception->getMessage()}\n");
            exit(1);
        }
    }
}

echo json_encode(['resources' => $resources, 'validators' => $validators], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
