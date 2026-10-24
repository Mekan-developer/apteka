# Apteka: поиск дешёвых лекарств по прайсам аптек

Раз в неделю админ загружает Excel-файл с прайсами. Каждый лист файла — прайс одной аптеки,
название листа — название аптеки. Посетитель сайта вводит название препарата и видит три аптеки,
где он дешевле всего, отдельно для каждой фасовки (№10, №20 …).

Интерфейс на двух языках: русском и туркменском.

## Возможности

**Публичный поиск** (`/`, без входа)
- находит препарат с опечатками («анолгин» → «Анальгин»), без дефиса («ношпа» → «Но-шпа»)
  и набранный латиницей («analgin», туркменские буквы тоже понимает);
- ищет по действующему веществу: «цитрамон» находит «ACTION PLUS (ЦИТРАМОН)»;
- учитывает синонимы из админки: «аспирин» ↔ «ацетилсалициловая кислота»;
- сортирует по цене после безусловной скидки на весь лист и показывает условия скидок аптеки;
- если ничего не нашлось, предлагает похожие названия.

**Админка** (`/login`)
- **Прайсы**: загрузка `.xlsx`, разбор в очереди с прогрессом, результат по каждому листу.
  Новый прайс попадает в поиск только после активации; прошлый уходит в историю, и к нему можно откатиться.
- **Проверка листа**: если колонки не распознались, админ выбирает строку заголовка и колонки
  названия и цены, лист разбирается заново. Выбор запоминается для аптеки и применяется
  к её следующим прайсам, пока не изменится заголовок листа.
- **Синонимы**: связки названий одного препарата.
- **История цен**: как менялась минимальная цена препарата за последние недели.
- **Администраторы** (только суперадмин): создание, блокировка, удаление. Самостоятельной регистрации нет.

## Стек

- PHP 8.4, Laravel, Fortify (вход, 2FA, passkeys)
- Inertia + Vue 3 + TypeScript, Tailwind, Wayfinder
- PostgreSQL 16 с `pg_trgm` (нечёткий поиск по триграммам), Redis (кэш, сессии, очереди)
- OpenSpout (чтение больших Excel-файлов с небольшим расходом памяти)
- Pest для тестов, Pint для форматирования

## Запуск для разработки

Всё работает в Docker: `php` (php-fpm), `queue` (воркер очереди), `nginx`, `postgres`, `redis`.

```bash
cp .env.example .env
docker compose up -d --build

docker compose exec php composer install
docker compose exec php php artisan key:generate
docker compose exec php php artisan migrate
docker compose exec php php artisan admins:create-super-admin

npm ci
npm run build
```

Сайт: http://localhost:8080, админка: http://localhost:8080/login.
PostgreSQL с хоста доступен на `127.0.0.1:5433`.

`docker-compose.override.yml` подключается автоматически: код монтируется с хоста,
а `www-data` в контейнере получает ваш UID/GID (`HOST_UID`, `HOST_GID`, по умолчанию 1000),
чтобы писать в `storage/` и `bootstrap/cache`.

### Зеркала пакетов

Если прямого доступа к Packagist, npm и Debian нет, образы собираются через зеркала из `.env`:
`PACKAGIST_MIRROR`, `DEBIAN_MIRROR`, `DEBIAN_SECURITY_MIRROR`, `NPM_REGISTRY`.
Оставьте их пустыми, чтобы качать напрямую.

### Фронтенд

Nginx отдаёт собранные файлы из `public/build`, поэтому после правок во Vue/TS
нужно выполнить `npm run build`, иначе изменений не будет видно.
Для горячей перезагрузки можно запустить `npm run dev`.

## Тесты

Тесты используют отдельную базу `testing`; она создаётся при первом запуске PostgreSQL
(`docker/postgres/init`). Запускать внутри контейнера, потому что хост `postgres` виден только в docker-сети:

```bash
docker compose exec php php artisan test --compact
docker compose exec php php artisan test --compact --filter=SearchController
```

Перед коммитом:

```bash
docker compose exec php php vendor/bin/pint --dirty   # форматирование PHP
npm run types:check                                     # проверка типов TypeScript
```

## Полезные команды

```bash
# Создать суперадмина (спросит недостающее)
php artisan admins:create-super-admin --name="Имя" --email=admin@example.com

# Показать листы прайса и их первые строки
php artisan prices:inspect путь/к/файлу.xlsx --rows=8

# Разобрать прайс без сохранения: какие листы распознались, а какие требуют проверки
php artisan prices:dry-run путь/к/файлу.xlsx --sheet="Название листа"
```

## Продакшен

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec php php artisan migrate --force
```

Код, `vendor` и собранный фронтенд лежат внутри образа; с сервера монтируются только `.env` и `storage`.
Кэш конфигурации и маршрутов собирается при старте контейнера. Порт задаётся `NGINX_PORT` (по умолчанию 80).

## Устройство кода

```
Request → Route → Controller → Action / Service → Repository → Resource
                      ↑
                FormRequest
```

- `app/Services/Prices/Parsing/`: разбор листа (поиск заголовка, цены, скидки, нормализация названий);
- `app/Services/Prices/PriceImportService.php`: импорт файла и повторный разбор листа;
- `app/Services/Prices/PriceSearchService.php` и `app/Repositories/PriceItemRepository.php`: поиск;
- `app/Jobs/`: импорт и повторный разбор идут в очереди;
- `config/prices.php`: пороги поиска, лимит размера файла, глубина истории.

Подробное описание правил разбора прайсов и поиска: [docs/price-search.md](docs/price-search.md).
Правила разработки (архитектура, локализация, уведомления, тесты): [CLAUDE.md](CLAUDE.md).
