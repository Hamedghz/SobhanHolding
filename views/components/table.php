<?php /* Reusable lightweight table wrapper: set $headers and $rows before include. */ ?>
<div class="table-wrap"><table><thead><tr><?php foreach ($headers ?? [] as $h): ?><th><?= e($h) ?></th><?php endforeach; ?></tr></thead><tbody><?= $rows ?? '' ?></tbody></table></div>
