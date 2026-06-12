# ECS Deployment Guide

Tai lieu nay mo ta flow tu local Docker test den CI/CD deploy len AWS ECS Fargate cho project Laravel/Vue nay.

## 1. Kien truc tong quan

```text
Developer
  -> GitHub push main / manual workflow_dispatch
  -> GitHub Actions
  -> docker build
  -> push image len Amazon ECR
  -> chay one-off ECS task migrate
  -> rolling deploy ECS web service
  -> rolling deploy ECS queue service

User
  -> ALB / CloudFront
  -> ECS Fargate web tasks
  -> RDS MySQL
  -> ElastiCache Redis
  -> S3 uploads
  -> SQS queue
  -> CloudWatch logs
```

Repo da co cac file chinh:

```text
Dockerfile
.dockerignore
docker/entrypoint.sh
docker/nginx/default.conf
docker/php/opcache.ini
docker/php/production.ini
aws/ecs/task-definition-web.json
aws/ecs/task-definition-queue.json
aws/ecs/task-definition-migrate.json
.github/workflows/deploy-ecs.yml
```

## 2. Test local bang Docker

Build image:

```bash
docker build -t eventech-app:local .
```

Tao env file rieng cho Docker local:

```bash
cp .env .env.docker.local
```

Sua cac bien quan trong trong `.env.docker.local`:

```env
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:18080
LOG_CHANNEL=stderr

DB_CONNECTION=mysql
DB_HOST=host.docker.internal
DB_PORT=3306
DB_DATABASE=sushi_eventos20260323
DB_USERNAME=root
DB_PASSWORD=12345678

CACHE_STORE=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local
```

Neu MySQL chay trong Docker Compose khac, doi `DB_HOST` thanh service name cua MySQL.

Chay web container:

```bash
docker run --rm \
  --name eventech-web \
  -p 18080:8080 \
  --env-file .env.docker.local \
  eventech-app:local
```

Mo app:

```text
http://localhost:18080
```

Kiem tra health check:

```bash
curl http://localhost:18080/health
```

Ket qua mong doi:

```json
{"status":"ok"}
```

Chay artisan trong container:

```bash
docker run --rm \
  --env-file .env.docker.local \
  eventech-app:local php artisan about
```

Chay migration local:

```bash
docker run --rm \
  --env-file .env.docker.local \
  eventech-app:local migrate
```

Chay queue worker local:

```bash
docker run --rm \
  --env-file .env.docker.local \
  eventech-app:local queue
```

Vao shell trong image:

```bash
docker run --rm -it \
  --env-file .env.docker.local \
  eventech-app:local sh
```

## 3. Chuan bi AWS

Can tao truoc cac resource sau:

```text
VPC
Public subnets cho ALB
Private subnets cho ECS tasks
NAT Gateway hoac VPC endpoints de ECS pull image / goi AWS APIs
Security Group cho ALB
Security Group cho ECS
Security Group cho RDS/Redis
ALB + Target Group
ECR repository
ECS Cluster
ECS Services: eventech-web, eventech-queue
RDS MySQL hoac Aurora MySQL
ElastiCache Redis
S3 bucket upload
SQS queue
CloudWatch log groups
IAM roles
SSM Parameter Store values
```

Neu dung Terraform trong repo nay thi cac resource tren se duoc tao tu folder:

```text
terraform/
```

Flow khuyen nghi:

```bash
cd terraform
cp terraform.tfvars.example terraform.tfvars
```

Sua `terraform.tfvars`, toi thieu cac bien:

```hcl
github_repository = "ORG/REPO"
app_url           = "https://app.example.com"
app_key           = "base64:..."
certificate_arn   = ""
```

Neu chua co image trong ECR, apply phase 1 de tao ECR truoc:

```bash
terraform init
terraform apply -target=aws_ecr_repository.app
```

Build va push bootstrap image:

