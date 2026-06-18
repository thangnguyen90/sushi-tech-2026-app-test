#!/bin/bash
# ============================================================
# Xóa toàn bộ AMI và Snapshot của project
# Chạy trên máy local trước khi terraform destroy
#
# Cách dùng:
#   bash scripts/cleanup-ami.sh
#   bash scripts/cleanup-ami.sh --dry-run   # xem trước, không xóa
# ============================================================

set -euo pipefail

AWS_REGION="ap-northeast-1"
PROJECT_TAG="sushi-tech"
DRY_RUN=false

if [[ "${1:-}" == "--dry-run" ]]; then
  DRY_RUN=true
  echo "⚠️  DRY RUN — chỉ hiển thị, không xóa"
  echo ""
fi

export AWS_DEFAULT_REGION="$AWS_REGION"

echo "=================================================="
echo "  Cleanup AMI + Snapshot"
echo "  Project : $PROJECT_TAG"
echo "  Region  : $AWS_REGION"
echo "=================================================="
echo ""

# Lấy danh sách AMI
AMIS=$(aws ec2 describe-images \
  --owners self \
  --filters "Name=tag:Project,Values=$PROJECT_TAG" \
  --query "Images[*].{ID:ImageId,Name:Name,Date:CreationDate}" \
  --output text)

if [[ -z "$AMIS" ]]; then
  echo "✅ Không có AMI nào để xóa"
  exit 0
fi

echo "Danh sách AMI sẽ bị xóa:"
echo "$AMIS" | while read -r DATE ID NAME; do
  echo "  - $NAME ($ID) — $DATE"
done
echo ""

if $DRY_RUN; then
  echo "⚠️  Dry run — không xóa gì cả"
  exit 0
fi

# Xác nhận trước khi xóa
read -p "Xác nhận xóa tất cả AMI trên? [y/N] " CONFIRM
if [[ "$CONFIRM" != "y" && "$CONFIRM" != "Y" ]]; then
  echo "Hủy."
  exit 0
fi

echo ""

# Xóa từng AMI + snapshot
AMI_IDS=$(aws ec2 describe-images \
  --owners self \
  --filters "Name=tag:Project,Values=$PROJECT_TAG" \
  --query "Images[*].ImageId" \
  --output text)

for AMI_ID in $AMI_IDS; do
  # Lấy snapshot trước khi deregister
  SNAP_IDS=$(aws ec2 describe-images \
    --image-ids "$AMI_ID" \
    --query "Images[0].BlockDeviceMappings[*].Ebs.SnapshotId" \
    --output text)

  aws ec2 deregister-image --image-id "$AMI_ID"
  echo "🗑️  Đã xóa AMI: $AMI_ID"

  for SNAP_ID in $SNAP_IDS; do
    if [[ -n "$SNAP_ID" && "$SNAP_ID" != "None" ]]; then
      aws ec2 delete-snapshot --snapshot-id "$SNAP_ID"
      echo "   └─ Snapshot: $SNAP_ID"
    fi
  done
done

echo ""
echo "✅ Đã xóa toàn bộ AMI và Snapshot của project $PROJECT_TAG"
