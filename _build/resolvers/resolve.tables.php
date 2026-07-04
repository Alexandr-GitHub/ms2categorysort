<?php

/**
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
ms2categorysort_ensure_schema($modx);

return true;
