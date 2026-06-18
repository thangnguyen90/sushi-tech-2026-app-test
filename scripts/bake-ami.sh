#!/bin/bash
# ============================================================
# Bake AMI từ Master EC2 → Update Launch Template → ASG Refresh → CloudFront Invalidation
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
MASTER_INSTANCE_ID="i-075dd4a590b1eef8d"   # terraform output master_instance_id
LAUNCH_TEMPLATE_ID="lt-0b386e8a7c9046c53"  # EC2 → Launch Templates → ID
ASG_NAME="eventech-stg-asg"                 # EC2 → Auto Scaling Groups → Name
CLOUDFRONT_DISTRIBUTION_ID=""               # CloudFront → Distributions → ID (để trống nếu chưa có)
AWS_REGION="ap-northeast-1"
PROJECT_TAG="sushi-tech"                    # tag Project dùng để tìm AMI cũ
AMI_KEEP_COUNT="${AMI_KEEP_COUNT:-3}"        # đọc từ env var hoặc dùng mặc định 3
# ============================================================

export AWS_DEFAULT_REGION="$AWS_REGION"

# Kiểm tra config chưa điền
if [[ "$MASTER_INSTANCE_ID" == i-0xxx* ]]; then
  echo "❌ Chưa điền MASTER_INSTANCE_ID!"
  echo "   Mở file scripts/bake-ami.sh và điền MASTER_INSTANCE_ID, LAUNCH_TEMPLATE_ID, ASG_NAME"
  exit 1
fi

echo "=================================================="
echo "  AMI Baking + ASG Rolling Deploy"
echo "  Master  : $MASTER_INSTANCE_ID"
echo "  Template: $LAUNCH_TEMPLATE_ID"
echo "  ASG     : $ASG_NAME"
echo "  CF      : ${CLOUDFRONT_DISTRIBUTION_ID:-<chưa có>}"
echo "=================================================="
echo ""

# ────────────────────────────────
# 0. Kiểm tra master đã setup xong chưa
# ────────────────────────────────
echo "🔍 [0/5] Kiểm tra master EC2..."

MASTER_PUBLIC_IP=$(aws ec2 describe-instances \
  --instance-ids "$MASTER_INSTANCE_ID" \
  --query "Reservations[0].Instances[0].PublicIpAddress" \
  --output text)

echo "  Master IP: $MASTER_PUBLIC_IP"

HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" --connect-timeout 5 --max-time 10 "http://$MASTER_PUBLIC_IP" || echo "000")

if [ "$HTTP_CODE" != "200" ] && [ "$HTTP_CODE" != "302" ]; then
  echo "❌ Master EC2 chưa sẵn sàng (HTTP $HTTP_CODE)"
  echo "   Cần SSH vào master và chạy setup-ec2.sh + cấu hình .env trước khi bake AMI"
  exit 1
fi

echo "✅ Master OK (HTTP $HTTP_CODE) — tiến hành bake"
echo ""

# ────────────────────────────────
# 1. Bake AMI từ Master
# ────────────────────────────────
echo "📸 [1/5] Bake AMI từ master..."
TIMESTAMP=$(date +%Y%m%d-%H%M%S)
AMI_NAME="${PROJECT_TAG}-master-ami-${TIMESTAMP}"

AMI_ID=$(aws ec2 create-image \
  --instance-id "$MASTER_INSTANCE_ID" \
  --name "$AMI_NAME" \
  --description "Production AMI - $TIMESTAMP" \
  --tag-specifications "ResourceType=image,Tags=[{Key=Name,Value=$AMI_NAME},{Key=Project,Value=$PROJECT_TAG}]" \
  --query 'ImageId' \
  --output text)

echo "  AMI ID  : $AMI_ID"
echo "  AMI Name: $AMI_NAME"
echo "  Đang chờ AMI available (3-10 phút)..."

aws ec2 wait image-available --image-ids "$AMI_ID"
echo "✅ AMI sẵn sàng: $AMI_ID"

# ────────────────────────────────
# 2. Tạo version mới cho Launch Template với AMI mới
# ────────────────────────────────
echo ""
echo "📋 [2/5] Cập nhật Launch Template với AMI mới..."

NEW_LT_VERSION=$(aws ec2 create-launch-template-version \
  --launch-template-id "$LAUNCH_TEMPLATE_ID" \
  --source-version '$Latest' \
  --launch-template-data "{\"ImageId\":\"$AMI_ID\"}" \
  --query 'LaunchTemplateVersion.VersionNumber' \
  --output text)

