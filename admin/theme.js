(() => {
  'use strict';

  const storageKey = 'bkk_ui_theme';
  const root = document.documentElement;
  const savedTheme = (() => {
    try { return localStorage.getItem(storageKey); } catch (_) { return null; }
  })();
  const theme = savedTheme === 'light' || savedTheme === 'dark' ? savedTheme : 'dark';
  root.dataset.theme = theme;

  const updateControls = () => {
    const nextTheme = root.dataset.theme === 'light' ? 'dark' : 'light';
    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
      button.setAttribute('aria-label', `Gunakan tema ${nextTheme}`);
      button.setAttribute('title', `Gunakan tema ${nextTheme}`);
      const icon = button.querySelector('[data-theme-icon]');
      if (icon) icon.textContent = nextTheme === 'light' ? '☀' : '☾';
      const text = button.querySelector('[data-theme-label]');
      if (text) text.textContent = nextTheme === 'light' ? 'Mode terang' : 'Mode gelap';
    });
  };

  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-theme-toggle]');
    if (!button) return;
    const nextTheme = root.dataset.theme === 'light' ? 'dark' : 'light';
    root.dataset.theme = nextTheme;
    try { localStorage.setItem(storageKey, nextTheme); } catch (_) { /* optional */ }
    updateControls();
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', updateControls, { once: true });
  } else {
    updateControls();
  }
})();
