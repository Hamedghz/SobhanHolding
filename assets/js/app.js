document.addEventListener('click', function (event) {
  const toggle = event.target.closest('[data-sidebar-toggle]');
  if (toggle) document.getElementById('adminSidebar')?.classList.toggle('open');
});
