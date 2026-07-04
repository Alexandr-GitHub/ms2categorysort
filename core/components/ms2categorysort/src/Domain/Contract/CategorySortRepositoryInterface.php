<?php

declare(strict_types=1);

namespace Ms2CategorySort\Domain\Contract;

interface CategorySortRepositoryInterface
{
    /**
     * @param int[] $seedIds
     * @return int[]
     */
    public function expandCategoryIds(array $seedIds, int $depth = 10): array;

    public function ensureSchema(): bool;

    public function migrateExistingMenuIndexes(): void;

    public function getNextMenuIndexInCategory(int $categoryId): int;

    /**
     * @param int[] $categoryIds
     */
    public function initMenuIndexForNewMembers(int $productId, array $categoryIds): void;

    public function isNativeInCategory(int $productId, int $categoryId): bool;

    /**
     * @return object|null msProduct
     */
    public function getProduct(int $productId);

    /**
     * @param object $source msProduct
     * @param object $target msProduct
     */
    public function sortNativeInCategory(int $categoryId, $source, $target): void;

    public function sortAlienInCategory(int $categoryId, int $sourceProductId, int $targetProductId): void;

    public function updateNativeIndexes(int $categoryId): void;

    public function updateMemberIndexes(int $categoryId): void;

    /**
     * @return int[]
     */
    public function getProductIdsInCategoryOrdered(int $categoryId): array;

    /**
     * @param int[] $orderedProductIds
     */
    public function persistCategoryOrder(int $categoryId, array $orderedProductIds): void;

    public function getTablePrefix(): string;

    public function isCategorySortEnabled(): bool;
}
