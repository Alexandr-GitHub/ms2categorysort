<?php

/**
 * Test transport package on a live MODX site: scan, install/upgrade, verify.
 *
 * CLI:  php _build/test-package-on-site.php
 * HTTP: /_build/ms2categorysort/test-package-on-site.php?key=ms2categorysort_build_2026
 */

declare(strict_types=1);

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    $key = $_GET['key'] ?? '';
    if ($key !== 'ms2categorysort_build_2026') {
        http_response_code(403);
        exit('Forbidden');
    }
    header('Content-Type: text/plain; charset=utf-8');
}

$results = [];
$failed = false;
$fail = function (string $msg) use (&$results, &$failed): void {
    $results[] = 'FAIL: ' . $msg;
    $failed = true;
};

register_shutdown_function(static function () use (&$results, &$failed): void {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $results[] = 'FAIL: fatal ' . $error['message'] . ' @ ' . $error['file'] . ':' . $error['line'];
        $failed = true;
        output($results);
    }
});

try {
    $siteRoot = dirname(__DIR__, 2);
    $configCore = $siteRoot . '/config.core.php';
    if (!is_readable($configCore)) {
        throw new RuntimeException('config.core.php not found at ' . $configCore);
    }

    require_once $configCore;
    require_once MODX_CORE_PATH . 'model/modx/modx.class.php';

    $signature = 'ms2categorysort-1.0.0-pl';
    $zipName = $signature . '.transport.zip';
    $distZip = __DIR__ . '/dist/' . $zipName;
    $packagesZip = MODX_CORE_PATH . 'packages/' . $zipName;

    $modx = new modX();
    $modx->initialize('mgr');
    $modx->setLogLevel(modX::LOG_LEVEL_ERROR);
    $modx->setLogTarget('ECHO');

    ms2categorysort_test_auth_mgr($modx);

    if (!is_readable($distZip)) {
        throw new RuntimeException("Dist zip missing: {$distZip}");
    }

    if (!is_dir(MODX_CORE_PATH . 'packages')) {
        mkdir(MODX_CORE_PATH . 'packages', 0755, true);
    }
    if (!copy($distZip, $packagesZip)) {
        throw new RuntimeException('Cannot copy zip to core/packages/');
    }
    $results[] = 'OK: zip copied to core/packages/ (' . round(filesize($packagesZip) / 1024, 1) . ' KB)';

    /** @var modTransportProvider|null $provider */
    $provider = $modx->getObject('transport.modTransportProvider', ['name' => 'local']);
    if (!$provider) {
        $provider = $modx->getObject('transport.modTransportProvider', ['id' => 1]);
    }
    if (!$provider) {
        throw new RuntimeException('Local transport provider not found');
    }

    if (method_exists($provider, 'scanLocalPackages')) {
        $provider->scanLocalPackages();
    } elseif (method_exists($provider, 'scan')) {
        $provider->scan();
    } else {
        $scan = $modx->runProcessor('workspace/packages/scanlocal');
        if ($scan->isError()) {
            throw new RuntimeException('scanlocal: ' . $scan->getMessage());
        }
    }
    $results[] = 'OK: local packages scanned';

    /** @var modTransportPackage|null $package */
    $package = $modx->getObject('transport.modTransportPackage', ['signature' => $signature]);
    if (!$package) {
        throw new RuntimeException('Package not found after scan: ' . $signature);
    }
    $results[] = 'OK: package in DB, installed=' . var_export($package->get('installed'), true);

    $action = $package->get('installed') ? 'upgrade' : 'install';
    if (!$package->install()) {
        throw new RuntimeException('Package ' . $action . '() returned false');
    }
    $results[] = 'OK: package ' . $action . ' completed';

    /** @var modPlugin|null $plugin */
    $plugin = $modx->getObject('modPlugin', ['name' => 'ms2CategorySort']);
    if (!$plugin || $plugin->get('disabled')) {
        $fail('Plugin ms2CategorySort not active after install');
    } else {
        $events = $modx->getCollection('modPluginEvent', ['pluginid' => $plugin->get('id')]);
        $results[] = 'OK: plugin ms2CategorySort id=' . $plugin->get('id') . ', events=' . count($events);
    }

    /** @var modSystemSetting|null $setting */
    $setting = $modx->getObject('modSystemSetting', ['key' => 'ms2_category_sort_by_category']);
    if (!$setting) {
        $fail('System setting ms2_category_sort_by_category missing');
    } else {
        $results[] = 'OK: setting ms2_category_sort_by_category value=' . $setting->get('value');
    }

    $plugins = $modx->fromJSON($modx->getOption('ms2_plugins', null, '{}')) ?: [];
    if (empty($plugins['categorysort']['controller'])) {
        $fail('ms2_plugins.categorysort not registered');
    } else {
        $results[] = 'OK: ms2_plugins.categorysort registered';
    }

    /** @var modSnippet|null $snippet */
    $snippet = $modx->getObject('modSnippet', ['name' => 'msProducts']);
    if (!$snippet || strpos($snippet->get('snippet'), 'ms2categorysort') === false) {
        $fail('Snippet msProducts not patched with ms2categorysort code');
    } else {
        $results[] = 'OK: snippet msProducts contains ms2categorysort marker';
    }

    $table = $modx->getTableName('msCategoryMember');
    $stmt = $modx->prepare("SHOW COLUMNS FROM {$table} LIKE 'menuindex'");
    if (!$stmt || !$stmt->execute() || !$stmt->fetch(PDO::FETCH_ASSOC)) {
        $fail('Column menuindex missing in ms2_product_categories');
    } else {
        $results[] = 'OK: ms2_product_categories.menuindex exists';
    }

    $paths = [
        MODX_CORE_PATH . 'components/ms2categorysort/bootstrap.php',
        MODX_ASSETS_PATH . 'components/ms2categorysort/connector.php',
        MODX_ASSETS_PATH . 'components/ms2categorysort/js/mgr/categorysort.grid.js',
    ];
    foreach ($paths as $path) {
        if (!is_readable($path)) {
            $fail('Missing installed file: ' . $path);
        }
    }
    if (count($paths) === 3 && is_readable($paths[0]) && is_readable($paths[1]) && is_readable($paths[2])) {
        $results[] = 'OK: core/assets files installed on disk';
    }

    /** @var modNamespace|null $namespace */
    $namespace = $modx->getObject('modNamespace', ['name' => 'ms2categorysort']);
    if (!$namespace) {
        $fail('Namespace ms2categorysort missing');
    } else {
        $results[] = 'OK: namespace ms2categorysort path=' . $namespace->get('path');
    }

    $modx->getCacheManager()->refresh();
    $results[] = 'OK: cache refreshed';
} catch (Throwable $e) {
    $fail($e->getMessage());
}

output($results);
exit($failed ? 1 : 0);

function ms2categorysort_test_auth_mgr(modX $modx): void
{
    /** @var modUser|null $user */
    $user = $modx->getObject('modUser', 1);
    if (!$user) {
        return;
    }

    $modx->user = $user;
    $modx->getRequest();
    $ctx = $modx->getContext('mgr');
    if ($ctx) {
        $ctx->set('modx.user.contextTokens', ['mgr' => (int) $user->get('id')]);
    }
}

function output(array $results): void
{
    foreach ($results as $line) {
        echo $line . "\n";
    }
}
