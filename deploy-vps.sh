#!/bin/bash
# ==============================================================================
# Script Otomatis Deployment Dompet Digital di VPS Linux (Ubuntu / Debian)
# Mendukung Free Domain DNS (DuckDNS, sslip.io, nip.io, No-IP, dll.) & Free SSL
# ==============================================================================

set -e

# Warna untuk output terminal
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${BLUE}=====================================================${NC}"
echo -e "${GREEN}  DEPLOYMENT SCRIPT - DOMPET DIGITAL (KAS KELAS)     ${NC}"
echo -e "${BLUE}=====================================================${NC}"

# Cek apakah dijalankan sebagai root / sudo
if [ "$EUID" -ne 0 ]; then
  echo -e "${RED}Harap jalankan script ini dengan hak akses sudo / root!${NC}"
  echo "Contoh: sudo bash deploy-vps.sh"
  exit 1
fi

# Default domain
DEFAULT_DOMAIN="dompetkelas.duckdns.org"

echo ""
echo -e "${YELLOW}Domain Deployment:${NC} ${GREEN}${DEFAULT_DOMAIN}${NC}"
read -p "Gunakan domain ini? (tekan Enter untuk langsung memakai ${DEFAULT_DOMAIN}, atau ketik domain lain): " USER_DOMAIN
DOMAIN_NAME=${USER_DOMAIN:-$DEFAULT_DOMAIN}

echo ""
read -p "Masukkan Email untuk Sertifikat SSL Let's Encrypt: " SSL_EMAIL
SSL_EMAIL=${SSL_EMAIL:-"admin@${DOMAIN_NAME}"}

DB_NAME="dompetDigitalDB"
DB_USER="postgres"
DB_PASS="Asgardian13"
APP_DIR="/var/www/dompetDigital"

echo ""
echo -e "${BLUE}Konfigurasi Deployment:${NC}"
echo -e " - Domain: ${GREEN}${DOMAIN_NAME}${NC}"
echo -e " - Directory: ${GREEN}${APP_DIR}${NC}"
echo -e " - Database: ${GREEN}${DB_NAME}${NC}"
echo -e " - DB User: ${GREEN}${DB_USER}${NC}"
echo -e " - SSL Email: ${GREEN}${SSL_EMAIL}${NC}"
echo ""
read -p "Lanjutkan proses instalasi? (y/n): " CONFIRM
if [[ "$CONFIRM" != "y" && "$CONFIRM" != "Y" ]]; then
    echo "Instalasi dibatalkan."
    exit 0
fi

# 1. Update Repository Sistem
echo -e "\n${YELLOW}[1/7] Memperbarui paket sistem Ubuntu/Debian...${NC}"
apt update && apt upgrade -y

# 2. Instalasi Paket yang Dibutuhkan
echo -e "\n${YELLOW}[2/7] Menginstal Nginx, PHP-FPM, PostgreSQL, Git, dan Certbot...${NC}"
apt install -y software-properties-common curl git ufw

# Tambahkan repository PHP jika di Ubuntu
if command -v add-apt-repository >/dev/null 2>&1; then
    add-apt-repository -y ppa:ondrej/php || true
    apt update
fi

# Install PHP & Ekstensi PostgreSQL
PHP_VER="8.3"
if ! apt install -y php${PHP_VER}-fpm php${PHP_VER}-pgsql php${PHP_VER}-mbstring php${PHP_VER}-xml php${PHP_VER}-curl php${PHP_VER}-zip php${PHP_VER}-intl 2>/dev/null; then
    # Fallback ke versi default jika 8.3 belum di-repo
    PHP_VER=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || echo "")
    if [ -z "$PHP_VER" ]; then
        apt install -y php-fpm php-pgsql php-mbstring php-xml php-curl php-zip php-intl
        PHP_VER=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
    fi
fi

# Install Nginx, PostgreSQL, Certbot
apt install -y nginx postgresql postgresql-contrib certbot python3-certbot-nginx

# 3. Konfigurasi Database PostgreSQL
echo -e "\n${YELLOW}[3/7] Mengonfigurasi PostgreSQL...${NC}"
systemctl start postgresql
systemctl enable postgresql

sudo -u postgres psql -c "ALTER USER postgres PASSWORD '${DB_PASS}';"
sudo -u postgres psql -tc "SELECT 1 FROM pg_database WHERE datname = '${DB_NAME}'" | grep -q 1 || \
sudo -u postgres psql -c "CREATE DATABASE \"${DB_NAME}\";"

