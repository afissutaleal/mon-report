#!/bin/bash
set -e

# Adjust Apache listening port if PORT environment variable is set (Render/Railway/Fly.io)
if [ -n "$PORT" ]; then
    sed -i "s/Listen 80/Listen $PORT/g" /etc/apache2/ports.conf
    sed -i "s/<VirtualHost \*:80>/<VirtualHost \*:$PORT>/g" /etc/apache2/sites-available/000-default.conf
fi

# Ensure permissions
chown -R www-data:www-data /var/www/html/database /var/www/html/uploads || true

exec "$@"
