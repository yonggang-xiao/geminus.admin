<!doctype html>
<html lang="<?= service('request')->getLocale() ?>">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= esc($page_title) ?></title>
    <script src="/static/js/tabler-theme.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.6.1/dist/css/tabler.min.css" integrity="sha384-tT2UAGE9hxG/p5d0iGIvZ/s8El3nWWG3tfG02i8iOY5Pbf8cZZRVmcrrVs+JG5Vw" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.35.0/dist/tabler-icons.min.css" />
    <link rel="stylesheet" href="/static/css/theme.css" />
    <?= $this->renderSection('head') ?>
</head>

<body>
    <div class="page">
        <!-- Sidebar -->
        <aside class="navbar navbar-vertical navbar-expand-lg" data-bs-theme="dark">
            <div class="container-fluid">
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu" aria-controls="sidebar-menu" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <h1 class="navbar-brand">
                    <a href="#">
                        <img src="/static/logo-white.png" width="110" height="32" alt="Admin" class="navbar-brand-image" />
                    </a>
                </h1>
                <div class="navbar-nav flex-row d-lg-none">
                    <?= $this->include('Geminus\Admin\Views\user_menu') ?>
                </div>
                <div class="collapse navbar-collapse" id="sidebar-menu">
                    <?= $this->include('Geminus\Admin\Views\sidebar') ?>
                </div>
            </div>
        </aside>
        <!-- Top navbar -->
        <header class="navbar sticky-top d-none d-lg-flex">
            <div class="container-xl">
                <div></div>
                <div class="navbar-nav flex-row order-lg-last">
                    <div class="d-lg-flex gap-2">
                        <?= $this->include('Geminus\Admin\Views\theme_toggle') ?>
                        <?= $this->include('Geminus\Admin\Views\language_selector') ?>
                        <?= $this->include('Geminus\Admin\Views\user_menu') ?>
                    </div>
                </div>
            </div>
        </header>
        <!-- Main content -->
        <div class="page-wrapper">
            <div class="page-header d-print-none" aria-label="Page header">
                <div class="container-xl">
                    <?= $this->renderSection('header') ?>
                </div>
            </div>
            <div class="page-body">
                <div class="container-xl">
                    <?php if (session('alert')): ?>
                        <?= $this->include('Geminus\Admin\Views\alert') ?>
                    <?php endif; ?>
                    <?= $this->renderSection('content') ?>
                </div>
            </div>
            <footer class="footer footer-transparent d-print-none">
                <div class="container-xl"></div>
            </footer>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.6.1/dist/js/tabler.min.js" integrity="sha384-1yCHfhyU8+V33urXkAlLLpNYd9jdzXkxdafCg0+auIIEtYFnOuM/DwJQgk5sXY98" crossorigin="anonymous"></script>
    <?= $this->renderSection('javascript') ?>
    <script src="/static/js/form-submission.js"></script>
</body>

</html>
