# 通用数据管理

## 业务需求

- 业务模块可以复用列表筛选、排序与分页、CSV 导入导出及安全文件存储能力，不需要在各模块重复实现；资源权限、字段规则和业务写入仍由接入模块负责。
- 第一版采用开发者配置式，不提供独立的“通用数据管理”菜单、在线建表、自动生成 CRUD 页面或 Excel 导入导出。
- 当前已在 Admin 内部接入用户列表、用户 CSV 导入导出、用户附件和个人头像；独立的[公告示例模块](example-business-module.md)接入关键词与日期区间筛选、排序分页、CSV 导入导出和公告附件，展示业务模块如何调用底座能力。
- 附件用于将文件关联到业务记录。具有 `users.manage-admins` 权限的管理员可以管理用户附件，不依赖账号是否可编辑；账号资料编辑、角色修改和邀请仍遵循用户管理的保护规则。

## 实现设计

通用组件位于 `admin/geminus/Admin/Libraries/DataManagement/`，由业务模块配置并调用。

### 列表筛选、排序与分页

`ListQuery` 从查询参数读取 `q`、`sort`、`direction`。搜索词最多 100 字符，非字符串参数被忽略；排序字段必须来自业务提供的白名单，排序方向仅允许 ASC 或 DESC，默认 DESC。

```php
use Geminus\Admin\Libraries\DataManagement\ListQuery;

$query = new ListQuery($this->request->getGet(), [
    'username' => 'users.username',
    'created_at' => 'users.created_at',
], 'created_at', 20);
$query->apply($model, ['users.username'], stableField: 'users.id');
$rows = $model->paginate($query->perPage);
```

`apply()` 使用 PostgreSQL 不区分大小写的模糊搜索，并追加稳定排序字段。可通过第三个参数配置枚举筛选，例如 `['status' => ['field' => 'status', 'values' => ['enabled', 'disabled']]]`。非法筛选值忽略，不作为 SQL 执行。

关联查询、资源范围和权限由业务模块决定，在调用 `apply()` 前完成。排序映射支持开发者定义的 SQL 表达式及 `escape` 设置，绝不能将请求参数直接作为 SQL 表达式。用户列表与导出复用同一查询逻辑，分页仍使用 CI4 Model 和 Pager。

区间筛选通过 `apply()` 的 `ranges` 参数配置，例如 `['created' => ['field' => 'users.created_at', 'type' => 'date']]`，读取 `created_from`、`created_to`。日期格式为 `YYYY-MM-DD`，结束边界包含整日；用户列表按 UTC 创建日期筛选，切换排序和导出会保留范围。`type => 'number'` 支持正负整数及小数（最多 30 个字符），边界使用严格格式校验后的数值字面量，避免整数列比较小数时的类型转换错误。非法边界忽略，起始大于结束时整个区间忽略。`range()` 返回归一化后的边界，供表单复显。

### 表单验证与业务写入

沿用 CI4 Validation：业务模块声明规则，验证失败返回字段错误和原输入；验证通过后只使用 `getValidated()` 中的字段。无需另建验证引擎。

用户创建和更新继续由 `UserProvisioning` 处理身份、查重、角色和事务；CSV 组件不直接操作业务数据表。认证、资源权限和全局 CSRF 防护继续沿用现有路由和过滤器。

### CSV 导入导出

`Csv::read($stream, $columns, $maxRows = 500)` 读取严格匹配表头的 CSV，兼容 UTF-8 BOM、引号和多行字段，忽略空行，保留原始记录行号。先检查总行数，再把记录交给业务处理。列数和字段验证、查重以及逐行结果由业务模块负责；用户导入会跳过已有邮箱并报告每行结果，不是整文件原子事务。

`Csv::write($columns, $rows, $maxRows = 10000)` 输出 CSV，限制记录数量并检查列数。以 `=`、`+`、`-`、`@`、制表符、回车或换行开头的单元格加前导单引号，防止电子表格公式注入。重新导入时保留这个单引号，因此不适合作为要求值完全不变的数据备份格式。

用户模块继续限制上传文件为 CSV、最大 1 MB、最多 500 条数据；导出最多 10000 人，超过上限返回 HTTP 413。权限检查、文件扩展名和实际 MIME 检查、下载响应头均由控制器负责。

公告模块采用相同的文件及行数限制，固定表头为 `title,body`，复用新建表单的标题与正文校验规则；每个有效行创建一条公告，无效行报告错误并继续处理。标题不要求唯一，因此重复导入会创建新记录，不更新或跳过同名公告。导出最多 10000 条，复用列表的关键词、UTC 创建日期区间和排序，不受当前分页限制；CSV 的公式防护及重新导入语义与上述组件一致。

