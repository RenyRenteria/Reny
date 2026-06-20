# Reny

Laravel application scaffold for the Reny web app.

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
php artisan serve
```

The app defaults to MySQL so the production environment can map cleanly to Laravel Forge. For a quick local SQLite setup, change `DB_CONNECTION=sqlite` in `.env` and create `database/database.sqlite`.

## Forge setup notes

Use this repository as the site repository in Laravel Forge:

- Repository: `RenyRenteria/Reny`
- Branch: `main`
- Web directory: `/public`
- PHP: `8.4+`
- Database: MySQL
- Queue driver: `database`
- Cache/session driver: `database`

Production `.env` values to set in Forge:

```env
APP_NAME=Reny
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
APP_TIMEZONE=America/Panama
ADMIN_CMS_ENABLED=true

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=reny
DB_USERNAME=forge
DB_PASSWORD=your-database-password
```

Recommended Forge deploy script:

```bash
cd /home/forge/your-domain.com
git pull origin main

composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm install
npm run build

php artisan migrate --force
php artisan storage:link
php artisan optimize:clear
php artisan optimize
```

`php artisan storage:link` is required anywhere public app-server media URLs are served, including QA smoke environments. Without the `public/storage` symlink, public media records can be created successfully while their URLs return `403` or `404`.

After the first deploy, configure a queue worker in Forge:

```bash
php artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

## Checks

```bash
composer test
npm run build
```
