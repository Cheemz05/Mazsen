document.querySelectorAll('.auth-form').forEach(form => {
  form.addEventListener('submit', event => {
    if (!form.reportValidity()) {
      event.preventDefault();
      return;
    }
    const submit = form.querySelector('button[type="submit"]');
    if (!submit || submit.disabled) return;
    submit.disabled = true;
    submit.classList.add('is-submitting');
    submit.setAttribute('aria-busy', 'true');
    submit.textContent = submit.dataset.loadingText || 'Please wait…';
  });
});

document.querySelectorAll('a.auth-back, a.auth-brand, .auth-switch a').forEach(link => {
  link.addEventListener('click', event => {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || link.target === '_blank') return;
    event.preventDefault();
    document.body.classList.add('auth-leaving');
    const delay = matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 180;
    window.setTimeout(() => { window.location.href = link.href; }, delay);
  });
});
