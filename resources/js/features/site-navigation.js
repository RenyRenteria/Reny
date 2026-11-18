// Delegated events survive the persistent music player's page replacements.
document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    const disclosure = event.target.closest('[data-site-disclosure][open]');
    if (!disclosure) return;
    event.preventDefault();
    disclosure.open = false;
    disclosure.querySelector('summary')?.focus();
});

document.addEventListener('click', (event) => {
    const link = event.target.closest('[data-site-disclosure] a');
    if (!link) return;
    const disclosure = link.closest('[data-site-disclosure]');
    disclosure.open = false;
    disclosure.querySelector('summary')?.focus({ preventScroll: true });
});

document.addEventListener('pointerdown', (event) => {
    document.querySelectorAll('[data-site-disclosure][open]').forEach((disclosure) => {
        if (!disclosure.contains(event.target)) disclosure.open = false;
    });
});

document.addEventListener('focusout', (event) => {
    const disclosure = event.target.closest('[data-site-disclosure]');
    if (disclosure && event.relatedTarget && !disclosure.contains(event.relatedTarget)) disclosure.open = false;
});

window.matchMedia('(min-width: 901px)').addEventListener('change', (event) => {
    if (event.matches) document.querySelector('.site-mobile-nav')?.removeAttribute('open');
});
