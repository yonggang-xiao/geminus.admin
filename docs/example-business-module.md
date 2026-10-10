# 业务模块示例：公告

## 业务需求

- 公告示例展示独立业务模块如何接入后台底座的通用数据管理和界面组件，业务代码与后台底座分开维护。
- 获管理授权的用户可创建草稿、编辑和发布公告；获阅读授权的用户只能查看已发布公告详情及可选附件。
- 附件按需添加，CSV 仅作为次要批量工具，导入记录默认成为草稿。
- 模块使用独立权限，超级管理员默认获得 `announcements.*`，其他角色由“系统设置 → 角色与权限”分配。不包含审批、定时发布、置顶、撤回或公告删除流程。

## 实现设计

### 模块接入

公告模块位于 [`admin/modules/Announcements/`](../admin/modules/Announcements/)，与后台底座 `admin/geminus/Admin/` 并列。

1. 在 [`admin/app/Config/Autoload.php`](../admin/app/Config/Autoload.php) 将 `Modules\Announcements` 映射到 `admin/modules/Announcements`。其他业务模块使用各自的命名空间和目录，不把业务代码放进后台底座。
2. 模块的 [`Config/Routes.php`](../admin/modules/Announcements/Config/Routes.php) 声明 `{locale}/admin/announcements` 下的业务路由。列表、详情及附件下载使用 `permission:announcements.access,announcements.manage`，满足任一权限即可进入；仅有阅读权限的用户只能读取已发布内容，公告管理者可查看草稿。新建、编辑、发布、CSV 工具及附件管理使用 `permission:announcements.manage`。仅显示菜单不构成访问控制，创建、更新、发布、导入、上传及移除均使用 POST。
3. 模块的 [`Config/Registrar.php`](../admin/modules/Announcements/Config/Registrar.php) 只向 [`Geminus\Admin\Config\AdminMenu`](../admin/geminus/Admin/Config/AdminMenu.php) 追加菜单条目。菜单的 `permission` 支持原有字符串或权限字符串列表，列表按“任一权限”显示；公告入口对 `announcements.access` 或 `announcements.manage` 可见。`admin.access` 不替代模块阅读权限；模块权限也不会自动授予其他后台权限。侧栏高亮当前路径，不在后台底座里写死具体业务模块。
4. 模块的 [`Database/Migrations/`](../admin/modules/Announcements/Database/Migrations/) 创建 `example_announcements` 表，将 `announcements.access` 和 `announcements.manage` 加入 Settings 的 `AuthGroups.permissions` 目录，并为 `superadmin` 补齐 `announcements.*`，其已有合法单项授权按域归并。其他角色授权矩阵保持不变，由“系统设置 → 角色与权限”分配。`AuthGroups` 配置和模块 Registrar 都不声明权限目录；已有权限描述及其他目录项保持不变。
5. 公告使用 `status` 和 `published_at` 表示发布状态及发布时间。`status` 只能是 `draft` 或 `published`；数据库约束保证草稿无发布时间、已发布公告有发布时间。

### 请求与页面

[`Announcements` 控制器](../admin/modules/Announcements/Controllers/Announcements.php) 的 `index()` 通过 `ListQuery` 和 [`AnnouncementModel`](../admin/modules/Announcements/Models/AnnouncementModel.php) 查询，每页 15 条。管理者看到草稿和已发布公告，默认 ID 倒序，可按状态、UTC 创建日期、标题及正文筛选，按标题、创建或发布时间排序。仅有公告阅读权限的用户只看到已发布公告，默认发布时间倒序，日期筛选改为 UTC 发布日期；不能通过筛选参数或直接访问 ID 读取草稿。查询追加稳定 ID 排序，非法参数不进入 SQL，切换筛选和排序不沿用页码。

`create()` 与 `edit()` 复用表单；标题最长 150 字符，正文最长 2000 字符，失败时带原输入和字段错误返回。创建只保存草稿，成功后进入详情；更新只写入验证通过的标题和正文，不接受请求中的状态或发布时间。管理者在详情页单独发布，服务端设置 UTC 发布时间；重复发布不会改写首次发布时间。已发布公告仍可编辑，表单明确提示修改立即对读者生效，状态与首次发布时间保持不变。

