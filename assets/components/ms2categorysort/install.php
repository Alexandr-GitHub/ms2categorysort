<?php

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

if (($_GET['key'] ?? '') !== 'ms2categorysort') {
    http_response_code(403);
    exit('Forbidden');
}

define('MODX_API_MODE', true);
require_once dirname(__FILE__, 4) . '/config.core.php';
require_once MODX_CORE_PATH . 'model/modx/modx.class.php';
require_once MODX_CORE_PATH . 'config/' . MODX_CONFIG_KEY . '.inc.php';
require_once MODX_CORE_PATH . 'components/ms2categorysort/bootstrap.install.php';

$modx = new modX();
$modx->initialize('mgr');

try {
    $result = ms2categorysort_run_install($modx);
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Install error: ' . $e->getMessage() . "\n";
    echo $e->getFile() . ':' . $e->getLine() . "\n";
    exit(1);
}
foreach ($result['messages'] as $line) {
    echo $line . "\n";
}
echo $result['ok'] ? "\nInstall OK\n" : "\nInstall FAILED\n";
