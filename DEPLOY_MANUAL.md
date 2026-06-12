# Hướng dẫn Deploy thủ công — Không dùng CI/CD

> Áp dụng cho: sushi-tech-2026-app (Laravel 12 + PHP 8.2)  
> Môi trường: STG / PROD  
> Pattern: Master EC2 → Bake AMI → Launch EC2 prod → Swap Route53

---

## Tổng quan quy trình

```
[Lần đầu]
  Terraform tạo Master EC2
        ↓
  SSH vào master → cài PHP/Nginx/Node → clone source → cấu hình .env
        ↓
  Kiểm tra app chạy OK trên master
        ↓
  Bake AMI từ master (snapshot toàn bộ ổ cứng)
        ↓
  Launch EC2 prod từ AMI → Trỏ domain vào prod

[Mỗi lần deploy tiếp theo]
  SSH vào master → git pull → build → migrate
        ↓
  Kiểm tra app OK → Bake AMI mới
        ↓
  Launch EC2 prod mới từ AMI → Swap Route53 → Stop EC2 prod cũ
```

---

## PHẦN 1 — Cài đặt Master EC2 (Thực hiện 1 lần duy nhất)

### Bước 1.1 — Tạo infrastructure bằng Terraform

```bash
# Trên máy local
export AWS_PROFILE=eventech-terraform

cd eventech-terraform/envs/stg   # hoặc prod

terraform init
terraform apply

# Lấy thông tin sau khi apply xong
terraform output master_public_ip    # IP để SSH
terraform output master_instance_id  # ID để bake AMI
```

Ghi lại 2 giá trị trên, sẽ dùng xuyên suốt.

---

### Bước 1.2 — Lấy GitHub Personal Access Token

Dùng để clone repo mà không cần SSH key.

1. Vào **GitHub → Settings → Developer settings → Personal access tokens → Fine-grained tokens**
2. **Generate new token**:
   - Token name: `ec2-sushi-tech-master`
   - Repository access: chọn repo `sushi-tech-2026-app`
   - Permissions → Contents: **Read-only**
3. Copy token (dạng `github_pat_xxxx...`)

---

### Bước 1.3 — SSH vào Master EC2

```bash
ssh -i ~/.ssh/eventech-key.pem ubuntu@<MASTER_PUBLIC_IP>
```

---

### Bước 1.4 — Clone repo

```bash
git clone https://<GITHUB_TOKEN>@github.com/bravesoft-inc/sushi-tech-2026-app.git \
  ~/apps/sushi-tech-2026-app

# Xóa token khỏi remote URL sau khi clone (bảo mật)
cd ~/apps/sushi-tech-2026-app
git remote set-url origin https://github.com/bravesoft-inc/sushi-tech-2026-app.git
```

---

### Bước 1.5 — Chạy setup script (từ trong repo)

```bash
cd ~/apps/sushi-tech-2026-app
bash scripts/setup-ec2.sh
```

Script tự động cài: PHP 8.3, Composer, Node.js 22, Nginx, composer install, npm build, cấu hình Nginx.

---

### Bước 1.6 — Tạo .env + migrate

```bash
cd ~/apps/sushi-tech-2026-app

cp .env.example .env
nano .env
```

Điền vào `.env`:
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=http://<MASTER_PUBLIC_IP>

DB_HOST=<aurora-endpoint>
DB_PORT=3306
DB_DATABASE=sushi_tech
DB_USERNAME=admin
DB_PASSWORD=<mật khẩu>

REDIS_HOST=<elasticache-endpoint>
REDIS_PORT=6379
```

```bash
php artisan key:generate
sudo chown -R ubuntu:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

---

### Bước 1.7 — Kiểm tra app

```bash
# Trên server
curl -s -o /dev/null -w "%{http_code}" http://localhost
# Phải ra: 200 hoặc 302

# Trên máy local (thay bằng IP thực)
curl -s -o /dev/null -w "%{http_code}" http://<MASTER_PUBLIC_IP>
# Phải ra: 200 hoặc 302
```

✅ Nếu ra 200/302 → Master đã sẵn sàng, tiếp tục bake AMI.

---

## PHẦN 2 — Bake AMI từ Master EC2

