    </section>
</main>
</div>
<script src="/assets/js/app.js"></script>
<script src="/assets/js/ui-modernization.js"></script>
<?php foreach (($adminExtraScripts ?? []) as $script): ?><script src="<?= e($script) ?>"></script><?php endforeach; ?>
</body>
</html>
