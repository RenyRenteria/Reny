(() => {
  const dialog = document.getElementById('dragon-dialog');
  if (!dialog || typeof dialog.showModal !== 'function') return;
  let opener;
  for (const button of document.querySelectorAll('.dragon-open')) {
    button.addEventListener('click', () => {
      opener = button;
      dialog.showModal();
    });
  }
  dialog.querySelector('.merch-close').addEventListener('click', () => dialog.close());
  dialog.addEventListener('close', () => opener?.focus());
  dialog.addEventListener('click', (event) => {
    if (event.target !== dialog) return;
    const bounds = dialog.getBoundingClientRect();
    if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) dialog.close();
  });
  const photo = dialog.querySelector('.dragon-photo');
  for (const thumbnail of dialog.querySelectorAll('[data-photo]')) {
    thumbnail.addEventListener('click', () => {
      photo.src = thumbnail.querySelector('img').src;
      photo.alt = `Dragon Wing Sneaker: ${thumbnail.getAttribute('aria-label')}`;
      for (const item of dialog.querySelectorAll('[data-photo]')) item.setAttribute('aria-pressed', String(item === thumbnail));
    });
  }
  for (const size of dialog.querySelectorAll('[name="dragon-size"]')) {
    size.addEventListener('change', () => {
      dialog.querySelector('.dragon-choice').textContent = `Talla ${size.value} seleccionada. El pago de prueba sigue pendiente.`;
    });
  }
})();
