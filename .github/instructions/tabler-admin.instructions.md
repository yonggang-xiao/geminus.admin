---
description: 'Tabler Admin（基于 Bootstrap 5）的后台界面规范：用于在本项目的 CodeIgniter 4 视图中生成一致的 Tabler 风格 UI'
applyTo: 'admin/app/Views/**/*.php, admin/geminus/**/Views/**/*.php, admin/geminus/**/Cells/**/*.php, admin/modules/**/Views/**/*.php'
---

# CodeIgniter 4 Admin 视图约定

本文件规定项目内 CodeIgniter 4 SSR 视图的集成、页面工作流与统一交互约定；Tabler 组件的结构、样式和交互以 `tabler` skill 为准。示例可参考组件标记，不要直接复制整页模板或示例文案。


## 1) 项目资源

- `admin/geminus/Admin/Views/layout_main.php` 与 `layout_public.php` 已通过 CDN 加载与 skill 一致的 Tabler Core 1.6.1，以及 Icons Webfont 3.35.0；主布局的 `<head>` 提前加载 `/static/js/tabler-theme.js`。图标沿用项目现有的 Webfont 接入方式，不要仅因 skill 的 SVG 示例切换图标来源或擅自改为本地打包。
- CSS/JS 由布局加载；仅在需要的页面通过 `section('head')` 追加插件资源，页面初始化脚本放入 `section('javascript')`。

## 2) CodeIgniter 4 视图与布局

使用 `extend/section/endSection` 填充已有布局，不在内容页重复输出 `<html>/<body>` 或页面骨架。布局提供 sidebar、topbar、page header、page body 和 footer。

