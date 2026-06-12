# sushi-tech-2026-app

SushiTech 2026アプリ用のソースコード  
Stack: Laravel 12 · PHP 8.3 · Node.js 22 · Nginx · MySQL · Redis

---

## Deploy Options

Có 2 cách deploy — chọn 1 trong 2:

| | Cách A — Thủ công | Cách B — GitHub Actions |
|---|---|---|
| Phù hợp | Kiểm soát từng bước, không cần CI/CD | Tự động hoàn toàn khi push code |
| Yêu cầu | AWS CLI + SSH | GitHub Secrets + OIDC |
| Tài liệu | Phần bên dưới | [DEPLOY_MANUAL.md](DEPLOY_MANUAL.md) · [deploy-prod.yml](.github/workflows/deploy-prod.yml) |

---

## Cách A — Deploy thủ công (AMI Baking)

### Tổng quan

```
Terraform tạo Master EC2
    ↓
SSH vào master → setup lần đầu → git pull → build → migrate
    ↓
Kiểm tra app OK trên master
    ↓
Bake AMI từ máy local → Launch EC2 prod → Swap Route53 → Stop EC2 cũ
```

### Scripts

| Script | Chạy ở đâu | Dùng khi nào |
|--------|-----------|-------------|
| `scripts/setup-ec2.sh` | Trên server | Lần đầu setup master (cài PHP, Nginx, Node...) |
| `scripts/deploy.sh` | Trên server | Mỗi lần deploy (git pull → build → migrate → reload) |
| `scripts/bake-ami.sh` | Máy local | Bake AMI → Launch prod → Swap domain → Dọn AMI cũ |
| `scripts/rollback.sh` | Máy local | Rollback về EC2 prod cũ khi có sự cố |

### Lần đầu setup Master EC2

```bash
# 1. Tạo infrastructure
cd eventech-terraform/envs/stg   # hoặc prod
terraform apply
terraform output master_public_ip    # lưu lại
terraform output master_instance_id  # lưu lại

# 2. SSH vào master
ssh -i ~/.ssh/eventech-key.pem ubuntu@<MASTER_PUBLIC_IP>

# 3. Clone repo (dùng GitHub Personal Access Token)
#    Lấy token: GitHub → Settings → Developer settings → Fine-grained tokens
#    Permission: Contents = Read-only
git clone https://<GITHUB_TOKEN>@github.com/bravesoft-inc/sushi-tech-2026-app.git \
  ~/apps/sushi-tech-2026-app
cd ~/apps/sushi-tech-2026-app
git remote set-url origin https://github.com/bravesoft-inc/sushi-tech-2026-app.git

# 4. Cài PHP, Nginx, Node, build...
bash scripts/setup-ec2.sh

# 5. Cấu hình .env + migrate
cp .env.example .env && nano .env
php artisan key:generate
sudo chown -R ubuntu:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache

# 6. Kiểm tra
curl -s -o /dev/null -w "%{http_code}" http://localhost
# → phải ra 200 hoặc 302
```

### Config bake-ami.sh (làm 1 lần)

Mở `scripts/bake-ami.sh` và điền:

```bash
MASTER_INSTANCE_ID="i-0xxxxxxxxxxxxxxxxx"   # terraform output master_instance_id
DOMAIN="your-domain.com"
ROUTE53_ZONE_ID="Z0XXXXXXXXXX"              # Route53 → Hosted zones
```

### Quy trình deploy (mỗi lần có code mới)

```bash
# Bước 1 — Trên server: update code
ssh -i ~/.ssh/eventech-key.pem ubuntu@<MASTER_PUBLIC_IP>
cd ~/apps/sushi-tech-2026-app
bash scripts/deploy.sh
# → thấy "✅ App healthy" → exit

# Bước 2 — Máy local: bake AMI + launch prod
export AWS_PROFILE=eventech-terraform
bash scripts/bake-ami.sh
```

### Rollback

```bash
# Instance ID của EC2 prod cũ in ra ở cuối bake-ami.sh
bash scripts/rollback.sh i-0abc123456789
```

---

## Cách B — Deploy tự động (GitHub Actions)

Push lên `main` → GitHub Actions tự động chạy toàn bộ quy trình:

```
git push origin main
    ↓
Deploy code → Smoke test → Bake AMI → Launch prod → Swap Route53 → Stop cũ
```

### Yêu cầu — GitHub Secrets

| Secret | Lấy từ đâu |
|--------|-----------|
| `AWS_ROLE_ARN` | `terraform output github_actions_role_arn` |
| `MASTER_INSTANCE_ID` | `terraform output master_instance_id` |
| `MASTER_EC2_HOST` | `terraform output master_public_ip` |
| `EC2_SSH_PRIVATE_KEY` | `cat ~/.ssh/eventech-key.pem` |
| `PROD_DOMAIN` | Domain của bạn |
| `ROUTE53_ZONE_ID` | Route53 → Hosted zones |

Xem chi tiết: [.github/workflows/deploy-prod.yml](.github/workflows/deploy-prod.yml)

---

## Cấu trúc thư mục

```
scripts/
  setup-ec2.sh      # Cài PHP 8.3, Nginx, Node.js 22 (chạy 1 lần trên server)
  deploy.sh         # git pull + build + migrate + reload (chạy trên server)
  bake-ami.sh       # Bake AMI + launch prod + swap domain (chạy trên máy local)
  rollback.sh       # Rollback về EC2 prod cũ (chạy trên máy local)
.github/
  workflows/
    deploy-prod.yml # GitHub Actions — AMI Baking tự động
DEPLOY_MANUAL.md    # Hướng dẫn chi tiết Cách A
```

---

## Yêu cầu môi trường

**Máy local:**
- AWS CLI + profile `eventech-terraform`
- SSH key `~/.ssh/eventech-key.pem`
- Terraform (ARM64 nếu dùng Mac M-chip)

**Server (Master EC2):**
- Ubuntu 24.04 LTS
- PHP 8.3, Composer, Node.js 22, Nginx (cài qua `setup-ec2.sh`)
