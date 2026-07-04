<?php

declare(strict_types=1);

namespace Ms2CategorySort\Application;

use Ms2CategorySort\Domain\CategorySortRules;
use Ms2CategorySort\Domain\Contract\CategorySortRepositoryInterface;
use Ms2CategorySort\Domain\XpdoMapExtension;

/**
 * Application service (use cases). Depends on repository abstraction (DIP).
 */
final class CategorySortService
{
    /** @var CategorySortRepositoryInterface */
    private $repository;

    public function __construct(CategorySortRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function getMapExtension(): array
    {
        return XpdoMapExtension::forMsCategoryMember();
    }

    /**
     * @param mixed $value
     */
    public function isSortByCategoryEnabled($value): bool
    {
        return CategorySortRules::isSortByCategoryEnabled($value);
    }

    public function getSortExpression(
        int $categoryId,
        string $productAlias = 'msProduct',
        string $memberAlias = CategorySortRules::MEMBER_JOIN_ALIAS
    ): string {
        return CategorySortRules::getSortExpression($categoryId, $productAlias, $memberAlias);
    }

    /**
     * @param array<string, mixed> $scriptProperties
     * @param object|null $resource
     */
    public function resolveCategoryContext(array $scriptProperties, $resource = null): ?int
    {
        return CategorySortRules::resolveCategoryContext($scriptProperties, $resource);
    }

    /**
     * @param array<string, mixed> $scriptProperties
     */
    public function shouldApplyCategorySort(array $scriptProperties, ?int $categoryId): bool
    {
        if (!$this->repository->isCategorySortEnabled()) {
            return false;
        }

        return CategorySortRules::shouldApplyCategorySort($scriptProperties, $categoryId);
    }

    public function isCategorySortEnabled(): bool
    {
        return $this->repository->isCategorySortEnabled();
    }

    /**
     * @return array{sql: ?string, categoryIds: int[], disablePdoParents: bool}
     */
    public function buildParentsWhere(string $parents, int $depth = 10): array
    {
        $empty = ['sql' => null, 'categoryIds' => [], 'disablePdoParents' => false];
        if ($parents === '' || $parents === '0') {
            return $empty;
        }

        $parts = CategorySortRules::parseParentsString($parents);
        if ($parts['include'] === []) {
            return $empty;
        }

        $includeExpanded = $this->repository->expandCategoryIds($parts['include'], $depth);
        $excludeExpanded = $parts['exclude'] !== []
            ? $this->repository->expandCategoryIds($parts['exclude'], $depth)
            : [];
        $categoryIds = array_values(array_diff($includeExpanded, $excludeExpanded));

        $sql = CategorySortRules::buildParentsWhereSql(
            $categoryIds,
            $this->repository->getTablePrefix()
        );

        return [
            'sql' => $sql,
            'categoryIds' => $categoryIds,
            'disablePdoParents' => $sql !== null,
        ];
    }

    public function getMemberJoinOn(
        int $categoryId,
        string $memberAlias = CategorySortRules::MEMBER_JOIN_ALIAS
    ): string {
        return CategorySortRules::getMemberJoinOn($categoryId, $memberAlias);
    }

    public function ensureSchema(): bool
    {
        return $this->repository->ensureSchema();
    }

    public function migrateExistingMenuIndexes(): void
    {
        $this->repository->migrateExistingMenuIndexes();
    }

    /**
     * @param int[] $categoryIds
     */
    public function initMenuIndexForNewMembers(int $productId, array $categoryIds): void
    {
        $this->repository->initMenuIndexForNewMembers($productId, $categoryIds);
    }

    public function isNativeInCategory(int $productId, int $categoryId): bool
    {
        return $this->repository->isNativeInCategory($productId, $categoryId);
    }

    /**
     * @param object $source
     * @param object $target
     */
    public function sortNativeInCategory(int $categoryId, $source, $target): void
    {
        $this->repository->sortNativeInCategory($categoryId, $source, $target);
    }

    public function sortAlienInCategory(int $categoryId, int $sourceProductId, int $targetProductId): void
    {
        $this->repository->sortAlienInCategory($categoryId, $sourceProductId, $targetProductId);
    }

    public function updateNativeIndexes(int $categoryId): void
    {
        $this->repository->updateNativeIndexes($categoryId);
    }

    public function updateMemberIndexes(int $categoryId): void
    {
        $this->repository->updateMemberIndexes($categoryId);
    }

    /**
     * @return int[]
     */
    public function getProductIdsInCategoryOrdered(int $categoryId): array
    {
        return $this->repository->getProductIdsInCategoryOrdered($categoryId);
    }

    /**
     * @param int[] $sourceIds
     */
    public function sortProductsInCategory(int $categoryId, array $sourceIds, int $targetProductId): void
    {
        $categoryId = (int) $categoryId;
        $targetProductId = (int) $targetProductId;
        if ($categoryId <= 0 || $targetProductId <= 0) {
            return;
        }

        foreach (array_values(array_unique(array_map('intval', $sourceIds))) as $sourceId) {
            if ($sourceId <= 0 || $sourceId === $targetProductId) {
                continue;
            }

            if ($this->isNativeInCategory($sourceId, $categoryId)) {
                $source = $this->repository->getProduct($sourceId);
                $target = $this->repository->getProduct($targetProductId);
                if ($source && $target) {
                    $this->repository->sortNativeInCategory($categoryId, $source, $target);
                }
                continue;
            }

            $this->repository->sortAlienInCategory($categoryId, $sourceId, $targetProductId);
        }

        $this->repository->updateNativeIndexes($categoryId);
        $this->repository->updateMemberIndexes($categoryId);
    }
}
