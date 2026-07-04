<?php

declare(strict_types=1);

require_once MODX_CORE_PATH . 'components/minishop2/processors/mgr/product/getlist.class.php';
require_once dirname(__DIR__, 4) . '/bootstrap.php';

/**
 * @extends msProductGetListProcessor
 */
class ms2CategorySortProductGetListProcessor extends msProductGetListProcessor
{
    /** @var int */
    protected $categoryId = 0;

    /**
     * @return bool
     */
    public function initialize()
    {
        $this->categoryId = (int) $this->getProperty('category_id');
        if ($this->categoryId <= 0) {
            $this->categoryId = (int) $this->getProperty('parent');
        }

        $addon = ms2categorysort_get_service($this->modx);
        $service = $addon->getCategorySortService();
        if (
            $service->isCategorySortEnabled()
            && $this->categoryId > 0
            && $this->getProperty('sort') === 'menuindex'
        ) {
            $this->setProperty('sort', $service->getSortExpression($this->categoryId));
        }

        return parent::initialize();
    }

    /**
     * @param xPDOQuery $c
     * @return xPDOQuery
     */
    public function prepareQueryBeforeCount(xPDOQuery $c)
    {
        $c = parent::prepareQueryBeforeCount($c);

        $addon = ms2categorysort_get_service($this->modx);
        if (!$addon->getCategorySortService()->isCategorySortEnabled()) {
            return $c;
        }

        if ($this->categoryId > 0) {
            $service = $addon->getCategorySortService();
            $c->leftJoin(
                'msCategoryMember',
                'CategoryMember',
                $service->getMemberJoinOn($this->categoryId)
            );
        }

        return $c;
    }
}

return 'ms2CategorySortProductGetListProcessor';
