<?php

/**
 * Validate ms2categorysort transport zip without MODX.
 *
 * Usage: php _build/validate.transport.php [_build/dist/ms2categorysort-1.0.0-pl.transport.zip]
 */

declare(strict_types=1);

$zipPath = $argv[1] ?? dirname(__DIR__) . '/_build/dist/ms2categorysort-1.0.0-pl.transport.zip';

if (!is_readable($zipPath)) {
    fwrite(STDERR, "Zip not found: {$zipPath}\n");
    exit(1);
}

$tmp = sys_get_temp_dir() . '/ms2categorysort-validate-' . getmypid();
if (is_dir($tmp)) {
    rrmdir($tmp);
}
mkdir($tmp, 0755, true);

$zip = new ZipArchive();
if ($zip->open($zipPath) !== true) {
    fwrite(STDERR, "Cannot open zip: {$zipPath}\n");
    exit(1);
}
$zip->extractTo($tmp);
$zip->close();

$root = findPackageRoot($tmp);
if ($root === null) {
    fwrite(STDERR, "Package root not found in zip\n");
    exit(1);
}

$errors = [];
$warnings = [];
$checks = 0;

$manifestFile = $root . '/manifest.php';
if (!is_readable($manifestFile)) {
    $errors[] = 'manifest.php missing';
} else {
    $manifest = include $manifestFile;
    $checks++;

    if (($manifest['manifest-version'] ?? '') !== '1.1') {
        $warnings[] = 'Unexpected manifest-version: ' . ($manifest['manifest-version'] ?? 'none');
    }

    $attrs = $manifest['manifest-attributes'] ?? [];
    foreach (['license', 'readme', 'changelog'] as $key) {
        if (empty($attrs[$key])) {
            $warnings[] = "manifest-attributes.{$key} is empty";
        }
    }

    $vehicles = $manifest['manifest-vehicles'] ?? [];
    if (count($vehicles) !== 3) {
        $errors[] = 'Expected 3 top-level vehicles, got ' . count($vehicles);
    }

    $classes = array_column($vehicles, 'class');
    foreach (['modNamespace', 'modCategory', 'modSystemSetting'] as $expected) {
        if (!in_array($expected, $classes, true)) {
            $errors[] = "Missing vehicle class: {$expected}";
        }
    }
}

$resolverBasenames = [
    'resolve.tables',
    'resolve.migrate',
    'resolve.ms2_plugins',
    'resolve.snippet',
    'resolve.events',
];

$categoryVehicle = glob($root . '/modCategory/*.vehicle');
if ($categoryVehicle === []) {
    $errors[] = 'modCategory vehicle missing';
} else {
    $vehicle = include $categoryVehicle[0];
    $checks++;

    $category = json_decode((string) ($vehicle['object'] ?? ''), true);
    if (($category['category'] ?? '') !== 'ms2CategorySort') {
        $errors[] = 'Category name must be ms2CategorySort, got: ' . ($category['category'] ?? 'null');
    }

    $plugins = $vehicle['related_objects']['Plugins'] ?? [];
    if (count($plugins) !== 1) {
        $errors[] = 'Expected 1 plugin in category vehicle, got ' . count($plugins);
    } else {
        $pluginVehicle = reset($plugins);
        $plugin = json_decode((string) ($pluginVehicle['object'] ?? ''), true);
        if (($plugin['name'] ?? '') !== 'ms2CategorySort') {
            $errors[] = 'Plugin name must be ms2CategorySort';
        }
        if (empty($plugin['plugincode'])) {
            $errors[] = 'Plugin code is empty';
        } elseif (strpos($plugin['plugincode'], 'OnMODXInit') === false) {
            $errors[] = 'Plugin code does not contain OnMODXInit handler';
        }

        $events = [];
        foreach ($pluginVehicle['related_objects']['PluginEvents'] ?? [] as $eventVehicle) {
            $eventObj = json_decode((string) ($eventVehicle['object'] ?? ''), true);
            if (!empty($eventObj['event'])) {
                $events[] = $eventObj['event'];
            }
        }
        $expectedEvents = ['OnMODXInit', 'OnManagerPageBeforeRender', 'msOnManagerCustomCssJs', 'OnDocFormSave'];
        foreach ($expectedEvents as $event) {
            if (!in_array($event, $events, true)) {
                $errors[] = "Missing plugin event: {$event}";
            }
        }
    }

    $resolveNames = [];
    foreach ($vehicle['resolve'] ?? [] as $item) {
        if (($item['type'] ?? '') === 'php') {
            $body = json_decode((string) ($item['body'] ?? ''), true);
            $resolveNames[] = $body['name'] ?? basename((string) ($body['source'] ?? ''), '.resolver');
            $resolverPath = dirname($categoryVehicle[0]) . '/' . basename((string) ($body['source'] ?? ''));
            if (!is_readable($resolverPath)) {
                $errors[] = 'Resolver file missing: ' . basename($resolverPath);
            } else {
                $lint = lintPhp($resolverPath);
                if ($lint !== true) {
                    $errors[] = 'Resolver syntax error (' . basename($resolverPath) . '): ' . $lint;
                }
            }
        }
    }

    foreach ($resolverBasenames as $resolverName) {
        if (!in_array($resolverName, $resolveNames, true)) {
            $errors[] = "Missing resolver in vehicle: {$resolverName}";
        }
    }
}

