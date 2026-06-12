#!/bin/bash
# ============================================================
# Setup domain + SSL (Certbot) cho Nginx
#
# Chạy SAU KHI app đã chạy được bằng IP (setup-ec2.sh xong)
# Yêu cầu: domain đã trỏ A record về IP của EC2
#
# Cách dùng:
#   bash scripts/setup-ssl.sh <domain> [email]
#
# Ví dụ:
#   bash scripts/setup-ssl.sh example.com
#   bash scripts/setup-ssl.sh example.com admin@example.com
# ============================================================

set -euo pipefail

DOMAIN="${1:-}"
EMAIL="${2:-}"
APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
PHP_VERSION="8.3"

if [ -z "$DOMAIN" ]; then
  echo "❌ Thiếu domain."
  echo "   Cách dùng: bash scripts/setup-ssl.sh example.com [email]"
  exit 1
fi

echo "🌐 Domain : $DOMAIN"
echo "📧 Email  : ${EMAIL:-<bỏ qua — dùng --register-unsafely-without-email>}"
echo ""

# ────────────────────────────────
# 1. Kiểm tra domain đã trỏ về IP này chưa
# ────────────────────────────────
echo "🔍 [1/4] Kiểm tra DNS..."
SERVER_IP="$(curl -s ifconfig.me)"
DOMAIN_IP="$(dig +short "$DOMAIN" | tail -1)"

echo "   Server IP : $SERVER_IP"
echo "   Domain IP : $DOMAIN_IP"

if [ "$SERVER_IP" != "$DOMAIN_IP" ]; then
  echo ""
  echo "⚠️  DNS chưa trỏ đúng!"
  echo "   $DOMAIN → $DOMAIN_IP (cần → $SERVER_IP)"
  echo ""
  echo "   Vào DNS provider → thêm A record:"
  echo "   Type: A | Name: @ | Value: $SERVER_IP | TTL: 300"
  echo ""
  read -r -p "   Tiếp tục anyway? (y/N): " CONFIRM
  [[ "$CONFIRM" =~ ^[Yy]$ ]] || exit 1
fi

# ────────────────────────────────
# 2. Cập nhật Nginx config với domain
# ────────────────────────────────
echo "🌐 [2/4] Cập nhật Nginx config..."

sudo tee /etc/nginx/sites-available/sushi-tech > /dev/null << NGINX
server {
    listen 80;
    server_name ${DOMAIN} www.${DOMAIN};
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

sudo nginx -t && sudo systemctl reload nginx
echo "✅ Nginx đã cập nhật với domain: $DOMAIN"

# ────────────────────────────────
# 3. Cài Certbot
# ────────────────────────────────
echo "🔒 [3/4] Cài Certbot..."
sudo apt install -y certbot python3-certbot-nginx
echo "✅ Certbot đã cài"

# ────────────────────────────────
# 4. Lấy SSL certificate
# ────────────────────────────────
echo "🔒 [4/4] Lấy SSL certificate..."

if [ -n "$EMAIL" ]; then
  sudo certbot --nginx \
    -d "$DOMAIN" \
    -d "www.${DOMAIN}" \
    --email "$EMAIL" \
    --agree-tos \
    --non-interactive \
    --redirect
else
  sudo certbot --nginx \
    -d "$DOMAIN" \
    -d "www.${DOMAIN}" \
    --register-unsafely-without-email \
    --agree-tos \
    --non-interactive \
    --redirect
fi

echo "✅ SSL đã cài — Certbot tự cập nhật Nginx thêm HTTPS"

# ────────────────────────────────
# Cập nhật APP_URL trong .env
# ────────────────────────────────
ENV_FILE="$APP_DIR/.env"
if [ -f "$ENV_FILE" ]; then
  echo ""
  echo "📝 Cập nhật APP_URL trong .env..."
  sed -i "s|^APP_URL=.*|APP_URL=https://${DOMAIN}|" "$ENV_FILE"
  cd "$APP_DIR"
  php artisan config:cache
  echo "✅ APP_URL=https://${DOMAIN}"
fi

# ────────────────────────────────
# Kết quả
# ────────────────────────────────
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✅ SSL setup xong!"
echo ""
echo "   https://${DOMAIN}"
echo "   https://www.${DOMAIN}"
echo ""
echo "Certbot tự động renew qua systemd timer."
echo "Kiểm tra: sudo systemctl status certbot.timer"
echo ""
echo "Test renew (dry run):"
echo "   sudo certbot renew --dry-run"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
