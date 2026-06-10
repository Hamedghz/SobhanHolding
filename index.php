<?php
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Response.php';
try {
    $items = Database::fetchAll('SELECT * FROM carousel_items WHERE status = "active" ORDER BY sort_order ASC, id DESC');
} catch (Throwable $e) {
    $items = [];
}
if (!$items) {
    $items = [[
        'title' => setting('company_name', 'هلدینگ سبحان'),
        'description' => setting('hero_subtitle', 'سامانه هلدینگ سبحان و بخش های وابسته.'),
        'image_path' => '',
        'button_text' => 'ورود به سامانه',
        'button_link' => '/login.php',
    ]];
}
require __DIR__ . '/views/partials/header.php';
?>
<main>
    <section class="hero-slider" aria-label="معرفی هلدینگ سبحان">
        <?php foreach ($items as $index => $item): ?>
            <article class="hero-slide <?= $index === 0 ? 'active' : '' ?>" data-hero-slide style="<?= $item['image_path'] ? 'background-image:url(' . e($item['image_path']) . ')' : '' ?>">
                <div class="hero-overlay"></div>
                <div class="hero-content">
                    <p class="hero-subtitle"><?= e(setting('hero_subtitle', 'سامانه هلدینگ سبحان و بخش های وابسته.')) ?></p>
                    <h1><?= e($item['title']) ?></h1>
                    <?php if (!empty($item['description'])): ?><p class="hero-description"><?= e($item['description']) ?></p><?php endif; ?>
                    <?php if (!empty($item['button_text'])): ?><a class="btn btn-primary hero-btn" href="<?= e($item['button_link'] ?: '/login.php') ?>"><?= e($item['button_text']) ?></a><?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
        <?php if (count($items) > 1): ?>
            <div class="hero-dots" aria-label="اسلایدها"><?php foreach ($items as $index => $item): ?><button type="button" class="<?= $index === 0 ? 'active' : '' ?>" data-hero-dot="<?= e($index) ?>" aria-label="اسلاید <?= e($index + 1) ?>"></button><?php endforeach; ?></div>
        <?php endif; ?>
    </section>
    <section class="home-contact" id="contact">
        <h2><?= e(setting('company_name', 'هلدینگ سبحان')) ?></h2>
        <p><?= e(setting('footer_text', 'تمامی حقوق محفوظ است.')) ?></p>
    </section>
</main>
<script>
const slides = [...document.querySelectorAll('[data-hero-slide]')];
const dots = [...document.querySelectorAll('[data-hero-dot]')];
let currentSlide = 0;
function showSlide(index){
    currentSlide = index % slides.length;
    slides.forEach((slide, i) => slide.classList.toggle('active', i === currentSlide));
    dots.forEach((dot, i) => dot.classList.toggle('active', i === currentSlide));
}
dots.forEach(dot => dot.addEventListener('click', () => showSlide(Number(dot.dataset.heroDot))));
if (slides.length > 1) setInterval(() => showSlide(currentSlide + 1), 6500);
</script>
<?php require __DIR__ . '/views/partials/footer.php'; ?>
