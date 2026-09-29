(() => {
  'use strict';
  document.documentElement.classList.add('js');
  const menu = document.querySelector('[data-nav-toggle]');
  const links = document.getElementById('nav-links');
  const closeMenu = () => { links?.classList.remove('is-open'); menu?.setAttribute('aria-expanded', 'false'); };
  menu?.addEventListener('click', () => {
    const open = links.classList.toggle('is-open');
    menu.setAttribute('aria-expanded', String(open));
  });
  links?.querySelectorAll('a').forEach(link => link.addEventListener('click', closeMenu));
  document.addEventListener('keydown', event => { if (event.key === 'Escape' && links?.classList.contains('is-open')) { closeMenu(); menu.focus(); } });

  const form = document.querySelector('[data-contact-form]');
  const message = form?.querySelector('#message');
  const count = document.querySelector('[data-message-count]');
  const updateCount = () => { if (count && message) count.textContent = `${message.value.length.toLocaleString('pt-BR')} / 4.000`; };
  message?.addEventListener('input', updateCount);
  updateCount();
  const submit = form?.querySelector('[type=submit]');
  const submitLabel = form?.querySelector('[data-submit-label]');
  form?.addEventListener('submit', () => { submit.disabled = true; submitLabel.textContent = 'Enviando mensagem…'; form.setAttribute('aria-busy', 'true'); });
  window.addEventListener('pageshow', () => { if (submit) { submit.disabled = false; submitLabel.textContent = 'Enviar mensagem'; form.removeAttribute('aria-busy'); } });
  const feedback = document.getElementById('form-feedback');
  if (feedback) feedback.focus({ preventScroll: true });

  const KEY = 'qeai_consent_v1';
  const banner = document.querySelector('[data-cookie-banner]');
  const modal = document.querySelector('[data-cookie-modal]');
  const toggles = [...document.querySelectorAll('[data-consent]')];
  let returnFocus = null;
  let memory = null;
  const read = () => {
    try {
      const value = JSON.parse(localStorage.getItem(KEY));
      if (value && value.necessary === true && Date.now() - Date.parse(value.updatedAt) < 31536000000) return value;
    } catch (_) { /* Storage can be blocked by browser privacy settings. */ }
    return memory;
  };
  const closeModal = () => {
    modal?.classList.remove('show');
    document.body.style.overflow = '';
    document.querySelectorAll('[data-consent-inert]').forEach(element => { element.inert = false; element.removeAttribute('data-consent-inert'); });
    if (returnFocus?.isConnected && returnFocus.getClientRects().length) returnFocus.focus();
  };
  const save = value => {
    value.updatedAt = new Date().toISOString();
    memory = value;
    try { localStorage.setItem(KEY, JSON.stringify(value)); } catch (_) { /* Keep the choice for the current page if storage is unavailable. */ }
    window.QEAIConsent = { can: category => category === 'necessary' || value[category] === true, value };
    closeModal();
    banner?.classList.remove('show');
    window.dispatchEvent(new CustomEvent('qeai:consent', { detail: value }));
  };
  const current = read();
  window.QEAIConsent = { can: category => category === 'necessary' || current?.[category] === true, value: current };
  if (!current) banner?.classList.add('show');
  document.querySelectorAll('[data-cookie-accept]').forEach(button => button.addEventListener('click', () => save({ necessary: true, analytics: true, marketing: true })));
  document.querySelectorAll('[data-cookie-reject]').forEach(button => button.addEventListener('click', () => save({ necessary: true, analytics: false, marketing: false })));
  document.querySelectorAll('[data-cookie-settings]').forEach(button => button.addEventListener('click', event => {
    event.preventDefault();
    if (!modal) return;
    returnFocus = button;
    const value = read() || {};
    toggles.forEach(toggle => { const on = value[toggle.dataset.consent] === true; toggle.classList.toggle('on', on); toggle.setAttribute('aria-pressed', String(on)); });
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
    [...document.body.children].filter(element => element !== modal && element.tagName !== 'SCRIPT' && !element.inert).forEach(element => { element.inert = true; element.setAttribute('data-consent-inert', ''); });
    modal.querySelector('button:not([disabled])')?.focus();
  }));
  toggles.forEach(toggle => toggle.addEventListener('click', () => { const on = toggle.classList.toggle('on'); toggle.setAttribute('aria-pressed', String(on)); }));
  document.querySelector('[data-cookie-save]')?.addEventListener('click', () => save({ necessary: true, ...Object.fromEntries(toggles.map(toggle => [toggle.dataset.consent, toggle.getAttribute('aria-pressed') === 'true'])) }));
  document.querySelector('[data-cookie-close]')?.addEventListener('click', closeModal);
  modal?.addEventListener('click', event => { if (event.target === modal) closeModal(); });
  modal?.addEventListener('keydown', event => {
    if (event.key === 'Escape') { event.preventDefault(); closeModal(); }
    if (event.key === 'Tab') {
      const focusable = [...modal.querySelectorAll('button:not([disabled]), a[href]')];
      const first = focusable[0], last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    }
  });
})();
