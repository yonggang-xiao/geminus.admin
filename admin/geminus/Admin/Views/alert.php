<?php $alert = session('alert'); ?>
<div class="alert alert-<?= esc($alert['type'], 'attr') ?> alert-dismissible" role="alert">
	<div><?= esc($alert['message']) ?></div>
	<?php if (isset($alert['detail'])): ?>
		<code class="user-select-all text-break d-block mt-2"><?= esc($alert['detail']) ?></code>
	<?php endif; ?>
	<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= esc(lang('Admin.close'), 'attr') ?>"></button>
</div>
