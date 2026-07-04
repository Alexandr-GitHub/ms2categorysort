<?php

/**
 * @var modX $modx
 */

/** @var modSystemSetting $setting */
$setting = $modx->newObject('modSystemSetting');
$setting->fromArray([
    'key' => 'ms2_category_sort_by_category',
    'value' => '1',
    'xtype' => 'combo-boolean',
    'namespace' => 'minishop2',
    'area' => 'ms2_category',
    'name' => 'setting_ms2_category_sort_by_category',
    'description' => 'setting_ms2_category_sort_by_category_desc',
    'lexicon' => 'ms2categorysort:setting',
], '', true, true);

return [$setting];
