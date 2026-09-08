#!/bin/bash

# ============================================================
# TURIMA FRAM — Script Deploy Otomatis ke VPS Ubuntu 22.04 / 24.04
# Domain: turima.my.id
# WhatsApp Bot: WAHA (devlikeapro/waha)
# Jalankan di VPS: bash deploy.sh
# ============================================================

set -e  # Berhenti jika ada error

# ============================================================
# ⚙️ KONFIGURASI
# ============================================================
DOMAIN="turima.my.id"
DB_NAME="turima_fram"
DB_USER="turima_user"
DB_PASS="TurimaFram@2025!"         # ← Ganti password ini sesuai keinginan Anda
GITHUB_REPO="https://github.com/rizkiabdullah0509-eng/turima_fram.git"
APP_DIR="/var/www/turima_fram"
# ============================================================

echo ""
echo "╔══════════════════════════════════════════════════════╗"
echo "║   🚀  TURIMA FRAM — AUTO DEPLOY SCRIPT              ║"
echo "║   Domain: turima.my.id                              ║"
echo "║   WhatsApp Bot Gateway: WAHA Docker                  ║"
echo "╚══════════════════════════════════════════════════════╝"
echo ""

# ── STEP 1: Update sistem ──────────────────────────────────
echo "📦 [1/14] Update sistem..."
sudo apt update -y && sudo apt upgrade -y
sudo apt install -y git curl wget unzip software-properties-common

# ── STEP 2: Install PHP 8.2 ───────────────────────────────
echo "🐘 [2/14] Install PHP 8.2..."
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update -y
sudo apt install -y php8.2 php8.2-fpm php8.2-mysql php8.2-xml php8.2-mbstring \
  php8.2-curl php8.2-zip php8.2-bcmath php8.2-tokenizer php8.2-fileinfo \
  php8.2-gd php8.2-intl php8.2-cli
echo "   ✅ PHP $(php -r 'echo PHP_VERSION;') terinstall"

# ── STEP 3: Install Composer ──────────────────────────────
echo "🎵 [3/14] Install Composer..."
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
echo "   ✅ Composer terinstall"

# ── STEP 4: Install Node.js 20 ────────────────────────────
echo "🟢 [4/14] Install Node.js 20..."
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
echo "   ✅ Node.js $(node -v) + npm $(npm -v) terinstall"

# ── STEP 5: Install MySQL ─────────────────────────────────
echo "🗄️  [5/14] Install MySQL..."
sudo apt install -y mysql-server
sudo systemctl start mysql
sudo systemctl enable mysql

sudo mysql -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';"
sudo mysql -e "GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';"
sudo mysql -e "FLUSH PRIVILEGES;"
echo "   ✅ Database '$DB_NAME' siap"

# ── STEP 6: Install Nginx ─────────────────────────────────
echo "🌐 [6/14] Install Nginx..."
sudo apt install -y nginx
sudo systemctl start nginx
sudo systemctl enable nginx
echo "   ✅ Nginx terinstall"

# ── STEP 7: Clone Repository ──────────────────────────────
echo "📁 [7/14] Clone repository dari GitHub..."
if [ -d "$APP_DIR" ]; then
  echo "   Folder sudah ada, melakukan git pull..."
  cd "$APP_DIR"
  sudo git pull origin main
else
  sudo git clone "$GITHUB_REPO" "$APP_DIR"
fi
sudo chown -R $USER:$USER "$APP_DIR"
cd "$APP_DIR"
echo "   ✅ Kode berhasil diunduh"

# ── STEP 8: Setup .env untuk Production ──────────────────
echo "⚙️  [8/14] Setup file .env production..."
cat > .env << 'ENVEOF'
APP_NAME="TURIMA FRAM"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://turima.my.id

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=turima_fram
DB_USERNAME=turima_user
DB_PASSWORD=TurimaFram@2025!

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=public
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

MAIL_MAILER=log
MAIL_FROM_ADDRESS="hello@turima.my.id"
MAIL_FROM_NAME="TURIMA FRAM"

SANCTUM_STATEFUL_DOMAINS=turima.my.id
SESSION_DOMAIN=turima.my.id

VITE_APP_NAME="TURIMA FRAM"

# ---- WAHA (WhatsApp HTTP API) ----
WAHA_URL=http://127.0.0.1:3005
WAHA_API_KEY=turima-secret-key-2026
WAHA_SESSION=default
WAHA_TIMEOUT_MINUTES=15
ENVEOF

# Ganti password di .env jika berbeda
sed -i "s|DB_PASSWORD=TurimaFram@2025!|DB_PASSWORD=$DB_PASS|g" .env

echo "   ✅ File .env production dikonfigurasi"

# ── STEP 9: Install Dependencies & Build ──────────────────
echo "📦 [9/14] Install dependencies & build frontend..."
composer install --no-dev --optimize-autoloader --no-interaction
php artisan key:generate --force
npm install
npm run build
echo "   ✅ Build frontend Vue 3 selesai"

