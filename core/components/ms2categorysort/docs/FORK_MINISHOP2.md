# Fork MiniShop2 — влитие ms2categorysort в production

Инструкция для **нативной интеграции** в fork боевого MiniShop2.  
Для установки addon без fork см. [INTEGRATION.md](INTEGRATION.md).

## Предпосылки

- Tag/commit MS2, совпадающий с production
- Git fork, ветка `feature/category-sort`
- Локальный бекап: `php tools/backup-ms2categorysort.php --key=ms2categorysort_backup_2026`

## Карта переноса (ms2categorysort → fork MS2)

| Источник (addon) | Цель (fork MS2) |
|---|---|
| `src/Domain/CategorySortRules.php` | merge в `model/` или processors |
| `src/Infrastructure/Modx/ModxCategorySortRepository.php` | `model/minishop2/` |
| `menuindex` в `msCategoryMember` | `model/schema/minishop2.mysql.schema.xml` |
| `processors/.../getlist.class.php` | `processors/mgr/product/getlist.class.php` |
| `processors/.../sort.class.php` | `processors/mgr/product/sort.class.php` |
| `categorysort.grid.js` | `product.grid.js` + `default.grid.js` |
| `snippet.ms_products.php` | `elements/snippets/snippet.ms_products.php` |
| SQL migration | resolver / upgrade script |

## Порядок merge

1. Schema + maps
2. Repository / service
3. Processors
4. Manager JS
5. Snippet + lexicon
6. Миграция + backfill

## Миграция БД

```sql
ALTER TABLE `ms2_product_categories`
  ADD COLUMN `menuindex` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `category_id`,
  ADD INDEX `category_menuindex` (`category_id`, `menuindex`);

UPDATE ms2_product_categories AS m
INNER JOIN site_content AS r ON r.id = m.product_id
SET m.menuindex = r.menuindex
WHERE m.menuindex = 0;
```

(Замените префикс таблиц при необходимости.)

## Чеклист после merge

- [ ] DnD native + alien в одной категории
- [ ] `kategoriya.tpl` с `&sortByCategory=`1``
- [ ] Legacy-вызовы без флага не изменились
- [ ] Кэш очищен
- [ ] Addon plugin снят, если мигрировали с addon

## Обновление upstream MS2

Конфликтные файлы: `sort.class.php`, `getlist.class.php`, `snippet.ms_products.php`, `product.grid.js`.

После каждого merge upstream — smoke DnD + sort на фронте.

## Откат

Восстановление из `backups/ms2categorysort/<timestamp>/` (только локально, не на сервере).
