(function () {
  const slider = document.querySelector('[data-hero-slider]');
  if (!slider) return;

  const slides = [...slider.querySelectorAll('[data-hero-slide]')];
  const previousButton = slider.querySelector('[data-hero-prev]');
  const nextButton = slider.querySelector('[data-hero-next]');
  const currentLabel = slider.querySelector('[data-hero-current]');
  const progress = slider.querySelector('[data-hero-progress]');
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const interval = 7000;
  let current = 0;
  let timer = null;

  function toPersianNumber(value) {
    return String(value).replace(/\d/g, digit => '۰۱۲۳۴۵۶۷۸۹'[Number(digit)]);
  }

  function restartProgress() {
    if (!progress || reducedMotion.matches) return;
    progress.style.animation = 'none';
    void progress.offsetWidth;
    progress.style.animation = `heroProgress ${interval}ms linear forwards`;
  }

  function showSlide(index) {
    if (!slides.length) return;
    current = (index + slides.length) % slides.length;
    slides.forEach((slide, slideIndex) => {
      const active = slideIndex === current;
      slide.classList.toggle('active', active);
      slide.setAttribute('aria-hidden', active ? 'false' : 'true');
    });
    if (currentLabel) currentLabel.textContent = toPersianNumber(String(current + 1).padStart(2, '0'));
    restartProgress();
  }

  function stop() {
    if (timer) window.clearInterval(timer);
    timer = null;
    if (progress) progress.style.animationPlayState = 'paused';
  }

  function start() {
    stop();
    if (slides.length < 2 || reducedMotion.matches) return;
    if (progress) progress.style.animationPlayState = 'running';
    timer = window.setInterval(() => showSlide(current + 1), interval);
  }

  function navigate(offset) {
    showSlide(current + offset);
    start();
  }

  previousButton?.addEventListener('click', () => navigate(-1));
  nextButton?.addEventListener('click', () => navigate(1));
  slider.addEventListener('mouseenter', stop);
  slider.addEventListener('mouseleave', start);
  slider.addEventListener('focusin', stop);
  slider.addEventListener('focusout', event => {
    if (!slider.contains(event.relatedTarget)) start();
  });
  slider.addEventListener('keydown', event => {
    if (event.key === 'ArrowRight') navigate(-1);
    if (event.key === 'ArrowLeft') navigate(1);
  });
  reducedMotion.addEventListener?.('change', start);

  showSlide(0);
  start();
})();
