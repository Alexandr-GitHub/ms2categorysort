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
_build/                              — сборка transport-пакета (modPackageBuilder)
_build/dist/                         — готовый .transport.zip для установки
core/components/ms2categorysort/     — PHP, processors, snippet, plugin
assets/components/ms2categorysort/   — connector, JS админки
```

## Установка через Менеджер пакетов MODX

Рекомендуемый способ — transport-пакет из `_build/dist/`.

### 1. Бекап

Сделайте резервную копию файлов и БД (таблицы `modx_site_*`, `modx_ms2_*`).

### 2. Загрузка пакета

1. **Extras → Installer** (или **Управление пакетами**)
2. **Download Extras** → **Search Locally for Packages**  
   или загрузите `_build/dist/ms2categorysort-1.0.0-pl.transport.zip` через **Download Extras**
3. Выберите пакет **ms2categorysort** → **Install**

Установщик transport-пакета:

- копирует файлы в `core/components/ms2categorysort/` и `assets/components/ms2categorysort/`
- регистрирует namespace, plugin **ms2CategorySort**, системную настройку `ms2_category_sort_by_category`
- добавляет колонку `menuindex` в `ms2_product_categories` и мигрирует данные
- обновляет snippet **msProducts** и запись `categorysort` в `ms2_plugins`
- очищает кэш MODX

### 3. Проверка

- **Система → Системные настройки → minishop2 → Категория** — настройка `ms2_category_sort_by_category`
- Категория MS2 → вкладка товаров → DnD за ID / артикул
- На витрине: `&sortByCategory=`1`` в вызове msProducts

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

### Админка

Откройте категорию → вкладка с товарами → перетаскивайте строки за **ID / артикул / цену** (не за ссылку названия).

## Тесты (dev)

```bash
cd core/components/ms2categorysort
php tests/run-smoke.php
```

## Откат

1. **Extras → Installer** → удалить пакет ms2categorysort (убирает запись из `ms2_plugins`)
2. Отключить plugin **ms2CategorySort** (если остался)
3. Вернуть snippet **msProducts** из vendor MiniShop2
4. Опционально: `ALTER TABLE modx_ms2_product_categories DROP COLUMN menuindex;`

## Документация

- [docs/INTEGRATION.md](core/components/ms2categorysort/docs/INTEGRATION.md)
- [docs/ARCHITECTURE.md](core/components/ms2categorysort/docs/ARCHITECTURE.md)
- [docs/FORK_MINISHOP2.md](core/components/ms2categorysort/docs/FORK_MINISHOP2.md)

## Лицензия

GPL-2.0-or-later — см. [LICENSE](LICENSE).

---

## Сборка transport-пакета

Сборка выполняется на машине с установленным MODX Revolution (нужен доступ к БД — `modPackageBuilder` инициализирует `modX`).

### Подготовка

```bash
git clone https://github.com/Alexandr-GitHub/ms2categorysort.git
cd ms2categorysort
cp _build/build.config.sample.php _build/build.config.php
```

В `_build/build.config.php` укажите путь к `core/` вашего MODX:

```php
define('MODX_CORE_PATH', '/var/www/modx/core/');
```

### Сборка

```bash
php _build/build.transport.php
```

Скрипт:

1. Стадирует файлы компонента (без `tests/`, `vendor/`, dev-файлов)
2. Упаковывает plugin, system setting, namespace через `modPackageBuilder`
3. Добавляет PHP-resolvers (схема БД, миграция, ms2_plugins, snippet msProducts)
4. Создаёт zip в `{MODX_CORE_PATH}packages/` и копирует в `_build/dist/`

Готовый файл: `_build/dist/ms2categorysort-1.0.0-pl.transport.zip` — его можно закоммитить в репозиторий или загрузить в Installer на другом сайте.

### Проверка пакета

```bash
php _build/validate.transport.php
```

Проверяет manifest, vehicles (plugin + 4 events), resolvers, обязательные файлы; убеждается, что в zip нет `install.php` и `tests/`.

Документация MODX: [Building a Transport Package](https://docs.modx.com/current/en/extending-modx/transport-packages/build-script).
