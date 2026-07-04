<?php

declare(strict_types=1);

namespace Ms2CategorySort\Infrastructure\Modx;

use Ms2CategorySort\Domain\CategorySortRules;
use Ms2CategorySort\Domain\Contract\CategorySortRepositoryInterface;
use modX;
use PDO;

final class ModxCategorySortRepository implements CategorySortRepositoryInterface
{
    /** @var modX */
    private $modx;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    public function getTablePrefix(): string
    {
        return (string) $this->modx->getOption('table_prefix', null, 'modx_');
    }

    public function isCategorySortEnabled(): bool
    {
        return CategorySortRules::isSortByCategoryEnabled(
            $this->modx->getOption(CategorySortRules::SYSTEM_SETTING_KEY, null, '1')
        );
    }

    /**
     * @param int[] $seedIds
     * @return int[]
     */
    public function expandCategoryIds(array $seedIds, int $depth = 10): array
    {
        $result = array_values(array_unique(array_map('intval', $seedIds)));
        if ($depth <= 0 || $result === []) {
            return $result;
        }

        $current = $result;
        for ($i = 0; $i < $depth; $i++) {
            $q = $this->modx->newQuery('msCategory');
            $q->where([
                'class_key' => 'msCategory',
                'parent:IN' => $current,
                'published' => 1,
                'deleted' => 0,
            ]);
            $q->select('id');
            $children = [];
            if ($q->prepare() && $q->stmt->execute()) {
                $children = array_map('intval', $q->stmt->fetchAll(PDO::FETCH_COLUMN));
            }
            if ($children === []) {
                break;
            }
            $result = array_values(array_unique(array_merge($result, $children)));
            $current = $children;
        }

        return $result;
    }

    public function ensureSchema(): bool
    {
        $table = $this->modx->getTableName('msCategoryMember');
        $sql = "SHOW COLUMNS FROM {$table} LIKE 'menuindex'";
        $stmt = $this->modx->prepare($sql);
        if ($stmt && $stmt->execute() && $stmt->fetch(PDO::FETCH_ASSOC)) {
            return true;
        }

        $this->modx->exec(
            "ALTER TABLE {$table} "
            . "ADD COLUMN `menuindex` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `category_id`, "
            . "ADD INDEX `category_menuindex` (`category_id`, `menuindex`)"
        );

        return true;
    }

    public function migrateExistingMenuIndexes(): void
    {
        $memberTable = $this->modx->getTableName('msCategoryMember');
        $contentTable = $this->modx->getTableName('modResource');
        $this->modx->exec(
            "UPDATE {$memberTable} AS m "
            . "INNER JOIN {$contentTable} AS r ON r.id = m.product_id "
            . "SET m.menuindex = r.menuindex "
            . "WHERE m.menuindex = 0"
        );
    }

    public function getNextMenuIndexInCategory(int $categoryId): int
    {
        $maxNative = 0;
        $q = $this->modx->newQuery('msProduct');
        $q->where(['parent' => $categoryId, 'class_key' => 'msProduct']);
        $q->select('MAX(menuindex) as max_idx');
        if ($q->prepare() && $q->stmt->execute()) {
            $maxNative = (int) $q->stmt->fetchColumn();
        }

        $memberTable = $this->modx->getTableName('msCategoryMember');
        $stmt = $this->modx->prepare(
            "SELECT MAX(menuindex) FROM {$memberTable} WHERE category_id = ?"
        );
        $maxMember = 0;
        if ($stmt && $stmt->execute([$categoryId])) {
            $maxMember = (int) $stmt->fetchColumn();
        }

        return max($maxNative, $maxMember) + 1;
    }

