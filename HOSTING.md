# PlayNote deploy на Sweb

## Что загружать

Загружайте архив `deploy/playnote-sweb.zip`.

Лучший вариант: в панели Sweb направить домен на папку `public`.

Если домен смотрит в корень распакованного проекта, корневой `.htaccess` уже прокидывает запросы и статические файлы в `public`.

## После распаковки

1. Скопируйте `.env.example` в `.env`.
2. Заполните:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ваш-домен

DB_CONNECTION=mysql
DB_HOST=адрес-mysql
DB_PORT=3306
DB_DATABASE=имя_базы
DB_USERNAME=пользователь
DB_PASSWORD=пароль

FILESYSTEM_DISK=public

MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=465
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=...
```

3. Если есть SSH:

```bash
php artisan key:generate --force
php artisan migrate --seed --force
php artisan storage:link
php artisan optimize
```

4. Если SSH нет:
   - сгенерируйте `APP_KEY` локально командой `php artisan key:generate --show` и вставьте в `.env`;
   - импортируйте базу через phpMyAdmin или включите SSH на время миграций;
   - папка `storage/app/public` должна быть доступна как `/storage` через `php artisan storage:link` или симлинк в панели.

## Тестовые аккаунты после seed

- `admin@example.com` / `admin123`
- `moderator@example.com` / `secret123`
- `teacher@example.com` / `teacher123`
- `student@example.com` / `student123`
