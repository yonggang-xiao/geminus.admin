# 业务模块示例：公告

公告模块位于 [`admin/modules/Announcements/`](../admin/modules/Announcements/)，与后台底座 `admin/geminus/Admin/` 并列。它展示一个独立业务模块如何接入路由、授权、导航、数据表、通用数据管理、界面组件与测试；示例提供列表、新建、CSV 导入导出和附件管理，不包含编辑、发布或公告删除流程。

## 模块接入

1. 在 [`admin/app/Config/Autoload.php`](../admin/app/Config/Autoload.php) 将 `Modules\Announcements` 映射到 `admin/modules/Announcements`。其他业务模块使用各自的命名空间和目录，不把业务代码放进后台底座。
2. 模块的 [`Config/Routes.php`](../admin/modules/Announcements/Config/Routes.php) 声明 `{locale}/admin/announcements` 下的列表、新建、导入导出、模板下载和附件路由；路由组统一使用 `permission:announcements.manage` 过滤器。仅显示菜单不构成访问控制，新建、导入、上传及移除均使用 POST。
3. 模块的 [`Config/Registrar.php`](../admin/modules/Announcements/Config/Registrar.php) 只向 [`Geminus\Admin\Config\AdminMenu`](../admin/geminus/Admin/Config/AdminMenu.php) 追加菜单条目。侧栏按权限显示入口并高亮当前路径，不在后台底座里写死具体业务模块。
4. 模块的 [`Database/Migrations/`](../admin/modules/Announcements/Database/Migrations/) 创建 `example_announcements` 表，并将 `announcements.manage` 加入 Settings 的 `AuthGroups.permissions` 目录及 `superadmin` 授权矩阵。`AuthGroups` 配置和模块 Registrar 都不声明这项业务权限；其他角色需要在“系统设置 → 角色与权限”中另行授权。迁移先检查已有目录和通配符授权，不覆盖管理员设置的权限描述。

## 请求与页面

[`Announcements` 控制器](../admin/modules/Announcements/Controllers/Announcements.php) 的 `index()` 通过 `ListQuery` 和 [`AnnouncementModel`](../admin/modules/Announcements/Models/AnnouncementModel.php) 查询，每页 15 条，默认 ID 倒序；支持标题及正文的不区分大小写搜索、UTC 创建日期区间、标题与创建时间排序，并追加稳定 ID 排序。非法排序与日期参数不会直接进入 SQL；筛选、排序及分页链接保留适用参数，切换筛选与排序不沿用页码。

`create()` 展示表单；`store()` 校验标题和正文后只用验证通过的字段插入。标题最长 150 字符，正文最长 2000 字符。失败时带输入和字段错误返回表单，成功后重定向到列表并显示反馈；CSV 导入复用相同字段规则。

页面在 [`Views/`](../admin/modules/Announcements/Views/) 中继承后台布局，表单携带 CSRF 字段，输出内容经过转义；全局 CSRF 过滤器保护 POST。文案放在模块的 `Language/en/`、`Language/zh-Hans/` 和 `Language/zh-Hant/` 中。新增页面时同时维护路由权限、语言文本、菜单可见性及测试。

## 通用能力接入

| 工作流 | 数据管理能力 | 界面组件 |
| --- | --- | --- |
| 公告列表 | `ListQuery`、CI4 Model / Pager | `FilterBarCell`、`SortHeaderCell`、`DateFieldCell`（由筛选栏组合）、`EmptyStateCell`、`PaginationCell` |
| CSV 导入 | `CsvImport`、共享字段规则、逐行公告写入 | `ImportReportCell` |
| CSV 导出及模板 | `Csv`、与列表相同的查询入口 | 模块自己的下载操作按钮 |
| 公告附件 | `Attachments`、`AttachmentModel`、底座安全存储 | `AttachmentsCell`、`EmptyStateCell`、`PaginationCell` |

列表右上角提供新建、导入和导出，行操作区提供附件入口；没有数据时可新建，筛选无结果时可清除筛选。日期控件按需加载 Tabler 日历资源，提交 `YYYY-MM-DD`；筛选边界为 UTC 日期，列表时间按当前用户时区显示。

### CSV

`/import` 是独立导入页，提供 `/template` 模板下载。表头必须严格为 `title,body`；CSV 最大 1 MB、最多 500 条数据，服务端检查实际 MIME 和扩展名。通用执行器完成列映射及字段校验，模块处理器每行插入公告；无效行或保存失败记入报告，其他行继续处理，不是整文件原子导入。报告显示行号、标题、结果及字段错误，不显示异常细节。

公告标题不要求唯一；同名与重复导入都创建新记录，不更新已有公告。导出 `/export` 保留列表关键词、日期区间和排序，不受页码影响，最多 10000 条，超出返回 HTTP 413。通用 CSV 写入器防护电子表格公式；重新导入会保留前导单引号，不适合作为值完全不变的备份格式。

### 附件

[`AnnouncementAttachments` 控制器](../admin/modules/Announcements/Controllers/AnnouncementAttachments.php) 管理 `/{announcementId}/attachments` 下的列表、POST 上传、下载和 POST 移除。所有入口需要 `announcements.manage`，先检查公告存在，再以 `announcement` 类型、公告 ID 和附件 ID 查找关联；其他公告或用户的附件不可通过此入口访问。

文件格式与大小复用 `Attachments`：PDF、TXT、CSV、JPEG、PNG、WebP，最大 10 MB，随机文件名保存于 `writable/`。下载为附件响应，带私有缓存与 `nosniff`；缺失公告、错误关联或缺失文件返回 404。共享附件元数据表由 Admin 迁移创建，模块不另建附件表；日后添加公告删除流程时，应在模块中处理附件生命周期。

字段规则、查询、授权与业务写入留在模块控制器；通用执行器不决定公告权限，Cell 只接收准备好的数据与受控路由，不查询数据库或执行业务回调。其他接入参数与约束见[通用数据管理](data-management.md)及[通用界面组件](ui-components.md)，不为示例新增万能表格或整页 CRUD 引擎。

## 本地验证

在仓库根目录启动服务并按 [README](../README.md#快速启动) 配置环境后，运行：

```sh
docker compose -f docker/docker-compose.yaml exec -T geminus-admin php spark migrate --all
docker compose -f docker/docker-compose.yaml exec -T geminus-admin php spark filter:check POST zh-Hans/admin/announcements/create
docker compose -f docker/docker-compose.yaml exec -T geminus-admin php spark filter:check POST zh-Hans/admin/announcements/import
docker compose -f docker/docker-compose.yaml exec -T geminus-admin php spark filter:check POST zh-Hans/admin/announcements/1/attachments
docker compose -f docker/docker-compose.yaml exec -T geminus-admin php spark filter:check GET zh-Hans/admin/announcements/1/attachments/1
docker compose -f docker/docker-compose.yaml exec -T geminus-admin php spark filter:check POST zh-Hans/admin/announcements/1/attachments/1/remove
docker compose -f docker/docker-compose.yaml exec -T geminus-admin vendor/bin/phpunit --no-coverage tests/unit/AnnouncementsTest.php
```

使用 `superadmin` 登录后访问 `/zh-Hans/admin/announcements`。[`AnnouncementsTest.php`](../admin/tests/unit/AnnouncementsTest.php) 覆盖未登录和无权限访问、CSRF、输入校验、成功写入与 HTML 转义、筛选排序分页、CSV 导入报告及导出限制、附件上传下载移除和跨资源隔离，也验证权限确实由迁移写入 Settings 而非配置默认值。测试需使用 [README 中的独立测试数据库](../README.md#运行测试)，不要指向业务库。