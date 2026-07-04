<?php

/**
 * @var modX $modx
 * @var xPDOTransport $transport
 */

if ($options[xPDOTransport::PACKAGE_ACTION] === xPDOTransport::ACTION_INSTALL
    || $options[xPDOTransport::PACKAGE_ACTION] === xPDOTransport::ACTION_UPGRADE
) {
    require_once MODX_CORE_PATH . 'components/ms2categorysort/bootstrap.php';
    $addon = ms2categorysort_get_service($modx);
    $addon->getCategorySortService()->migrateExistingMenuIndexes();
}

return true;
