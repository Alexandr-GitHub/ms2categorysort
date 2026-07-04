# ms2categorysort

Per-category сортировка товаров (`menuindex`) для **MODX Revolution** + **MiniShop2** без правок vendor MS2.

- Родной товар в категории → `site_content.menuindex`
- «Чужой» товар (доп. категория) → `ms2_product_categories.menuindex`
- Глобальный переключатель в системных настройках MS2
- Opt-in на витрине через `&sortByCategory=`1``

## Требования

| Компонент | Версия |
|---|---|
| MODX Revolution | 2.8+ |
| MiniShop2 | 3.x / 4.x |
| pdoTools | для листинга (mFilter2 / msProducts) |

## Структура репозитория

```
core/components/ms2categorysort/   — PHP, processors, snippet, plugin
assets/components/ms2categorysort/ — connector, JS админки, web-install
tools/install-cli.php              — CLI-установщик (из корня сайта MODX)
```

Файлы копируются в корень MODX с сохранением путей `core/` и `assets/`.

## Установка

### 1. Бекап

Сделайте резервную копию файлов и БД (таблицы `modx_site_*`, `modx_ms2_*`).

### 2. Копирование файлов

```bash
# из каталога, куда клонировали репозиторий
rsync -av core/components/ms2categorysort/ /path/to/modx/core/components/ms2categorysort/
rsync -av assets/components/ms2categorysort/ /path/to/modx/assets/components/ms2categorysort/
rsync -av tools/install-cli.php /path/to/modx/tools/ms2categorysort-install.php
```

Или вручную через FTP/SFTP те же две папки.

### 3. Запуск установщика

**CLI** (рекомендуется, из корня MODX):

```bash
php tools/ms2categorysort-install.php
```

**Web** (одноразово, если нет SSH):

```
https://your-site.ru/assets/components/ms2categorysort/install.php?key=ms2categorysort
```

После успешной установки **удалите** `assets/components/ms2categorysort/install.php` с сервера.

Установщик:

- добавляет колонку `menuindex` в `ms2_product_categories` (если нет)
- мигрирует текущие индексы
- регистрирует plugin `ms2CategorySort`, snippet `msProducts`, namespace
- добавляет системную настройку `ms2_category_sort_by_category`
- регистрирует MS2 plugin `categorysort` в `ms2_plugins`
- очищает кэш MODX

### 4. Очистить кэш

**Управление → Очистить кэш** или через CLI установщика (уже выполняется).

## Настройка

### Системная настройка (глобально)

**Система → Системные настройки → minishop2 → Категория**

| Ключ | По умолчанию | Описание |
|---|---|---|
| `ms2_category_sort_by_category` | `1` (Да) | Включить per-category сортировку |

- **Да** — DnD в админке категории и `sortByCategory=1` на витрине работают
- **Нет** — поведение как в MS2 (только `menuindex` основной категории товара)

Переустановка addon **не сбрасывает** сохранённое значение.

### Витрина (snippet msProducts)

На страницах **категорий** добавьте параметр:

```modx
[[!msProducts?
  &parents=`[[*id]]`
  &sortby=`menuindex`
  &sortdir=`ASC`
  &sortByCategory=`1`
]]
```

| Параметр | По умолчанию | Описание |
|---|---|---|
| `sortByCategory` | `0` | `1` — порядок в контексте текущей категории (если включена системная настройка) |

На других страницах (акции, популярное) параметр не нужен — остаётся legacy MS2.

### Админка

Откройте категорию → вкладка с товарами → перетаскивайте строки за **ID / артикул / цену** (не за ссылку названия).

Включена опция «Показывать вложенные товары» — порядок пишется в `ms2_product_categories.menuindex` для текущей категории.

## Сборка transport-пакета

Нужен установленный MODX с разложенным компонентом в `core/components/ms2categorysort/`:

```bash
cd /path/to/modx
php core/components/ms2categorysort/_build/build.transport.php
```

Создаёт `ms2categorysort-1.0.0-pl.transport.zip` (без `tests/`, dev-файлов). Установка через **Управление пакетами** MODX.

## Тесты (dev)

```bash
cd core/components/ms2categorysort
php tests/run-smoke.php
# или PHPUnit, если установлен: php phpunit.phar -c phpunit.xml
```

## Откат

1. Отключить plugin **ms2CategorySort**
2. Вернуть snippet **msProducts** из vendor MiniShop2
3. Удалить `categorysort` из system setting `ms2_plugins`
4. Опционально: `ALTER TABLE modx_ms2_product_categories DROP COLUMN menuindex;`

## Документация

- [docs/INTEGRATION.md](core/components/ms2categorysort/docs/INTEGRATION.md) — детали интеграции
- [docs/ARCHITECTURE.md](core/components/ms2categorysort/docs/ARCHITECTURE.md) — слои и модель данных
- [docs/FORK_MINISHOP2.md](core/components/ms2categorysort/docs/FORK_MINISHOP2.md) — перенос в fork MS2

## Лицензия

GPL-2.0-or-later — см. [LICENSE](LICENSE).
