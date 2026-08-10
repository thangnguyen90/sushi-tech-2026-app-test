#!/bin/bash
# ============================================================
# Setup EC2 cho môi trường DEV — cài MySQL local
#
# Cách dùng:
#   bash scripts/setup-ec2-dev.sh [db_password] [db_user] [db_name]
#
# Ví dụ:
#   bash scripts/setup-ec2-dev.sh                        # dùng mặc định
#   bash scripts/setup-ec2-dev.sh my_password            # đổi password
#   bash scripts/setup-ec2-dev.sh my_password myuser     # đổi password + user
#   bash scripts/setup-ec2-dev.sh my_password myuser mydb  # đổi tất cả
#
# Khác với setup-ec2.sh:
#   - Cài MySQL 8.0 local (stg/prod dùng RDS)
#   - Tạo database + user sẵn
# ============================================================

set -euo pipefail

APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
PHP_VERSION="8.3"
DB_PASS="${1:-secret}"
DB_USER="${2:-admin}"
DB_NAME="${3:-sushi_tech}"

echo "🚀 Bắt đầu setup EC2 (DEV — MySQL local)..."
echo "   PHP: $PHP_VERSION"
echo "   App: $APP_DIR"
echo ""

# ────────────────────────────────
# 0. Swap 2GB (t3.micro cần cho npm build)
# ────────────────────────────────
if ! swapon --show | grep -q /swapfile; then
  echo "💾 [0/8] Tạo swap 2GB..."
  sudo fallocate -l 2G /swapfile
  sudo chmod 600 /swapfile
  sudo mkswap /swapfile
  sudo swapon /swapfile
  grep -qxF '/swapfile none swap sw 0 0' /etc/fstab \
    || echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
  echo "✅ Swap: $(free -h | awk '/Swap/{print $2}')"
else
  echo "💾 [0/8] Swap đã có, bỏ qua"
fi

# ────────────────────────────────
# 1. Cập nhật hệ thống
# ────────────────────────────────
echo "📦 [1/8] Cập nhật hệ thống..."
sudo apt update -y && sudo apt upgrade -y
sudo apt install -y git curl unzip software-properties-common

# ────────────────────────────────
# 2. Cài PHP 8.3 + extensions
# ────────────────────────────────
echo "🐘 [2/8] Cài PHP $PHP_VERSION..."
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update -y
sudo apt install -y \
  php${PHP_VERSION} php${PHP_VERSION}-fpm php${PHP_VERSION}-cli \
  php${PHP_VERSION}-mysql php${PHP_VERSION}-redis php${PHP_VERSION}-intl \
  php${PHP_VERSION}-zip php${PHP_VERSION}-mbstring php${PHP_VERSION}-xml \
  php${PHP_VERSION}-curl php${PHP_VERSION}-opcache php${PHP_VERSION}-bcmath \
  php${PHP_VERSION}-gd

sudo systemctl enable php${PHP_VERSION}-fpm
sudo systemctl start php${PHP_VERSION}-fpm
echo "✅ PHP $(php -r 'echo PHP_VERSION;') đã cài"

# ────────────────────────────────
# 3. Cài Composer
# ────────────────────────────────
echo "📦 [3/8] Cài Composer..."
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer
echo "✅ Composer $(composer --version --no-ansi | cut -d' ' -f3) đã cài"

# ────────────────────────────────
# 4. Cài Node.js 22
# ────────────────────────────────
echo "🟩 [4/8] Cài Node.js 22..."
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo bash -
sudo apt install -y nodejs
echo "✅ Node.js $(node -v) đã cài"

# ────────────────────────────────
# 5. Cài MySQL 8.0 (DEV only)
# ────────────────────────────────
echo "🗄️  [5/8] Cài MySQL 8.0..."
sudo apt install -y mysql-server
sudo systemctl enable mysql
sudo systemctl start mysql

# Tạo database + user
sudo mysql -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
sudo mysql -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';"
sudo mysql -e "FLUSH PRIVILEGES;"

echo "✅ MySQL đã cài — DB: ${DB_NAME}, User: ${DB_USER}"

# ────────────────────────────────
# 6. Cài Nginx
# ────────────────────────────────
echo "🌐 [6/8] Cài Nginx..."
sudo apt install -y nginx
sudo systemctl enable nginx
sudo systemctl start nginx
echo "✅ Nginx đã cài"

# ────────────────────────────────
# 7. Cài dependencies + build assets
# ────────────────────────────────
echo "📦 [7/8] Cài dependencies + build..."
cd "$APP_DIR"

composer install --no-dev --optimize-autoloader --no-interaction
NODE_OPTIONS="--max-old-space-size=512" npm ci && NODE_OPTIONS="--max-old-space-size=512" npm run build
echo "✅ Dependencies + assets đã xong"

# ────────────────────────────────
# 8. Cấu hình Nginx
# ────────────────────────────────
echo "🌐 [8/8] Cấu hình Nginx..."

sudo chmod 755 /home/ubuntu

sudo tee /etc/nginx/sites-available/sushi-tech > /dev/null << NGINX
server {
    listen 80;
    server_name _;
    root ${APP_DIR}/public;
    index index.php index.html;

    client_max_body_size 20m;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location /build/ {
        try_files \$uri =404;
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php${PHP_VERSION}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX

sudo ln -sf /etc/nginx/sites-available/sushi-tech /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
echo "✅ Nginx đã cấu hình"

# ────────────────────────────────
# Kết quả — hướng dẫn bước tiếp
# ────────────────────────────────
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✅ Cài đặt xong! (DEV — MySQL local)"
echo ""
echo "MySQL đã tạo sẵn:"
echo "  DB_HOST=127.0.0.1"
echo "  DB_DATABASE=${DB_NAME}"
echo "  DB_USERNAME=${DB_USER}"
echo "  DB_PASSWORD=${DB_PASS}"
echo ""
echo "Việc cần làm tiếp:"
echo ""
echo "  1. Tạo file .env:"
echo "     cp $APP_DIR/.env.example $APP_DIR/.env"
echo "     nano $APP_DIR/.env"
echo "     (điền DB_HOST=127.0.0.1, DB_DATABASE=${DB_NAME},"
echo "      DB_USERNAME=${DB_USER}, DB_PASSWORD=${DB_PASS})"
echo ""
echo "  2. Generate app key:"
echo "     cd $APP_DIR && php artisan key:generate"
echo ""
echo "  3. Phân quyền thư mục:"
echo "     sudo chown -R ubuntu:www-data $APP_DIR/storage $APP_DIR/bootstrap/cache"
echo "     sudo chmod -R 775 $APP_DIR/storage $APP_DIR/bootstrap/cache"
echo ""
echo "  4. Chạy migrate:"
echo "     php artisan migrate"
echo ""
echo "  5. Cache config:"
echo "     php artisan config:cache && php artisan route:cache && php artisan view:cache"
echo ""
echo "  6. Kiểm tra:"
echo "     curl -s -o /dev/null -w '%{http_code}' http://localhost"
echo "     → phải ra 200 hoặc 302"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
