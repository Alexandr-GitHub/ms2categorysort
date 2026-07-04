<?php

/**
 * Ensure custom MS2 manager event exists; clean broken plugin_event rows after install.
 *
 * @var xPDOTransport $transport
 * @var array $options
 */

$modx = $transport->xpdo;

if ($options[xPDOTransport::PACKAGE_ACTION] !== xPDOTransport::ACTION_INSTALL
    && $options[xPDOTransport::PACKAGE_ACTION] !== xPDOTransport::ACTION_UPGRADE
) {
    return true;
}

require_once MODX_CORE_PATH . 'components/ms2categorysort/bootstrap.install.php';

$eventNames = ['OnMODXInit', 'OnManagerPageBeforeRender', 'msOnManagerCustomCssJs', 'OnDocFormSave'];
foreach ($eventNames as $eventName) {
    ms2categorysort_ensure_system_event($modx, $eventName);
}

$messages = [];
ms2categorysort_remove_broken_plugin_events($modx, $messages);

return true;
