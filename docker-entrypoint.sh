#!/bin/sh
set -e

echo "=================================================="
echo "  Memulai Container Dompet Digital di Coolify     "
echo "=================================================="

# Tunggu database PostgreSQL siap menerima koneksi
echo "Menunggu database PostgreSQL siap..."
MAX_TRIES=30
COUNT=0

until php -r "
try {
    \$host = getenv('DB_HOST') ?: 'db';
    \$port = getenv('DB_PORT') ?: '5432';
    \$dbname = getenv('DB_NAME') ?: 'dompetDigitalDB';
    \$user = getenv('DB_USER') ?: 'postgres';
    \$pass = getenv('DB_PASSWORD') ?: (getenv('DB_PASS') ?: 'Asgardian13');
    new PDO(\"pgsql:host=\$host;port=\$port;dbname=\$dbname\", \$user, \$pass);
    exit(0);
} catch (Exception \$e) {
    exit(1);
}
" >/dev/null 2>&1; do
    COUNT=$((COUNT + 1))
    if [ $COUNT -ge $MAX_TRIES ]; then
        echo "⚠️ Timeout menunggu database. Melanjutkan startup..."
        break
    fi
    sleep 2
done

echo "✅ Database PostgreSQL terhubung! Menjalankan auto-init schema & seed..."
php /var/www/html/api/init_db.php || true

echo "🚀 Menjalankan PHP-FPM..."
exec php-fpm
