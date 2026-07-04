<?php

declare(strict_types=1);

namespace Ms2CategorySort\Domain;

/**
 * Pure domain rules (no MODX / DB). Testable without framework.
 */
final class CategorySortRules
{
    public const ALIEN_FALLBACK = 999999;
    public const MEMBER_JOIN_ALIAS = 'CategoryMember';
    public const SYSTEM_SETTING_KEY = 'ms2_category_sort_by_category';

    /**
     * @param mixed $value
     */
    public static function isSortByCategoryEnabled($value): bool
    {
        if ($value === true || $value === 1 || $value === '1') {
            return true;
        }

        return is_string($value) && strtolower($value) === 'true';
    }

    public static function getSortExpression(
        int $categoryId,
        string $productAlias = 'msProduct',
        string $memberAlias = self::MEMBER_JOIN_ALIAS
    ): string {
        $fallback = self::ALIEN_FALLBACK;

        return "CASE WHEN `{$productAlias}`.`parent` = {$categoryId} "
            . "THEN `{$productAlias}`.`menuindex` "
            . "ELSE COALESCE(`{$memberAlias}`.`menuindex`, {$fallback}) END";
    }

    public static function sortByContainsMenuindex(string $sortby): bool
    {
        return stripos($sortby, 'menuindex') !== false;
    }

    /**
     * @param array<string, mixed> $scriptProperties
     */
    public static function shouldApplyCategorySort(array $scriptProperties, ?int $categoryId): bool
    {
        if (!self::isSortByCategoryEnabled($scriptProperties['sortByCategory'] ?? 0)) {
            return false;
        }
        if ($categoryId === null || $categoryId <= 0) {
            return false;
        }

        $sortby = $scriptProperties['sortby'] ?? '';
        if (is_array($sortby)) {
            $sortby = json_encode($sortby);
        }

        return self::sortByContainsMenuindex((string) $sortby);
    }

    /**
     * @param array<string, mixed> $scriptProperties
     * @param object|null $resource modResource-like: get(string): mixed
     */
    public static function resolveCategoryContext(array $scriptProperties, $resource = null): ?int
    {
        if ($resource !== null && method_exists($resource, 'get')) {
            if ($resource->get('class_key') === 'msCategory') {
                return (int) $resource->get('id');
            }
        }

        $parents = isset($scriptProperties['parents']) ? (string) $scriptProperties['parents'] : '';
        if ($parents === '' || $parents === '0') {
            return null;
        }

        $ids = [];
        foreach (array_map('trim', explode(',', $parents)) as $part) {
            $id = (int) $part;
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $ids = array_values(array_unique($ids));

        return count($ids) === 1 ? $ids[0] : null;
    }

    /**
     * @return array{include: int[], exclude: int[]}
     */
    public static function parseParentsString(string $parents): array
    {
        $include = [];
        $exclude = [];
        foreach (array_map('trim', explode(',', $parents)) as $part) {
            if ($part === '') {
                continue;
            }
            $id = (int) $part;
            if ($id > 0) {
                $include[] = $id;
            } elseif ($id < 0) {
                $exclude[] = abs($id);
            }
        }

        return [
            'include' => array_values(array_unique($include)),
            'exclude' => array_values(array_unique($exclude)),
        ];
    }

    /**
     * @param int[] $categoryIds
     */
    public static function buildParentsWhereSql(array $categoryIds, string $tablePrefix = ''): ?string
    {
        $categoryIds = array_values(array_unique(array_filter(array_map('intval', $categoryIds), static function (int $id): bool {
            return $id > 0;
        })));

        if ($categoryIds === []) {
            return null;
        }

        $list = implode(',', $categoryIds);
        $memberTable = $tablePrefix . 'ms2_product_categories';

        return "(`msProduct`.`parent` IN ({$list}) OR `msProduct`.`id` IN ("
            . "SELECT `product_id` FROM `{$memberTable}` WHERE `category_id` IN ({$list})))";
    }

    public static function getMemberJoinOn(
        int $categoryId,
        string $memberAlias = self::MEMBER_JOIN_ALIAS
    ): string {
        return "`{$memberAlias}`.`product_id` = `msProduct`.`id` "
            . "AND `{$memberAlias}`.`category_id` = {$categoryId}";
    }
}
