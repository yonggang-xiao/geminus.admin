# 业务模块示例：公告

公告模块位于 [`admin/modules/Announcements/`](../admin/modules/Announcements/)，与后台底座 `admin/geminus/Admin/` 并列。它展示一个独立业务模块如何接入路由、授权、导航、数据表、页面与测试；示例只提供公告列表和新建，不包含编辑、发布或删除流程。

## 模块接入

1. 在 [`admin/app/Config/Autoload.php`](../admin/app/Config/Autoload.php) 将 `Modules\Announcements` 映射到 `admin/modules/Announcements`。其他业务模块使用各自的命名空间和目录，不把业务代码放进后台底座。
2. 模块的 [`Config/Routes.php`](../admin/modules/Announcements/Config/Routes.php) 声明 `{locale}/admin/announcements` 下的列表、创建页和 POST 新建路由；路由组统一使用 `permission:announcements.manage` 过滤器。仅显示菜单不构成访问控制，写操作也不能通过 GET 路由执行。
3. 模块的 [`Config/Registrar.php`](../admin/modules/Announcements/Config/Registrar.php) 只向 [`Geminus\Admin\Config\AdminMenu`](../admin/geminus/Admin/Config/AdminMenu.php) 追加菜单条目。侧栏按权限显示入口并高亮当前路径，不在后台底座里写死具体业务模块。
4. 模块的 [`Database/Migrations/`](../admin/modules/Announcements/Database/Migrations/) 创建 `example_announcements` 表，并将 `announcements.manage` 加入 Settings 的 `AuthGroups.permissions` 目录及 `superadmin` 授权矩阵。`AuthGroups` 配置和模块 Registrar 都不声明这项业务权限；其他角色需要在“系统设置 → 角色与权限”中另行授权。迁移先检查已有目录和通配符授权，不覆盖管理员设置的权限描述。

## 请求与页面

[`Announcements` 控制器](../admin/modules/Announcements/Controllers/Announcements.php) 的 `index()` 通过 [`AnnouncementModel`](../admin/modules/Announcements/Models/AnnouncementModel.php) 按 ID 倒序分页，每页 15 条；`create()` 展示表单；`store()` 校验标题和正文后只用验证通过的字段插入。标题最长 150 字符，正文最长 2000 字符。失败时带输入和字段错误返回表单，成功后重定向到列表并显示反馈。

页面在 [`Views/`](../admin/modules/Announcements/Views/) 中继承后台布局，表单携带 CSRF 字段，输出内容经过转义；全局 CSRF 过滤器保护 POST。文案放在模块的 `Language/en/`、`Language/zh-Hans/` 和 `Language/zh-Hant/` 中。新增页面时同时维护路由权限、语言文本、菜单可见性及测试。

## 本地验证

在仓库根目录启动服务并按 [README](../README.md#快速启动) 配置环境后，运行：

```sh
docker compose -f docker/docker-compose.yaml exec -T geminus-admin php spark migrate --all
docker compose -f docker/docker-compose.yaml exec -T geminus-admin php spark filter:check POST zh-Hans/admin/announcements/create
docker compose -f docker/docker-compose.yaml exec -T geminus-admin vendor/bin/phpunit --no-coverage tests/unit/AnnouncementsTest.php
```

使用 `superadmin` 登录后访问 `/zh-Hans/admin/announcements`。[`AnnouncementsTest.php`](../admin/tests/unit/AnnouncementsTest.php) 覆盖未登录和无权限访问、CSRF、输入校验、成功写入与 HTML 转义，也验证权限确实由迁移写入 Settings 而非配置默认值。测试需使用 [README 中的独立测试数据库](../README.md#运行测试)，不要指向业务库。