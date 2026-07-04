<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use Ms2CategorySort\Domain\CategorySortRules;
use Ms2CategorySort\Domain\XpdoMapExtension;
use PHPUnit\Framework\TestCase;

final class CategorySortRulesTest extends TestCase
{
    public function testIsSortByCategoryEnabled(): void
    {
        $this->assertFalse(CategorySortRules::isSortByCategoryEnabled(null));
        $this->assertFalse(CategorySortRules::isSortByCategoryEnabled(0));
        $this->assertTrue(CategorySortRules::isSortByCategoryEnabled(1));
        $this->assertTrue(CategorySortRules::isSortByCategoryEnabled('true'));
        $this->assertSame('ms2_category_sort_by_category', CategorySortRules::SYSTEM_SETTING_KEY);
    }

    public function testGetSortExpression(): void
    {
        $expr = CategorySortRules::getSortExpression(42);
        $this->assertStringContainsString('WHEN `msProduct`.`parent` = 42', $expr);
        $this->assertStringContainsString('COALESCE(`CategoryMember`.`menuindex`', $expr);
    }

    public function testSortByCategoryDisabled(): void
    {
        $props = ['sortByCategory' => 0, 'sortby' => 'menuindex', 'parents' => '10'];
        $this->assertFalse(CategorySortRules::shouldApplyCategorySort($props, 10));
    }

    public function testShouldApplyCategorySortWhenEnabled(): void
    {
        $props = ['sortByCategory' => 1, 'sortby' => '{"menuindex":"ASC"}', 'parents' => '10'];
        $this->assertTrue(CategorySortRules::shouldApplyCategorySort($props, 10));
    }

    public function testResolveCategoryContextFromSingleParent(): void
    {
        $this->assertSame(15, CategorySortRules::resolveCategoryContext(['parents' => '15']));
        $this->assertNull(CategorySortRules::resolveCategoryContext(['parents' => '15,20']));
    }

    public function testResolveCategoryContextFromMsCategoryResource(): void
    {
        $resource = new class () {
            /** @var array<string, mixed> */
            private $data = ['class_key' => 'msCategory', 'id' => 99];

            public function get(string $key)
            {
                return $this->data[$key] ?? null;
            }
        };
        $this->assertSame(99, CategorySortRules::resolveCategoryContext(['parents' => '1,2'], $resource));
    }

    public function testParseParentsString(): void
    {
        $parsed = CategorySortRules::parseParentsString('10, 20, -5');
        $this->assertSame([10, 20], $parsed['include']);
        $this->assertSame([5], $parsed['exclude']);
    }

    public function testBuildParentsWhereSql(): void
    {
        $sql = CategorySortRules::buildParentsWhereSql([10, 20], 'modx_');
        $this->assertNotNull($sql);
        $this->assertStringContainsString('modx_ms2_product_categories', $sql);
    }

    public function testMapExtensionContainsMenuindex(): void
    {
        $map = XpdoMapExtension::forMsCategoryMember();
        $this->assertArrayHasKey('menuindex', $map['msCategoryMember']['fields']);
    }
}
