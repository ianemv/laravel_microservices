#!/bin/sh
# Create .env file from environment variables
cat > /var/www/html/.env << EOF
APP_NAME=${APP_NAME:-AuthService}
APP_ENV=${APP_ENV:-production}
APP_DEBUG=${APP_DEBUG:-false}
APP_KEY=${APP_KEY}
APP_URL=${APP_URL:-http://localhost}
LOG_CHANNEL=${LOG_CHANNEL:-stderr}
LOG_LEVEL=${LOG_LEVEL:-info}
DB_CONNECTION=${DB_CONNECTION:-mysql}
DB_HOST=${DB_HOST:-127.0.0.1}
DB_PORT=${DB_PORT:-3306}
DB_DATABASE=${DB_DATABASE:-microservices}
DB_USERNAME=${DB_USERNAME:-root}
DB_PASSWORD=${DB_PASSWORD}
JWT_SECRET=${JWT_SECRET}
JWT_TTL=${JWT_TTL:-1440}
JWT_ALGO=${JWT_ALGO:-HS256}
EOF

# Clear any cached config
php artisan config:clear

# Start Laravel server
exec php artisan serve --host=0.0.0.0 --port=8000
