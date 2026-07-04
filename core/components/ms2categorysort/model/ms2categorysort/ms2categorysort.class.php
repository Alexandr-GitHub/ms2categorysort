<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use Ms2CategorySort\Application\CategorySortService;
use Ms2CategorySort\Infrastructure\Modx\ModxCategorySortRepository;

/**
 * MODX service entry (xPDO-style) — composition root for the addon.
 */
class ms2categorysort
{
    /** @var modX */
    public $modx;

    /** @var array<string, mixed> */
    public $config = [];

    /** @var CategorySortService|null */
    private $categorySort;

    /**
     * @param modX $modx
     * @param array<string, mixed> $config
     */
    public function __construct(modX $modx, array $config = [])
    {
        $this->modx = $modx;
        $corePath = $modx->getOption(
            'ms2categorysort.core_path',
            null,
            $modx->getOption('core_path') . 'components/ms2categorysort/'
        );
        $assetsUrl = $modx->getOption(
            'ms2categorysort.assets_url',
            null,
            $modx->getOption('assets_url') . 'components/ms2categorysort/'
        );

        $this->config = array_merge([
            'core_path' => $corePath,
            'assets_url' => $assetsUrl,
            'processors_path' => $corePath . 'processors/',
        ], $config);
    }

    public function getCategorySortService(): CategorySortService
    {
        if ($this->categorySort === null) {
            $this->categorySort = new CategorySortService(
                new ModxCategorySortRepository($this->modx)
            );
        }

        return $this->categorySort;
    }

    public function initialize(): void
    {
        $this->getCategorySortService()->ensureSchema();
    }
}

if (!class_exists('Ms2CategorySort', false)) {
    class_alias(ms2categorysort::class, 'Ms2CategorySort');
}
