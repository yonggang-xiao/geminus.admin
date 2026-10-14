<!doctype html>
<html lang="<?= service('request')->getLocale() ?>">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= $this->renderSection('page_title') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.6.1/dist/css/tabler.min.css" integrity="sha384-tT2UAGE9hxG/p5d0iGIvZ/s8El3nWWG3tfG02i8iOY5Pbf8cZZRVmcrrVs+JG5Vw" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.35.0/dist/tabler-icons.min.css" />
    <link rel="stylesheet" href="/static/css/theme.css" />
</head>

<body>
    <div class="page page-center">
        <?= $this->renderSection('content') ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.6.1/dist/js/tabler.min.js" integrity="sha384-1yCHfhyU8+V33urXkAlLLpNYd9jdzXkxdafCg0+auIIEtYFnOuM/DwJQgk5sXY98" crossorigin="anonymous"></script>
    <?= $this->renderSection('javascript') ?>
    <script src="/static/js/form-submission.js"></script>
</body>

</html>
