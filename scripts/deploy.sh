#!/bin/bash
# ============================================================
# Deploy script — chạy trên Master EC2 mỗi lần update code
#
# Cách dùng (SSH vào master rồi chạy):
#   cd ~/apps/sushi-tech-2026-app
#   bash scripts/deploy.sh
# ============================================================

set -euo pipefail

APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
PHP_VERSION="8.3"

cd "$APP_DIR"

echo "🚀 Bắt đầu deploy..."
echo "   Dir: $APP_DIR"
echo ""

# ────────────────────────────────
# 1. Pull code mới nhất
# ────────────────────────────────
echo "📥 [1/5] Pull code..."
git pull origin main
echo "✅ Code mới nhất: $(git log -1 --format='%h %s')"

# ────────────────────────────────
# 2. Cài PHP dependencies
# ────────────────────────────────
echo "📦 [2/5] Composer install..."
composer install --no-dev --optimize-autoloader --no-interaction
echo "✅ PHP dependencies đã cài"

# ────────────────────────────────
# 3. Build frontend assets
# ────────────────────────────────
echo "🔨 [3/5] Build assets..."
npm ci && npm run build
echo "✅ Assets đã build"

# ────────────────────────────────
# 4. Migrate + cache
# ────────────────────────────────
echo "⚙️  [4/5] Migrate + cache..."
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
echo "✅ Migrate + cache xong"

# ────────────────────────────────
# 5. Reload PHP-FPM
# ────────────────────────────────
echo "🔄 [5/5] Reload PHP-FPM..."
sudo systemctl reload php${PHP_VERSION}-fpm
echo "✅ PHP-FPM đã reload"

# ────────────────────────────────
# Kiểm tra
# ────────────────────────────────
STATUS=$(curl -s -o /dev/null -w "%{http_code}" http://localhost || echo "000")
echo ""
if [ "$STATUS" = "200" ] || [ "$STATUS" = "302" ]; then
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  echo "✅ Deploy xong! App healthy (HTTP $STATUS)"
  echo ""
  echo "Bước tiếp theo — Bake AMI từ máy local:"
  echo "  export MASTER_INSTANCE_ID=<instance-id>"
  echo "  bash scripts/bake-ami.sh"
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
else
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  echo "❌ App không healthy (HTTP $STATUS)"
  echo "   Kiểm tra log: sudo tail -50 /var/log/nginx/error.log"
  echo "   Kiểm tra Laravel: tail -50 storage/logs/laravel.log"
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  exit 1
fi
