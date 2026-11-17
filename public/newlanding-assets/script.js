// Hora publicada en el catálogo de Reny Renteria Takeover (America/Panama).
const target = new Date('2027-10-02T20:30:00-05:00').getTime();
function countdownParts(now) {
  const total = Math.max(0, Math.floor((target - now) / 1000));
  return {
    days: Math.floor(total / 86400),
    hours: Math.floor(total / 3600) % 24,
    minutes: Math.floor(total / 60) % 60,
    seconds: total % 60,
  };
}
function tick() {
  const now = Date.now();
  const parts = countdownParts(now);
  for (const [unit, value] of Object.entries(parts)) {
    document.querySelector(`[data-unit="${unit}"]`).textContent = String(value).padStart(2, '0');
  }
  if (now >= target) {
    document.getElementById('countdown-note').textContent = 'Llegó la fecha · Consulta la información del concierto';
  }
}
tick();
setInterval(tick, 1000);

// Concepts open an accessible image preview; no mock item enters the live checkout.
const merchDialog = document.getElementById('merch-dialog');
if (merchDialog && typeof merchDialog.showModal === 'function') {
  const image = merchDialog.querySelector('.merch-dialog-image');
  const close = merchDialog.querySelector('.merch-close');
  for (const link of document.querySelectorAll('[data-merch-preview]')) {
    link.setAttribute('aria-haspopup', 'dialog');
    link.setAttribute('aria-controls', 'merch-dialog');
    link.addEventListener('click', (event) => {
      // Preserve opening the full image in a new tab or downloading it.
      if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) return;
      event.preventDefault();
      image.src = link.href;
      image.alt = `Propuesta de ${link.dataset.pieceName}`;
      merchDialog.querySelector('#merch-dialog-title').textContent = link.dataset.pieceName;
      merchDialog.querySelector('.merch-dialog-detail').textContent = link.dataset.pieceDetail;
      merchDialog.showModal();
    });
  }
  close.addEventListener('click', () => merchDialog.close());
  merchDialog.addEventListener('click', (event) => {
    if (event.target !== merchDialog) return;
    const bounds = merchDialog.getBoundingClientRect();
    if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) {
      merchDialog.close();
    }
  });
}
