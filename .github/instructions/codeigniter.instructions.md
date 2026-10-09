---
description: 'Geminus 后台应用的 CodeIgniter 4 项目约定'
applyTo: 'admin/app/**/*.php, admin/geminus/**/*.php, admin/modules/**/*.php, admin/tests/**/*.php, admin/public/**/*.php, admin/*.php'
---

# CodeIgniter 项目约定

框架行为以 CodeIgniter 4 官方用户指南为准；本文件不能替代官方文档：

- [RESTful 资源处理](https://codeigniter.com/user_guide/incoming/restful.html)：资源路由与 presenter 路由。
- [路由与过滤器](https://codeigniter.com/user_guide/incoming/routing.html)：显式路由、HTTP 方法与路由过滤器。
- [安全与验证](https://codeigniter.com/user_guide/libraries/security.html)：CSRF 防护；输入规则查阅 [Validation](https://codeigniter.com/user_guide/libraries/validation.html)。
- [数据库迁移](https://codeigniter.com/user_guide/dbmgmt/migration.html)：模块命名空间下的结构变更。
- [代码模块](https://codeigniter.com/user_guide/general/modules.html)：模块命名空间、发现机制与路由。
- [官方扩展包](https://codeigniter.com/user_guide/libraries/official_packages.html)：Settings、Tasks 和 Queue 的用途与文档入口。
- [CodeIgniter Shield](https://shield.codeigniter.com/)：身份认证、用户组与权限管理。
- [CodeIgniter Coding Standard](https://github.com/CodeIgniter/coding-standard)：PHP 代码风格规范。

## 项目约定

### 开发环境与命令

- PHP、Composer、Spark、PHPUnit 和 PHP CS Fixer 均在 Docker 的 `geminus-admin` 服务中运行；不要使用宿主机的 `php`、`composer` 或 `vendor/bin/*`。下文的 PHP 命令均指容器内命令。
- 从仓库根目录执行命令：容器运行时用 `docker compose -f docker/docker-compose.yaml exec -T geminus-admin <命令>`；仅做不依赖其他服务的检查且容器未运行时，用 `docker compose -f docker/docker-compose.yaml run --rm --no-deps geminus-admin <命令>`。需要数据库或 Redis 的命令应先启动相关服务。

### 结构与路由

- 共享应用代码放在 `admin/app/`，后台底座代码放在 `admin/geminus/Admin/`，命名空间为 `Geminus\Admin`；独立业务模块放在 `admin/modules/{ModuleName}/`，命名空间为 `Modules\{ModuleName}`（例如 `Modules\Announcements`）。业务模块复用后台底座的布局与通用组件，不把具体业务逻辑放进底座。
- 在 `admin/app/Config/Autoload.php` 注册业务模块的命名空间；模块自己的控制器、模型、视图、语言文件及数据库迁移放在所属模块目录，路由放在其 `Config/Routes.php` 中。接入方式参考 [公告业务模块示例](../../docs/example-business-module.md)。
- 业务模块通过自己的 `Config/Registrar.php` 向 `Geminus\Admin\Config\AdminMenu` 追加菜单，不在后台底座中写死业务入口；菜单可见性不能替代路由授权。业务权限通过模块迁移加入 Settings 的 `AuthGroups.permissions` 目录及所需授权矩阵，不在 Registrar 或 `AuthGroups` 配置默认值中声明，也不覆盖管理员已有设置。
- 后台页面涉及 AJAX 或表单提交时，若符合资源工作流，优先使用 presenter 路由及对应的 `ResourcePresenter` 方法。REST API 使用资源路由；非资源操作沿用现有的显式路由。
- 保持 `admin/app/Config/Routing.php` 的自动路由关闭；新路由按 HTTP 方法显式定义，不用可被 GET 访问的通用路由执行写操作。后台路由明确认证与所需权限，并用 `php spark filter:check <方法> <路径>` 核对过滤器是否生效。
- 权限相同的相邻路由优先通过路由组统一声明 `filter`；权限不同的路由分别声明。仅因共享路径前缀分组时不改变原有权限要求；调整分组后核对路径、路由别名及实际生效的过滤器。
- 项目使用 `en`、`zh-Hans`、`zh-Hant`；在处理本地化请求时确保 Request 与 Language 服务的 locale 一致。框架验证文案优先复用已安装的 `codeigniter4/translations`，注意包内中文目录为 `zh-CN`、`zh-TW`，需适配项目 locale，并补齐当前框架缺少的规则键。

### Libraries 与职责边界

- `Libraries` 用于组织非 HTTP、非展示层的业务流程、策略和技术组件，不仅用于工具类。应用级共享能力放在 `admin/app/Libraries/`，后台底座能力放在 `admin/geminus/Admin/Libraries/`，具体业务能力放在 `admin/modules/{ModuleName}/Libraries/`；可复用不等于必须归入后台底座。
- 每个类表达一个明确能力，使用 `UserProvisioning`、`UserManagementPolicy`、`Csv` 等具体名称，避免 `Common`、`Utils`、`Manager` 等泛化容器；同类能力较多时按主题建立子目录。类的命名空间、目录和文件名遵循 PSR-4，并保持大小写一致。
- Controller 负责读取请求、调用业务能力及映射 HTTP 状态与响应；Library 接收明确参数，返回结果或抛出明确异常，不读取全局请求、不重定向，也不生成页面或 JSON 响应。
- Library 可以编排多个 Model、执行业务验证并管理完整业务操作的事务；Model 负责查询与持久化，Entity 负责实体状态与行为，Cell/View 负责展示。Controller 可以先验证请求格式，但可复用的业务约束不能只放在 Controller 中。
- 新增或实质调整的 Library 优先显式传入依赖：持续使用的协作对象通过构造函数注入，单次操作的数据和上下文通过方法参数传入。避免在业务类内部通过 `service()`、`model()`、`auth()` 隐式获取依赖，由调用方或工厂负责装配；不为简单的 PHP 内置函数创建依赖包装，也不要求每个类都配接口。
- `Config\Services` 负责实例创建，不是业务逻辑的存放位置；只有需要统一装配、替换实现或共享实例时才注册服务，不把所有 Library 都注册为共享服务。绑定当前用户或含可变状态的对象不要默认共享。参考官方 [自动加载](https://codeigniter.com/user_guide/concepts/autoloader.html)与 [Services](https://codeigniter.com/user_guide/concepts/services.html) 文档。
- 保留现有 `Libraries` 组织方式，现有类在相关功能修改时逐步对齐；不为遵循这些约定而批量搬迁目录或进行无关的依赖注入重构。

### 接口与安全

- JSON 表单接口成功时返回 `{ message, data }`，失败时返回 `{ message, errors }`，并使用相应的 HTTP 状态码。
- 使用 Geminus.js 的 AJAX 接口在 CSRF token 重新生成后，将新 token 放入成功及可处理的失败 JSON 响应的 `csrf: { name, hash }` 字段，以便客户端更新后续请求使用的 token。
- 新增或修改数据的请求必须在服务端验证输入并检查当前用户的操作权限；表单中的 CSRF 字段和前端校验不能替代服务端检查。
- `admin/app/Config/Filters.php` 已在全局 `before` 启用 CSRF；不要在每条写入路由上重复声明 `filter => csrf`。新增写入路由时仍用 `php spark filter:check <方法> <路径>` 核对覆盖范围；例外需说明原因并测试。表单保留 `csrf_field()`，测试分别覆盖缺少令牌被拒绝和携带令牌后到达业务逻辑。
- 表单与 JSON 请求按明确的字段规则验证，写入 Model 时只使用验证器 `getValidated()` 返回的字段，不直接传入整个请求数组；JSON 数据使用 Strict Rules，不改用 Traditional Rules。
- 上传文件要在服务端限制大小、实际 MIME 类型及扩展名，使用随机文件名并保存于 `writable/`；更新用户关联记录成功后再清理旧文件，且只删除该用户关联的受控文件。上传流程至少覆盖缺失或无效文件、成功上传及移除的测试。
- Shield 权限名称采用 `{domain}.{ability}` 两段式（例如 `users.create`）；用用户组表示角色，用权限表示能力。遵循 `admin/app/Config/AuthGroups.php` 中的现有定义。

### 扩展包与视图

- 项目设置统一使用 [CodeIgniter Settings](https://settings.codeigniter.com/) 包管理：配置类提供默认值，需持久化或运行时调整的值通过 Settings 读写；不要另建设置存储机制。数据库连接、密钥等启动配置和敏感信息仍使用环境配置，不写入 Settings 数据库。
- 定时或周期性任务按需使用 [CodeIgniter Tasks](https://tasks.codeigniter.com/)；需要异步或延后执行的任务按需使用 [CodeIgniter Queue](https://queue.codeigniter.com/)。实际使用前先确认相应包已安装，未安装则通过 Composer 引入。
- 可复用的服务端渲染 UI 沿用 `admin/geminus/Admin/Cells/` 中的 View Cell 模式：Cell 类与其模板放在同一目录。业务逻辑留在 Cell 外。
- 数据库结构变更放入所属模块的 `Database/Migrations/`，不依赖手工 SQL 改库；涉及跨命名空间迁移时运行 `php spark migrate --all` 并核对结果。
- 本项目业务数据库仅使用 PostgreSQL。需不区分大小写的独立业务标识字段（如用户名）在迁移中使用 `citext` 并建立数据库唯一约束，让 CI4 普通等值查询遵循字段语义；不要在各业务模块重复编写 `LOWER()` 查重。模糊搜索明确使用 CI4 `like(..., 'both', null, true)`（PostgreSQL `ILIKE`）。混存不同凭据类型的列（如 Shield 的 `auth_identities.secret`）不得整列改为 `citext`，只对需要不区分大小写的身份类型建立定向约束；密码哈希校验不属于数据库文本比较。

### 测试与风格

- 新增或修改后端行为时（包括 `admin/modules/` 中的业务模块），按行为在 `admin/tests/` 中补充 PHPUnit 单元、功能或数据库测试并运行相关测试；`admin/phpunit.xml.dist` 已将 `app/`、`geminus/Admin/` 与 `modules/` 纳入覆盖率统计范围，并排除视图及路由文件，但仅列入范围不代表已有测试覆盖。
- 按能力选择测试：纯计算和策略优先使用单元测试，涉及数据库与事务的流程使用数据库测试，HTTP 行为使用功能测试；以可观察的结果、失败路径及相关边界为断言目标，不仅验证方法调用。
- 新增和修改的 PHP 代码遵循 CodeIgniter Coding Standard；使用项目现有的 `admin/.php-cs-fixer.dist.php` 配置，从 `admin/` 目录运行 `vendor/bin/php-cs-fixer fix --dry-run --diff <改动文件路径>` 检查改动文件。

当项目附近的代码与通用示例不同时，以项目现有写法为准；框架 API 和语法查阅对应的官方指南章节。