<?php

/**
 * @var modX $modx
 * @var xPDOTransport $transport
 */

if ($options[xPDOTransport::PACKAGE_ACTION] === xPDOTransport::ACTION_INSTALL
    || $options[xPDOTransport::PACKAGE_ACTION] === xPDOTransport::ACTION_UPGRADE
) {
    $table = $modx->getTableName('msCategoryMember');
    $sql = "SHOW COLUMNS FROM {$table} LIKE 'menuindex'";
    $stmt = $modx->prepare($sql);
    if ($stmt && $stmt->execute() && !$stmt->fetch(PDO::FETCH_ASSOC)) {
        $modx->exec(
            "ALTER TABLE {$table} "
            . "ADD COLUMN `menuindex` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `category_id`, "
            . "ADD INDEX `category_menuindex` (`category_id`, `menuindex`)"
        );
    }

    $plugins = $modx->fromJSON($modx->getOption('ms2_plugins', null, '{}')) ?: [];
    if (!is_array($plugins)) {
        $plugins = [];
    }
    $plugins['categorysort'] = ['controller' => '{core_path}components/ms2categorysort/index.php'];
    $modx->setOption('ms2_plugins', $modx->toJSON($plugins));
}

return true;
