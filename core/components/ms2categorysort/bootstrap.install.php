<?php

declare(strict_types=1);

/**
 * Shared install steps for ms2categorysort (CLI + web deploy).
 *
 * @return array{ok: bool, messages: string[]}
 */
function ms2categorysort_run_install(modX $modx): array
{
    $messages = [];

    require_once MODX_CORE_PATH . 'components/ms2categorysort/bootstrap.php';

    /** @var Ms2CategorySort $addon */
    $addon = ms2categorysort_get_service($modx);

    $service = $addon->getCategorySortService();
    $service->ensureSchema();
    $messages[] = 'Схема БД: menuindex в ms2_product_categories — OK';
    $service->migrateExistingMenuIndexes();
    $messages[] = 'Миграция данных menuindex — OK';

    ms2categorysort_ensure_namespace($modx, $messages);

    $settingKey = \Ms2CategorySort\Domain\CategorySortRules::SYSTEM_SETTING_KEY;
    $sortSetting = $modx->getObject('modSystemSetting', ['key' => $settingKey]);
    $isNewSetting = !$sortSetting;
    if ($isNewSetting) {
        $sortSetting = $modx->newObject('modSystemSetting');
        $sortSetting->set('key', $settingKey);
    }
    $sortSetting->fromArray([
        'value' => $isNewSetting ? '1' : $sortSetting->get('value'),
        'xtype' => 'combo-boolean',
        'namespace' => 'minishop2',
        'area' => 'ms2_category',
        'name' => 'Сортировка по menuindex внутри категории',
        'description' => 'Отдельный порядок товаров в каждой категории (в т.ч. доп. категории). Выкл. — стандартный MS2.',
    ]);
    if ($sortSetting->save()) {
        $messages[] = 'System setting ' . $settingKey . ' — OK';
    } else {
        $messages[] = 'WARN: не удалось сохранить ' . $settingKey;
    }

    $plugins = $modx->fromJSON($modx->getOption('ms2_plugins', null, '{}')) ?: [];
    if (!is_array($plugins)) {
        $plugins = [];
    }
    $plugins['categorysort'] = [
        'controller' => '{core_path}components/ms2categorysort/index.php',
    ];
    $pluginsJson = $modx->toJSON($plugins);
    $setting = $modx->getObject('modSystemSetting', ['key' => 'ms2_plugins']);
    if ($setting) {
        $setting->set('value', $pluginsJson);
        $setting->save();
        $messages[] = 'ms2_plugins обновлён в system_settings';
    } else {
        $messages[] = 'WARN: system_setting ms2_plugins не найден';
    }

    ms2categorysort_register_plugin($modx, $messages);

    $snippet = $modx->getObject('modSnippet', ['name' => 'msProducts']);
    if ($snippet) {
        $snippetFile = MODX_CORE_PATH . 'components/ms2categorysort/elements/snippets/snippet.ms_products.php';
        if (!is_readable($snippetFile)) {
            $messages[] = 'WARN: файл snippet msProducts не найден: ' . $snippetFile;
        } else {
            $snippetCode = file_get_contents($snippetFile);
            $snippet->set('snippet', $snippetCode);
            $sourceId = 1;
            $source = $modx->getObject('modMediaSource', ['id' => 1]);
            if (!$source) {
                $source = $modx->getObject('modMediaSource', ['name' => 'Filesystem']);
            }
            if ($source) {
                $sourceId = (int) $source->get('id');
            }
            $snippet->set('source', $sourceId);
            $snippet->set('static_file', 'core/components/ms2categorysort/elements/snippets/snippet.ms_products.php');
            $snippet->save();
            $messages[] = 'Snippet msProducts обновлён из addon (source=' . $sourceId . ', '
                . (strpos($snippetCode, 'ms2categorysort') !== false ? 'OK' : 'WARN no marker') . ')';
        }
    } else {
        $messages[] = 'WARN: snippet msProducts не найден';
    }

    $modx->getCacheManager()->refresh();
    $messages[] = 'Кэш MODX очищен';

    return ['ok' => true, 'messages' => $messages];
}

function ms2categorysort_format_save_errors($object): string
{
    if (!is_object($object) || !method_exists($object, 'getErrors')) {
        return 'save failed';
    }

    $errors = $object->getErrors();
    if (!is_array($errors) || $errors === []) {
        return 'save failed';
    }

    $parts = [];
    foreach ($errors as $field => $msg) {
        $parts[] = $field . ': ' . (is_array($msg) ? implode(', ', $msg) : $msg);
    }

    return implode('; ', $parts);
}

