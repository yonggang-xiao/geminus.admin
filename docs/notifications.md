# 通知中心

## 业务需求

- 通知中心属于后台底座 `Geminus\Admin`，提供通用站内通知；业务模块决定通知的触发时机、接收人和业务权限，公告只是通知来源之一。
- 用户通过 Tabler 铃铛查看自己的未读通知；桌面入口位于主题切换之后，手机导航保留通知入口，不显示独立主题切换按钮。通知时间按当前用户时区显示。
- 点击通知进入业务页面后不再显示为未读；直接进入通知目标页面也可自动标记已读。读取通知时重新检查权限，撤销授权后不再显示或允许打开相应通知。
- 通知随刷新或页面跳转更新，不提供实时推送、轮询、邮件通知、已读历史页面或通知偏好设置。

## 实现设计

### 通用服务与存储

[`Notifications`](../admin/geminus/Admin/Libraries/Notifications.php) 负责发送、接收人隔离、权限检查及已读状态，[`NotificationModel`](../admin/geminus/Admin/Models/NotificationModel.php) 持久化到 `admin_notifications`，由 [Admin 数据库迁移](../admin/geminus/Admin/Database/Migrations/2026-10-10-000001_CreateNotifications.php) 创建。服务依赖由 [后台 Services](../admin/geminus/Admin/Config/Services.php) 装配，通过 `service('notifications')` 获取。

通知以接收人、来源和业务编号唯一；重复投递不生成新通知，也不恢复已读状态。标题在发送时保存，展示时转义。创建和已读时间以 UTC 存储，显示时转换为用户时区。

### 业务模块接入

业务模块可以直接调用通知服务，也可以在自己的 `Config/Events.php` 注册监听器，由监听器调用通知服务。Admin 不订阅所有业务事件，也不包含公告状态或接收人筛选逻辑。

```php
service('notifications')->send(
    $userId,
    'announcements.published',
    $announcementId,
    $title,
    'admin/announcements/show',
    ['announcements.access', 'announcements.manage'],
    [$announcementId],
);
```

| 参数 | 约束 |
| --- | --- |
| `$userId` | 正整数，由业务模块选择接收人 |
| `$source` | 通知来源，最长 64 字符，仅允许字母、数字、下划线、点和连字符 |
| `$reference` | 正整数业务编号，与来源和接收人共同保证投递幂等 |
| `$title` | 非空标题，最长 200 字符 |
| `$targetRoute` | `admin/` 开头的后台命名路由，不接受外部 URL |
| `$permissions` | 具体的两段式权限名称列表，满足任一权限即可读取；空列表表示仅检查接收人身份 |
| `$targetParameters` | 按路由占位符顺序排列的整数或字符串参数列表 |

发送不会自动判断用户是否应接收通知，业务模块必须自行筛选接收人。通知权限检查也不替代目标页面的资源授权，目标控制器仍须独立检查权限、资源存在性和业务可见性。

[公告示例](example-business-module.md#发布事件与通知中心) 展示首次发布后触发 `announcements.published`，事件携带公告 ID 和本次发布操作者 ID。模块监听器排除操作者，向其他有公告阅读或管理权限的用户投递通知；发布者通过页面成功提示获得操作反馈。Admin 通知中心不负责排除操作者，其他业务模块按各自规则选择接收人。CI Events 同步执行，不是浏览器推送或消息队列；公告监听异常记录到错误日志，不撤销已发布状态，也不将发布响应改为失败。当前投递为尽力而为，没有自动重试、事务 outbox 或历史通知回填。

### 展示与已读

后台布局通过 [`NotificationsCell`](../admin/geminus/Admin/Cells/NotificationsCell.php) 和 [通知视图](../admin/geminus/Admin/Cells/notifications.php) 显示当前用户有权读取的未读列表，按通知 ID 倒序排列。目标路由失效或参数不匹配的通知不显示；未读列表当前不分页。

点击通知提交带 CSRF 字段的普通 POST 到 `/{locale}/admin/notifications/{id}/open`。[通知控制器](../admin/geminus/Admin/Controllers/Notifications.php) 检查所有权和当前权限，解析目标路由，标记已读后重定向。其他用户的通知、权限不足或失效目标返回 404，不标记已读；已读写入失败返回安全错误，不暴露异常细节。响应不允许缓存，已读 POST 沿用全局操作审计。

直接进入通知目标页面时，[通知脚本](../admin/public/static/js/notifications.js) 将当前路径对应的未读通知通过同一 POST 接口标记已读，并同步桌面与移动端的数量和列表。请求携带 CSRF 字段，成功响应更新后续请求使用的令牌；请求失败保留未读状态。

## 运行约束

按 [README](../README.md#快速启动) 配置并启动服务，运行 `php spark migrate --all` 创建通知表。下拉菜单依赖 Tabler JavaScript，直接访问目标页面自动标记已读依赖通知脚本；禁用 JavaScript 时不提供这些交互。

业务模块须注册有效的后台命名路由并提供匹配的参数，同时维护目标页面的权限和资源授权。通知服务本身不依赖 Events；使用事件的模块须按项目约定接入事件自动发现。公告当前发布为单条数据库更新；若业务使用显式事务，应在最外层事务提交成功后触发事件，避免为未提交的业务结果发送通知。

在仓库根目录运行通知相关测试：

```sh
docker compose -f docker/docker-compose.yaml exec -T geminus-admin vendor/bin/phpunit --no-coverage tests/unit/NotificationsTest.php
```

测试需使用 [README 中的独立测试数据库](../README.md#运行测试)，不要指向业务库。