# ── STEP 10: Migrasi Database & Storage ───────────────────
echo "🗄️  [10/14] Migrasi database..."
php artisan migrate --force
php artisan storage:link --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
echo "   ✅ Database migrasi selesai"

# ── STEP 11: Set Permission ────────────────────────────────
echo "🔐 [11/14] Setting permission..."
sudo chown -R www-data:www-data "$APP_DIR"
sudo chmod -R 755 "$APP_DIR"
sudo chmod -R 775 "$APP_DIR/storage"
sudo chmod -R 775 "$APP_DIR/bootstrap/cache"
echo "   ✅ Permission diset"

# ── STEP 12: Konfigurasi Nginx dengan Domain & WAHA Proxy ───
echo "🌐 [12/14] Konfigurasi Nginx untuk turima.my.id..."
sudo tee /etc/nginx/sites-available/turima_fram > /dev/null << 'NGINXEOF'
server {
    listen 80;
    server_name turima.my.id www.turima.my.id;
    root /var/www/turima_fram/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    # WAHA Dashboard & API Proxy (/waha/)
    location /waha/ {
        proxy_pass http://127.0.0.1:3000/;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # Vue Router SPA — semua route diarahkan ke index.php
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Batas upload foto absensi (10MB)
    client_max_body_size 10M;
}
NGINXEOF

sudo ln -sf /etc/nginx/sites-available/turima_fram /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl reload nginx
echo "   ✅ Nginx dikonfigurasi untuk turima.my.id"

# ── STEP 13: Pasang SSL HTTPS Gratis (Certbot) ────────────
echo "🔒 [13/14] Memasang SSL / HTTPS gratis (Certbot)..."
sudo apt install -y certbot python3-certbot-nginx

CURRENT_IP=$(curl -s ifconfig.me || echo "unknown")
DOMAIN_IP=$(dig +short turima.my.id | tail -1 || echo "unknown")

if [ "$CURRENT_IP" = "$DOMAIN_IP" ] && [ "$CURRENT_IP" != "unknown" ]; then
  echo "   ✅ Domain turima.my.id sudah mengarah ke VPS ini ($CURRENT_IP)"
  sudo certbot --nginx -d turima.my.id -d www.turima.my.id --non-interactive --agree-tos --email admin@turima.my.id
  echo "   ✅ SSL/HTTPS aktif!"
else
  echo "   ⚠️  Domain belum mengarah ke server ini ($CURRENT_IP vs $DOMAIN_IP)."
  echo "   Setelah DNS aktif, jalankan: sudo certbot --nginx -d turima.my.id -d www.turima.my.id"
fi

# ── STEP 14: Install Docker & Jalankan WAHA Gateway ────────
echo "🤖 [14/14] Menyiapkan WhatsApp Gateway (WAHA Docker)..."
if ! command -v docker &> /dev/null; then
  echo "   Mengunduh & menginstall Docker..."
  curl -fsSL https://get.docker.com -o get-docker.sh
  sudo sh get-docker.sh
  sudo usermod -aG docker $USER
  rm -f get-docker.sh
  echo "   ✅ Docker terinstall"
fi

sudo mkdir -p /var/waha-data
sudo chmod -R 777 /var/waha-data

if [ "$(sudo docker ps -q -f name=waha-turima-fram)" ]; then
  echo "   ✅ Kontainer waha-turima-fram sudah berjalan."
elif [ "$(sudo docker ps -aq -f name=waha-turima-fram)" ]; then
  echo "   Menyalakan kontainer waha-turima-fram..."
  sudo docker start waha-turima-fram
  echo "   ✅ WAHA dinyalakan"
else
  echo "   Menjalankan kontainer WAHA Turima Fram..."
  sudo docker compose -f "$APP_DIR/docker-compose.waha.yml" up -d
  echo "   ✅ Kontainer WAHA berhasil diluncurkan di port 3005"
fi

# ── SELESAI ────────────────────────────────────────────────
echo ""
echo "╔══════════════════════════════════════════════════════════════╗"
echo "║   ✅  DEPLOY TURIMA FRAM + WHATSAPP BOT BERHASIL!            ║"
echo "╠══════════════════════════════════════════════════════════════╣"
echo "║   🌐  Web App     : https://turima.my.id                    ║"
echo "║   🤖  WAHA QR/Dash: https://turima.my.id/waha/dashboard/    ║"
echo "║   📁  Folder App  : /var/www/turima_fram                    ║"
echo "║   🗄️   Database    : turima_fram                             ║"
echo "╚══════════════════════════════════════════════════════════════╝"
echo ""
echo "📱 LANGKAH SELANJUTNYA UNTUK WHATSAPP BOT:"
echo "   1. Buka browser ke: https://turima.my.id/waha/dashboard/"
echo "   2. Klik 'Start' pada session default, lalu Scan QR Code menggunakan WhatsApp Bot."
echo "   3. Masukkan nomor WhatsApp karyawan di menu 'Daftar Karyawan' di web."
echo "   4. Karyawan siap mengirim pesan ke bot WhatsApp!"
echo ""