> Thực hiện trên **máy local** (cần AWS CLI đã cấu hình)

### Bước 2.1 — Tạo AMI

```bash
export AWS_PROFILE=eventech-terraform
MASTER_INSTANCE_ID=<i-0abc123...>   # lấy từ terraform output
TIMESTAMP=$(date +%Y%m%d-%H%M%S)
AMI_NAME="sushi-tech-prod-$TIMESTAMP"

AMI_ID=$(aws ec2 create-image \
  --instance-id $MASTER_INSTANCE_ID \
  --name "$AMI_NAME" \
  --description "Production AMI - $TIMESTAMP" \
  --no-reboot \
  --tag-specifications "ResourceType=image,Tags=[{Key=Name,Value=$AMI_NAME},{Key=Project,Value=sushi-tech}]" \
  --query 'ImageId' \
  --output text \
  --region ap-northeast-1)

echo "AMI ID: $AMI_ID"
echo "AMI Name: $AMI_NAME"
```

> `--no-reboot`: không restart master khi bake → app không bị gián đoạn

---

### Bước 2.2 — Chờ AMI sẵn sàng

```bash
echo "Đang chờ AMI $AMI_ID..."
aws ec2 wait image-available \
  --image-ids $AMI_ID \
  --region ap-northeast-1

echo "✅ AMI sẵn sàng: $AMI_ID"
```

> Thường mất 3–10 phút tùy dung lượng ổ cứng.

---

## PHẦN 3 — Launch EC2 Prod từ AMI

### Bước 3.1 — Lấy thông tin từ Master EC2

```bash
# Lấy subnet ID
SUBNET_ID=$(aws ec2 describe-instances \
  --instance-ids $MASTER_INSTANCE_ID \
  --query 'Reservations[0].Instances[0].SubnetId' \
  --output text --region ap-northeast-1)

# Lấy security group IDs
SG_IDS=$(aws ec2 describe-instances \
  --instance-ids $MASTER_INSTANCE_ID \
  --query 'Reservations[0].Instances[0].SecurityGroups[*].GroupId' \
  --output text --region ap-northeast-1 | tr '\t' ' ')

# Lấy key pair name
KEY_NAME=$(aws ec2 describe-instances \
  --instance-ids $MASTER_INSTANCE_ID \
  --query 'Reservations[0].Instances[0].KeyName' \
  --output text --region ap-northeast-1)

# Lấy IAM instance profile
IAM_PROFILE=$(aws ec2 describe-instances \
  --instance-ids $MASTER_INSTANCE_ID \
  --query 'Reservations[0].Instances[0].IamInstanceProfile.Arn' \
  --output text --region ap-northeast-1 | awk -F'/' '{print $NF}')

echo "Subnet: $SUBNET_ID"
echo "SG: $SG_IDS"
echo "Key: $KEY_NAME"
echo "IAM: $IAM_PROFILE"
```

---

### Bước 3.2 — Launch EC2 prod mới

```bash
NEW_INSTANCE_ID=$(aws ec2 run-instances \
  --image-id $AMI_ID \
  --instance-type t3.micro \
  --subnet-id $SUBNET_ID \
  --security-group-ids $SG_IDS \
  --key-name $KEY_NAME \
  --iam-instance-profile Name=$IAM_PROFILE \
  --associate-public-ip-address \
  --tag-specifications "ResourceType=instance,Tags=[{Key=Name,Value=sushi-tech-prod},{Key=Role,Value=prod},{Key=AMI,Value=$AMI_NAME}]" \
  --query 'Instances[0].InstanceId' \
  --output text --region ap-northeast-1)

echo "EC2 mới: $NEW_INSTANCE_ID"

# Chờ EC2 running
aws ec2 wait instance-running \
  --instance-ids $NEW_INSTANCE_ID \
  --region ap-northeast-1

# Lấy public IP
NEW_IP=$(aws ec2 describe-instances \
  --instance-ids $NEW_INSTANCE_ID \
  --query 'Reservations[0].Instances[0].PublicIpAddress' \
  --output text --region ap-northeast-1)

echo "✅ EC2 prod mới: $NEW_INSTANCE_ID ($NEW_IP)"
```

---

### Bước 3.3 — Kiểm tra EC2 prod mới

