<?php
$type = $type ?? 'text'; $name = $name ?? ''; $label = $label ?? ''; $value = $value ?? ''; $required = !empty($required);
?>
<label class="form-field">
    <span><?= e($label) ?><?= $required ? ' *' : '' ?></span>
    <input type="<?= e($type) ?>" name="<?= e($name) ?>" value="<?= e($value) ?>" <?= $required ? 'required' : '' ?>>
</label>
