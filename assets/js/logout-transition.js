document.addEventListener('click', (event) => {
  const link = event.target.closest('a[href]');
  if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || link.target === '_blank') return;

  const destination = new URL(link.href, window.location.href);
  if (!destination.pathname.toLowerCase().endsWith('/logout.php')) return;

  event.preventDefault();
  if (document.body.classList.contains('is-logging-out')) return;

  document.body.classList.add('is-logging-out');
  window.setTimeout(() => window.location.assign(destination.href), 1000);
});
