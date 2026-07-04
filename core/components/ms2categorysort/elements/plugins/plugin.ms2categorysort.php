<?php

/** @var modX $modx */
switch ($modx->event->name) {
    case 'OnMODXInit':
        $corePath = $modx->getOption('core_path') . 'components/ms2categorysort/';
        if (is_readable($corePath . 'bootstrap.php')) {
            require_once $corePath . 'bootstrap.php';
        }
        $modx->lexicon->load('ms2categorysort:default');
        $modx->lexicon->load('ms2categorysort:setting');
        if (!class_exists('msCategoryMember', false)) {
            $modx->loadClass('msCategoryMember', MODX_CORE_PATH . 'components/minishop2/model/minishop2/');
        }
        $map = Ms2CategorySort\Domain\XpdoMapExtension::forMsCategoryMember();
        foreach ($map as $class => $extension) {
            if (!isset($modx->map[$class])) {
                continue;
            }
            foreach ($extension as $key => $values) {
                if (!isset($modx->map[$class][$key])) {
                    $modx->map[$class][$key] = $values;
                } elseif (is_array($values)) {
                    $modx->map[$class][$key] = array_merge($modx->map[$class][$key], $values);
                }
            }
        }
        break;

    case 'OnManagerPageBeforeRender':
        $addon = ms2categorysort_get_service($modx);
        $addon->initialize();
        break;

    case 'msOnManagerCustomCssJs':
        if (empty($page) || $page !== 'category_update') {
            break;
        }
        $addon = ms2categorysort_get_service($modx);
        if (!$addon->getCategorySortService()->isCategorySortEnabled()) {
            break;
        }
        $assetsUrl = $modx->getOption('assets_url') . 'components/ms2categorysort/';
        $script = $assetsUrl . 'js/mgr/categorysort.grid.js?v=20260704b';
        if (!empty($controller) && is_object($controller) && method_exists($controller, 'addLastJavascript')) {
            $controller->addLastJavascript($script);
        } else {
            $modx->regClientScript($script);
        }
        break;

    case 'OnDocFormSave':
        /** @var modResource $resource */
        if (!$resource || $resource->get('class_key') !== 'msProduct') {
            break;
        }
        $addon = ms2categorysort_get_service($modx);
        if (!$addon->getCategorySortService()->isCategorySortEnabled()) {
            break;
        }
        $productId = (int) $resource->get('id');
        $categoryIds = [];
        $collection = $modx->getCollection('msCategoryMember', ['product_id' => $productId]);
        foreach ($collection as $member) {
            $categoryIds[] = (int) $member->get('category_id');
        }
        if ($categoryIds !== []) {
            $addon->getCategorySortService()->initMenuIndexForNewMembers($productId, $categoryIds);
        }
        break;
}
