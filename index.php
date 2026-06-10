<?php
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Response.php';
try { $items = Database::fetchAll('SELECT * FROM carousel_items WHERE status="active" ORDER BY sort_order ASC, id DESC'); } catch (Throwable $e) { $items = []; }
require __DIR__ . '/views/partials/header.php';
?>
<section class="hero">
    <h1><?= e(setting('company_name', 'هلدینگ سبحان')) ?></h1>
    <p>سامانه هلدینگ سبحان و بخش های وابسته.</p>
</section>
<section class="carousel-shell" aria-label="معرفی خدمات">
    <div class="carousel-track" data-carousel-track>
        <?php foreach ($items as $item): ?>
            <article class="carousel-card">
                <?php if ($item['image_path']): ?><img src="<?= e($item['image_path']) ?>" alt="<?= e($item['title']) ?>"><?php endif; ?>
                <div class="body"><h3><?= e($item['title']) ?></h3><p><?= e($item['description']) ?></p><?php if ($item['button_text']): ?><a class="btn btn-primary" href="<?= e($item['button_link'] ?: '#') ?>"><?= e($item['button_text']) ?></a><?php endif; ?></div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<script src="/assets/js/carousel.js"></script>
<?php require __DIR__ . '/views/partials/footer.php'; ?>
