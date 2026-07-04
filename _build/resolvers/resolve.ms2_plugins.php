<?php

/**
 * @var xPDOTransport $transport
 * @var array $options
 */

$modx = $transport->xpdo;

require_once MODX_CORE_PATH . 'components/ms2categorysort/bootstrap.install.php';

$action = $options[xPDOTransport::PACKAGE_ACTION] ?? null;

if ($action === xPDOTransport::ACTION_INSTALL || $action === xPDOTransport::ACTION_UPGRADE) {
    ms2categorysort_register_ms2_plugin_entry($modx, false);
} elseif ($action === xPDOTransport::ACTION_UNINSTALL) {
    ms2categorysort_register_ms2_plugin_entry($modx, true);
}

return true;
