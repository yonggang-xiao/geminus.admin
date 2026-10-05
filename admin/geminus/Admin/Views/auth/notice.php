<?php $errors = session('error') ?? session('errors'); ?>
<?php if ($errors !== null): ?>
    <div class="alert alert-danger" role="alert">
        <?php foreach ((array) $errors as $error): ?>
            <div><?= esc($error) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php if (session('message') !== null): ?>
    <div class="alert alert-success" role="alert"><?= esc(session('message')) ?></div>
<?php endif; ?>