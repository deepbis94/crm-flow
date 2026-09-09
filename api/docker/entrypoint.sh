#!/bin/sh
set -e

cd /var/www/html

composer install --no-interaction --prefer-dist

if [ ! -f .env ]; then
  cp .env.example .env
fi

if ! grep -q '^APP_KEY=base64:' .env; then
  php artisan key:generate --no-interaction --force
fi

echo "Waiting for MySQL..."
i=0
until php artisan migrate --force; do
  i=$((i + 1))
  if [ "$i" -ge 30 ]; then
    echo "MySQL did not become ready in time."
    exit 1
  fi
  sleep 2
done

php artisan db:seed --force

if [ "$#" -gt 0 ]; then
  exec "$@"
fi

exec php artisan serve --host=0.0.0.0 --port=8000