`show()` 对普通读者与附件下载复用模型的可见性查询；普通读者访问草稿或任何人访问不存在的公告均返回 404。详情显示完整转义正文、发布时间和已有附件下载入口；管理者另有编辑、发布及管理附件入口。没有附件的普通读者页面不展示空附件区域。单独授予 `announcements.manage` 的角色也能进入公告列表、详情及下载，管理功能仍由服务端管理权限保护。

页面在 [`Views/`](../admin/modules/Announcements/Views/) 中继承后台布局，表单携带 CSRF 字段，输出内容经过转义；全局 CSRF 过滤器保护 POST。文案放在模块的 `Language/en/`、`Language/zh-Hans/` 和 `Language/zh-Hant/` 中。新增页面时同时维护路由权限、语言文本、菜单可见性及测试。

### 通用能力接入

| 工作流 | 数据管理能力 | 界面组件 |
| --- | --- | --- |
| 公告列表 | `ListQuery`、CI4 Model / Pager | `FilterBarCell`、`SortHeaderCell`、`DateFieldCell`（由筛选栏组合）、`EmptyStateCell`、`PaginationCell` |
| CSV 导入 | `CsvImport`、共享字段规则、逐行公告写入 | `ImportReportCell` |
| CSV 导出及模板 | `Csv`、与列表相同的查询入口 | 模块自己的下载操作按钮 |
| 公告详情及可选附件 | 模型可见性查询、`Attachments`、`AttachmentModel`、底座安全存储 | 只读或管理模式的 `AttachmentsCell`、`EmptyStateCell`、`PaginationCell` |

管理列表右上角突出新建，导入和导出收进“CSV 工具”下拉菜单；标题进入详情，行操作提供编辑。创建后不强制上传附件，管理者可从详情的附件区域按需进入管理页，再返回详情。没有数据时管理者可新建，读者只看到已发布公告空态；筛选无结果时可清除筛选。筛选栏与表格沿用 Admin 用户列表的同一卡片组合；管理者增加状态字段，普通读者不显示管理字段。日期控件按需加载 Tabler 日历资源，提交 `YYYY-MM-DD`；筛选边界为 UTC 日期，显示时间按当前用户时区转换。

### 发布事件与通知中心

通知中心属于后台底座 `Geminus\Admin`，公告只是通知来源之一。通用发送接口、存储、权限及已读行为见[通知中心](notifications.md)，公告模块只负责发布事件和业务接收人筛选。

[`AnnouncementPublication`](../admin/modules/Announcements/Libraries/AnnouncementPublication.php) 使用数据库条件更新完成首次发布，成功后触发 `Events::trigger('announcements.published', $announcementId, $publisherId)`，事件参数依次为公告 ID 和本次发布操作者 ID。操作者由控制器从当前登录用户取得，显式传入发布服务和事件，不从请求字段或监听器中的当前会话推断。草稿保存、CSV 导入、已发布公告编辑和重复发布不会触发该事件。当前发布操作是单条数据库更新；若未来改为显式事务，应在最外层事务提交成功后触发事件。

模块的 [`Config/Events.php`](../admin/modules/Announcements/Config/Events.php) 由 CI 自动发现，监听事件并调用 [`AnnouncementNotifications`](../admin/modules/Announcements/Libraries/AnnouncementNotifications.php)。监听器向发布时未删除且具有 `announcements.access` 或 `announcements.manage` 的用户发送通知，但排除本次发布操作者；其他管理员仍正常接收。发布者通过页面的发布成功提示获得操作反馈，不接收自己触发的发布通知。这一接收人规则由公告模块负责，Admin 通知中心不判断操作者或公告业务。项目未启用注册激活流程，接收人不以 Shield 的 `active` 字段筛选。没有逐用户回填历史公告；之后新获授权的用户仍可从公告列表阅读内容。通知以接收人、来源和业务编号唯一，重复投递不会再次生成通知，也不会恢复已读。

