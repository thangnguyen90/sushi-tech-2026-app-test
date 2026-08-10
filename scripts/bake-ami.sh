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
# CONFIG — tự đọc từ Terraform, không cần điền tay
#
# Script gọi `tf.sh <env> output -json` một lần rồi lấy hết giá trị ra, nên
# không bao giờ lỗi thời khi destroy/apply lại (ID thay đổi mỗi lần dựng).
#
# Ghi đè khi cần:
#   TF_ENV=stg bash scripts/bake-ami.sh
#   TF_DIR=/duong/dan/khac bash scripts/bake-ami.sh
#   MASTER_INSTANCE_ID=i-xxx bash scripts/bake-ami.sh    # bỏ qua Terraform
# ============================================================
TF_ENV="${TF_ENV:-dev}"
TF_DIR="${TF_DIR:-$HOME/Documents/RESOURCE/eventech-terraform}"
AWS_REGION="${AWS_REGION:-ap-northeast-1}"
AMI_KEEP_COUNT="${AMI_KEEP_COUNT:-3}"

# Đọc toàn bộ output một lần. tf.sh in header ra stdout nên cắt từ dấu { đầu tiên.
# Gộp stderr để nếu tf.sh lỗi thì in được lý do thật, thay vì chết im lặng.

# Lấy 1 output. Output của repo trả về câu tiếng Việt khi dịch vụ chưa bật
# (vd "CloudFront chưa bật...") — coi những giá trị đó là rỗng.
tf_get() {
  echo "$TF_JSON" | python3 -c "
import json,sys
try: d=json.load(sys.stdin)
except Exception: sys.exit(0)
v=str(d.get('$1',{}).get('value','') or '')
print('' if ('chưa' in v or 'Dùng ' in v) else v)
"
}

echo "🔍 Đọc thông tin từ Terraform (env: $TF_ENV)..."

[ -d "$TF_DIR" ] || { echo "❌ Không thấy thư mục Terraform: $TF_DIR"; exit 1; }

# || true là bắt buộc: có set -e, phép gán từ command substitution thất bại sẽ
# giết script ngay, không kịp in thông báo nào.
TF_RAW="$( ( cd "$TF_DIR" && ./tf.sh "$TF_ENV" output -json ) 2>&1 || true )"
TF_JSON="$(printf '%s' "$TF_RAW" | sed -n '/^{/,$p')"

if [ -z "$TF_JSON" ]; then
  echo "❌ Không đọc được output Terraform (thư mục: $TF_DIR, env: $TF_ENV)"
  echo "   tf.sh báo:"
  printf '%s\n' "$TF_RAW" | sed 's/^/     /' | head -12
  echo ""
  echo "   Thường do một trong các nguyên nhân:"
  echo "     - Terraform sai kiến trúc CPU (Intel trên Mac ARM)"
  echo "     - Chưa đăng nhập AWS: aws login --profile <profile>"
  echo "     - Thiếu file envs/$TF_ENV.s3.tfbackend"
  exit 1
fi

MASTER_INSTANCE_ID="${MASTER_INSTANCE_ID:-$(tf_get master_instance_id)}"
LAUNCH_TEMPLATE_ID="${LAUNCH_TEMPLATE_ID:-$(tf_get launch_template_id)}"
ASG_NAME="${ASG_NAME:-$(tf_get asg_name)}"
CLOUDFRONT_DISTRIBUTION_ID="${CLOUDFRONT_DISTRIBUTION_ID:-$(tf_get cloudfront_distribution_id)}"
PROJECT_TAG="${PROJECT_TAG:-$(tf_get project)}"
# Fallback: output "project" chỉ có sau khi apply. Đọc thẳng tfvars thì luôn được.
[ -n "$PROJECT_TAG" ] || PROJECT_TAG="$(sed -n 's/^project[[:space:]]*=[[:space:]]*"\(.*\)".*/\1/p' "$TF_DIR/envs/$TF_ENV.tfvars" 2>/dev/null | head -1)"

# ── Kiểm tra giá trị đọc được có đúng dạng ──────────────────
case "$MASTER_INSTANCE_ID" in
  i-*) ;;
  *) echo "❌ master_instance_id không hợp lệ: '${MASTER_INSTANCE_ID:-<rỗng>}'"
     echo "   Env '$TF_ENV' có bật enable_master = true chưa?"; exit 1 ;;
esac
case "$LAUNCH_TEMPLATE_ID" in
  lt-*) ;;
  *) echo "❌ launch_template_id không hợp lệ: '${LAUNCH_TEMPLATE_ID:-<rỗng>}'"
     echo "   Env '$TF_ENV' có bật enable_infra = true chưa?"; exit 1 ;;
esac
[ -n "$ASG_NAME" ] || { echo "❌ Không lấy được asg_name"; exit 1; }
[ -n "$PROJECT_TAG" ] || { echo "❌ Không lấy được project (dùng làm tag Project)"; exit 1; }

export AWS_DEFAULT_REGION="$AWS_REGION"

echo "=================================================="
echo "  AMI Baking + ASG Rolling Deploy"
echo "  Env     : $TF_ENV"
echo "  Project : $PROJECT_TAG  (tag Project của AMI)"
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

LATEST_VERSION=$(aws ec2 describe-launch-template-versions \
  --launch-template-id "$LAUNCH_TEMPLATE_ID" \
  --versions '$Latest' \
  --query 'LaunchTemplateVersions[0].VersionNumber' \
  --output text)

NEW_LT_VERSION=$((LATEST_VERSION + 1))
INSTANCE_NAME="${ASG_NAME}-v${NEW_LT_VERSION}-${TIMESTAMP}"

aws ec2 create-launch-template-version \
  --launch-template-id "$LAUNCH_TEMPLATE_ID" \
  --source-version '$Latest' \
  --launch-template-data "{\"ImageId\":\"$AMI_ID\",\"TagSpecifications\":[{\"ResourceType\":\"instance\",\"Tags\":[{\"Key\":\"Name\",\"Value\":\"$INSTANCE_NAME\"}]}]}" \
  --query 'LaunchTemplateVersion.VersionNumber' \
  --output text > /dev/null

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
    "MinHealthyPercentage": 100,
    "InstanceWarmup": 180,
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