```bash
# Chờ app khởi động (EC2 mới boot từ AMI cần ~30 giây)
sleep 30

for i in {1..10}; do
  STATUS=$(curl -s -o /dev/null -w "%{http_code}" http://$NEW_IP || echo "000")
  if [ "$STATUS" = "200" ] || [ "$STATUS" = "302" ]; then
    echo "✅ EC2 mới healthy (HTTP $STATUS)"
    break
  fi
  echo "Thử lần $i/10... (HTTP $STATUS)"
  sleep 15
done
```

---

## PHẦN 4 — Trỏ Domain vào EC2 Prod Mới

### Bước 4.1 — Cập nhật Route53

```bash
DOMAIN="your-domain.com"           # ví dụ: app.sushitech.jp
ROUTE53_ZONE_ID="Z0XXXXXXXXXX"     # lấy từ AWS Console → Route53 → Hosted zones

aws route53 change-resource-record-sets \
  --hosted-zone-id $ROUTE53_ZONE_ID \
  --change-batch "{
    \"Changes\": [{
      \"Action\": \"UPSERT\",
      \"ResourceRecordSet\": {
        \"Name\": \"$DOMAIN\",
        \"Type\": \"A\",
        \"TTL\": 60,
        \"ResourceRecords\": [{
          \"Value\": \"$NEW_IP\"
        }]
      }
    }]
  }" \
  --region ap-northeast-1

echo "✅ Domain $DOMAIN → $NEW_IP"
```

---

### Bước 4.2 — Kiểm tra domain

```bash
# DNS cần vài giây để propagate (TTL=60 giây)
sleep 30
curl -s -o /dev/null -w "%{http_code}" http://$DOMAIN
# Phải ra: 200 hoặc 302
```

---

## PHẦN 5 — Stop EC2 Prod Cũ

```bash
# Tìm EC2 prod cũ (tag Role=prod, đang running, không phải EC2 mới vừa tạo)
OLD_INSTANCE_IDS=$(aws ec2 describe-instances \
  --filters \
    "Name=tag:Role,Values=prod" \
    "Name=instance-state-name,Values=running" \
  --query "Reservations[*].Instances[?InstanceId!='$NEW_INSTANCE_ID'].InstanceId" \
  --output text --region ap-northeast-1)

if [ -z "$OLD_INSTANCE_IDS" ]; then
  echo "Không có EC2 prod cũ cần stop"
else
  echo "Stop EC2 prod cũ: $OLD_INSTANCE_IDS"
  aws ec2 stop-instances \
    --instance-ids $OLD_INSTANCE_IDS \
    --region ap-northeast-1
  echo "✅ Đã stop (giữ lại 24h để rollback nếu cần)"
fi
```

> ⚠️ **Stop** (không Terminate) — để rollback được nếu EC2 mới có vấn đề.  
> Sau 24h nếu ổn định → Terminate để tiết kiệm chi phí.

---

## PHẦN 6 — Deploy lần tiếp theo (Update code)

> Mỗi lần có code mới, thực hiện theo thứ tự này:

### Bước 6.1 — SSH vào Master

```bash
ssh -i ~/.ssh/eventech-key.pem ubuntu@<MASTER_PUBLIC_IP>
```

### Bước 6.2 — Chạy deploy script

```bash
cd ~/apps/sushi-tech-2026-app
bash scripts/deploy.sh
```

Script tự động: git pull → composer install → npm build → migrate → cache → reload PHP-FPM → health check.

Nếu thấy `✅ Deploy xong! App healthy (HTTP 200)` → tiếp tục bake AMI.

### Bước 6.3 — Thoát SSH, bake AMI mới + deploy

```bash
exit   # thoát khỏi SSH
```

Quay lại **máy local**, thực hiện lại từ **Phần 2** đến **Phần 5**.

---

## PHẦN 7 — Rollback khi có sự cố

### Rollback về EC2 prod cũ (nhanh nhất)

