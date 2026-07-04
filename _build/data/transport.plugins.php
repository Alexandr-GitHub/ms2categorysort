<?php

/**
 * @var modX $modx
 * @var array $sources
 */

$pluginFile = $sources['elements'] . 'plugins/plugin.ms2categorysort.php';
if (!is_readable($pluginFile)) {
    $modx->log(modX::LOG_LEVEL_ERROR, 'Plugin file not found: ' . $pluginFile);

    return [];
}

$pluginCode = file_get_contents($pluginFile);
$pluginCode = trim(str_replace(['<?php', '?>'], '', $pluginCode));

/** @var modPlugin $plugin */
$plugin = $modx->newObject('modPlugin');
$plugin->fromArray([
    'name' => 'ms2CategorySort',
    'description' => 'Per-category menuindex для MiniShop2',
    'plugincode' => $pluginCode,
    'disabled' => 0,
    'locked' => 0,
], '', true, true);

$pluginEvents = include $sources['data'] . 'transport.plugin_events.php';
if (!empty($pluginEvents)) {
    $plugin->addMany($pluginEvents);
}

return [$plugin];
