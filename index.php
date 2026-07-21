<?php
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Response.php';
require_once __DIR__ . '/core/CarouselModule.php';

try {
    $items = CarouselModule::publicItems();
} catch (Throwable $e) {
    $items = [];
}

if (!$items) {
    $items = [[
        'title' => setting('company_name', 'هلدینگ سبحان'),
        'description' => setting('hero_subtitle', 'سامانه هلدینگ سبحان و بخش های وابسته.'),
        'image_path' => '',
        'mobile_image_path' => '',
        'alt_text' => setting('company_name', 'هلدینگ سبحان'),
        'button_text' => '',
        'button_link' => '',
    ]];
}

$companyName = setting('company_name', 'هلدینگ سبحان');
$heroSubtitle = setting('hero_subtitle', 'سامانه هلدینگ سبحان و بخش های وابسته.');
$logoPath = setting('logo_path', '');

require __DIR__ . '/views/partials/header.php';
?>
<main class="home-page">
    <section class="hero-slider" data-hero-slider aria-roledescription="carousel" aria-label="معرفی <?= e($companyName) ?>">
        <div class="hero-static-fallback" aria-hidden="true"></div>

        <?php foreach ($items as $index => $item): ?>
            <?php $hasImage = !empty($item['image_path']); ?>
            <article
                class="hero-slide <?= $index === 0 ? 'active' : '' ?> <?= $hasImage ? 'has-image' : 'no-image' ?>"
                data-hero-slide
                role="group"
                aria-roledescription="اسلاید"
                aria-label="<?= e((string)($index + 1)) ?> از <?= e((string)count($items)) ?>"
                aria-hidden="<?= $index === 0 ? 'false' : 'true' ?>"
                <?= $hasImage ? 'style="--slide-image:url(\'' . e($item['image_path']) . '\')"' : '' ?>
            >
                <?php if ($hasImage): ?><picture class="hero-slide-picture"><source media="(max-width: 700px)" srcset="<?= e($item['mobile_image_path'] ?: $item['image_path']) ?>"><img src="<?= e($item['image_path']) ?>" alt="<?= e($item['alt_text'] ?? '') ?>" <?= $index === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>></picture><?php endif; ?>
                <div class="hero-overlay"></div>
                <div class="hero-layout">
                    <div class="hero-content">
                        <div class="hero-brand-mark">
                            <?php if ($logoPath): ?>
                                <img class="hero-logo" src="<?= e($logoPath) ?>" alt="">
                            <?php else: ?>
                                <div class="hero-logo hero-logo-text" aria-hidden="true">س</div>
                            <?php endif; ?>
                            <p class="hero-kicker"><?= e($companyName) ?></p>
                        </div>
                        <h1><?= e($item['title'] ?: $companyName) ?></h1>
                        <p class="hero-subtitle"><?= e($item['description'] ?: $heroSubtitle) ?></p>

                        <div class="hero-actions">
                            <?php if (!empty($item['button_text'])): ?>
                                <a class="glass-button" href="<?= e($item['button_link'] ?: '/login.php') ?>" target="<?= e($item['link_target'] ?? '_self') ?>"<?= ($item['link_target'] ?? '') === '_blank' ? ' rel="noopener noreferrer"' : '' ?>><?= e($item['button_text']) ?><span aria-hidden="true">←</span></a>
                            <?php endif; ?>
                            <a class="ghost-button" href="/login.php">ورود به پنل</a>
                        </div>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>

        <?php if (count($items) > 1): ?>
            <div class="hero-controls">
                <div class="hero-pagination" aria-live="polite">
                    <span data-hero-current>۰۱</span>
                    <span class="hero-pagination-line"></span>
                    <span><?= e(str_pad((string)count($items), 2, '0', STR_PAD_LEFT)) ?></span>
                </div>
                <div class="hero-progress" aria-hidden="true"><span data-hero-progress></span></div>
                <div class="hero-nav" aria-label="کنترل اسلایدر">
                    <button type="button" data-hero-prev aria-label="اسلاید قبلی">→</button>
                    <button type="button" data-hero-next aria-label="اسلاید بعدی">←</button>
                </div>
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
<script src="/assets/js/carousel.js"></script>
<?php require __DIR__ . '/views/partials/footer.php'; ?>