```bash
docker build -t eventech-app:bootstrap ..

aws ecr get-login-password --region ap-northeast-1 \
  | docker login --username AWS --password-stdin <AWS_ACCOUNT_ID>.dkr.ecr.ap-northeast-1.amazonaws.com

docker tag eventech-app:bootstrap <AWS_ACCOUNT_ID>.dkr.ecr.ap-northeast-1.amazonaws.com/eventech-app:bootstrap
docker push <AWS_ACCOUNT_ID>.dkr.ecr.ap-northeast-1.amazonaws.com/eventech-app:bootstrap
```

Sau do apply toan bo ha tang:

```bash
terraform apply
```

Lay cac output de tao GitHub Actions secrets:

```bash
terraform output github_actions_aws_deploy_role_arn
terraform output github_actions_ecs_private_subnets
terraform output github_actions_ecs_security_groups
```

Neu muon apply ha tang truoc roi push image sau, tam thoi set:

```hcl
web_desired_count   = 0
queue_desired_count = 0
```

Sau khi image da co, doi lai:

```hcl
web_desired_count   = 2
queue_desired_count = 1
```

roi chay lai:

```bash
terraform apply
```

Ten mac dinh dang duoc dung trong workflow:

```text
AWS_REGION=ap-northeast-1
ECR_REPOSITORY=eventech-app
ECS_CLUSTER=eventech-production
ECS_WEB_SERVICE=eventech-web
ECS_QUEUE_SERVICE=eventech-queue
```

Neu dung ten khac, sua trong `.github/workflows/deploy-ecs.yml` va Terraform variables cho dong bo.

## 4. Tao ECR repository

```bash
aws ecr create-repository \
  --repository-name eventech-app \
  --region ap-northeast-1
```

Dang nhap ECR local neu muon push thu:

```bash
aws ecr get-login-password --region ap-northeast-1 \
  | docker login --username AWS --password-stdin <AWS_ACCOUNT_ID>.dkr.ecr.ap-northeast-1.amazonaws.com
```

Tag va push image local:

```bash
docker tag eventech-app:local <AWS_ACCOUNT_ID>.dkr.ecr.ap-northeast-1.amazonaws.com/eventech-app:local
docker push <AWS_ACCOUNT_ID>.dkr.ecr.ap-northeast-1.amazonaws.com/eventech-app:local
```

## 5. Tao CloudWatch log groups

```bash
aws logs create-log-group --log-group-name /ecs/eventech-web --region ap-northeast-1
aws logs create-log-group --log-group-name /ecs/eventech-queue --region ap-northeast-1
aws logs create-log-group --log-group-name /ecs/eventech-migrate --region ap-northeast-1
```

## 6. SSM Parameter Store

Task definitions doc env tu SSM Parameter Store. Tao cac parameter sau:

```text
/eventech/production/APP_KEY
/eventech/production/APP_URL
/eventech/production/DB_HOST
/eventech/production/DB_DATABASE
/eventech/production/DB_USERNAME
/eventech/production/DB_PASSWORD
/eventech/production/REDIS_HOST
/eventech/production/AWS_BUCKET
/eventech/production/AWS_DEFAULT_REGION
/eventech/production/SQS_PREFIX
/eventech/production/SQS_QUEUE
```

Vi du:

```bash
aws ssm put-parameter \
  --name /eventech/production/APP_URL \
  --type String \
  --value https://app.example.com \
  --region ap-northeast-1
```

Secret nhu `APP_KEY`, `DB_PASSWORD` nen dung `SecureString`:

```bash
aws ssm put-parameter \
  --name /eventech/production/DB_PASSWORD \
  --type SecureString \
  --value 'change-me' \
  --region ap-northeast-1
```

## 7. Sua task definition placeholders

Trong 3 file sau, thay placeholder bang gia tri that:

```text
aws/ecs/task-definition-web.json
aws/ecs/task-definition-queue.json
aws/ecs/task-definition-migrate.json
```

