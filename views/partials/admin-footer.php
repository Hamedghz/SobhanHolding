    </section>
</main>
</div>
<script src="/assets/vendor/jalalidatepicker/jalalidatepicker-1.0.0.min.js"></script>
<script src="/assets/vendor/motion/motion-12.42.2.min.js"></script>
<script src="/assets/js/app-jalali-date.js"></script>
<script src="/assets/js/app-motion.js"></script>
<script src="/assets/js/app-compact-ui.js"></script>
<script src="/assets/js/app-dashboard-preferences.js"></script>
<script src="/assets/js/sobhan-ai-status.js"></script>
<script src="/assets/js/app.js"></script>
<?php foreach (($adminExtraScripts ?? []) as $script): ?><script src="<?= e($script) ?>"></script><?php endforeach; ?>
</body>
</html>