# Tạo version mới với tag Name cho EC2 instance (dùng version vừa lấy được)
INSTANCE_NAME="${ASG_NAME}-v${NEW_LT_VERSION}-${TIMESTAMP}"
aws ec2 create-launch-template-version \
  --launch-template-id "$LAUNCH_TEMPLATE_ID" \
  --source-version "$NEW_LT_VERSION" \
  --launch-template-data "{\"TagSpecifications\":[{\"ResourceType\":\"instance\",\"Tags\":[{\"Key\":\"Name\",\"Value\":\"$INSTANCE_NAME\"}]}]}" \
  --query 'LaunchTemplateVersion.VersionNumber' \
  --output text > /dev/null

NEW_LT_VERSION=$((NEW_LT_VERSION + 1))

echo "  Launch Template version mới: $NEW_LT_VERSION"

# Set version mới làm default
aws ec2 modify-launch-template \
  --launch-template-id "$LAUNCH_TEMPLATE_ID" \
  --default-version "$NEW_LT_VERSION"

echo "✅ Launch Template default → version $NEW_LT_VERSION"

# ────────────────────────────────
# 3. ASG Instance Refresh (rolling update)
# ────────────────────────────────
echo ""
echo "🔄 [3/5] Bắt đầu ASG Instance Refresh..."

REFRESH_ID=$(aws autoscaling start-instance-refresh \
  --auto-scaling-group-name "$ASG_NAME" \
  --preferences '{
    "MinHealthyPercentage": 50,
    "InstanceWarmup": 120,
    "SkipMatching": false
  }' \
  --query 'InstanceRefreshId' \
  --output text)

echo "  Refresh ID: $REFRESH_ID"
echo "  Đang chờ instance refresh hoàn thành..."
echo "  (Theo dõi: AWS Console → EC2 → Auto Scaling Groups → $ASG_NAME → Instance refresh)"

# Chờ refresh hoàn thành
while true; do
  STATUS=$(aws autoscaling describe-instance-refreshes \
    --auto-scaling-group-name "$ASG_NAME" \
    --instance-refresh-ids "$REFRESH_ID" \
    --query 'InstanceRefreshes[0].Status' \
    --output text)

  PCT=$(aws autoscaling describe-instance-refreshes \
    --auto-scaling-group-name "$ASG_NAME" \
    --instance-refresh-ids "$REFRESH_ID" \
    --query 'InstanceRefreshes[0].PercentageComplete' \
    --output text 2>/dev/null || echo "0")

  echo "  Status: $STATUS ($PCT%)"

  if [ "$STATUS" = "Successful" ]; then
    echo "✅ Instance refresh hoàn thành!"
    break
  elif [ "$STATUS" = "Failed" ] || [ "$STATUS" = "Cancelled" ]; then
    echo "❌ Instance refresh thất bại (Status: $STATUS)"
    echo "   Kiểm tra: AWS Console → EC2 → Auto Scaling Groups → Instance refresh"
    exit 1
  fi

  sleep 30
done

# ────────────────────────────────
# 4. CloudFront Invalidation
# ────────────────────────────────
echo ""
if [ -n "$CLOUDFRONT_DISTRIBUTION_ID" ]; then
  echo "🌐 [4/5] CloudFront Invalidation..."

  INVALIDATION_ID=$(aws cloudfront create-invalidation \
    --distribution-id "$CLOUDFRONT_DISTRIBUTION_ID" \
    --paths "/*" \
    --query 'Invalidation.Id' \
    --output text)

  echo "  Invalidation ID: $INVALIDATION_ID"
  echo "  Chờ invalidation hoàn thành..."

  aws cloudfront wait invalidation-completed \
    --distribution-id "$CLOUDFRONT_DISTRIBUTION_ID" \
    --id "$INVALIDATION_ID"

  echo "✅ CloudFront cache đã được xóa"
else
  echo "⏭️  [4/5] Bỏ qua CloudFront (chưa có distribution ID)"
fi

# ────────────────────────────────
# 5. Dọn AMI cũ
# ────────────────────────────────
echo ""
echo "🧹 [5/5] Dọn AMI cũ (giữ $AMI_KEEP_COUNT bản)..."

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
echo "  AMI              : $AMI_NAME ($AMI_ID)"
echo "  Launch Template  : $LAUNCH_TEMPLATE_ID (version $NEW_LT_VERSION)"
echo "  ASG              : $ASG_NAME → rolling update xong"
if [ -n "$CLOUDFRONT_DISTRIBUTION_ID" ]; then
  echo "  CloudFront       : cache đã invalidate"
fi
echo ""
echo "Rollback:"
echo "  1. Vào EC2 → Launch Templates → $LAUNCH_TEMPLATE_ID"
echo "  2. Chọn version cũ → Actions → Set as default version"
echo "  3. Chạy lại: bash scripts/bake-ami.sh (hoặc trigger ASG refresh thủ công)"
echo "=================================================="