Can thay:

```text
<AWS_ACCOUNT_ID>
<AWS_REGION>
```

Vi du image:

```json
"image": "123456789012.dkr.ecr.ap-northeast-1.amazonaws.com/eventech-app:latest"
```

Vi du role:

```json
"executionRoleArn": "arn:aws:iam::123456789012:role/ecsTaskExecutionRole",
"taskRoleArn": "arn:aws:iam::123456789012:role/eventechTaskRole"
```

## 8. IAM roles

Can co 2 role cho ECS:

```text
ecsTaskExecutionRole
eventechTaskRole
```

`ecsTaskExecutionRole` can quyen:

```text
AmazonECSTaskExecutionRolePolicy
ssm:GetParameters
ssm:GetParameter
kms:Decrypt neu SSM dung SecureString voi customer KMS key
```

`eventechTaskRole` can quyen runtime cho app:

```text
s3:GetObject
s3:PutObject
s3:DeleteObject
sqs:SendMessage
sqs:ReceiveMessage
sqs:DeleteMessage
sqs:GetQueueAttributes
ssm:GetParameter neu app doc them parameter luc runtime
```

Nen gioi han resource ARN theo bucket/queue/parameter that thay vi dung `*`.

## 9. Tao ECS Cluster va Services

Tao cluster:

```bash
aws ecs create-cluster \
  --cluster-name eventech-production \
  --region ap-northeast-1
```

Dang ky task definitions lan dau:

```bash
aws ecs register-task-definition \
  --cli-input-json file://aws/ecs/task-definition-web.json \
  --region ap-northeast-1

aws ecs register-task-definition \
  --cli-input-json file://aws/ecs/task-definition-queue.json \
  --region ap-northeast-1
```

Tao web service gan voi ALB Target Group. Nen cau hinh:

```text
Launch type: FARGATE
Desired tasks: 2 tro len
Deployment minimum healthy percent: 100
Deployment maximum percent: 200
Health check path: /health
Container port: 8080
```

Tao queue service khong can ALB:

```text
Launch type: FARGATE
Desired tasks: 1 tro len
Command: queue
```

## 10. GitHub Actions secrets

Vao GitHub repo:

```text
Settings -> Secrets and variables -> Actions
```

Them secrets:

```text
AWS_DEPLOY_ROLE_ARN
ECS_PRIVATE_SUBNETS
ECS_SECURITY_GROUPS
```

`AWS_DEPLOY_ROLE_ARN` la IAM role cho GitHub OIDC assume role, vi du:

```text
arn:aws:iam::123456789012:role/github-actions-eventech-deploy
```

`ECS_PRIVATE_SUBNETS` phai co format dung de gan vao AWS CLI network config:

```text
"subnet-aaa","subnet-bbb"
```

`ECS_SECURITY_GROUPS`:

```text
"sg-aaa"
```

Role deploy cua GitHub can quyen:

```text
ecr:GetAuthorizationToken
ecr:BatchCheckLayerAvailability
ecr:CompleteLayerUpload
ecr:InitiateLayerUpload
ecr:PutImage
ecr:UploadLayerPart
ecs:RegisterTaskDefinition
ecs:RunTask
ecs:DescribeTasks
ecs:UpdateService
ecs:DescribeServices
iam:PassRole
logs:DescribeLogStreams
logs:GetLogEvents
```

## 11. CI/CD deploy flow

Workflow nam o:

```text
.github/workflows/deploy-ecs.yml
```

Workflow chay khi:

```text
push len branch main
hoac bam Run workflow thu cong
```

Trinh tu:

```text
Checkout code
Setup PHP 8.3
Setup Node 22
composer install
npm ci
npm run lint
npm run build
php artisan test --compact
Configure AWS credentials bang OIDC
Login ECR
docker build
docker push
register migration task definition
run one-off migrate task
deploy web ECS service
deploy queue ECS service
```