- 主布局 `Geminus\Admin\Views\layout_main` 用于后台页；公共布局 `Geminus\Admin\Views\layout_public` 用于登录、404 等无侧边栏页面。
- 可复用且需要独立展示逻辑或参数的 UI 片段优先使用 [CodeIgniter View Cells](https://codeigniter.com/user_guide/outgoing/view_cells.html)：沿用 `Geminus\Admin\Cells\AvatarCell`（`avatar.php`）和 `Geminus\Admin\Cells\TimezoneSelectorCell`（`timezone_selector.php`）的受控 Cell 模式，在视图中通过 `view_cell()` 调用；片段的数据准备放在 Cell 的 `mount()` 或 Controller，不放在模板中。
- 纯模板片段沿用 `$this->include('Geminus\Admin\Views\...')`，例如现有的 `sidebar`、`user_menu`、`theme_toggle`、`language_selector`、`alert`；不要仅为静态标记创建 Cell。

### 后台页面

`layout_main` 约定：

- 页面标题由 Controller 通过 `$page_title` 传入：布局用作 HTML 标题，页面通过 `section('header')` 渲染可见标题与操作。
- 内容区使用 `section('content')`。
- 页面专属资源使用 `section('head')`，初始化脚本使用 `section('javascript')`（注意：布局渲染的是 `javascript`，不是 `scripts`）。

```php
<?= $this->extend('Geminus\Admin\Views\layout_main') ?>

<?= $this->section('header') ?>
  <h2 class="page-title"><?= esc($page_title) ?></h2>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
  <p>Hello World!</p>
<?= $this->endSection() ?>

<?= $this->section('javascript') ?>
  <script>
    // page-only js
  </script>
<?= $this->endSection() ?>
```

### 公共页面

`layout_public` 约定：

- 页面标题使用 `section('page_title')`。
- 主体使用 `section('content')`。
- 页面脚本使用 `section('javascript')`。

```php
<?= $this->extend('Geminus\Admin\Views\layout_public') ?>

<?= $this->section('page_title') ?><?= esc($title ?? '') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
  <!-- public page content -->
<?= $this->endSection() ?>
```

## 3) 导航与 locale

- 侧边栏当前路由高亮，有子菜单时展开父级；面包屑只在层级不少于 2 时显示，最后一项为不可点击的当前页。
- Admin 路由使用 `{locale}/admin/...` 分组（见 `admin/geminus/Admin/Config/Routes.php`）；生成链接优先用 `route_to('admin/dashboard')` 等命名路由，检查生成的 URL 是否保留首段 locale。`language_selector` 当前只是 UI 外壳，不要假设它已完成切换逻辑。

## 4) 页面交互与反馈

- 在 `section('header')` 中呈现可见标题、说明和操作，不要在 `section('content')` 中重复构造 page header；保留 1 个主操作、0~2 个次操作，其余放入下拉菜单。
- 危险的行操作与普通操作分组；长表单或多步骤流程使用独立页面，而非弹窗。
- 表单操作的结果消息统一通过 flashdata `alert` 交给 `layout_main` 渲染，不在内容页重复输出提示。`alert` 为包含 `type`（`success`、`danger` 或 `warning`）和纯文本 `message` 的数组；仅需一次性展示的附加内容可放在 `detail`，例如新生成的 API 密钥。示例：`return redirect()->back()->with('alert', ['type' => 'success', 'message' => lang('Admin.profileSaved')]);`。
- 字段校验错误仍显示在对应控件下方，不用通用结果消息替代；无法归属到单个字段的操作错误使用 `alert`。
- 列表无数据、筛选无结果时展示相应空态和“新建/清除筛选”入口；404 页面参考现有 `Geminus\Admin\Views\errors\404`。

### 按钮与提示

- 文字操作按钮左侧使用语义对应的 Tabler 图标，例如 `<i class="ti ti-device-floppy me-1" aria-hidden="true"></i>`；保留主操作、次操作和危险操作的配色语义。同一列表的行操作统一尺寸及 `btn-icon` 样式，不改权限、路由、HTTP 方法或 CSRF。
- 纯图标操作按钮使用 `btn-icon`，提供本地化的 `aria-label` 和 tooltip；关闭按钮也要有可访问名称和提示。装饰图标设置 `aria-hidden="true"`。表头排序、分页和插件生成的控件保留组件原有结构，不机械套用操作按钮规则。
- 复用两个布局已加载的 `/static/js/button-tooltips.js`，不要在页面重复初始化。普通图标按钮以 `aria-label` 提供提示文案，不额外添加原生 `title` 造成双重提示。
- navbar 的主题和语言按钮明确不显示 tooltip：设置 `data-button-tooltip="false"`，保留 `aria-label`，不添加 `title` 或 `data-bs-toggle="tooltip"`。
- Tooltip 不与 Dropdown、Offcanvas 等 Bootstrap 组件绑定在同一元素。需要提示的组件触发按钮保留原有 `data-bs-toggle`，在独立的 `span.d-inline-flex` 上设置 `data-button-tooltip` 和经属性转义的 `title`；内层按钮保留 `aria-label`，不重复设置 `title`。不要把 tooltip 绑定到包含菜单项的祖先容器。
- Dropdown 触发元素与 `dropdown-menu` 保持同级；不要单独包裹触发元素而破坏菜单查找。`btn-icon` 的图标直接放在按钮内，避免额外包装导致偏移。

## 5) CI4 表单

- 优先渐进增强：表单保留可用的 `action`、`method` 和原生提交路径，在需要就地反馈时再由 JS 接管；接入 [Geminus.js](https://github.com/yonggang-xiao/geminus.js) 后，后台 AJAX 表单和操作优先使用其 `submitForm()`、`ajaxRequest()`。接入前核对 CSRF token 更新、JSON 响应契约及 HTTP 方法支持，不要假设该库已在项目中加载。
- 表单包含 `csrf_field()`；使用 `old('field')` 复显输入，动态输出使用 `esc()`；字段错误展示在对应控件下方，必要时在顶部汇总。
- 输入提示使用 Tabler 的 `.form-text` 并用 `aria-describedby` 关联控件；文件选择表单设置 `enctype="multipart/form-data"`，提示允许的格式和大小，预览复用现有 Avatar Cell。
- 编辑表单按数据域分组；复杂流程分段，避免将长表单塞进弹窗。

```php
<input
  name="email"
  type="email"
  class="form-control<?= session('errors.email') ? ' is-invalid' : '' ?>"
  value="<?= esc(old('email')) ?>"
/>
<?php if (session('errors.email')): ?>
  <div class="invalid-feedback"><?= esc(session('errors.email')) ?></div>
<?php endif; ?>
```

### 日期输入

- 日期字段统一使用 Tabler Datepicker，不使用原生 `type="date"` 替代：输入使用 `type="text"`、`data-bs-toggle="datepicker"`、`autocomplete="off"` 和 `placeholder="YYYY-MM-DD"`。按需在 `section('head')` 加载与 Tabler Core 版本一致的 Vanilla Calendar Pro，在 `section('javascript')` 使用 `new tabler.Datepicker(...)` 初始化；不重复加载布局已有的 Tabler CSS/JS。
- 使用 `dateFormat` 保持 `YYYY-MM-DD` 提交与回显格式；保留字段的 `id`、`name`、`old()` 回显、必填约束及日期上下限，不改变后端日期契约。
- 日期输入使用 `.input-icon` 包装，添加 `.input-icon-addon` 和 `<i class="ti ti-calendar" aria-hidden="true"></i>`。有校验状态的字段将 addon 放在 input 前，使日历图标位于左侧，避免与右侧的校验状态图标重叠。
- 包装输入后，确保 `.invalid-feedback` 仍显示在控件下方；错误提示若位于 `.input-icon` 外，仅在有错误时渲染并添加 `d-block`，不要依赖已不再匹配的 `.is-invalid ~ .invalid-feedback` 兄弟选择器。不要将错误文字放入图标定位区域，导致图标垂直偏移。

## 6) 列表与登录页面

- 新增或改造列表页前，先选取同类 Admin 页面作为布局基准，例如用户列表 `admin/geminus/Admin/Views/users.php`；同时参考筛选栏、表格及分页的外层组合，不只复制 View Cell 调用或参数。
- 同类页面保持筛选栏容器、内边距、响应式字段列宽、按钮排列及分页位置一致；例如沿用用户列表时，将筛选栏置于表格卡片的 `card-body` 中。字段数量和业务流程不同时可调整布局，并说明业务理由；不统一规定所有页面的固定列宽，也不为统一样式扩大 Cell 的业务或整页布局职责。
- 列表页提供搜索/筛选、必要时的批量操作与导出、行操作、分页和总数；后端 pager 提供范围时同时显示当前范围。无数据或无搜索结果时按上述空态规则提供入口。
- 登录页使用 `layout_public`，不输出敏感错误细节；登录失败提示保持通用。

## 7) 交付检查

- 新增或调整列表布局后，在桌面和移动端视口中对照参考页检查筛选栏、表格与分页的容器、间距、字段排列及换行，确认无重叠或整页横向溢出；功能测试通过不能代替视觉检查。关键组合结构按需补充 DOM 断言，浏览器验证受限时说明未验证项。
- 核对布局及 section、导航高亮与 locale、表单 CSRF/回显/错误、列表的分页与空态。
- 核对文字按钮图标、图标按钮的可访问名称与提示、navbar 的免提示例外；确认提示不影响下拉菜单和 Offcanvas。检查日期选择、提交格式及正常/错误状态下的图标对齐和反馈显示。
- 确认脚本只按需加载，内容与路由使用本项目的数据和约定。
