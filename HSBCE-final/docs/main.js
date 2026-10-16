let selectedCard = 'Platinum';
let currentStep = 1;

/* ── Inject Toast Styles ──────────────────────── */
(function injectToastStyles() {
  const style = document.createElement('style');
  style.textContent = `
    .toast-container { position: fixed; top: 24px; left: 50%; transform: translateX(-50%); z-index: 9999; display: flex; flex-direction: column; align-items: center; gap: 12px; pointer-events: none; width: 100%; max-width: 420px; padding: 0 16px; box-sizing: border-box; }
    @media (max-width: 640px) { .toast-container { top: 16px; max-width: 100%; } }
    .toast { display: flex; align-items: flex-start; gap: 12px; padding: 16px 20px; width: 100%; border-radius: 16px; font-family: 'Figtree', system-ui, sans-serif; background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(20px) saturate(180%); -webkit-backdrop-filter: blur(20px) saturate(180%); border: 1px solid rgba(255, 255, 255, 0.3); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 10px 25px -5px rgba(0, 0, 0, 0.15), 0 0 0 1px rgba(0, 0, 0, 0.05); pointer-events: auto; opacity: 0; transform: translateY(-100%) scale(0.95); transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1); position: relative; }
    .toast.show { opacity: 1; transform: translateY(0) scale(1); }
    .toast.hide { opacity: 0; transform: translateY(-100%) scale(0.95); }
    .toast-icon { flex-shrink: 0; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
    .toast-icon svg { width: 14px; height: 14px; stroke-width: 2.5; }
    .toast--error .toast-icon { background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%); color: #dc2626; }
    .toast--warning .toast-icon { background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%); color: #d97706; }
    .toast--success .toast-icon { background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); color: #16a34a; }
    .toast--info .toast-icon { background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); color: #2563eb; }
    .toast-content { flex: 1; min-width: 0; }
    .toast-title { font-size: 0.9375rem; font-weight: 600; color: #111827; margin: 0 0 2px 0; line-height: 1.4; }
    .toast-message { font-size: 0.875rem; font-weight: 400; color: #6b7280; margin: 0; line-height: 1.5; }
    .toast-close { flex-shrink: 0; width: 28px; height: 28px; border: none; background: transparent; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #9ca3af; transition: all 0.2s ease; margin: -4px -8px -4px 0; }
    .toast-close:hover { background: rgba(0, 0, 0, 0.05); color: #6b7280; }
    .toast-close svg { width: 16px; height: 16px; }
    .toast-progress { position: absolute; bottom: 0; left: 0; right: 0; height: 3px; background: rgba(0, 0, 0, 0.05); border-radius: 0 0 16px 16px; overflow: hidden; }
    .toast-progress-bar { height: 100%; border-radius: 0 0 0 16px; transition: width 0.1s linear; }
    .toast--error .toast-progress-bar { background: linear-gradient(90deg, #dc2626, #ef4444); }
    .toast--warning .toast-progress-bar { background: linear-gradient(90deg, #d97706, #f59e0b); }
    .toast--success .toast-progress-bar { background: linear-gradient(90deg, #16a34a, #22c55e); }
    .toast--info .toast-progress-bar { background: linear-gradient(90deg, #2563eb, #3b82f6); }
  `;
  document.head.appendChild(style);
})();

/* ── Toast Container ─────────────────────────── */
function getToastContainer() {
  let c = document.getElementById('toast-container');
  if (!c) {
    c = document.createElement('div');
    c.id = 'toast-container';
    c.className = 'toast-container';
    c.setAttribute('aria-live', 'polite');
    c.setAttribute('aria-atomic', 'true');
    document.body.appendChild(c);
  }
  return c;
}

const toastIcons = {
  error:   `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>`,
  warning: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`,
  success: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>`,
  info:    `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>`,
};

const toastTitles = { error: 'Error', warning: 'Atención', success: 'Éxito', info: 'Información' };

