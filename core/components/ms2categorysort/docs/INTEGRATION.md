# ms2categorysort — интеграция (MODX addon)

Установка сортировки товаров **по категории** без форка MiniShop2.

## Требования

- MODX Revolution 2.8+
- MiniShop2 3.x / 4.x
- pdoTools, mFilter2 (для листинга категорий)

## Установка

1. **Бекап** (обязательно перед prod):

   ```bash
   php tools/backup-ms2categorysort.php --key=ms2categorysort_backup_2026
   ```

2. Залить на сервер:

   - `core/components/ms2categorysort/` (без `tests/`, `vendor/`)
   - `assets/components/ms2categorysort/`

3. Запустить установщик:

   ```bash
   php tools/install-ms2categorysort.php --key=ms2categorysort_install_2026
   ```

   Или через браузер (nginx блокирует `/tools/` снаружи — используйте install в assets):

   ```
   https://septiki-rus.ru/assets/components/ms2categorysort/install.php?key=ms2categorysort_deploy_2026
   ```

   Скрипт `tools/deploy-ms2categorysort-ftp.sh` заливает файлы и вызывает этот URL (нужен User-Agent браузера).
   После успешного деплоя **удалите** `assets/components/ms2categorysort/install.php` с сервера.

4. Snippet **msProducts** обновляется из файла addon и привязывается к media source (Filesystem). Установщик копирует код в элемент MODX и задаёт `static_file` — без этого MODX мог продолжать выполнять старый inline-snippet MS2.

5. Только страница категории — флаг:

   ```
   &sortByCategory=`1`
   ```

   (см. `core/elements/templates/kategoriya.tpl`)

6. Очистить кэш MODX.

## Системная настройка MiniShop2

| Ключ | Область | По умолчанию | Описание |
|---|---|---|---|
| `ms2_category_sort_by_category` | `ms2_category` | `1` (Да) | Глобальный переключатель per-category сортировки |

Настройка в админке: **Система → Системные настройки → minishop2 → Категория** (или поиск по ключу).

- **Да** — на витрине работает `sortByCategory=1`; в админке категории DnD пишет порядок в контексте категории.
- **Нет** — поведение как в MS2: `menuindex` только основной категории товара; параметр `sortByCategory` в шаблоне игнорируется.

Переустановка addon **не сбрасывает** уже сохранённое значение настройки.

## Параметры snippet

| Параметр | По умолчанию | Описание |
|---|---|---|
| `sortByCategory` | `0` | `1` — `sortby menuindex` по **текущей** категории (только если включена системная настройка `ms2_category_sort_by_category`); `0` — как в MS2 |

Других новых параметров не требуется.

## Откат

1. Восстановить файлы и БД из `backups/ms2categorysort/<timestamp>/`
2. Отключить plugin `ms2CategorySort`
3. Вернуть snippet msProducts на vendor MS2
4. Опционально: `ALTER TABLE ms2_product_categories DROP COLUMN menuindex`

## Transport-пакет

Сборка: `php core/components/ms2categorysort/_build/build.transport.php` (исключает `tests/`, `phpunit.xml`, `composer.json`, `vendor/`).