Sau khi push len `main`, xem tien trinh tai:

```text
GitHub -> Actions -> Deploy ECS
```

## 12. Zero downtime deploy

De deploy khong downtime, web service can cau hinh:

```text
Desired count: 2 tro len
minimumHealthyPercent: 100
maximumPercent: 200
Health check path: /health
Deployment circuit breaker: enabled
Rollback: enabled
ALB deregistration delay: 30-60 seconds
```

Quan trong nhat la migration phai backward-compatible, vi trong luc rolling deploy se co luc code cu va code moi cung chay.

Vi du an toan:

```text
Deploy 1: add column moi, code doc fallback
Deploy 2: backfill data, code chuyen sang column moi
Deploy 3: drop column cu sau khi chac chan khong con code cu
```

Khong nen rename/drop column ma code cu van dang dung trong cung mot deploy.

## 13. Rollback

Cach nhanh nhat la rollback ECS service ve task definition revision truoc:

```bash
aws ecs update-service \
  --cluster eventech-production \
  --service eventech-web \
  --task-definition eventech-web:<PREVIOUS_REVISION> \
  --region ap-northeast-1
```

Rollback queue service:

```bash
aws ecs update-service \
  --cluster eventech-production \
  --service eventech-queue \
  --task-definition eventech-queue:<PREVIOUS_REVISION> \
  --region ap-northeast-1
```

Neu migration da thay doi schema khong backward-compatible, rollback code co the khong du. Vi vay moi migration production can duoc thiet ke de co the chay song song giua code cu va moi.

## 14. Troubleshooting

Docker build loi `ext-ftp`:

```text
Project co league/flysystem-ftp nen image phai cai PHP ftp extension.
Dockerfile da cai ext-ftp trong vendor stage va runtime stage.
```

Vite build loi `Could not find config file`:

```text
Asset stage can copy eslint.config.js. Dockerfile da co dong copy file nay.
```

Container start loi route cache:

```text
Project co closure route nen entrypoint khong chay php artisan route:cache.
Neu sau nay doi tat ca route sang controller actions, co the bat lai route:cache.
```

ECS task khong pull duoc image:

```text
Kiem tra private subnet co NAT Gateway hoac ECR VPC endpoints.
Kiem tra ecsTaskExecutionRole co quyen ECR va CloudWatch Logs.
```

ECS task health check fail:

```text
Kiem tra /health trong container.
Kiem tra ALB target group health check path la /health.
Kiem tra container port la 8080.
Kiem tra security group ALB -> ECS da mo port 8080.
```

Migration task fail:

```text
Vao CloudWatch log group /ecs/eventech-migrate.
Kiem tra DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD trong SSM.
Kiem tra ECS security group co duoc connect RDS port 3306.
```

Queue khong xu ly job:

```text
Kiem tra QUEUE_CONNECTION=sqs.
Kiem tra SQS_PREFIX va SQS_QUEUE.
Kiem tra eventechTaskRole co quyen SQS.
Kiem tra CloudWatch log group /ecs/eventech-queue.
```

File upload mat sau deploy:

```text
Khong luu file production vao local container.
Production can FILESYSTEM_DISK=s3 va AWS_BUCKET dung.
```

## 15. Checklist truoc production

```text
[ ] docker build -t eventech-app:local . pass
[ ] http://localhost:18080/health tra ve {"status":"ok"}
[ ] ECR repository da tao
[ ] ECS cluster da tao
[ ] ALB target group health check /health
[ ] RDS/Redis/S3/SQS da tao
[ ] SSM parameters da tao
[ ] Task definition da thay <AWS_ACCOUNT_ID> va <AWS_REGION>
[ ] ECS web service desired count >= 2
[ ] ECS deployment circuit breaker + rollback da bat
[ ] GitHub secrets da tao
[ ] GitHub Actions deploy staging pass
[ ] Migration production backward-compatible
```
