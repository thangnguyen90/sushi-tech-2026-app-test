#!/bin/bash
# ============================================================
# Rollback về EC2 prod cũ
#
# Cách dùng:
#   bash scripts/rollback.sh <OLD_INSTANCE_ID>
#
# Ví dụ:
#   bash scripts/rollback.sh i-0abc123456789
# ============================================================

set -euo pipefail

OLD_INSTANCE_ID="${1:-}"
DOMAIN="your-domain.com"        # phải khớp với bake-ami.sh
ROUTE53_ZONE_ID="Z0XXXXXXXXXX"  # phải khớp với bake-ami.sh
AWS_REGION="ap-northeast-1"

export AWS_DEFAULT_REGION="$AWS_REGION"

if [ -z "$OLD_INSTANCE_ID" ]; then
  echo "❌ Thiếu instance ID"
  echo "   Cách dùng: bash scripts/rollback.sh <OLD_INSTANCE_ID>"
  exit 1
fi

echo "🔄 Rollback về EC2: $OLD_INSTANCE_ID"

# Start EC2 cũ
echo "  Start EC2 cũ..."
aws ec2 start-instances --instance-ids "$OLD_INSTANCE_ID"
aws ec2 wait instance-running --instance-ids "$OLD_INSTANCE_ID"

OLD_IP=$(aws ec2 describe-instances \
  --instance-ids "$OLD_INSTANCE_ID" \
  --query 'Reservations[0].Instances[0].PublicIpAddress' \
  --output text)

echo "  EC2 cũ IP: $OLD_IP"

# Swap Route53 về EC2 cũ
echo "  Trỏ domain về EC2 cũ..."
aws route53 change-resource-record-sets \
  --hosted-zone-id "$ROUTE53_ZONE_ID" \
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
  }"

echo ""
echo "✅ Rollback xong!"
echo "   Domain $DOMAIN → $OLD_IP ($OLD_INSTANCE_ID)"
