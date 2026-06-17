#!/bin/sh
set -e

# Default PORT if not provided
: ${PORT:=80}

# Render nginx template
if [ -f /etc/nginx/sites-available/default.template ]; then
  envsubst '$PORT' < /etc/nginx/sites-available/default.template > /etc/nginx/sites-available/default
fi

# Ensure php-fpm socket dir exists (if socket is used)
mkdir -p /var/run/php || true
chown -R www-data:www-data /var/run/php || true

# Exec supervisord as PID 1
exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf
