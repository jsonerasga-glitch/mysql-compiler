#!/bin/sh
set -e

KEY_FILE=/var/lib/sql-client/encryption.key

# Generate a persistent encryption key on first start unless one was provided.
if [ -z "$ENCRYPTION_KEY" ] && [ ! -s "$KEY_FILE" ]; then
    php -r 'echo bin2hex(random_bytes(32));' > "$KEY_FILE"
    echo "Generated a new encryption key in $KEY_FILE"
fi
chown www-data:www-data "$KEY_FILE" 2>/dev/null || true
chmod 600 "$KEY_FILE" 2>/dev/null || true

# Create/upgrade the app database. Retry for a while in case MySQL is still
# starting up (e.g. right after the PC boots).
attempt=1
until php /var/www/html/database/migrate.php; do
    if [ "$attempt" -ge 30 ]; then
        echo "Could not reach the MySQL server at ${DB_HOST}:${DB_PORT} as ${DB_USERNAME}."
        echo "Check the DB_* settings in your .env file, then restart."
        exit 1
    fi
    echo "MySQL not ready yet (attempt $attempt/30), retrying in 5 seconds..."
    attempt=$((attempt + 1))
    sleep 5
done

exec "$@"
