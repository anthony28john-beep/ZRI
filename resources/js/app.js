window.addEventListener('pageshow', event => {
    if (event.persisted) { window.location.reload(); return; }
    document.querySelectorAll('form[aria-busy="true"]').forEach(form => {
        form.removeAttribute('aria-busy');
        form.querySelectorAll('button[data-original-label]').forEach(button => {
            button.innerHTML = button.dataset.originalLabel;
            button.disabled = false;
        });
    });
});
document.querySelectorAll('.field-error[id]').forEach(error => {
    const name = error.id.replace(/-error$/, '');
    const field = document.getElementById(name);
    if (!field) return;
    field.setAttribute('aria-invalid', 'true');
    const descriptions = new Set((field.getAttribute('aria-describedby') || '').split(' ').filter(Boolean));
    descriptions.add(error.id);
    field.setAttribute('aria-describedby', [...descriptions].join(' '));
});
document.querySelector('[data-error-summary]')?.focus();
document.addEventListener('submit', event => {
    const form = event.target;
    if (event.defaultPrevented || !(form instanceof HTMLFormElement)) return;
    if (form.getAttribute('aria-busy') === 'true') { event.preventDefault(); return; }
    form.setAttribute('aria-busy', 'true');
    form.querySelectorAll('button[type="submit"]').forEach(button => {
        button.dataset.originalLabel = button.innerHTML;
        button.disabled = true;
        button.textContent = 'Please wait?';
    });
});
