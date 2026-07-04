<?php

declare(strict_types=1);

require_once MODX_CORE_PATH . 'components/minishop2/processors/mgr/product/sort.class.php';
require_once dirname(__DIR__, 4) . '/bootstrap.php';

class ms2CategorySortProductSortProcessor extends modObjectProcessor
{
    public $classKey = 'msProduct';

    /**
     * @return array|string
     */
    public function process()
    {
        $categoryId = (int) $this->getProperty('category_id');
        if ($categoryId <= 0) {
            $categoryId = (int) $this->getProperty('parent');
        }
        if ($categoryId <= 0) {
            return $this->failure('Category context is required');
        }

        $targetId = (int) $this->getProperty('target');
        if ($targetId <= 0) {
            return $this->failure();
        }

        $sources = json_decode($this->getProperty('sources'), true);
        if (!is_array($sources)) {
            return $this->failure();
        }

        /** @var Ms2CategorySort $addon */
        $addon = ms2categorysort_get_service($this->modx);
        $service = $addon->getCategorySortService();
        if (!$service->isCategorySortEnabled()) {
            $legacy = new msProductSortProcessor($this->modx, $this->getProperties());

            return $legacy->process();
        }

        $sourceIds = array_map('intval', $sources);
        $service->sortProductsInCategory($categoryId, $sourceIds, $targetId);

        return $this->modx->error->success();
    }
}

return 'ms2CategorySortProductSortProcessor';
