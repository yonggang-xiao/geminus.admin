# 通用界面组件

## 业务需求

- 业务模块可以复用筛选栏、排序表头、列表空态、分页摘要、日期控件、附件区域和导入报告，减少重复模板并保持一致的 Tabler 界面。
- 采用开发者配置式，使用 CI4 受控 View Cells 封装具有参数或展示逻辑的界面片段；不提供在线配置、整页 CRUD、任意 HTML 插槽或业务回调。
- 当前已接入用户列表、用户附件、用户 CSV 导入、个人中心、邮件队列与投递记录、操作审计；公告示例模块复用筛选栏、排序表头、日期控件、列表空态与分页、导入报告和附件区域，展示独立业务模块的组合方式。
- 资源权限、字段规则和业务写入仍由接入模块负责；隐藏操作按钮不代替服务端权限检查。

## 实现设计

通用组件位于 [admin/geminus/Admin/Cells/](../admin/geminus/Admin/Cells/)，Cell 类与对应的 snake_case 模板放在同一目录，通过完整命名空间的 `view_cell()` 调用。

沿用 [CI4 View Cells](https://codeigniter.com/user_guide/outgoing/view_cells.html#performing-setup-logic) 的约定，展示数据可在 Cell 的 `mount()` 或 Controller 中准备；Cell 负责局部展示逻辑和 HTML 转义，业务逻辑留在 Cell 外。输入归一化、验证、资源授权及业务写入由 Controller / Library 按职责处理。

本文列出的开发者配置式通用组件采用调用方传入数据、翻译文案及受控链接的方式，不自行查询业务数据、读取请求参数或决定当前用户的权限。这是这些组件的接口约定，不是对所有 Cell 的统一限制。

纯模板片段继续使用视图 `include()`；页面布局、业务表格行及页面操作保持在所属模块，不为静态标记创建 Cell。

### 头像与时区

`AvatarCell` 提供用户头像与尺寸展示，已用于个人中心和用户菜单。`TimezoneSelectorCell` 提供时区选项、选中状态及校验样式，已用于个人中心。

### 列表筛选与排序

`FilterBarCell` 已接入用户列表、邮件投递记录和操作审计，`SortHeaderCell` 已接入用户列表和操作审计。审计的操作者与对象选项由 Controller 准备，保留对象类型、具体对象及已删除用户的显示方式。`FilterBarCell` 接收 `action`、`clearUrl`、`submitLabel`、`clearLabel`、`hidden` 和 `fields`。表单固定使用 GET，不沿用分页参数；`hidden` 只传需要保留的标量参数，例如排序字段与方向，不直接传整个请求。

每个字段提供 `id`、`name`、`label`、`value`，可选 `class` 指定 Bootstrap 网格宽度。支持的 `type` 为 `text`（默认）、`select`、`date`、`number`；不支持的类型抛出异常。文本可指定 `maxlength`，枚举通过 `options` 传入值到文案的映射。区间通过两个独立字段表达，名称与后端筛选规则保持一致；数字允许小数，日期提交格式为 `YYYY-MM-DD`。字段配置来自开发者，值由 Controller 归一化。

```php
<?= view_cell('Geminus\Admin\Cells\FilterBarCell', [
    'action' => route_to('admin/users'),
    'clearUrl' => route_to('admin/users'),
    'submitLabel' => lang('Admin.userFilter'),
    'clearLabel' => lang('Admin.userClear'),
    'hidden' => ['sort' => $sort, 'direction' => $direction],
    'fields' => [
        ['id' => 'user-search', 'name' => 'q', 'label' => lang('Admin.userSearch'), 'value' => $search, 'maxlength' => 100],
    ],
]) ?>
```

`SortHeaderCell` 输出完整的 `<th scope="col">`，在 `<thead><tr>` 中调用。传入 `action`、`field`、`label`、当前 `sort`、`direction` 和标量 `filters`。活动列切换 ASC / DESC，非活动列使用 `defaultDirection`（默认 ASC）；创建时间列可设置 DESC。组件保留筛选，移除 `filters` 中的 `sort`、`direction` 与 `page`，避免重复参数及沿用旧页码。命名分页组的页码参数也不要传入 `filters`。后端仍必须执行排序白名单检查。

### 列表空态与分页

`EmptyStateCell` 和 `PaginationCell` 已用于用户列表、用户附件、邮件队列与投递记录、操作审计和公告示例。个人中心的密钥列表复用空态，不增加新建入口或分页；邮件与审计的筛选无结果时提供清除筛选入口，无记录且未筛选时仅显示提示。

`EmptyStateCell` 接收 `message` 和 `filtered`；未筛选时选择 `createUrl` / `createLabel` / `createIcon`（默认 `plus`），筛选时选择 `clearUrl` / `clearLabel`。没有相应链接或文案时不输出按钮。表格内由调用方提供 `<tr><td colspan="...">`，组件不决定列数。调用方根据授权决定是否提供新建入口。

`PaginationCell` 接收 `totalLabel`、`total`、`currentPage`、`perPage` 和 `links`，输出摘要与导航，不提供外层卡片或布局。`links` 是唯一不转义的参数，必须来自 CI4 Pager 的 `links()` / `makeLinks()`，不得来自请求、数据库字段或任意用户 HTML。不要将 Pager 对象传入 Cell：CI4 会序列化 Cell 参数，而 Pager 可能包含闭包。

```php
<?= view_cell('Geminus\Admin\Cells\PaginationCell', [
    'totalLabel' => lang('Admin.userTotal'),
    'total' => $pager->getTotal(),
    'currentPage' => $pager->getCurrentPage(),
    'perPage' => $pager->getPerPage(),
    'links' => $pager->links(),
]) ?>
```

命名分页组要在所有 Pager 调用中使用同一组名。分页器及筛选参数仍由原页面管理，不在 Cell 内使用共享 Pager 服务。

### 日期控件

`DateFieldCell` 已用于用户筛选、操作审计筛选和个人中心密钥有效期。组件接收 `inputId`、`name`、`label`、`value`，可选 `required`、`min`、`max`、`hint`、`error`。错误通过 `aria-invalid` 和 `aria-describedby` 关联到输入，图标在左侧，错误反馈在包装器外显式显示。`value` 和 `error` 由调用方使用 `old()`、字段错误或已有数据准备，Cell 不依赖 session 错误键。

控件只输出标记，不重复加载资源或注入脚本。页面在 `head` section 按需加载与 Tabler Core 一致的 Vanilla Calendar Pro，并在 `javascript` section 初始化 `tabler.Datepicker`，通过 `dateFormat` 保持 `YYYY-MM-DD`。同页多个控件必须使用不同的 `inputId`。参考用户列表和个人中心的现有初始化。

### 附件区域

`AttachmentsCell` 已接入公告附件页及详情页的附件区域。组件接收通用附件记录 `attachments`，每条包含 `id`、`original_name`、`size_bytes`；不执行文件读取、上传或移除。用户管理的上传记录页使用只读来源列表，显示用户在业务模块上传的文件，不调用附件上传表单组件。

组件直接放在 `.card` 内，外层不包裹 `.card-body`。组件输出的上传表单自带 `.card-body` 内边距，表格使用 `.table-responsive > .table.card-table` 贴齐卡片边缘；窄屏下表格在容器内横向滚动，文件名保持单行。分页组件放在同一卡片的 `.card-footer` 内，详情页的只读附件区域沿用相同结构。

- `uploadUrl`：受控 POST 上传地址；为空时不显示上传表单。
- `downloadRoute`、`removeRoute`：模块提供的命名路由；`routeArguments` 是附件 ID 之前的参数，例如 `[$announcementId]`。组件追加每条附件 ID 并生成 URL；路由名为空时隐藏对应操作。
- `accept`、`hint`：接入模块提供的上传格式及提示；这些前端信息不代替服务端 MIME、扩展名和大小检查。
- `error`：当前文件字段的错误；`inputId` 默认 `attachment-file`，同页多实例时需指定不同 ID。
- `labels`：传入 `file`、`size`、`actions`、`upload`、`download`、`remove`、`empty` 的本地化文案。

上传和移除表单固定使用 POST 并包含 CSRF 字段，下载使用链接。资源存在性、附件关联和操作权限仍由业务接口检查；组件不能根据提供的路由自行推断授权。分页由外部 `PaginationCell` 组合，附件区域本身不查询记录。

### 导入报告

`ImportReportCell` 已接入用户与公告 CSV 导入。组件接收 `title`、`rowLabel`、`resultLabel`、`reasonLabel`、`columns`、`rows`、`resultLabels` 和 `reasonLabels`。

`columns` 是报告字段到显示文案的映射；每条 `rows` 包含 `row`、`result`、`reason` 及对应列的标量值，可选 `errors` 提供字段错误文案数组。`resultLabels`、`reasonLabels` 将业务原因码映射为本地化文本，未映射值作为纯文本显示。组件统计各结果数量，不执行 CSV 解析、字段验证或业务处理。

用户导入器现有的扁平报告可直接接入；使用通用 `CsvImport` 的嵌套 `data` 报告时，由 Controller 将要展示的字段映射到上述扁平结构。导入的行号、结果及原因码不应在展示层重新判定。

## 运行约束

组件依赖后台布局加载的 Tabler 资源，日期控件还需页面按需加载 Vanilla Calendar Pro 并初始化。所有组件调用均不启用 Cell 缓存；含 CSRF、用户数据或权限相关操作的内容不得使用共享缓存。

在仓库根目录运行组件与接入页面测试，使用已配置的独立测试数据库：

```sh
docker compose -f docker/docker-compose.yaml exec -T geminus-admin vendor/bin/phpunit --no-coverage tests/unit/UiCellsTest.php tests/unit/UsersTest.php tests/unit/UserCsvImportTest.php tests/unit/ProfileAccessTest.php tests/unit/EmailSettingsTest.php tests/unit/OperationAuditTest.php tests/unit/AnnouncementsTest.php
```

组件测试覆盖参数复显与转义、排序状态、枚举与区间控件、日期错误关联、空态操作、分页范围、导入报告计数以及附件路由和 POST / CSRF 标记；页面测试继续检查原有查询、权限和写入行为。