    /**
     * @param int[] $categoryIds
     */
    public function initMenuIndexForNewMembers(int $productId, array $categoryIds): void
    {
        $product = $this->modx->getObject('msProduct', $productId);
        $parent = $product ? (int) $product->get('parent') : 0;

        foreach ($categoryIds as $categoryId) {
            $categoryId = (int) $categoryId;
            if ($categoryId <= 0 || $categoryId === $parent) {
                continue;
            }
            $member = $this->modx->getObject('msCategoryMember', [
                'product_id' => $productId,
                'category_id' => $categoryId,
            ]);
            if ($member && (int) $member->get('menuindex') === 0) {
                $member->set('menuindex', $this->getNextMenuIndexInCategory($categoryId));
                $member->save();
            }
        }
    }

    public function isNativeInCategory(int $productId, int $categoryId): bool
    {
        $product = $this->modx->getObject('msProduct', $productId);

        return $product !== null && (int) $product->get('parent') === $categoryId;
    }

    /**
     * @return object|null msProduct
     */
    public function getProduct(int $productId)
    {
        return $this->modx->getObject('msProduct', $productId);
    }

    /**
     * @param object $source msProduct
     * @param object $target msProduct
     */
    public function sortNativeInCategory(int $categoryId, $source, $target): void
    {
        $c = $this->modx->newQuery('msProduct');
        $c->command('UPDATE');
        $c->where([
            'parent' => $categoryId,
            'class_key' => 'msProduct',
        ]);
        if ($source->get('menuindex') < $target->get('menuindex')) {
            $c->query['set']['menuindex'] = [
                'value' => '`menuindex` - 1',
                'type' => false,
            ];
            $c->andCondition([
                'menuindex:<=' => $target->get('menuindex'),
                'menuindex:>' => $source->get('menuindex'),
            ]);
            $c->andCondition(['menuindex:>' => 0]);
        } else {
            $c->query['set']['menuindex'] = [
                'value' => '`menuindex` + 1',
                'type' => false,
            ];
            $c->andCondition([
                'menuindex:>=' => $target->get('menuindex'),
                'menuindex:<' => $source->get('menuindex'),
            ]);
        }
        $c->prepare();
        $c->stmt->execute();

        $source->set('menuindex', $target->get('menuindex'));
        $source->save();
    }

    public function sortAlienInCategory(int $categoryId, int $sourceProductId, int $targetProductId): void
    {
        $this->ensureMemberLink($sourceProductId, $categoryId, $this->getNextMenuIndexInCategory($categoryId));
        $this->ensureMemberLink($targetProductId, $categoryId, $this->getNextMenuIndexInCategory($categoryId));

        $source = $this->modx->getObject('msCategoryMember', [
            'product_id' => $sourceProductId,
            'category_id' => $categoryId,
        ]);
        $target = $this->modx->getObject('msCategoryMember', [
            'product_id' => $targetProductId,
            'category_id' => $categoryId,
        ]);
        if (!$source || !$target) {
            return;
        }

        $sourceIdx = (int) $source->get('menuindex');
        $targetIdx = (int) $target->get('menuindex');
        $table = $this->modx->getTableName('msCategoryMember');

        if ($sourceIdx < $targetIdx) {
            $this->modx->exec(
                "UPDATE {$table} SET menuindex = menuindex - 1 "
                . "WHERE category_id = {$categoryId} AND menuindex <= {$targetIdx} "
                . "AND menuindex > {$sourceIdx} AND menuindex > 0"
            );
        } else {
            $this->modx->exec(
                "UPDATE {$table} SET menuindex = menuindex + 1 "
                . "WHERE category_id = {$categoryId} AND menuindex >= {$targetIdx} "
                . "AND menuindex < {$sourceIdx}"
            );
        }

        $source->set('menuindex', $targetIdx);
        $source->save();
    }

