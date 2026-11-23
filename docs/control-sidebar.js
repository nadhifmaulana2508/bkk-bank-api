(() => {
  'use strict';

  const sidebar = document.querySelector('[data-control-sidebar]');
  const toggle = document.querySelector('[data-control-nav-toggle]');
  const edge = document.querySelector('[data-control-nav-edge]');
  const closeButton = document.querySelector('[data-control-nav-close]');
  const backdrop = document.querySelector('[data-control-nav-backdrop]');
  if (!sidebar || !toggle || !edge || !closeButton || !backdrop) return;

  let hoverOpened = false;
  const isOpen = () => sidebar.classList.contains('is-open');
  const isModal = () => window.matchMedia('(max-width: 900px)').matches;
  const syncModalSemantics = () => {
    if (isModal()) {
      sidebar.setAttribute('role', 'dialog');
      sidebar.setAttribute('aria-modal', 'true');
    } else {
      sidebar.removeAttribute('role');
      sidebar.removeAttribute('aria-modal');
    }
  };
  const close = (restoreFocus = true) => {
    hoverOpened = false;
    sidebar.classList.remove('is-open');
    sidebar.setAttribute('aria-hidden', 'true');
    sidebar.removeAttribute('role');
    sidebar.removeAttribute('aria-modal');
    sidebar.inert = true;
    toggle.setAttribute('aria-expanded', 'false');
    backdrop.hidden = true;
    document.body.classList.remove('docs-control-nav-open');
    if (restoreFocus) toggle.focus();
  };
  const open = (focus = true, fromHover = false) => {
    hoverOpened = fromHover;
    syncModalSemantics();
    sidebar.inert = false;
    sidebar.setAttribute('aria-hidden', 'false');
    sidebar.classList.add('is-open');
    toggle.setAttribute('aria-expanded', 'true');
    backdrop.hidden = false;
    document.body.classList.add('docs-control-nav-open');
    if (focus) closeButton.focus();
  };

  toggle.addEventListener('click', () => isOpen() ? close() : open());
  edge.addEventListener('pointerenter', (event) => {
    if (event.pointerType !== 'mouse' || isOpen()) return;
    open(false, true);
  });
  edge.addEventListener('focus', () => {
    hoverOpened = false;
    if (!isOpen()) open();
  });
  edge.addEventListener('click', () => {
    if (isOpen()) hoverOpened = false;
    else open();
  });
  sidebar.addEventListener('pointerleave', (event) => {
    if (event.pointerType === 'mouse' && hoverOpened && !sidebar.contains(document.activeElement)) close(false);
  });
  closeButton.addEventListener('click', () => close());
  backdrop.addEventListener('click', () => close());
  document.addEventListener('keydown', (event) => {
    if (!isOpen()) return;
    if (event.key === 'Escape') {
      event.preventDefault();
      close();
      return;
    }
    if (event.key !== 'Tab' || !isModal()) return;
    const focusable = [...sidebar.querySelectorAll('a[href], button:not([disabled])')];
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (!sidebar.contains(document.activeElement)) {
      event.preventDefault();
      (event.shiftKey ? last : first)?.focus();
      return;
    }
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last?.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first?.focus();
    }
  });
  window.addEventListener('resize', () => {
    if (isOpen()) syncModalSemantics();
  });
})();
