#!/bin/bash
# ============================================================
# Bake AMI từ Master EC2 → Launch EC2 prod mới → Swap Route53
# Chạy trên máy local sau khi deploy.sh chạy OK trên master
#
# Cách dùng:
#   bash scripts/bake-ami.sh
#
# Yêu cầu:
#   - AWS CLI đã cấu hình: export AWS_PROFILE=eventech-terraform
#   - Điền các biến CONFIG bên dưới trước khi chạy
# ============================================================

set -euo pipefail

# ============================================================
# CONFIG — điền 1 lần, giữ nguyên cho các lần sau
# ============================================================
MASTER_INSTANCE_ID="i-0xxxxxxxxxxxxxxxxx"   # terraform output master_instance_id
PROD_INSTANCE_TYPE="t3.micro"
DOMAIN="your-domain.com"                    # ví dụ: app.sushitech.jp
ROUTE53_ZONE_ID="Z0XXXXXXXXXX"             # AWS Console → Route53 → Hosted zones
AWS_REGION="ap-northeast-1"
PROJECT_TAG="sushi-tech"                    # tag Project dùng để tìm AMI cũ
AMI_KEEP_COUNT=3                            # số AMI giữ lại, xóa cũ hơn
# ============================================================

export AWS_DEFAULT_REGION="$AWS_REGION"

# Kiểm tra config chưa điền
if [[ "$MASTER_INSTANCE_ID" == i-0xxx* ]] || [[ "$DOMAIN" == "your-domain.com" ]]; then
  echo "❌ Chưa điền config!"
  echo "   Mở file scripts/bake-ami.sh và điền MASTER_INSTANCE_ID, DOMAIN, ROUTE53_ZONE_ID"
  exit 1
fi

echo "=================================================="
echo "  AMI Baking + Deploy Production"
echo "  Master : $MASTER_INSTANCE_ID"
echo "  Domain : $DOMAIN"
echo "=================================================="
echo ""

# ────────────────────────────────
# 1. Bake AMI từ Master
# ────────────────────────────────
echo "📸 [1/6] Bake AMI từ master..."
TIMESTAMP=$(date +%Y%m%d-%H%M%S)
AMI_NAME="${PROJECT_TAG}-prod-${TIMESTAMP}"

AMI_ID=$(aws ec2 create-image \
  --instance-id "$MASTER_INSTANCE_ID" \
  --name "$AMI_NAME" \
  --description "Production AMI - $TIMESTAMP" \
  --no-reboot \
  --tag-specifications "ResourceType=image,Tags=[{Key=Name,Value=$AMI_NAME},{Key=Project,Value=$PROJECT_TAG}]" \
  --query 'ImageId' \
  --output text)

echo "  AMI ID  : $AMI_ID"
echo "  AMI Name: $AMI_NAME"
echo "  Đang chờ AMI available (3-10 phút)..."

aws ec2 wait image-available --image-ids "$AMI_ID"
echo "✅ AMI sẵn sàng: $AMI_ID"

# ────────────────────────────────
# 2. Lấy config từ Master
# ────────────────────────────────
echo ""
echo "🔍 [2/6] Lấy config từ master..."

SUBNET_ID=$(aws ec2 describe-instances \
  --instance-ids "$MASTER_INSTANCE_ID" \
  --query 'Reservations[0].Instances[0].SubnetId' \
  --output text)

SG_IDS=$(aws ec2 describe-instances \
  --instance-ids "$MASTER_INSTANCE_ID" \
  --query 'Reservations[0].Instances[0].SecurityGroups[*].GroupId' \
  --output text | tr '\t' ' ')

KEY_NAME=$(aws ec2 describe-instances \
  --instance-ids "$MASTER_INSTANCE_ID" \
  --query 'Reservations[0].Instances[0].KeyName' \
  --output text)

IAM_PROFILE=$(aws ec2 describe-instances \
  --instance-ids "$MASTER_INSTANCE_ID" \
  --query 'Reservations[0].Instances[0].IamInstanceProfile.Arn' \
  --output text | awk -F'/' '{print $NF}')

echo "  Subnet : $SUBNET_ID"
echo "  SG     : $SG_IDS"
echo "  Key    : $KEY_NAME"
echo "  IAM    : $IAM_PROFILE"

# ────────────────────────────────
# 3. Launch EC2 prod mới
# ────────────────────────────────
echo ""
echo "🚀 [3/6] Launch EC2 prod mới..."

NEW_INSTANCE_ID=$(aws ec2 run-instances \
  --image-id "$AMI_ID" \
  --instance-type "$PROD_INSTANCE_TYPE" \
  --subnet-id "$SUBNET_ID" \
  --security-group-ids $SG_IDS \
  --key-name "$KEY_NAME" \
  --iam-instance-profile Name="$IAM_PROFILE" \
  --associate-public-ip-address \
  --tag-specifications "ResourceType=instance,Tags=[{Key=Name,Value=${PROJECT_TAG}-prod},{Key=Role,Value=prod},{Key=AMI,Value=$AMI_NAME}]" \
  --query 'Instances[0].InstanceId' \
  --output text)

echo "  Chờ EC2 running: $NEW_INSTANCE_ID"
aws ec2 wait instance-running --instance-ids "$NEW_INSTANCE_ID"

NEW_IP=$(aws ec2 describe-instances \
  --instance-ids "$NEW_INSTANCE_ID" \
  --query 'Reservations[0].Instances[0].PublicIpAddress' \
  --output text)

