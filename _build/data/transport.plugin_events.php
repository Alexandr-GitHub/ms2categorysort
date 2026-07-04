<?php

/**
 * @var modX $modx
 */

$events = [];
$eventNames = [
    'OnMODXInit',
    'OnManagerPageBeforeRender',
    'msOnManagerCustomCssJs',
    'OnDocFormSave',
];

foreach ($eventNames as $index => $eventName) {
    /** @var modPluginEvent $pluginEvent */
    $pluginEvent = $modx->newObject('modPluginEvent');
    $pluginEvent->fromArray([
        'event' => $eventName,
        'priority' => 0,
        'propertyset' => 0,
    ], '', true, true);
    $events[$index] = $pluginEvent;
}

return $events;
