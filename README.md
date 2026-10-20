# IIT Shelf Laravel API

The backend is a Laravel 12 application. API requests are handled by Laravel controllers, requests, middleware, services, and Eloquent/query-builder code. The former `api/` PHP endpoint tree and dispatcher have been removed. Existing Flutter-compatible `.php` URL suffixes are Laravel route aliases only; no PHP endpoint scripts execute.

## Requirements

- PHP 8.2+ with PDO MySQL, fileinfo, and OpenSSL extensions
- Composer
- MariaDB/MySQL
- Flutter/Dart for the client

## Configure

Copy `.env.example` to `.env`, set `APP_KEY`, and configure the application's main settings there:

- `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` configure the single MySQL connection. Alternatively, set `DB_URL` to the deployment's database connection URL. The `iit_shelf` database stores the library, pre-registration, settings, and temporary OTP tables; the application does not use separate pre-registration or OTP databases.
- If Laravel configuration is cached, run `php artisan config:clear` after changing `.env` so the updated database credentials are loaded.

Use `MAIL_MAILER=log` for local development. Configure `MAIL_MAILER=smtp` and the corresponding `MAIL_*` settings in `.env` when email delivery is required.

For a fresh database, review the Laravel migrations and run `php artisan migrate` deliberately. For an existing installation, back up and review the single database schema before migrating. Do not run migrations against existing data without that review. The application does not migrate the database automatically at startup.

## Start

```bash
composer install
php artisan key:generate
php artisan serve --host=0.0.0.0 --port=8000
```

Flutter uses the shared API client. Configure `API_BASE_URL` at build time when the default emulator/web host is not correct, for example `--dart-define=API_BASE_URL=http://192.168.1.20:8000`.

`php artisan serve` prints one request line per HTTP request with its timestamp (UTC), method, path, status code, and response time. Query strings and request contents are omitted.

## Validate

```bash
php artisan test
flutter analyze
```

Database-backed tests use transaction rollback and require the main database with its expected tables. Upload tests should use temporary files; never clear the existing `uploads/` directory as part of testing.

Run `php artisan db:seed` to add fake demo books, copies, shelves, courses, and pre-registration records to a non-production database. The seeder is idempotent and refuses to run in production. It creates no login accounts; the `example.test` identities are for local data/UI testing only and cannot receive email. Use the institution's approved data process for real identities.

## Structure

- `app/Http/Controllers`: Laravel endpoint behavior
- `app/Http/Requests`: validation
- `app/Http/Middleware`: bearer authentication, role, permission, and owner checks
- `app/Services`: authentication, token, catalog, and borrow workflows
- `routes/api.php`: Laravel API routes
- `database/migrations`: Laravel schema migrations
- `database/seeders`: Laravel seeders
- `public/index.php`: Laravel front controller
- `uploads/`: existing user-uploaded files; do not delete during cleanup
"# iitbackend" 
