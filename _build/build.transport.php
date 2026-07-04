<?php

/**
 * Build MODX transport package (modPackageBuilder).
 *
 * Prerequisites:
 *   1. Copy _build/build.config.sample.php → _build/build.config.php
 *   2. Set MODX_CORE_PATH to a working MODX Revolution install
 *
 * Run from repository root:
 *   php _build/build.transport.php
 *
 * Output:
 *   {MODX_CORE_PATH}packages/ms2categorysort-{version}-{release}.transport.zip
 *   _build/dist/ms2categorysort-{version}-{release}.transport.zip  (copy for repo)
 *
 * @see https://docs.modx.com/current/en/extending-modx/transport-packages/build-script
 */

$tstart = microtime(true);
set_time_limit(0);

define('PKG_NAME', 'ms2CategorySort');
define('PKG_NAME_LOWER', 'ms2categorysort');
define('PKG_VERSION', '1.0.0');
define('PKG_RELEASE', 'pl');

if (defined('PKG_SITE_ROOT')) {
    $root = rtrim(PKG_SITE_ROOT, '/\\') . '/';
} else {
    $root = dirname(__DIR__) . '/';
}

$sources = [
    'root' => $root,
    'build' => __DIR__ . '/',
    'data' => __DIR__ . '/data/',
    'resolvers' => __DIR__ . '/resolvers/',
    'elements' => $root . 'core/components/' . PKG_NAME_LOWER . '/elements/',
    'docs' => $root . 'core/components/ms2categorysort/docs/',
    'source_assets' => $root . 'assets/components/' . PKG_NAME_LOWER,
    'source_core' => $root . 'core/components/' . PKG_NAME_LOWER,
];

if (!defined('MODX_CORE_PATH')) {
    $buildConfig = __DIR__ . '/build.config.php';
    if (!is_readable($buildConfig)) {
        fwrite(STDERR, "Create _build/build.config.php from build.config.sample.php and set MODX_CORE_PATH,\n");
        fwrite(STDERR, "or run via _build/run-on-site.php on a MODX installation.\n");
        exit(1);
    }
    require_once $buildConfig;
}

if (!defined('MODX_CORE_PATH') || !is_dir(MODX_CORE_PATH)) {
    fwrite(STDERR, "MODX_CORE_PATH is missing or not a directory.\n");
    exit(1);
}

require_once MODX_CORE_PATH . 'model/modx/modx.class.php';

$modx = new modX();
$modx->initialize('mgr');
echo '<pre>';
$modx->setLogLevel(modX::LOG_LEVEL_INFO);
$modx->setLogTarget('ECHO');

$modx->loadClass('transport.modPackageBuilder', '', false, true);
/** @var modPackageBuilder $builder */
$builder = new modPackageBuilder($modx);
$builder->createPackage(PKG_NAME_LOWER, PKG_VERSION, PKG_RELEASE);
$builder->registerNamespace(
    PKG_NAME_LOWER,
    false,
    true,
    '{core_path}components/' . PKG_NAME_LOWER . '/',
    '{assets_path}components/' . PKG_NAME_LOWER . '/'
);

$changelogFile = $sources['docs'] . 'changelog.txt';
$builder->setPackageAttributes([
    'license' => ms2categorysort_read_build_file($sources, 'LICENSE', 'LICENSE'),
    'readme' => ms2categorysort_read_build_file($sources, 'README.md', 'README.md'),
    'changelog' => is_readable($changelogFile) ? file_get_contents($changelogFile) : '',
]);

$modx->log(modX::LOG_LEVEL_INFO, 'Staging component files (excluding dev paths)...');
$staged = ms2categorysort_stage_package_files($sources);

/** @var modCategory $category */
$category = $modx->newObject('modCategory');
$category->set('category', PKG_NAME);

$modx->log(modX::LOG_LEVEL_INFO, 'Packaging plugin...');
$plugins = include $sources['data'] . 'transport.plugins.php';
if (empty($plugins)) {
    $modx->log(modX::LOG_LEVEL_ERROR, 'Could not package plugin.');
    exit(1);
}
$category->addMany($plugins);

$categoryAttr = [
    xPDOTransport::UNIQUE_KEY => 'category',
    xPDOTransport::PRESERVE_KEYS => false,
    xPDOTransport::UPDATE_OBJECT => true,
    xPDOTransport::RELATED_OBJECTS => true,
    xPDOTransport::RELATED_OBJECT_ATTRIBUTES => [
        'Plugins' => [
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UPDATE_OBJECT => true,
            xPDOTransport::UNIQUE_KEY => 'name',
            xPDOTransport::RELATED_OBJECTS => true,
            xPDOTransport::RELATED_OBJECT_ATTRIBUTES => [
                'PluginEvents' => [
                    xPDOTransport::PRESERVE_KEYS => false,
                    xPDOTransport::UPDATE_OBJECT => true,
                    xPDOTransport::UNIQUE_KEY => ['event'],
                ],
            ],
        ],
    ],
];

$vehicle = $builder->createVehicle($category, $categoryAttr);

$modx->log(modX::LOG_LEVEL_INFO, 'Adding file resolvers...');
$vehicle->resolve('file', [
    'source' => $staged['source_assets'],
    'target' => "return MODX_ASSETS_PATH . 'components/';",
]);
$vehicle->resolve('file', [
    'source' => $staged['source_core'],
    'target' => "return MODX_CORE_PATH . 'components/';",
]);

