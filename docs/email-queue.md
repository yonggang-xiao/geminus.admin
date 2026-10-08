# 邮件队列

普通邮件通过 Queue 的 `email` 队列发送，投递状态、尝试次数、收件人、主题和失败原因保存在 `email_delivery_logs` 表；失败原因仅记录入队或投递失败类别及可用的 SMTP 状态码，不保存原始调试信息，后续重试成功时清除。邮件设置页的“发送测试邮件”仍同步发送。

## 调度与部署

Compose 中的 `geminus-admin` 容器内运行 cron，每分钟执行一次 `php spark tasks:run`，无需额外容器或宿主机定时任务。修改镜像后需在仓库根目录运行 `docker compose -f docker/docker-compose.yaml up -d --build geminus-admin` 才会启用；可通过 `docker compose -f docker/docker-compose.yaml logs geminus-admin` 查看任务输出。非 Compose 部署需自行每分钟触发一次 `php spark tasks:run`；部署时先运行 `php spark migrate --all`。

## 排查与安全

失败任务最多尝试三次，可使用 Queue 的 `queue:failed`、`queue:retry` 命令排查或重试。队列载荷含邮件正文及认证令牌，应限制数据库读取权限并按需清理过期任务。