/* ── Show Toast ──────────────────────────────── */
function showToast(message, type = 'error', duration = 4000) {
  const container = getToastContainer();
  const existing = container.querySelectorAll('.toast');
  if (existing.length >= 3) hideToast(existing[0]);

  const toast = document.createElement('div');
  toast.className = `toast toast--${type}`;
  toast.setAttribute('role', 'alert');

  const iconDiv = document.createElement('div');
  iconDiv.className = 'toast-icon';
  iconDiv.innerHTML = toastIcons[type] || toastIcons.error;

  const contentDiv = document.createElement('div');
  contentDiv.className = 'toast-content';
  const titleP = document.createElement('p');
  titleP.className = 'toast-title';
  titleP.textContent = toastTitles[type] || 'Aviso';
  const messageP = document.createElement('p');
  messageP.className = 'toast-message';
  messageP.textContent = message;
  contentDiv.appendChild(titleP);
  contentDiv.appendChild(messageP);

  const closeBtn = document.createElement('button');
  closeBtn.className = 'toast-close';
  closeBtn.setAttribute('aria-label', 'Cerrar notificación');
  closeBtn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>`;

  const progressDiv = document.createElement('div');
  progressDiv.className = 'toast-progress';
  const progressBar = document.createElement('div');
  progressBar.className = 'toast-progress-bar';
  progressBar.style.width = '100%';
  progressDiv.appendChild(progressBar);

  toast.appendChild(iconDiv);
  toast.appendChild(contentDiv);
  toast.appendChild(closeBtn);
  toast.appendChild(progressDiv);
  container.appendChild(toast);

  let startTime = Date.now();
  let remainingTime = duration;
  let isPaused = false;

  function updateProgress() {
    if (isPaused) return;
    const elapsed = Date.now() - startTime;
    const progress = Math.max(0, 100 - (elapsed / duration) * 100);
    progressBar.style.width = `${progress}%`;
    if (progress > 0) requestAnimationFrame(updateProgress);
  }

  toast.addEventListener('mouseenter', () => {
    isPaused = true;
    remainingTime = Math.max(0, duration - (Date.now() - startTime));
    clearTimeout(toast._timer);
  });
  toast.addEventListener('mouseleave', () => {
    isPaused = false;
    startTime = Date.now() - (duration - remainingTime);
    updateProgress();
    toast._timer = setTimeout(() => hideToast(toast), remainingTime);
  });
  closeBtn.addEventListener('click', () => hideToast(toast));

  requestAnimationFrame(() => {
    toast.classList.add('show');
    updateProgress();
  });

  toast._timer = setTimeout(() => hideToast(toast), duration);
  return toast;
}

function hideToast(toast) {
  if (!toast || toast._hiding) return;
  toast._hiding = true;
  clearTimeout(toast._timer);
  toast.classList.remove('show');
  toast.classList.add('hide');
  setTimeout(() => toast.remove(), 400);
}

/* ── Stepper ─────────────────────────────────── */
function goTo(step) {
  if (step > currentStep && !validateStep(currentStep)) return;
  document.getElementById('step-' + currentStep).classList.remove('active');
  document.getElementById('step-' + step).classList.add('active');

  for (let i = 1; i <= 3; i++) {
    const dot = document.getElementById('dot-' + i);
    dot.classList.remove('active', 'done');
    if (i < step)  dot.classList.add('done');
    if (i === step) dot.classList.add('active');
  }
  for (let i = 1; i <= 2; i++) {
    document.getElementById('line-' + i).classList.toggle('done', i < step);
  }

  currentStep = step;
  document.getElementById('stepper').setAttribute('aria-valuenow', step);
}

/* ── Validación cliente ──────────────────────── */
function validateStep(step) {
  if (step === 1) {
    const fn = document.getElementById('firstName').value.trim();
    const ln = document.getElementById('lastName').value.trim();
    const em = document.getElementById('email').value.trim();
    const ph = document.getElementById('phone').value.trim();

    const nameRegex = /^[\p{L}\p{M}\s\-'\.]{2,100}$/u;
    if (!nameRegex.test(fn)) { showToast('Nombre inválido (solo letras, mín. 2 caracteres).', 'warning'); return false; }
    if (!nameRegex.test(ln)) { showToast('Apellidos inválidos (solo letras, mín. 2 caracteres).', 'warning'); return false; }

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
    if (!emailRegex.test(em) || em.length > 120) { showToast('Email inválido.', 'warning'); return false; }

    if (!/^\d{10}$/.test(ph)) { showToast('Teléfono debe ser exactamente 10 dígitos.', 'warning'); return false; }
  }
  if (step === 2) {
    const oc = document.getElementById('ocupacion').value;
    const ig = document.getElementById('ingreso').value;
    if (!oc) { showToast('Selecciona tu ocupación.', 'warning'); return false; }
    if (!ig) { showToast('Selecciona tu ingreso mensual.', 'warning'); return false; }
  }
  return true;
}

/* ── Card selector ───────────────────────────── */
function selectCard(el) {
  document.querySelectorAll('.card-option').forEach(o => {
    o.classList.remove('selected');
    o.setAttribute('aria-checked', 'false');
  });
  el.classList.add('selected');
  el.setAttribute('aria-checked', 'true');
  selectedCard = el.dataset.card;
}

/* ── Form submit ─────────────────────────────── */
async function handleFormSubmit(e) {
  e.preventDefault();
  if (!validateStep(3)) return;
  if (!document.getElementById('terms').checked) {
    showToast('Confirma que usarás datos ficticios.', 'warning');
    return;
  }

  const form = document.getElementById('hsbc-form');
  const button = document.getElementById('btn-submit');
  button.disabled = true;
  button.textContent = 'Preparando demostración…';

  // Esta copia pública solo simula el flujo localmente. No realiza solicitudes de red.
  window.setTimeout(() => {
    form.reset();
    form.style.display = 'none';
    document.getElementById('stepper').style.display = 'none';
    document.getElementById('success-state').classList.add('show');
    showToast('Demo completada. No se enviaron ni guardaron datos.', 'success');
  }, 250);
}

/* ============================================
   INIT
============================================ */
function init() {

  document.querySelectorAll('[data-goto]').forEach(btn => {
    btn.addEventListener('click', () => {
      const step = parseInt(btn.getAttribute('data-goto'), 10);
      if (!isNaN(step)) goTo(step);
    });
  });

  document.querySelectorAll('.card-option').forEach(el => {
    el.addEventListener('click', () => selectCard(el));
    el.addEventListener('keydown', e => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        selectCard(el);
      }
    });
  });

  document.querySelectorAll('[data-noop]').forEach(link => {
    link.addEventListener('click', e => e.preventDefault());
  });

  const form = document.getElementById('hsbc-form');
  if (form) form.addEventListener('submit', handleFormSubmit);

}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
