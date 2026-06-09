<?php if (!empty($message)): ?>
<div class="alert alert-<?= e($type ?? 'success') ?>"><?= e($message) ?></div>
<?php endif; ?>
