resource "aws_ssm_parameter" "app_key" {
  name  = "/${var.app_name}/${var.environment}/APP_KEY"
  type  = "SecureString"
  value = var.app_key

  tags = local.common_tags
}

resource "aws_ssm_parameter" "app_url" {
  name  = "/${var.app_name}/${var.environment}/APP_URL"
  type  = "String"
  value = var.app_url

  tags = local.common_tags
}

resource "aws_ssm_parameter" "db_host" {
  name  = "/${var.app_name}/${var.environment}/DB_HOST"
  type  = "String"
  value = aws_db_instance.mysql.address

  tags = local.common_tags
}

resource "aws_ssm_parameter" "db_database" {
  name  = "/${var.app_name}/${var.environment}/DB_DATABASE"
  type  = "String"
  value = var.db_name

  tags = local.common_tags
}

resource "aws_ssm_parameter" "db_username" {
  name  = "/${var.app_name}/${var.environment}/DB_USERNAME"
  type  = "String"
  value = var.db_username

  tags = local.common_tags
}

resource "aws_ssm_parameter" "db_password" {
  name  = "/${var.app_name}/${var.environment}/DB_PASSWORD"
  type  = "SecureString"
  value = local.db_password

  tags = local.common_tags
}

resource "aws_ssm_parameter" "redis_host" {
  name  = "/${var.app_name}/${var.environment}/REDIS_HOST"
  type  = "String"
  value = aws_elasticache_replication_group.redis.primary_endpoint_address

  tags = local.common_tags
}

resource "aws_ssm_parameter" "aws_bucket" {
  name  = "/${var.app_name}/${var.environment}/AWS_BUCKET"
  type  = "String"
  value = aws_s3_bucket.uploads.bucket

  tags = local.common_tags
}

resource "aws_ssm_parameter" "aws_default_region" {
  name  = "/${var.app_name}/${var.environment}/AWS_DEFAULT_REGION"
  type  = "String"
  value = var.aws_region

  tags = local.common_tags
}

resource "aws_ssm_parameter" "sqs_prefix" {
  name  = "/${var.app_name}/${var.environment}/SQS_PREFIX"
  type  = "String"
  value = "https://sqs.${var.aws_region}.amazonaws.com/${data.aws_caller_identity.current.account_id}"

  tags = local.common_tags
}

resource "aws_ssm_parameter" "sqs_queue" {
  name  = "/${var.app_name}/${var.environment}/SQS_QUEUE"
  type  = "String"
  value = aws_sqs_queue.app.name

  tags = local.common_tags
}