$settingVehicle = glob($root . '/modSystemSetting/*.vehicle');
if ($settingVehicle === []) {
    $errors[] = 'modSystemSetting vehicle missing';
} else {
    $vehicle = include $settingVehicle[0];
    $checks++;
    $setting = json_decode((string) ($vehicle['object'] ?? ''), true);
    if (($setting['key'] ?? '') !== 'ms2_category_sort_by_category') {
        $errors[] = 'System setting key must be ms2_category_sort_by_category';
    }
    if (($setting['namespace'] ?? '') !== 'minishop2') {
        $errors[] = 'System setting namespace must be minishop2';
    }
}

$namespaceVehicle = glob($root . '/modNamespace/*.vehicle');
if ($namespaceVehicle === []) {
    $errors[] = 'modNamespace vehicle missing';
} else {
    $vehicle = include $namespaceVehicle[0];
    $checks++;
    $namespace = json_decode((string) ($vehicle['object'] ?? ''), true);
    if (($namespace['name'] ?? '') !== 'ms2categorysort') {
        $errors[] = 'Namespace must be ms2categorysort';
    }
}

$requiredFiles = [
    '1/ms2categorysort/bootstrap.php',
    '1/ms2categorysort/bootstrap.install.php',
    '1/ms2categorysort/index.php',
    '1/ms2categorysort/elements/plugins/plugin.ms2categorysort.php',
    '1/ms2categorysort/elements/snippets/snippet.ms_products.php',
    '1/ms2categorysort/processors/mgr/categorysort/product/getlist.class.php',
    '1/ms2categorysort/processors/mgr/categorysort/product/sort.class.php',
    '0/ms2categorysort/connector.php',
    '0/ms2categorysort/js/mgr/categorysort.grid.js',
];

$categoryDir = glob($root . '/modCategory/*/', GLOB_ONLYDIR);
$categoryPrefix = $categoryDir !== [] ? rtrim($categoryDir[0], '/') . '/' : '';
foreach ($requiredFiles as $rel) {
    if ($categoryPrefix === '' || !is_readable($categoryPrefix . $rel)) {
        $errors[] = "Required packaged file missing: {$rel}";
    }
}

$forbidden = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    $path = str_replace('\\', '/', $file->getPathname());
    if (str_ends_with($path, '/install.php') || str_contains($path, '/tests/') || str_ends_with($path, 'phpunit.xml')) {
        $forbidden[] = substr($path, strlen($root) + 1);
    }
}
if ($forbidden !== []) {
    $errors[] = 'Forbidden paths in package: ' . implode(', ', $forbidden);
}

rrmdir($tmp);

echo "Transport validation: {$zipPath}\n";
echo "Size: " . round(filesize($zipPath) / 1024, 1) . " KB\n";
echo "Checks run: {$checks}\n";

if ($warnings !== []) {
    echo "\nWarnings:\n";
    foreach ($warnings as $warning) {
        echo "  - {$warning}\n";
    }
}

if ($errors !== []) {
    echo "\nFAILED (" . count($errors) . " errors):\n";
    foreach ($errors as $error) {
        echo "  - {$error}\n";
    }
    exit(1);
}

echo "\nOK — transport package structure is valid.\n";
exit(0);

function findPackageRoot(string $tmp): ?string
{
    $dirs = glob($tmp . '/*', GLOB_ONLYDIR) ?: [];
    foreach ($dirs as $dir) {
        if (is_readable($dir . '/manifest.php')) {
            return $dir;
        }
    }

    return null;
}

function lintPhp(string $path): bool|string
{
    $output = [];
    $code = 0;
    exec('php -l ' . escapeshellarg($path) . ' 2>&1', $output, $code);

    return $code === 0 ? true : implode("\n", $output);
}

function rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($dir);
}
