/* =============================================
   SINKING FUNDS SYSTEM — MAIN JS
   ============================================= */

// ---- LIVE CLOCK ----
function updateClocks() {
  const now = new Date();
  const timeStr = now.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
  const dateStr = now.toLocaleDateString('en-PH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

  document.querySelectorAll('#sb-time, #top-time').forEach(el => el && (el.textContent = timeStr));
  document.querySelectorAll('#sb-date, #top-date').forEach(el => el && (el.textContent = dateStr));
}
updateClocks();
setInterval(updateClocks, 1000);

// ---- MODAL HELPERS ----
function openModal(id) {
  document.getElementById(id)?.classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeModal(id) {
  document.getElementById(id)?.classList.remove('open');
  document.body.style.overflow = '';
}

// Close modal on overlay click
document.addEventListener('click', function(e) {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('open');
    document.body.style.overflow = '';
  }
});

// ---- SIDEBAR TOGGLE (mobile) ----
function toggleSidebar() {
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebar-overlay');
  if (sidebar.classList.contains('open')) {
    closeSidebar();
  } else {
    sidebar.classList.add('open');
    overlay && overlay.classList.add('show');
    document.body.style.overflow = 'hidden';
  }
}
function closeSidebar() {
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebar-overlay');
  sidebar && sidebar.classList.remove('open');
  overlay && overlay.classList.remove('show');
  document.body.style.overflow = '';
}

// Close sidebar when a nav link is tapped on mobile
document.querySelectorAll('.nav-item').forEach(link => {
  link.addEventListener('click', () => {
    if (window.innerWidth <= 768) closeSidebar();
  });
});

// ---- FLASH MESSAGES: auto-dismiss ----
document.querySelectorAll('.alert').forEach(el => {
  setTimeout(() => el.style.transition = 'opacity .5s', 3000);
  setTimeout(() => el.style.opacity = '0', 3500);
  setTimeout(() => el.remove(), 4000);
});

// ---- TOAST NOTIFICATIONS ----
function showToast(message, type = 'success') {
  const container = document.getElementById('toast-container') || (() => {
    const div = document.createElement('div');
    div.id = 'toast-container';
    div.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:10px;';
    document.body.appendChild(div);
    return div;
  })();

  const toast = document.createElement('div');
  const colors = { success: '#4ecb8d', error: '#e74c6a', info: '#4a9df0', warning: '#f0a500' };
  const icons  = { success: '✓', error: '×', info: 'i', warning: '!' };
  toast.style.cssText = `
    background:#1a1a2e;border:1px solid ${colors[type]};color:${colors[type]};
    padding:12px 18px;border-radius:12px;font-size:13.5px;
    display:flex;align-items:center;gap:8px;box-shadow:0 4px 20px rgba(0,0,0,0.4);
    animation:slideIn .3s ease;min-width:240px;max-width:340px;
  `;
  toast.innerHTML = `<span>${icons[type]}</span><span>${message}</span>`;

  const style = document.createElement('style');
  style.textContent = '@keyframes slideIn{from{opacity:0;transform:translateX(40px)}to{opacity:1;transform:translateX(0)}}';
  document.head.appendChild(style);

  container.appendChild(toast);
  setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity .4s'; }, 3000);
  setTimeout(() => toast.remove(), 3500);
}

// ---- CONFIRM HELPERS ----
function confirmAction(message, callback) {
  if (confirm(message)) callback();
}

// ---- NUMBER FORMATTING ----
function formatPeso(amount) {
  return '₱' + parseFloat(amount).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// ---- AJAX POST HELPER ----
async function ajaxPost(url, data) {
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    body: JSON.stringify(data)
  });
  return await res.json();
}

// ---- SEARCH/FILTER TABLE ----
function filterTable(inputId, tableId) {
  const input = document.getElementById(inputId);
  const table = document.getElementById(tableId);
  if (!input || !table) return;

  input.addEventListener('input', () => {
    const q = input.value.toLowerCase();
    table.querySelectorAll('tbody tr').forEach(row => {
      row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });
}

// ---- PROGRESS BAR ANIMATION ----
document.querySelectorAll('.progress-bar[data-width]').forEach(bar => {
  const w = bar.dataset.width;
  setTimeout(() => bar.style.width = w + '%', 100);
});
