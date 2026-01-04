<!doctype html>
<html lang="<?= service('request')->getLocale() ?>">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= esc($page_title) ?></title>
    <script src="/static/js/tabler-theme.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.4.0/dist/css/tabler.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.35.0/dist/tabler-icons.min.css" />
</head>

<body>
    <div class="page">
        <!-- Sidebar -->
        <aside class="navbar navbar-vertical navbar-expand-lg" data-bs-theme="dark">
            <div class="container-fluid">
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu" aria-controls="sidebar-menu" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <h1 class="navbar-brand navbar-brand-autodark">
                    <a href="#">
                        <img src="/static/logo-white.svg" width="110" height="32" alt="Admin" class="navbar-brand-image" />
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
        <header class="navbar navbar-expand-lg sticky-top d-none d-lg-flex">
            <div class="container-xl">
                <div class="row flex-fill align-items-center">
                    <div class="col">
                        <h2 class="page-title"><?= esc($page_title) ?></h2>
                    </div>
                    <div class="col d-flex">
                        <div class="navbar-nav ms-auto gap-2 align-items-center">
                            <?= $this->include('Geminus\Admin\Views\theme_toggle') ?>
                            <?= $this->include('Geminus\Admin\Views\language_selector') ?>
                            <?= $this->include('Geminus\Admin\Views\user_menu') ?>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        <!-- Main content -->
        <div class="page-wrapper">
        <?php if (session()->getFlashdata('alert')) : ?>
            <div class="page-header d-print-none">
                <div class="container-xl">
                    <?= $this->include('Geminus\Admin\Views\alert') ?>
                </div>
            </div>
        <?php endif ?>
            <div class="page-body">
                <div class="container-xl">
                    <?= $this->renderSection('content') ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.4.0/dist/js/tabler.min.js"></script>
    <?= $this->renderSection('javascript') ?>
</body>

</html>
