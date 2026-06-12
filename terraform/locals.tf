data "aws_caller_identity" "current" {}

data "aws_region" "current" {}

data "aws_availability_zones" "available" {
  state = "available"
}

locals {
  name_prefix = "${var.app_name}-${var.environment}"
  azs         = slice(data.aws_availability_zones.available.names, 0, var.az_count)

  common_tags = {
    Application = var.app_name
    Environment = var.environment
    ManagedBy   = "terraform"
  }

  image_uri       = "${aws_ecr_repository.app.repository_url}:${var.image_tag}"
  db_password     = var.db_password != "" ? var.db_password : random_password.db.result
  bucket_name     = var.upload_bucket_name != "" ? var.upload_bucket_name : "${local.name_prefix}-uploads-${random_id.bucket.hex}"
  sqs_queue_name  = var.sqs_queue_name != "" ? var.sqs_queue_name : "${local.name_prefix}-queue"
  github_oidc_sub = var.github_oidc_sub != "" ? var.github_oidc_sub : "repo:${var.github_repository}:ref:refs/heads/${var.github_branch}"
  github_oidc_provider_arn = (
    var.github_oidc_provider_arn != ""
    ? var.github_oidc_provider_arn
    : try(aws_iam_openid_connect_provider.github[0].arn, null)
  )

  ecs_environment = [
    { name = "APP_ENV", value = var.environment },
    { name = "APP_DEBUG", value = "false" },
    { name = "LOG_CHANNEL", value = "stderr" },
    { name = "CACHE_STORE", value = "redis" },
    { name = "SESSION_DRIVER", value = "redis" },
    { name = "QUEUE_CONNECTION", value = "sqs" },
    { name = "FILESYSTEM_DISK", value = "s3" },
  ]

  ecs_secrets = [
    { name = "APP_KEY", valueFrom = aws_ssm_parameter.app_key.arn },
    { name = "APP_URL", valueFrom = aws_ssm_parameter.app_url.arn },
    { name = "DB_HOST", valueFrom = aws_ssm_parameter.db_host.arn },
    { name = "DB_DATABASE", valueFrom = aws_ssm_parameter.db_database.arn },
    { name = "DB_USERNAME", valueFrom = aws_ssm_parameter.db_username.arn },
    { name = "DB_PASSWORD", valueFrom = aws_ssm_parameter.db_password.arn },
    { name = "REDIS_HOST", valueFrom = aws_ssm_parameter.redis_host.arn },
    { name = "AWS_BUCKET", valueFrom = aws_ssm_parameter.aws_bucket.arn },
    { name = "AWS_DEFAULT_REGION", valueFrom = aws_ssm_parameter.aws_default_region.arn },
    { name = "SQS_PREFIX", valueFrom = aws_ssm_parameter.sqs_prefix.arn },
    { name = "SQS_QUEUE", valueFrom = aws_ssm_parameter.sqs_queue.arn },
  ]
}
