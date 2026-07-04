# ms2categorysort — интеграция (MODX addon)

Установка сортировки товаров **по категории** без форка MiniShop2.

## Требования

- MODX Revolution 2.8+
- MiniShop2 3.x / 4.x
- pdoTools, mFilter2 (для листинга категорий)

## Установка (transport-пакет)

1. **Бекап** файлов и БД.

2. Загрузить `_build/dist/ms2categorysort-1.0.0-pl.transport.zip` через **Extras → Installer** и установить пакет **ms2categorysort**.

   Transport выполняет всё автоматически: файлы, plugin, настройку, схему БД, миграцию, patch snippet **msProducts**, запись в `ms2_plugins`.

3. На странице категории в шаблоне:

   ```
   &sortByCategory=`1`
   ```

   (см. `core/elements/templates/kategoriya.tpl` на сайте septiki-rus.ru)

4. Очистить кэш MODX (установщик делает это сам).

## Системная настройка MiniShop2

| Ключ | Область | По умолчанию | Описание |
|---|---|---|---|
| `ms2_category_sort_by_category` | `ms2_category` | `1` (Да) | Глобальный переключатель per-category сортировки |

Настройка: **Система → Системные настройки → minishop2 → Категория**.

## Параметры snippet

| Параметр | По умолчанию | Описание |
|---|---|---|
| `sortByCategory` | `0` | `1` — `sortby menuindex` по **текущей** категории |

## Откат

1. Удалить пакет через Installer (убирает `categorysort` из `ms2_plugins`)
2. Вернуть snippet msProducts на vendor MS2
3. Опционально: `ALTER TABLE ms2_product_categories DROP COLUMN menuindex`

## Сборка transport-пакета

См. раздел **«Сборка transport-пакета»** в [README.md](../../../README.md) в корне репозитория.
