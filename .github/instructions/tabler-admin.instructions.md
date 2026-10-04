---
description: 'Tabler Admin（基于 Bootstrap 5）的后台界面规范：用于在本项目的 CodeIgniter 4 视图中生成一致的 Tabler 风格 UI'
applyTo: 'admin/app/Views/**/*.php, admin/geminus/**/Views/**/*.php, admin/geminus/**/Cells/**/*.php'
---

# CodeIgniter 4 Admin 视图约定

本文件只规定项目内 CodeIgniter 4 SSR 视图的集成与页面工作流；Tabler 组件的结构、样式和交互以 `tabler` skill 为准。示例可参考组件标记，不要直接复制整页模板或示例文案。


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

## 6) 列表与登录页面

- 列表页提供搜索/筛选、必要时的批量操作与导出、行操作、分页和总数；后端 pager 提供范围时同时显示当前范围。无数据或无搜索结果时按上述空态规则提供入口。
- 登录页使用 `layout_public`，不输出敏感错误细节；登录失败提示保持通用。

## 7) 交付检查

- 核对布局及 section、导航高亮与 locale、表单 CSRF/回显/错误、列表的分页与空态。
- 确认脚本只按需加载，内容与路由使用本项目的数据和约定。