echo "✅ EC2 prod mới: $NEW_INSTANCE_ID ($NEW_IP)"

# ────────────────────────────────
# 4. Health check EC2 mới
# ────────────────────────────────
echo ""
echo "🧪 [4/6] Health check EC2 mới (chờ app khởi động)..."
sleep 30

HEALTHY=false
for i in {1..10}; do
  STATUS=$(curl -s -o /dev/null -w "%{http_code}" "http://$NEW_IP" 2>/dev/null || echo "000")
  if [ "$STATUS" = "200" ] || [ "$STATUS" = "302" ]; then
    echo "✅ EC2 mới healthy (HTTP $STATUS)"
    HEALTHY=true
    break
  fi
  echo "  Thử $i/10... (HTTP $STATUS)"
  sleep 15
done

if [ "$HEALTHY" = "false" ]; then
  echo ""
  echo "❌ EC2 mới không healthy — rollback!"
  echo "   Terminate EC2 mới: $NEW_INSTANCE_ID"
  aws ec2 terminate-instances --instance-ids "$NEW_INSTANCE_ID"
  echo "   AMI vẫn còn: $AMI_ID (xóa thủ công nếu không cần)"
  exit 1
fi

# ────────────────────────────────
# 5. Swap Route53 → EC2 mới
# ────────────────────────────────
echo ""
echo "🌐 [5/6] Trỏ domain $DOMAIN → $NEW_IP..."

aws route53 change-resource-record-sets \
  --hosted-zone-id "$ROUTE53_ZONE_ID" \
  --change-batch "{
    \"Changes\": [{
      \"Action\": \"UPSERT\",
      \"ResourceRecordSet\": {
        \"Name\": \"$DOMAIN\",
        \"Type\": \"A\",
        \"TTL\": 60,
        \"ResourceRecords\": [{\"Value\": \"$NEW_IP\"}]
      }
    }]
  }"

echo "✅ Domain đã trỏ vào EC2 mới"

# ────────────────────────────────
# 6. Stop EC2 prod cũ
# ────────────────────────────────
echo ""
echo "🛑 [6/6] Stop EC2 prod cũ..."

OLD_INSTANCE_IDS=$(aws ec2 describe-instances \
  --filters \
    "Name=tag:Role,Values=prod" \
    "Name=instance-state-name,Values=running" \
  --query "Reservations[*].Instances[?InstanceId!='$NEW_INSTANCE_ID'].InstanceId" \
  --output text | tr '\t' ' ')

if [ -z "$OLD_INSTANCE_IDS" ] || [ "$OLD_INSTANCE_IDS" = "None" ]; then
  echo "  Không có EC2 prod cũ cần stop"
else
  aws ec2 stop-instances --instance-ids $OLD_INSTANCE_IDS
  echo "✅ Đã stop: $OLD_INSTANCE_IDS"
  echo "   (Giữ lại 24h để rollback nếu cần, sau đó terminate)"
fi

# ────────────────────────────────
# Dọn AMI cũ
# ────────────────────────────────
echo ""
echo "🧹 Dọn AMI cũ (giữ $AMI_KEEP_COUNT bản)..."

OLD_AMIS=$(aws ec2 describe-images \
  --owners self \
  --filters "Name=tag:Project,Values=$PROJECT_TAG" \
  --query "sort_by(Images, &CreationDate)[*].ImageId" \
  --output text)

TOTAL=$(echo $OLD_AMIS | wc -w)
DELETE_COUNT=$((TOTAL - AMI_KEEP_COUNT))

if [ $DELETE_COUNT -le 0 ]; then
  echo "  Chưa đủ $AMI_KEEP_COUNT AMI, bỏ qua"
else
  DELETE_AMIS=$(echo $OLD_AMIS | tr ' ' '\n' | head -$DELETE_COUNT)
  for DEL_AMI in $DELETE_AMIS; do
    SNAP_IDS=$(aws ec2 describe-images \
      --image-ids "$DEL_AMI" \
      --query 'Images[0].BlockDeviceMappings[*].Ebs.SnapshotId' \
      --output text)
    aws ec2 deregister-image --image-id "$DEL_AMI"
    for SNAP in $SNAP_IDS; do
      aws ec2 delete-snapshot --snapshot-id "$SNAP"
    done
    echo "  Đã xóa: $DEL_AMI"
  done
  echo "✅ Đã dọn $DELETE_COUNT AMI cũ"
fi

# ────────────────────────────────
# Tổng kết
# ────────────────────────────────
echo ""
echo "=================================================="
echo "✅ Deploy Production thành công!"
echo ""
echo "  AMI       : $AMI_NAME ($AMI_ID)"
echo "  EC2 mới   : $NEW_INSTANCE_ID ($NEW_IP)"
echo "  Domain    : http://$DOMAIN"
if [ ! -z "$OLD_INSTANCE_IDS" ] && [ "$OLD_INSTANCE_IDS" != "None" ]; then
echo ""
echo "  EC2 cũ đã stop: $OLD_INSTANCE_IDS"
echo "  → Rollback: bash scripts/rollback.sh $OLD_INSTANCE_IDS"
echo "  → Terminate sau 24h nếu ổn: aws ec2 terminate-instances --instance-ids $OLD_INSTANCE_IDS"
fi
echo "=================================================="
