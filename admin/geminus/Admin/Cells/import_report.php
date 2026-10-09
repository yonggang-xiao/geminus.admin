<div class="card">
    <div class="card-header"><h3 class="card-title"><?= esc($title) ?></h3></div>
    <div class="card-body py-2 d-flex flex-wrap gap-3">
        <?php foreach ($counts as $result => $count): ?><span><?= esc($resultLabels[$result] ?? $result) ?>: <?= esc($count) ?></span><?php endforeach; ?>
    </div>
    <div class="table-responsive">
        <table class="table card-table table-vcenter">
            <thead><tr><th scope="col"><?= esc($rowLabel) ?></th><?php foreach ($columns as $label): ?><th scope="col"><?= esc($label) ?></th><?php endforeach; ?><th scope="col"><?= esc($resultLabel) ?></th><th scope="col"><?= esc($reasonLabel) ?></th></tr></thead>
            <tbody>
                <?php foreach ($rows as $entry): ?>
                    <tr>
                        <td><?= esc($entry['row']) ?></td>
                        <?php foreach ($columns as $field => $label): ?><td class="text-break"><?= esc($entry[$field] ?? '') ?></td><?php endforeach; ?>
                        <td><?= esc($resultLabels[$entry['result']] ?? $entry['result']) ?></td>
                        <td class="text-break">
                            <?= esc($reasonLabels[$entry['reason']] ?? $entry['reason']) ?>
                            <?php foreach ($entry['errors'] ?? [] as $error): ?><div><?= esc($error) ?></div><?php endforeach; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>