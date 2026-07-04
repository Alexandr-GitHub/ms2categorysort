#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Lightweight test runner when PHPUnit phar is unavailable (dev only).
 */
require_once dirname(__DIR__) . '/tests/bootstrap.php';

use Ms2CategorySort\Domain\CategorySortRules;

$failures = 0;

function assertTrue(bool $cond, string $msg): void
{
    global $failures;
    if (!$cond) {
        echo "FAIL: {$msg}\n";
        $failures++;
    }
}

assertTrue(!CategorySortRules::isSortByCategoryEnabled(0), 'disabled flag');
assertTrue(CategorySortRules::isSortByCategoryEnabled(1), 'enabled flag');
assertTrue(
    CategorySortRules::shouldApplyCategorySort(['sortByCategory' => 1, 'sortby' => 'menuindex'], 5),
    'should apply'
);
assertTrue(
    stripos(CategorySortRules::getSortExpression(7), 'parent` = 7') !== false,
    'sort expression'
);
assertTrue(CategorySortRules::resolveCategoryContext(['parents' => '12']) === 12, 'context');
assertTrue(
    CategorySortRules::SYSTEM_SETTING_KEY === 'ms2_category_sort_by_category',
    'system setting key'
);

if ($failures > 0) {
    echo "{$failures} test(s) failed.\n";
    exit(1);
}

echo "All CategorySortRules smoke assertions passed.\n";
