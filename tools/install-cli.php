<?php

declare(strict_types=1);

/**
 * CLI install for ms2categorysort.
 * Run from MODX site root: php tools/ms2categorysort-install.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$root = dirname(__DIR__);
if (!is_readable($root . '/config.core.php')) {
    $root = dirname(__DIR__, 2);
}

define('MODX_API_MODE', true);
require_once $root . '/config.core.php';
require_once MODX_CORE_PATH . 'model/modx/modx.class.php';
require_once MODX_CORE_PATH . 'config/' . MODX_CONFIG_KEY . '.inc.php';
require_once MODX_CORE_PATH . 'components/ms2categorysort/bootstrap.install.php';

$modx = new modX();
$modx->initialize('mgr');

$result = ms2categorysort_run_install($modx);
foreach ($result['messages'] as $line) {
    echo $line . "\n";
}
exit($result['ok'] ? 0 : 1);