```bash
# Start lại EC2 prod cũ
OLD_INSTANCE_ID="i-0abc..."   # ID của EC2 prod cũ đã stop

aws ec2 start-instances \
  --instance-ids $OLD_INSTANCE_ID \
  --region ap-northeast-1

# Chờ running
aws ec2 wait instance-running \
  --instance-ids $OLD_INSTANCE_ID \
  --region ap-northeast-1

# Lấy IP cũ
OLD_IP=$(aws ec2 describe-instances \
  --instance-ids $OLD_INSTANCE_ID \
  --query 'Reservations[0].Instances[0].PublicIpAddress' \
  --output text --region ap-northeast-1)

# Trỏ domain về EC2 cũ
aws route53 change-resource-record-sets \
  --hosted-zone-id $ROUTE53_ZONE_ID \
  --change-batch "{
    \"Changes\": [{
      \"Action\": \"UPSERT\",
      \"ResourceRecordSet\": {
        \"Name\": \"$DOMAIN\",
        \"Type\": \"A\",
        \"TTL\": 60,
        \"ResourceRecords\": [{\"Value\": \"$OLD_IP\"}]
      }
    }]
  }" --region ap-northeast-1

echo "✅ Đã rollback về $OLD_INSTANCE_ID ($OLD_IP)"
```

---

## PHẦN 8 — Dọn AMI cũ (tiết kiệm chi phí)

```bash
AMI_KEEP_COUNT=3   # giữ lại 3 bản gần nhất

OLD_AMIS=$(aws ec2 describe-images \
  --owners self \
  --filters "Name=tag:Project,Values=sushi-tech" \
  --query "sort_by(Images, &CreationDate)[*].ImageId" \
  --output text --region ap-northeast-1)

TOTAL=$(echo $OLD_AMIS | wc -w)
DELETE_COUNT=$((TOTAL - AMI_KEEP_COUNT))

if [ $DELETE_COUNT -le 0 ]; then
  echo "Chưa đủ $AMI_KEEP_COUNT AMI, bỏ qua"
else
  DELETE_AMIS=$(echo $OLD_AMIS | tr ' ' '\n' | head -$DELETE_COUNT)
  for AMI_ID in $DELETE_AMIS; do
    echo "Xóa AMI: $AMI_ID"
    SNAPSHOT_IDS=$(aws ec2 describe-images \
      --image-ids $AMI_ID \
      --query 'Images[0].BlockDeviceMappings[*].Ebs.SnapshotId' \
      --output text --region ap-northeast-1)
    aws ec2 deregister-image --image-id $AMI_ID --region ap-northeast-1
    for SNAP_ID in $SNAPSHOT_IDS; do
      aws ec2 delete-snapshot --snapshot-id $SNAP_ID --region ap-northeast-1
    done
  done
  echo "✅ Đã xóa $DELETE_COUNT AMI cũ"
fi
```

---

## Checklist deploy nhanh

```
Lần đầu:
  [ ] terraform apply → lấy master IP + instance ID
  [ ] SSH vào master → chạy setup (Phần 1)
  [ ] Kiểm tra http://MASTER_IP trả về 200
  [ ] Bake AMI (Phần 2)
  [ ] Launch EC2 prod (Phần 3)
  [ ] Trỏ domain (Phần 4)

Mỗi lần deploy tiếp:
  [ ] SSH vào master → git pull + build + migrate (Phần 6.1)
  [ ] Kiểm tra http://localhost → 200
  [ ] Bake AMI mới (Phần 2)
  [ ] Launch EC2 prod mới (Phần 3)
  [ ] Kiểm tra EC2 mới healthy
  [ ] Trỏ domain vào EC2 mới (Phần 4)
  [ ] Stop EC2 prod cũ (Phần 5)
  [ ] Sau 24h: Terminate EC2 prod cũ nếu ổn định
```

---

## Biến môi trường cần ghi lại

| Biến | Giá trị | Lấy từ đâu |
|------|---------|------------|
| `MASTER_INSTANCE_ID` | `i-0abc...` | `terraform output master_instance_id` |
| `MASTER_PUBLIC_IP` | `13.114.xxx.xxx` | `terraform output master_public_ip` |
| `ROUTE53_ZONE_ID` | `Z0XXXXXXX` | AWS Console → Route53 → Hosted zones |
| `DOMAIN` | `app.sushitech.jp` | Domain của bạn |
| `AWS_PROFILE` | `eventech-terraform` | Đã cấu hình sẵn |
