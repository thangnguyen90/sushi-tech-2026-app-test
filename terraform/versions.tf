terraform {
  required_version = ">= 1.6.0"

  # Uncomment and fill in to store state remotely (strongly recommended for team use).
  # Create the S3 bucket and DynamoDB table before running terraform init.
  #
  # backend "s3" {
  #   bucket         = "YOUR-TERRAFORM-STATE-BUCKET"
  #   key            = "eventech/production/terraform.tfstate"
  #   region         = "ap-northeast-1"
  #   dynamodb_table = "terraform-state-lock"
  #   encrypt        = true
  # }

  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }

    random = {
      source  = "hashicorp/random"
      version = "~> 3.6"
    }
  }
}

provider "aws" {
  region = var.aws_region
}
