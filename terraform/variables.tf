variable "aws_region" {
  description = "AWS region to deploy into."
  type        = string
  default     = "ap-northeast-1"
}

variable "app_name" {
  description = "Short application name used in AWS resource names."
  type        = string
  default     = "eventech"
}

variable "environment" {
  description = "Environment name."
  type        = string
  default     = "production"
}

variable "github_repository" {
  description = "GitHub repository in ORG/REPO format. Leave empty to skip GitHub OIDC deploy role."
  type        = string
  default     = ""
}

variable "github_branch" {
  description = "Branch allowed to assume the GitHub deploy role."
  type        = string
  default     = "main"
}

variable "github_oidc_sub" {
  description = "Optional exact GitHub OIDC sub claim. Example: repo:ORG/REPO:environment:production"
  type        = string
  default     = ""
}

variable "github_oidc_provider_arn" {
  description = "Existing GitHub OIDC provider ARN. Leave empty to let Terraform create one."
  type        = string
  default     = ""
}

variable "vpc_cidr" {
  description = "CIDR block for the VPC."
  type        = string
  default     = "10.40.0.0/16"
}

variable "az_count" {
  description = "Number of availability zones to use."
  type        = number
  default     = 2
}

variable "enable_nat_gateway" {
  description = "Create one NAT Gateway for private subnets."
  type        = bool
  default     = true
}

variable "nat_per_az" {
  description = "Create one NAT Gateway per AZ for high availability. Requires enable_nat_gateway = true. Costs more but survives single-AZ failure."
  type        = bool
  default     = false
}

variable "enable_vpc_endpoints" {
  description = "Create AWS VPC endpoints for private ECS tasks. Useful when NAT Gateway is disabled."
  type        = bool
  default     = false
}

variable "certificate_arn" {
  description = "ACM certificate ARN. If empty, ALB only serves HTTP on port 80."
  type        = string
  default     = ""
}

variable "app_url" {
  description = "Production APP_URL for Laravel."
  type        = string
  default     = "https://example.com"
}

variable "app_key" {
  description = "Laravel APP_KEY. Generate with php artisan key:generate --show."
  type        = string
  sensitive   = true
}

variable "image_tag" {
  description = "Initial image tag used by Terraform task definitions. CI/CD replaces this on deploy."
  type        = string
  default     = "bootstrap"
}

variable "web_desired_count" {
  description = "Initial ECS web task count. Auto scaling manages count after first deploy."
  type        = number
  default     = 2
}

variable "web_min_count" {
  description = "Minimum ECS web task count for auto scaling."
  type        = number
  default     = 1
}

variable "web_max_count" {
  description = "Maximum ECS web task count for auto scaling."
  type        = number
  default     = 4
}

variable "queue_desired_count" {
  description = "Desired ECS queue task count."
  type        = number
  default     = 1
}

variable "web_cpu" {
  description = "Fargate CPU units for the web task."
  type        = number
  default     = 1024
}

variable "web_memory" {
  description = "Fargate memory MB for the web task."
  type        = number
  default     = 2048
}

variable "queue_cpu" {
  description = "Fargate CPU units for the queue task."
  type        = number
  default     = 512
}

variable "queue_memory" {
  description = "Fargate memory MB for the queue task."
  type        = number
  default     = 1024
}

variable "db_name" {
  description = "RDS database name."
  type        = string
  default     = "eventech"
}

variable "db_username" {
  description = "RDS master username."
  type        = string
  default     = "eventech"
}

variable "db_password" {
  description = "Optional RDS master password. If empty, Terraform generates one."
  type        = string
  default     = ""
  sensitive   = true
}

variable "db_instance_class" {
  description = "RDS instance class."
  type        = string
  default     = "db.t4g.micro"
}

variable "db_allocated_storage" {
  description = "RDS allocated storage in GB."
  type        = number
  default     = 30
}

variable "db_engine_version" {
  description = "MySQL engine version."
  type        = string
  default     = "8.0"
}

variable "db_multi_az" {
  description = "Enable Multi-AZ for RDS."
  type        = bool
  default     = false
}

variable "db_deletion_protection" {
  description = "Enable RDS deletion protection."
  type        = bool
  default     = true
}

variable "redis_node_type" {
  description = "ElastiCache Redis node type."
  type        = string
  default     = "cache.t4g.micro"
}

variable "redis_engine_version" {
  description = "Redis engine version."
  type        = string
  default     = "7.1"
}

variable "upload_bucket_name" {
  description = "Optional fixed S3 bucket name. Leave empty to generate one."
  type        = string
  default     = ""
}

variable "sqs_queue_name" {
  description = "Optional fixed SQS queue name. Leave empty to generate from app/environment."
  type        = string
  default     = ""
}

variable "redis_transit_encryption" {
  description = "Enable Redis in-transit (TLS) encryption. When true, Laravel must connect with scheme: tls."
  type        = bool
  default     = false
}