# 4. Menyiapkan File Aplikasi
echo -e "\n${YELLOW}[4/7] Menyalin file aplikasi ke ${APP_DIR}...${NC}"
mkdir -p ${APP_DIR}
cp -r ./* ${APP_DIR}/ 2>/dev/null || true

# Buat file .env di server
cat <<EOF > ${APP_DIR}/.env
APP_ENV=production
APP_URL=http://${DOMAIN_NAME}
DB_HOST=localhost
DB_PORT=5432
DB_NAME=${DB_NAME}
DB_USER=${DB_USER}
DB_PASSWORD=${DB_PASS}
EOF

# Inisialisasi Tabel & Data Awal Database
echo -e "\n${YELLOW}[5/7] Menginisialisasi tabel database PostgreSQL...${NC}"
cd ${APP_DIR}
php api/init_db.php

# Atur Hak Akses File
chown -R www-data:www-data ${APP_DIR}
chmod -R 755 ${APP_DIR}

# 5. Konfigurasi Virtual Host Nginx
echo -e "\n${YELLOW}[6/7] Mengonfigurasi Web Server Nginx...${NC}"
NGINX_CONF="/etc/nginx/sites-available/dompetDigital"

cat <<EOF > ${NGINX_CONF}
server {
    listen 80;
    server_name ${DOMAIN_NAME};

    root ${APP_DIR};
    index index.html login.php index.php;

    charset utf-8;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Gzip
    gzip on;
    gzip_types text/plain text/css text/xml application/json application/javascript;

    # Static file caching
    location ~* \.(css|js|jpg|jpeg|png|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, no-transform";
        access_log off;
    }

    location / {
        try_files \$uri \$uri/ /index.html;
    }

    # PHP-FPM Handler
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php${PHP_VER}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
    }

    # Proteksi file tersembunyi & file sensitif
    location ~ /\.(?!well-known).* {
        deny all;
    }

    location ~ \.(env|git|sql|md|log|sh)$ {
        deny all;
    }
}
EOF

# Aktifkan konfigurasi
ln -sf ${NGINX_CONF} /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default

# Test & Reload Nginx
nginx -t
systemctl reload nginx

# 6. Konfigurasi Firewall UFW
echo -e "\n${YELLOW}[7/7] Mengaktifkan Firewall (SSH, HTTP, HTTPS)...${NC}"
ufw allow 22/tcp || true
ufw allow 80/tcp
ufw allow 443/tcp
echo "y" | ufw enable || true

# 7. Penerbitan SSL Gratis Let's Encrypt
echo -e "\n${YELLOW}Menerbitkan Sertifikat SSL Gratis (HTTPS) via Let's Encrypt...${NC}"
certbot --nginx -d ${DOMAIN_NAME} --non-interactive --agree-tos --email ${SSL_EMAIL} --redirect || {
    echo -e "${YELLOW}Catatan: Penerbitan SSL otomatis memerlukan domain sudah terarah (pointed) ke IP VPS.${NC}"
    echo -e "Jika DNS belum propagasi, jalankan perintah ini nanti:"
    echo -e "   ${GREEN}sudo certbot --nginx -d ${DOMAIN_NAME}${NC}"
}

echo ""
echo -e "${GREEN}=====================================================${NC}"
echo -e "${GREEN}  DEPLOYMENT BERHASIL SELESAI!                       ${NC}"
echo -e "${GREEN}=====================================================${NC}"
echo -e "Aplikasi Anda sekarang dapat diakses di:"
echo -e "👉 ${BLUE}http://${DOMAIN_NAME}${NC} (atau ${GREEN}https://${DOMAIN_NAME}${NC})"
echo ""
echo -e "Kredensial Login:"
echo -e " 👑 Super Admin : NIS ${YELLOW}superadmin${NC} | Password ${YELLOW}admin123${NC}"
echo -e " 👨‍💼 Admin Kelas : NIS ${YELLOW}admin1${NC}     | Password ${YELLOW}password${NC}"
echo -e " 🎒 Siswa       : NIS ${YELLOW}siswa1${NC}     | Password ${YELLOW}password${NC}"
echo ""
EOF

chmod +x deploy-vps.sh
