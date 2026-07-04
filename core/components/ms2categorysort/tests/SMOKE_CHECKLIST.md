# Smoke checklist (manual QA, phase 4)

Run after deploy to staging/prod. Backup first.

## Admin

- [ ] System setting `ms2_category_sort_by_category` = Yes → per-category sort active
- [ ] System setting = No → admin DnD uses legacy MS2 (main parent menuindex); no categorysort.grid.js override
- [ ] Open category with native products → DnD reorder → refresh → order persisted
- [ ] Same category with alien products (extra categories) → DnD → order independent from main category
- [ ] Mixed native + alien in one grid → DnD across types → unified order
- [ ] Nested categories toggle → products from child categories visible; sort uses alien path in parent grid

## Front

- [ ] Category page (`kategoriya.tpl`, `sortByCategory=1`, setting ON) → order matches admin for that category
- [ ] Same page with setting OFF → legacy main-category order regardless of `sortByCategory`
- [ ] `popular.tpl` / `sale.tpl` without `sortByCategory` → legacy main-category order
- [ ] mFilter2 pagination preserves sort

## DB

- [ ] Column `ms2_product_categories.menuindex` exists
- [ ] New category assignment gets `menuindex = max+1`
