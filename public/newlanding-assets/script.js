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
