<?php

declare(strict_types=1);

/**
 * PSR-4 autoload for Ms2CategorySort (MODX addon, update-safe).
 */
$autoload = __DIR__ . '/src/Infrastructure/Autoload.php';
if (is_readable($autoload)) {
    require_once $autoload;
    Ms2CategorySort\Infrastructure\Autoload::register(__DIR__ . '/src');
}

/**
 * Load addon service (modX::getService is unreliable for this class name).
 */
function ms2categorysort_get_service(modX $modx): ms2categorysort
{
    static $modelLoaded = false;
    if (!$modelLoaded) {
        require_once __DIR__ . '/model/ms2categorysort/ms2categorysort.class.php';
        $modelLoaded = true;
    }

    return new ms2categorysort($modx);
}