/**
 * @param string[] $messages
 */
function ms2categorysort_register_plugin(modX $modx, array &$messages): void
{
    $pluginFile = MODX_CORE_PATH . 'components/ms2categorysort/elements/plugins/plugin.ms2categorysort.php';
    if (!is_readable($pluginFile)) {
        $messages[] = 'WARN: файл plugin ms2CategorySort не найден';

        return;
    }

    ms2categorysort_remove_broken_plugin_events($modx, $messages);

    /** @var modPlugin|null $plugin */
    $plugin = $modx->getObject('modPlugin', ['name' => 'ms2CategorySort']);
    if (!$plugin) {
        $plugin = $modx->newObject('modPlugin');
    }

    $plugin->fromArray([
        'name' => 'ms2CategorySort',
        'description' => 'Per-category menuindex для MiniShop2',
        'plugincode' => file_get_contents($pluginFile),
        'category' => 0,
        'disabled' => 0,
        'locked' => 0,
    ]);

    if (!$plugin->save()) {
        $messages[] = 'WARN: Plugin ms2CategorySort не сохранён: '
            . ms2categorysort_format_save_errors($plugin);

        return;
    }

    $pluginId = (int) $plugin->get('id');
    if ($pluginId <= 0) {
        $messages[] = 'WARN: Plugin ms2CategorySort без id после save — события пропущены';

        return;
    }

    $messages[] = 'Plugin ms2CategorySort — OK (id=' . $pluginId . ')';

    $eventNames = ['OnMODXInit', 'OnManagerPageBeforeRender', 'msOnManagerCustomCssJs', 'OnDocFormSave'];
    foreach ($eventNames as $eventName) {
        ms2categorysort_ensure_system_event($modx, $eventName);
    }

    $existing = $modx->getCollection('modPluginEvent', ['pluginid' => $pluginId]);
    foreach ($existing as $pluginEvent) {
        $pluginEvent->remove();
    }

    $failedEvents = [];
    foreach ($eventNames as $eventName) {
        /** @var modPluginEvent $pluginEvent */
        $pluginEvent = $modx->newObject('modPluginEvent');
        $pluginEvent->set('pluginid', $pluginId);
        $pluginEvent->set('event', $eventName);
        $pluginEvent->set('priority', 0);
        $pluginEvent->set('propertyset', 0);
        if (!$pluginEvent->save()) {
            $failedEvents[] = $eventName . ': ' . ms2categorysort_format_save_errors($pluginEvent);
        }
    }

    if ($failedEvents !== []) {
        $messages[] = 'WARN: события plugin: ' . implode(' | ', $failedEvents);
    } else {
        $messages[] = 'События plugin — OK';
    }
}

/**
 * @param string[] $messages
 */
function ms2categorysort_remove_broken_plugin_events(modX $modx, array &$messages): void
{
    $table = $modx->getTableName('modPluginEvent');
    $sql = "DELETE FROM {$table} WHERE `pluginid` = 0 OR `event` = '' OR `event` IS NULL";
    if ($modx->exec($sql) === false) {
        $messages[] = 'WARN: не удалось очистить битые plugin_events';

        return;
    }

    $messages[] = 'Очистка битых plugin_events — OK';
}

function ms2categorysort_ensure_system_event(modX $modx, string $eventName): void
{
    if ($modx->getCount('modEvent', ['name' => $eventName]) > 0) {
        return;
    }

    /** @var modEvent $event */
    $event = $modx->newObject('modEvent');
    $event->fromArray([
        'name' => $eventName,
        'service' => 1,
        'groupname' => 'ms2categorysort',
    ]);
    $event->save();
}

/**
 * @param string[] $messages
 */
function ms2categorysort_ensure_namespace(modX $modx, array &$messages): void
{
    /** @var modNamespace|null $namespace */
    $namespace = $modx->getObject('modNamespace', ['name' => 'ms2categorysort']);
    if (!$namespace) {
        $namespace = $modx->newObject('modNamespace');
        $namespace->set('name', 'ms2categorysort');
    }
    $namespace->fromArray([
        'path' => '{core_path}components/ms2categorysort/',
        'assets_path' => '{assets_path}components/ms2categorysort/',
    ]);
    if ($namespace->save()) {
        $messages[] = 'Namespace ms2categorysort — OK';
    } else {
        $messages[] = 'WARN: namespace ms2categorysort не сохранён';
    }
}
