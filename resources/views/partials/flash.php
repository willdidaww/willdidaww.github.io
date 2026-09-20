<?php /** @var array $_flash */ ?>
<?php foreach (($_flash ?? []) as $f): ?>
    <div class="alert <?= e($f['type']) ?>"><?= e($f['message']) ?></div>
<?php endforeach; ?>