CI Events 同步执行。通知监听异常记录到错误日志，不撤销已发布状态，也不把发布响应改为失败；通知投递是尽力而为，不保证故障后的自动重试或完整投递。若需要可靠投递或耗时通知，应另行使用事务 outbox 或队列，而不是把事件等同于消息队列。

### CSV

`/import` 是独立导入页，提供 `/template` 模板下载。表头必须严格为 `title,body`；CSV 最大 1 MB、最多 500 条数据，服务端检查实际 MIME 和扩展名。通用执行器完成列映射及字段校验，模块处理器每行插入公告；无效行或保存失败记入报告，其他行继续处理，不是整文件原子导入。报告显示行号、标题、结果及字段错误，不显示异常细节。

所有导入记录均为草稿，不自动发布。公告标题不要求唯一；同名与重复导入都创建新草稿，不更新已有公告。导出 `/export` 保留管理列表的关键词、状态、日期区间和排序，不受页码影响，最多 10000 条，超出返回 HTTP 413。CSV 仅含标题和正文，不包含发布状态、发布时间或附件。通用 CSV 写入器防护电子表格公式；重新导入会保留前导单引号，不适合作为值完全不变的备份格式。

### 附件

[`AnnouncementAttachments` 控制器](../admin/modules/Announcements/Controllers/AnnouncementAttachments.php) 管理 `/{announcementId}/attachments` 下的列表、POST 上传、下载和 POST 移除。附件管理需要 `announcements.manage`；下载需要 `announcements.access` 或 `announcements.manage`，仅有阅读权限的用户只能下载已发布公告的附件，管理者可下载草稿附件。先检查公告可见性，再以 `announcement` 类型、公告 ID 和附件 ID 查找关联；其他公告或用户的附件不可通过此入口访问。已发布公告的附件修改同样立即生效。

文件格式与大小复用 `Attachments`：PDF、TXT、CSV、JPEG、PNG、WebP，大小上限读取 PHP 环境配置，随机文件名保存于 `writable/`。`Attachments::maxBytes()` 同时用于服务端校验和页面大小提示，部署时应让 `post_max_size` 高于 `upload_max_filesize`，为表单开销留出空间。下载为附件响应，带私有缓存与 `nosniff`；缺失公告、错误关联或缺失文件返回 404。共享附件元数据表由 Admin 迁移创建，模块不另建附件表；日后添加公告删除流程时，应在模块中处理附件生命周期。

用户管理中的上传记录按附件的 `uploaded_by` 汇总，公告模块向底座提供公告来源、详情及下载路由和读取可见性。公告管理者可看到所查用户上传到草稿及已发布公告的附件；仅有公告阅读权限时只显示已发布公告附件；没有公告权限时不展示公告附件或其计数。汇总页只提供原业务详情和下载入口，不提供上传或移除操作，也不改变附件的公告归属。

字段规则、查询、授权与业务写入留在模块控制器；通用执行器不决定公告权限，Cell 只接收准备好的数据与受控路由，不查询数据库或执行业务回调。其他接入参数与约束见[通用数据管理](data-management.md)及[通用界面组件](ui-components.md)，不为示例新增万能表格或整页 CRUD 引擎。

## 运行约束

按 [README](../README.md#快速启动) 配置并启动本地服务，再由超级管理员在“系统设置 → 角色与权限”为其他目标角色勾选 `announcements.access` 或 `announcements.manage` 并保存。使用该角色的用户访问 `/zh-Hans/admin/announcements`；超级管理员默认拥有 `announcements.*`，其他默认角色没有公告授权，仅有 `admin.access` 不能读取公告。管理权限已包含本模块的读取能力，无需同时授予阅读权限。

在仓库根目录运行公告模块相关测试：

```sh
docker compose -f docker/docker-compose.yaml exec -T geminus-admin vendor/bin/phpunit --no-coverage tests/unit/AnnouncementsTest.php
```

自动化测试覆盖角色授权与撤销、迁移目录登记、草稿创建及导入、编辑与发布、读者可见性、权限和 CSRF，以及筛选排序分页、CSV 和附件隔离。测试需使用 [README 中的独立测试数据库](../README.md#运行测试)，不要指向业务库。