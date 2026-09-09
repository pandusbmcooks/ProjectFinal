/* ── Confirm dialogs ── */
document.querySelectorAll('[data-confirm]').forEach(el =>
  el.addEventListener('click', e => { if (!confirm(el.dataset.confirm)) e.preventDefault(); })
);

/* ── Stat counter animation ── */
document.querySelectorAll('.stat-value').forEach(el => {
  const raw = el.textContent.trim();
  const isRp = raw.startsWith('Rp');
  const num = parseInt(raw.replace(/[^0-9]/g, ''), 10);
  if (isNaN(num) || num === 0) return;

  let start = null;
  const duration = 900;
  const fmt = v => isRp ? 'Rp' + v.toLocaleString('id-ID') : String(v);

  const step = ts => {
    if (!start) start = ts;
    const p = Math.min((ts - start) / duration, 1);
    const eased = 1 - Math.pow(1 - p, 3);
    el.textContent = fmt(Math.floor(eased * num));
    if (p < 1) requestAnimationFrame(step);
  };

  const obs = new IntersectionObserver(entries => {
    if (entries[0].isIntersecting) { requestAnimationFrame(step); obs.disconnect(); }
  });
  obs.observe(el);
});

/* ── Stagger-in cards / rows ── */
const stagger = (selector, delay = 60) => {
  document.querySelectorAll(selector).forEach((el, i) => {
    el.style.animationDelay = `${i * delay}ms`;
    el.classList.add('stagger-in');
  });
};
stagger('.stat-card');
stagger('.iphone-card', 80);
stagger('tbody tr', 45);

/* ── Liquid-glass sidebar nav ripple ── */
document.querySelectorAll('.sidebar nav a').forEach(link => {
  link.addEventListener('mousedown', function (e) {
    const ripple = document.createElement('span');
    ripple.className = 'lg-ripple';
    const rect = this.getBoundingClientRect();
    ripple.style.left = (e.clientX - rect.left) + 'px';
    ripple.style.top = (e.clientY - rect.top) + 'px';
    this.appendChild(ripple);
    ripple.addEventListener('animationend', () => ripple.remove());
  });
});

/* ── Topbar scroll shadow ── */
const topbar = document.querySelector('.topbar');
if (topbar) {
  window.addEventListener('scroll', () => {
    topbar.classList.toggle('scrolled', window.scrollY > 20);
  }, { passive: true });
}

/* ── Ambient orbs – subtle mouse parallax ── */
const orbs = document.querySelectorAll('.ambient');
if (orbs.length) {
  document.addEventListener('mousemove', e => {
    const cx = e.clientX / window.innerWidth - 0.5;
    const cy = e.clientY / window.innerHeight - 0.5;
    orbs.forEach((o, i) => {
      const factor = i === 0 ? 28 : -18;
      o.style.transform = `translate(${cx * factor}px, ${cy * factor}px)`;
    });
  }, { passive: true });
}

/* ── Glass-header scroll opacity ── */
const glassHeader = document.querySelector('.glass-header');
if (glassHeader) {
  window.addEventListener('scroll', () => {
    const s = Math.min(window.scrollY / 80, 1);
    glassHeader.style.background =
      `rgba(255,255,255,${0.04 + s * 0.08})`;
  }, { passive: true });
}

function updateClock() {
  const now = new Date();

  const hours = String(now.getHours()).padStart(2, '0');
  const minutes = String(now.getMinutes()).padStart(2, '0');
  const seconds = String(now.getSeconds()).padStart(2, '0');
  const timeString = `${hours}:${minutes}:${seconds}`;

  const options = { weekday: 'long', day: '2-digit', month: 'short', year: 'numeric' };
  const dateString = now.toLocaleDateString('id-ID', options);

  const clockEl = document.getElementById('realtime-clock');
  const dateEl = document.getElementById('realtime-date');

  if (clockEl) clockEl.textContent = timeString;
  if (dateEl) dateEl.textContent = dateString;
}

// Jalankan saat halaman dibuka & loop per detik
document.addEventListener('DOMContentLoaded', () => {
  updateClock();
  setInterval(updateClock, 1000);
});

// Kirim waktu lokal perangkat persis saat formulir transaksi disimpan.
function localTransactionTime() {
  const now = new Date();
  const pad = value => String(value).padStart(2, '0');
  return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())} ${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;
}

document.querySelectorAll('form').forEach(form => {
  const deviceTime = form.querySelector('input[name="device_datetime"]');
  if (!deviceTime) return;

  form.addEventListener('submit', () => {
    deviceTime.value = localTransactionTime();
  });
});