    public function updateNativeIndexes(int $categoryId): void
    {
        $c = $this->modx->newQuery('msProduct', [
            'parent' => $categoryId,
            'class_key' => 'msProduct',
        ]);
        $c->groupby('menuindex');
        $c->select('COUNT(menuindex) as idx');
        $c->sortby('idx', 'DESC');
        $c->limit(1);
        if ($c->prepare() && $c->stmt->execute() && $c->stmt->fetchColumn() == 1) {
            return;
        }

        $c = $this->modx->newQuery('msProduct', [
            'parent' => $categoryId,
            'class_key' => 'msProduct',
        ]);
        $c->select('id');
        $c->sortby('menuindex ASC, id', 'ASC');
        if ($c->prepare() && $c->stmt->execute()) {
            $table = $this->modx->getTableName('msProduct');
            $update = $this->modx->prepare("UPDATE {$table} SET menuindex = ? WHERE id = ?");
            $i = 0;
            while ($id = $c->stmt->fetch(PDO::FETCH_COLUMN)) {
                $update->execute([$i, $id]);
                $i++;
            }
        }
    }

    public function updateMemberIndexes(int $categoryId): void
    {
        $table = $this->modx->getTableName('msCategoryMember');
        $q = $this->modx->prepare(
            "SELECT product_id FROM {$table} WHERE category_id = ? ORDER BY menuindex ASC, product_id ASC"
        );
        if (!$q || !$q->execute([$categoryId])) {
            return;
        }
        $update = $this->modx->prepare(
            "UPDATE {$table} SET menuindex = ? WHERE category_id = ? AND product_id = ?"
        );
        $i = 0;
        while ($productId = $q->fetch(PDO::FETCH_COLUMN)) {
            $update->execute([$i, $categoryId, $productId]);
            $i++;
        }
    }

    /**
     * @return int[]
     */
    public function getProductIdsInCategoryOrdered(int $categoryId): array
    {
        $categoryId = (int) $categoryId;
        $memberTable = $this->modx->getTableName('msCategoryMember');
        $contentTable = $this->modx->getTableName('modResource');
        $fallback = CategorySortRules::ALIEN_FALLBACK;
        $sql = "SELECT p.id FROM {$contentTable} AS p "
            . "LEFT JOIN {$memberTable} AS m ON m.product_id = p.id AND m.category_id = {$categoryId} "
            . "WHERE p.class_key = 'msProduct' AND p.deleted = 0 "
            . "AND (p.parent = {$categoryId} OR m.category_id = {$categoryId}) "
            . "ORDER BY CASE WHEN p.parent = {$categoryId} THEN p.menuindex "
            . "ELSE COALESCE(m.menuindex, {$fallback}) END ASC, p.id ASC";

        $ids = [];
        $stmt = $this->modx->prepare($sql);
        if ($stmt && $stmt->execute()) {
            $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        }

        return $ids;
    }

    /**
     * @param int[] $orderedProductIds
     */
    public function persistCategoryOrder(int $categoryId, array $orderedProductIds): void
    {
        $categoryId = (int) $categoryId;
        $contentTable = $this->modx->getTableName('modResource');
        $memberTable = $this->modx->getTableName('msCategoryMember');

        foreach (array_values($orderedProductIds) as $index => $productId) {
            $productId = (int) $productId;
            if ($this->isNativeInCategory($productId, $categoryId)) {
                $this->modx->exec(
                    "UPDATE {$contentTable} SET menuindex = {$index} WHERE id = {$productId}"
                );
                continue;
            }

            $this->ensureMemberLink($productId, $categoryId, $index);
            $this->modx->exec(
                "UPDATE {$memberTable} SET menuindex = {$index} "
                . "WHERE category_id = {$categoryId} AND product_id = {$productId}"
            );
        }
    }

    private function ensureMemberLink(int $productId, int $categoryId, int $menuindex): void
    {
        $member = $this->modx->getObject('msCategoryMember', [
            'product_id' => $productId,
            'category_id' => $categoryId,
        ]);
        if ($member) {
            return;
        }

        $member = $this->modx->newObject('msCategoryMember');
        $member->fromArray([
            'product_id' => $productId,
            'category_id' => $categoryId,
            'menuindex' => $menuindex,
        ]);
        $member->save();
    }
}
