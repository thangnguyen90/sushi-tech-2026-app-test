#!/bin/bash
# ============================================================
# Setup Master EC2 lần đầu — chạy trên server sau khi clone repo
#
# Cách dùng:
#   1. SSH vào master EC2
#   2. Clone repo (dùng token):
#      git clone https://<TOKEN>@github.com/bravesoft-inc/sushi-tech-2026-app.git ~/apps/sushi-tech-2026-app
#   3. Chạy script này:
#      cd ~/apps/sushi-tech-2026-app
#      bash scripts/setup-ec2.sh
# ============================================================

set -euo pipefail

APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
PHP_VERSION="8.3"

echo "🚀 Bắt đầu setup Master EC2..."
echo "   PHP: $PHP_VERSION"
echo "   App: $APP_DIR"
echo ""

# ────────────────────────────────
# 1. Cập nhật hệ thống
# ────────────────────────────────
echo "📦 [1/7] Cập nhật hệ thống..."
sudo apt update -y && sudo apt upgrade -y
sudo apt install -y git curl unzip software-properties-common

# ────────────────────────────────
# 2. Cài PHP 8.3 + extensions
# ────────────────────────────────
echo "🐘 [2/7] Cài PHP $PHP_VERSION..."
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
echo "📦 [3/7] Cài Composer..."
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer
echo "✅ Composer $(composer --version --no-ansi | cut -d' ' -f3) đã cài"

# ────────────────────────────────
# 4. Cài Node.js 22
# ────────────────────────────────
echo "🟩 [4/7] Cài Node.js 22..."
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo bash -
sudo apt install -y nodejs
echo "✅ Node.js $(node -v) đã cài"

# ────────────────────────────────
# 5. Cài Nginx
# ────────────────────────────────
echo "🌐 [5/7] Cài Nginx..."
sudo apt install -y nginx
sudo systemctl enable nginx
sudo systemctl start nginx
echo "✅ Nginx đã cài"

# ────────────────────────────────
# 6. Cài dependencies + build assets
# ────────────────────────────────
echo "📦 [6/7] Cài dependencies + build..."
cd "$APP_DIR"

composer install --no-dev --optimize-autoloader --no-interaction
npm ci && npm run build
echo "✅ Dependencies + assets đã xong"

# ────────────────────────────────
# 7. Cấu hình Nginx
# ────────────────────────────────
echo "🌐 [7/7] Cấu hình Nginx..."

# Cấp quyền home dir để Nginx đọc được
sudo chmod 755 /home/ubuntu

sudo tee /etc/nginx/sites-available/sushi-tech > /dev/null << NGINX
server {
    listen 80;
    server_name _;
    root /home/ubuntu/apps/sushi-tech-2026-app/public;
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
echo "✅ Cài đặt xong!"
echo ""
echo "Việc cần làm tiếp:"
echo ""
echo "  1. Tạo file .env:"
echo "     cp $APP_DIR/.env.example $APP_DIR/.env"
echo "     nano $APP_DIR/.env"
echo "     (điền DB_HOST, DB_PASSWORD, REDIS_HOST, APP_URL...)"
echo ""
echo "  2. Generate app key:"
echo "     cd $APP_DIR && php artisan key:generate"
echo ""
echo "  3. Phân quyền thư mục:"
echo "     sudo chown -R ubuntu:www-data $APP_DIR/storage $APP_DIR/bootstrap/cache"
echo "     sudo chmod -R 775 $APP_DIR/storage $APP_DIR/bootstrap/cache"
echo ""
echo "  4. Chạy migrate:"
echo "     php artisan migrate --force"
echo ""
echo "  5. Cache config:"
echo "     php artisan config:cache && php artisan route:cache && php artisan view:cache"
echo ""
echo "  6. Kiểm tra:"
echo "     curl -s -o /dev/null -w '%{http_code}' http://localhost"
echo "     → phải ra 200 hoặc 302"
echo ""
echo "  7. Nếu OK → Bake AMI từ máy local (xem DEPLOY_MANUAL.md)"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
