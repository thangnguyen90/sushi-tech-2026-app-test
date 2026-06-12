output "alb_dns_name" {
  description = "ALB DNS name."
  value       = aws_lb.app.dns_name
}

output "ecr_repository_url" {
  description = "ECR repository URL."
  value       = aws_ecr_repository.app.repository_url
}

output "ecs_cluster_name" {
  description = "ECS cluster name."
  value       = aws_ecs_cluster.main.name
}

output "ecs_web_service_name" {
  description = "ECS web service name."
  value       = aws_ecs_service.web.name
}

output "ecs_queue_service_name" {
  description = "ECS queue service name."
  value       = aws_ecs_service.queue.name
}

output "github_actions_aws_deploy_role_arn" {
  description = "Put this value in GitHub Actions secret AWS_DEPLOY_ROLE_ARN."
  value       = try(aws_iam_role.github_actions_deploy[0].arn, null)
}

output "github_actions_ecs_private_subnets" {
  description = "Put this value in GitHub Actions secret ECS_PRIVATE_SUBNETS."
  value       = join(",", formatlist("\"%s\"", values(aws_subnet.private)[*].id))
}

output "github_actions_ecs_security_groups" {
  description = "Put this value in GitHub Actions secret ECS_SECURITY_GROUPS."
  value       = join(",", formatlist("\"%s\"", [aws_security_group.ecs.id]))
}

output "s3_upload_bucket" {
  description = "S3 upload bucket name."
  value       = aws_s3_bucket.uploads.bucket
}

output "sqs_queue_name" {
  description = "SQS queue name."
  value       = aws_sqs_queue.app.name
}

output "sqs_dlq_name" {
  description = "SQS dead-letter queue name."
  value       = aws_sqs_queue.dlq.name
}

output "ssm_parameter_prefix" {
  description = "SSM parameter prefix."
  value       = "/${var.app_name}/${var.environment}"
}
