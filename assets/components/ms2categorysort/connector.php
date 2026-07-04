<?php

/** @noinspection PhpIncludeInspection */

require_once dirname(__FILE__, 4) . '/config.core.php';
/** @noinspection PhpIncludeInspection */
require_once MODX_CORE_PATH . 'config/' . MODX_CONFIG_KEY . '.inc.php';
/** @noinspection PhpIncludeInspection */
require_once MODX_CONNECTORS_PATH . 'index.php';

/** @var modX $modx */
require_once MODX_CORE_PATH . 'components/ms2categorysort/bootstrap.php';

ms2categorysort_get_service($modx);
$modx->lexicon->load('ms2categorysort:default');

$path = MODX_CORE_PATH . 'components/ms2categorysort/processors/';
/** @var modConnectorRequest $request */
$request = $modx->request;
$request->handleRequest([
    'processors_path' => $path,
    'location' => '',
]);
