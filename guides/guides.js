/* guides.js
   Shared JS for guides pages:
   - settings panel open/close + settings wheel rotation
   - dark mode toggle (persisted to localStorage)
   - keyboard shortcut (Ctrl/Cmd + S) to toggle theme
   - demo image modal helper
   - demo navbar login state handling
*/

(function () {
  // safe references (may be null on pages without these elements)
  const settingsBtn = document.getElementById('settingsBtn');
  const settingsPanel = document.getElementById('settingsPanel');
  const darkToggle = document.getElementById('darkModeToggle');
  const body = document.body;
  const cardModal = document.getElementById('cardModal');
  const cardModalImg = document.getElementById('cardModalImg');

  // Apply theme helper
  function applyTheme(isDark) {
    if (isDark) {
      body.classList.add('dark-mode');
      body.setAttribute('data-bs-theme', 'dark');
      if (darkToggle) darkToggle.checked = true;
    } else {
      body.classList.remove('dark-mode');
      body.setAttribute('data-bs-theme', 'light');
      if (darkToggle) darkToggle.checked = false;
    }
  }

  // Try to restore saved theme preference
  try {
    const saved = localStorage.getItem('cardhub_dark_mode');
    applyTheme(saved === 'true');
  } catch (err) {
    applyTheme(false);
  }

  // Toggle settings panel: adds/removes visible class and an "active" state on button
  function toggleSettingsPanel(forceState) {
    if (!settingsBtn || !settingsPanel) return;
    let newState;
    if (typeof forceState === 'boolean') {
      newState = forceState;
    } else {
      newState = !settingsPanel.classList.contains('visible');
    }

    settingsPanel.classList.toggle('visible', newState);
    settingsBtn.classList.toggle('active', newState);
    settingsBtn.setAttribute('aria-expanded', String(newState));
    settingsPanel.setAttribute('aria-hidden', String(!newState));
  }

  // Click handler to open/close the panel
  if (settingsBtn) {
    settingsBtn.addEventListener('click', function (ev) {
      ev.stopPropagation();
      toggleSettingsPanel();
    });

    // Allow keyboard toggling (Enter/Space) when the button has focus
    settingsBtn.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter' || ev.key === ' ') {
        ev.preventDefault();
        toggleSettingsPanel();
      }
    });
  }

  // Close settings panel when clicking outside
  document.addEventListener('click', function (ev) {
    if (!settingsPanel || !settingsBtn) return;
    if (!settingsPanel.contains(ev.target) && !settingsBtn.contains(ev.target)) {
      toggleSettingsPanel(false);
    }
  });

  // Dark mode toggle checkbox
  if (darkToggle) {
    darkToggle.addEventListener('change', function (ev) {
      const isDark = Boolean(ev.target.checked);
      applyTheme(isDark);
      try { localStorage.setItem('cardhub_dark_mode', String(isDark)); } catch (err) { /* ignore */ }
    });
  }

  // Keyboard shortcut: Ctrl/Cmd + S toggles theme
  document.addEventListener('keydown', function (ev) {
    if ((ev.ctrlKey || ev.metaKey) && ev.key.toLowerCase() === 's') {
      ev.preventDefault();
      const newMode = !body.classList.contains('dark-mode');
      applyTheme(newMode);
      try { localStorage.setItem('cardhub_dark_mode', String(newMode)); } catch (err) { /* ignore */ }
    }
  });

  // Demo: simple card focus modal API for showing a preview image
  if (cardModal && cardModalImg) {
    window.showCardFocus = function (cardIdOrUrl) {
      // If the argument looks like a URL use it; otherwise pass to placehold for demo
      let src = String(cardIdOrUrl || 'Preview');
      if (!/^https?:\/\//i.test(src)) {
        src = 'https://placehold.co/800x450?text=' + encodeURIComponent(src);
      }
      cardModalImg.src = src;
      cardModal.classList.add('visible');
      cardModal.setAttribute('aria-hidden', 'false');
    };

    // Close modal on click or Escape
    cardModal.addEventListener('click', function () {
      cardModal.classList.remove('visible');
      cardModal.setAttribute('aria-hidden', 'true');
    });
    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && cardModal.classList.contains('visible')) {
        cardModal.classList.remove('visible');
        cardModal.setAttribute('aria-hidden', 'true');
      }
    });
  }

  // Demo: hide login/signup if username exists in localStorage
  document.addEventListener('DOMContentLoaded', function () {
    try {
      const username = localStorage.getItem('username');
      const loginNav = document.getElementById('loginNav');
      const signupNav = document.getElementById('signupNav');
      if (username) {
        if (loginNav) loginNav.style.display = 'none';
        if (signupNav) signupNav.style.display = 'none';
      }
    } catch (err) {
      // ignore storage errors
    }
  });

  // Expose a small API for other scripts if needed
  window.cardhub = window.cardhub || {};
  window.cardhub.toggleSettingsPanel = toggleSettingsPanel;
  window.cardhub.applyTheme = applyTheme;
})();
