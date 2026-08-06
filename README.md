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
php artisan community:generate-video-thumbnails
php artisan storage:link
php artisan optimize:clear
php artisan optimize
```

`php artisan storage:link` is required anywhere public app-server media URLs are served, including QA smoke environments. Without the `public/storage` symlink, public media records can be created successfully while their URLs return `403` or `404`.

Royal post videos use FFmpeg to save a real JPEG thumbnail for iPhone and other mobile browsers. Install it once on the Forge server (`sudo apt-get update && sudo apt-get install -y ffmpeg`). The deploy command above backfills videos uploaded before thumbnail support was added; new uploads generate their thumbnail automatically.

Community videos are limited to 1 GB each by the browser and Laravel. The versioned `public/.user.ini` gives PHP-FPM enough headroom to return Laravel's clear validation error and supports all 12 allowed attachments in one request. Add the directives from `ops/forge/nginx-community-upload-limits.conf` to the site's Forge Nginx configuration, then reload Nginx and PHP-FPM. Without the Nginx change, Forge's default request limit will reject large videos before Laravel receives them.

After the first deploy, configure a queue worker in Forge:

```bash
php artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

## Checks

```bash
composer test
npm run build
```
