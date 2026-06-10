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

$companyName = setting('company_name', 'هلدینگ سبحان');
$heroSubtitle = setting('hero_subtitle', 'سامانه هلدینگ سبحان و بخش های وابسته.');
$logoPath = setting('logo_path', '');

require __DIR__ . '/views/partials/header.php';
?>
<main class="home-page">
    <section class="hero-slider" aria-label="معرفی <?= e($companyName) ?>">
        <div class="hero-static-fallback" aria-hidden="true"></div>

        <?php foreach ($items as $index => $item): ?>
            <?php $hasImage = !empty($item['image_path']); ?>
            <article
                class="hero-slide <?= $index === 0 ? 'active' : '' ?> <?= $hasImage ? 'has-image' : 'no-image' ?>"
                data-hero-slide
                <?= $hasImage ? 'style="--slide-image:url(\'' . e($item['image_path']) . '\')"' : '' ?>
            >
                <div class="hero-overlay"></div>
                <div class="hero-content">
                    <?php if ($logoPath): ?>
                        <img class="hero-logo" src="<?= e($logoPath) ?>" alt="<?= e($companyName) ?>">
                    <?php else: ?>
                        <div class="hero-logo hero-logo-text" aria-hidden="true">S</div>
                    <?php endif; ?>

                    <p class="hero-kicker"><?= e($companyName) ?></p>
                    <h1><?= e($item['title'] ?: $companyName) ?></h1>
                    <p class="hero-subtitle"><?= e($item['description'] ?: $heroSubtitle) ?></p>

                    <div class="hero-actions">
                        <?php if (!empty($item['button_text'])): ?>
                            <a class="glass-button" href="<?= e($item['button_link'] ?: '/login.php') ?>"><?= e($item['button_text']) ?></a>
                        <?php endif; ?>
                        <a class="ghost-button" href="/login.php">ورود به پنل</a>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>

        <?php if (count($items) > 1): ?>
            <div class="hero-dots" aria-label="انتخاب اسلاید">
                <?php foreach ($items as $index => $item): ?>
                    <button type="button" class="<?= $index === 0 ? 'active' : '' ?>" data-hero-dot="<?= e((string)$index) ?>" aria-label="اسلاید <?= e((string)($index + 1)) ?>"></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <a class="scroll-indicator" href="#contact" aria-label="ادامه صفحه">
            <span></span>
        </a>
    </section>

    <section class="home-contact" id="contact">
        <div>
            <span class="section-eyebrow">تماس و اطلاعات</span>
            <h2><?= e($companyName) ?></h2>
            <p><?= e($heroSubtitle) ?></p>
        </div>
        <a class="btn btn-primary" href="/login.php">ورود به سامانه</a>
    </section>
</main>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const slides = [...document.querySelectorAll('[data-hero-slide]')];
    const dots = [...document.querySelectorAll('[data-hero-dot]')];
    let currentSlide = 0;

    function showSlide(index) {
        if (!slides.length) return;
        currentSlide = ((index % slides.length) + slides.length) % slides.length;
        slides.forEach((slide, i) => slide.classList.toggle('active', i === currentSlide));
        dots.forEach((dot, i) => dot.classList.toggle('active', i === currentSlide));
    }

    dots.forEach(dot => dot.addEventListener('click', () => showSlide(Number(dot.dataset.heroDot))));
    if (slides.length > 1) setInterval(() => showSlide(currentSlide + 1), 6500);
});
</script>
<?php require __DIR__ . '/views/partials/footer.php'; ?>
