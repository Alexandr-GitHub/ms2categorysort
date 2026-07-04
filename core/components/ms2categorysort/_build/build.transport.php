<?php

/**
 * Build transport package for ms2categorysort.
 * Run from repo root: php core/components/ms2categorysort/_build/build.transport.php
 */

$tstart = microtime(true);
define('MODX_API_MODE', true);

$root = dirname(__DIR__, 4);
require_once $root . '/config.core.php';
require_once MODX_CORE_PATH . 'model/modx/modx.class.php';

$modx = new modX();
$modx->initialize('mgr');

$component = dirname(__DIR__);
$packageName = 'ms2categorysort';
$version = '1.0.0';

/** @var modTransportPackage $package */
$package = $modx->newObject('transport.modTransportPackage');
$package->fromArray([
    'name' => $packageName,
    'version' => $version,
    'release' => 'pl',
    'signature' => "{$packageName}-{$version}-pl",
]);

$package->save();
$vehicle = $modx->newObject('transport.xPDOTransportVehicle');
$vehicle->set('resolve_files', true);
$vehicle->set('source', $component);
$vehicle->set('category', 'components');

$exclude = [
    '/tests/',
    '/vendor/',
    '/composer.json',
    '/composer.lock',
    '/phpunit.xml',
    '/phpunit.phar',
    '/.gitkeep',
];

$vehicle->set('exclude_files', $exclude);
$vehicle->set('resolver', [
    ['type' => 'php', 'source' => 'resolvers/resolve.tables.php'],
    ['type' => 'php', 'source' => 'resolvers/resolve.migrate_menuindex.php'],
]);

$package->addVehicle($vehicle, ['vehicle_class' => 'xPDOTransportVehicle']);

$package->pack();

echo "Built {$packageName}-{$version}-pl.transport.zip in " . round(microtime(true) - $tstart, 2) . "s\n";
