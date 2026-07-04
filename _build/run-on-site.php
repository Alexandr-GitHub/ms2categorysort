<?php

/**
 * One-shot transport build on a live MODX site (CLI or HTTP with key).
 * Not included in the transport package.
 *
 * CLI:  php _build/run-on-site.php
 * HTTP: /_build/ms2categorysort/run-on-site.php?key=ms2categorysort_build_2026
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

$siteRoot = dirname(__DIR__, 2);
$configCore = $siteRoot . '/config.core.php';
if (!is_readable($configCore)) {
    fwrite(STDERR, "config.core.php not found at {$configCore}\n");
    exit(1);
}

require_once $configCore;

if (!defined('MODX_CORE_PATH')) {
    fwrite(STDERR, "MODX_CORE_PATH not defined\n");
    exit(1);
}

if (!is_readable(__DIR__ . '/build.transport.php')) {
    fwrite(STDERR, "Upload full _build/ directory to {$siteRoot}/_build/ms2categorysort/\n");
    exit(1);
}

// build.config.php not required — MODX_CORE_PATH from config.core.php
define('PKG_SITE_ROOT', $siteRoot);

require __DIR__ . '/build.transport.php';