$modx->log(modX::LOG_LEVEL_INFO, 'Adding PHP resolvers...');
$vehicle->resolve('php', ['source' => $sources['resolvers'] . 'resolve.tables.php']);
$vehicle->resolve('php', ['source' => $sources['resolvers'] . 'resolve.migrate.php']);
$vehicle->resolve('php', ['source' => $sources['resolvers'] . 'resolve.ms2_plugins.php']);
$vehicle->resolve('php', ['source' => $sources['resolvers'] . 'resolve.snippet.php']);
$vehicle->resolve('php', ['source' => $sources['resolvers'] . 'resolve.events.php']);

$builder->putVehicle($vehicle);
unset($vehicle, $category, $plugins);

$modx->log(modX::LOG_LEVEL_INFO, 'Packaging system settings...');
$settings = include $sources['data'] . 'transport.settings.php';
if (empty($settings)) {
    $modx->log(modX::LOG_LEVEL_ERROR, 'Could not package system settings.');
    exit(1);
}

foreach ($settings as $setting) {
    $settingVehicle = $builder->createVehicle($setting, [
        xPDOTransport::UNIQUE_KEY => 'key',
        xPDOTransport::PRESERVE_KEYS => true,
        xPDOTransport::UPDATE_OBJECT => false,
    ]);
    $builder->putVehicle($settingVehicle);
}
unset($settings, $setting, $settingVehicle);

$modx->log(modX::LOG_LEVEL_INFO, 'Packing transport zip...');
if (!$builder->pack()) {
    $modx->log(modX::LOG_LEVEL_ERROR, 'Package build failed.');
    exit(1);
}

$signature = PKG_NAME_LOWER . '-' . PKG_VERSION . '-' . PKG_RELEASE;
$builtZip = MODX_CORE_PATH . 'packages/' . $signature . '.transport.zip';
$distDir = $sources['build'] . 'dist/';

if (is_readable($builtZip)) {
    if (!is_dir($distDir)) {
        mkdir($distDir, 0755, true);
    }
    $distZip = $distDir . $signature . '.transport.zip';
    copy($builtZip, $distZip);
    $modx->log(modX::LOG_LEVEL_INFO, 'Copied to ' . $distZip);
} else {
    $modx->log(modX::LOG_LEVEL_WARN, 'Built zip not found at ' . $builtZip);
}

ms2categorysort_delete_dir($sources['build'] . 'staging/');

$elapsed = sprintf('%.4f s', microtime(true) - $tstart);
$modx->log(modX::LOG_LEVEL_INFO, "\nPackage built: {$signature}.transport.zip\nExecution time: {$elapsed}\n");

session_write_close();
exit(0);

function ms2categorysort_read_build_file(array $sources, string $rootRel, string $dataRel): string
{
    $rootPath = $sources['root'] . $rootRel;
    if (is_readable($rootPath)) {
        return (string) file_get_contents($rootPath);
    }

    $dataPath = $sources['data'] . $dataRel;
    if (is_readable($dataPath)) {
        return (string) file_get_contents($dataPath);
    }

    return '';
}

/**
 * @return array{source_core: string, source_assets: string}
 */
function ms2categorysort_stage_package_files(array $sources): array
{
    $stagingRoot = $sources['build'] . 'staging/';
    $coreDest = $stagingRoot . 'core/components/' . PKG_NAME_LOWER;
    $assetsDest = $stagingRoot . 'assets/components/' . PKG_NAME_LOWER;

    ms2categorysort_delete_dir($stagingRoot);

    ms2categorysort_copy_tree(
        $sources['source_core'],
        $coreDest,
        ['tests', 'vendor', '_build', 'composer.json', 'composer.lock', 'phpunit.xml', '.gitignore']
    );
    ms2categorysort_copy_tree(
        $sources['source_assets'],
        $assetsDest,
        ['install.php']
    );

    return [
        'source_core' => $coreDest,
        'source_assets' => $assetsDest,
    ];
}

function ms2categorysort_copy_tree(string $src, string $dst, array $excludeNames = []): void
{
    if (!is_dir($src)) {
        throw new RuntimeException('Source directory not found: ' . $src);
    }

    if (!is_dir($dst) && !mkdir($dst, 0755, true) && !is_dir($dst)) {
        throw new RuntimeException('Cannot create directory: ' . $dst);
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        /** @var SplFileInfo $item */
        $relative = substr($item->getPathname(), strlen(rtrim($src, '/\\')) + 1);
        $parts = explode(DIRECTORY_SEPARATOR, $relative);
        $skip = false;
        foreach ($excludeNames as $exclude) {
            if ($parts[0] === $exclude || in_array($exclude, $parts, true)) {
                $skip = true;
                break;
            }
        }
        if ($skip) {
            continue;
        }

        $target = $dst . DIRECTORY_SEPARATOR . $relative;
        if ($item->isDir()) {
            if (!is_dir($target) && !mkdir($target, 0755, true) && !is_dir($target)) {
                throw new RuntimeException('Cannot create directory: ' . $target);
            }
            continue;
        }

        $targetDir = dirname($target);
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            throw new RuntimeException('Cannot create directory: ' . $targetDir);
        }
        if (!copy($item->getPathname(), $target)) {
            throw new RuntimeException('Cannot copy ' . $item->getPathname());
        }
    }
}

function ms2categorysort_delete_dir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        /** @var SplFileInfo $item */
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }

    rmdir($dir);
}