`CsvImport::import($stream, $columns, $process, $maxRows = 500, $rules = [])` 提供字段映射、可选 CI4 验证、逐行执行和统一报告。处理器接收字段数组；配置验证规则时只接收 `getValidated()` 的字段。返回 `['result' => 'created|skipped|error', 'reason' => '业务原因码']`。统一报告包含 `row`、`data`、`result`、`reason`、`errors`，列数或验证失败不调用处理器；处理器异常记日志，并返回 `processing` 后继续下一行，不泄露异常内容。

行数和表头在处理任何记录前检查。每行事务、查重和业务副作用由处理器负责，不能假设处理器异常时通用执行器会回滚已执行的业务写入。用户模块已接入执行器，同时保留其原有邮箱查重和报告格式。

### 上传与文件访问

`UploadStorage` 接收受控目录名、允许的 MIME 类型、扩展名和字节上限。`store()` 检查有效 HTTP 上传、是否已移动、实际 MIME、扩展名及实际大小，自动创建 `writable/uploads/<folder>/`，返回随机文件名。

`path()` 仅解析目录内的受控文件，拒绝路径穿越、符号链接和不允许的扩展名；`delete()` 仅删除成功解析的文件。组件本身不负责资源授权，必须在业务确认访问权限和文件关联后调用，不能开放任意目录及文件名的下载接口。

头像通过 `AvatarFiles` 统一上传、验证和读取策略：JPEG、PNG、WebP，最大 2 MB。新文件写入后先保存用户关联，再清理旧文件；保存失败保留旧关联并清理新文件，清理失败记录日志，不覆盖原请求结果。头像读取保持现有登录访问规则。上传存储支持注入删除操作，测试通过该依赖模拟删除失败并核对成功响应、文件状态和日志，不依赖容器权限。

### 附件关联与权限

`Attachments` 负责附件文件和 `admin_attachments` 元数据，按 `resource_type`、`resource_id` 关联业务资源。默认支持 PDF、TXT、CSV、JPEG、PNG 和 WebP，最大 10 MB，同时核对扩展名对应的实际 MIME，原始文件名去除路径及危险字符，存储名随机生成。

业务先验证资源存在和权限，再调用 `upload()`、`find()`、`remove()`。`find()` 和 `remove()` 必须同时提供资源类型、资源 ID、附件 ID；下载需通过 `path()` 获取受控路径。文件上传后元数据保存失败会清理新文件；移除先删除关联再清理文件，清理失败写日志，附件仍不可经业务接口下载。资源生命周期的批量清理和每行业务事务由接入模块负责。

用户列表的每行操作区提供独立的回形针附件入口，用户编辑页也保留快捷入口；附件页返回用户列表，不依赖用户编辑页面。列表分页、上传、下载和移除位于 `{locale}/admin/users/{userId}/attachments`。所有接口需要 `users.manage-admins`，并检查目标用户存在；附件管理与账号可编辑状态分开判断，具有该权限的管理员可以管理自身、超级管理员及多角色账号的附件，但账号资料编辑、角色修改和邀请仍受原有保护。目标用户不存在或附件不属于指定用户时返回 404。这里仍复用用户管理权限，尚未新增附件专用权限。写操作采用 POST 并受全局 CSRF 保护；下载采用附件响应，带私有缓存和 `nosniff` 设置。

业务文件统一通过 `Attachments` 关联资源并经业务接口授权下载。头像使用专用入口 `/admin/avatars/{userId}`，由 `AvatarController` 查找用户当前的头像关联，再通过 `AvatarFiles` 复用 `UploadStorage` 的安全存储策略；登录用户可以读取头像，不要求用户管理权限，响应采用私有缓存和 `nosniff` 设置。用户没有头像、头像文件缺失或关联路径不安全时返回 404。头像上传、移除和读取均复用同一存储能力，不加入用户附件列表。

## 运行约束

公告附件关联使用 `resource_type = announcement`，所有页面与接口沿用 `announcements.manage` 权限，路径为 `{locale}/admin/announcements/{announcementId}/attachments`。模块先确认公告存在，下载及移除再核对资源类型、公告 ID 与附件 ID；跨公告、跨资源类型或缺失文件返回 404。上传与移除为 POST 并受全局 CSRF 保护，下载使用私有缓存与 `nosniff`；文件策略复用 `Attachments`，不另建存储目录或附件表。示例没有公告删除流程，扩展删除时须由模块处理附件清理。

部署前在仓库根目录运行数据库迁移，创建附件元数据表：

```sh
docker compose -f docker/docker-compose.yaml exec -T geminus-admin php spark migrate --all
```

在仓库根目录使用已配置的独立测试数据库运行：

```sh
docker compose -f docker/docker-compose.yaml exec -T geminus-admin vendor/bin/phpunit --no-coverage tests/unit/DataManagementTest.php tests/unit/UsersTest.php tests/unit/UserCsvImportTest.php tests/unit/ProfileAccessTest.php tests/unit/AnnouncementsTest.php
```