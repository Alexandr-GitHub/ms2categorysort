# ms2categorysort — архитектура

Per-category `menuindex` для MiniShop2 как **update-safe MODX addon** (без форка vendor).

## Стандарты и ссылки

| Стандарт / источник | Применение в компоненте |
|---|---|
| [PSR-12 (PHP-FIG, 2019)](https://www.php-fig.org/psr/psr-12/) | `declare(strict_types=1)`, скобки, видимость, один класс на файл |
| [PSR-4 Autoloading (PHP-FIG)](https://www.php-fig.org/psr/psr-4/) | Namespace `Ms2CategorySort\` → `src/` |
| **SOLID** (Martin) | `CategorySortRepositoryInterface` (DIP); domain без MODX (SRP) |
| **Clean Architecture** (Martin, *Clean Architecture*, 2017) | Слои: Domain → Application → Infrastructure → Presentation |
| **ADR** (Nygard, 2011) | Гибридное хранение: native `parent.menuindex` vs junction — см. `FORK_MINISHOP2.md` |
| **Strangler Fig** (Fowler, 2004) | Addon оборачивает MS2 без правок `core/components/minishop2/` |

## Карта слоёв

```
Presentation   processors/, assets/js/, elements/snippets/
Application    src/Application/CategorySortService.php
Domain         src/Domain/CategorySortRules.php, Contract/
Infrastructure src/Infrastructure/Modx/ModxCategorySortRepository.php
```

## Модель данных (гибрид)

- **Родной** товар в категории C: `site_content.menuindex` при `parent = C`
- **Чужой** товар в C: `ms2_product_categories.menuindex` для `(product_id, category_id)`

Чтение (при `ms2_category_sort_by_category=1` и `sortByCategory=1`):

```sql
CASE WHEN msProduct.parent = C THEN msProduct.menuindex
     ELSE COALESCE(CategoryMember.menuindex, 999999) END
```

При выключенной системной настройке — стандартный MS2 (`msProduct.menuindex` основного родителя).

## Тесты (только dev)

- `tests/CategorySortRulesTest.php` — PHPUnit (domain, без MODX)
- `tests/run-smoke.php` — запасной runner
- В production transport не включаются
