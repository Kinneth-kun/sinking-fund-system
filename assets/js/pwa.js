(function () {
  const state = {
    deferredPrompt: null,
    installButton: null,
    offlineBadge: null
  };

  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  function updateOfflineBadge() {
    if (!state.offlineBadge) return;
    state.offlineBadge.hidden = navigator.onLine;
  }

  function createOfflineBadge() {
    const badge = document.createElement('div');
    badge.className = 'offline-badge';
    badge.textContent = 'Offline mode';
    badge.hidden = navigator.onLine;
    document.body.appendChild(badge);
    state.offlineBadge = badge;
  }

  function createInstallButton() {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'pwa-install-btn';
    button.textContent = 'Install app';
    button.hidden = true;
    button.addEventListener('click', async () => {
      if (!state.deferredPrompt) return;
      state.deferredPrompt.prompt();
      await state.deferredPrompt.userChoice;
      state.deferredPrompt = null;
      button.hidden = true;
    });
    document.body.appendChild(button);
    state.installButton = button;
  }

  function isStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
  }

  ready(() => {
    createOfflineBadge();
    createInstallButton();
    updateOfflineBadge();

    window.addEventListener('online', updateOfflineBadge);
    window.addEventListener('offline', updateOfflineBadge);

    window.addEventListener('beforeinstallprompt', event => {
      event.preventDefault();
      state.deferredPrompt = event;
      if (state.installButton && !isStandalone()) {
        state.installButton.hidden = false;
      }
    });

    window.addEventListener('appinstalled', () => {
      state.deferredPrompt = null;
      if (state.installButton) state.installButton.hidden = true;
    });
  });

  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/sw.js').catch(() => undefined);
    });
  }
})();
