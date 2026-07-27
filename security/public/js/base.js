(() => {
  const root = document.documentElement;
  const themeStorageKey = 'lex-theme';
  const themeToggle = document.getElementById('themeToggle');
  const moonIcon = '&#9681;';
  const sunIcon = '&#9728;';

  const getPreferredTheme = () => {
    const storedTheme = localStorage.getItem(themeStorageKey);
    if (storedTheme === 'dark' || storedTheme === 'light') {
      return storedTheme;
    }
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  };

  const applyTheme = (theme) => {
    const normalizedTheme = theme === 'dark' ? 'dark' : 'light';
    root.dataset.theme = normalizedTheme;
    localStorage.setItem(themeStorageKey, normalizedTheme);

    if (themeToggle) {
      const isDark = normalizedTheme === 'dark';
      themeToggle.innerHTML = isDark ? sunIcon : moonIcon;
      themeToggle.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');
      themeToggle.setAttribute('title', isDark ? 'Switch to light mode' : 'Switch to dark mode');
      themeToggle.setAttribute('aria-pressed', String(isDark));
    }
  };

  applyTheme(getPreferredTheme());

  const sidebar = document.getElementById('sidebar');
  const toggle = document.getElementById('sidebarToggle');
  if (toggle && sidebar) {
    toggle.addEventListener('click', () => {
      sidebar.classList.toggle('open');
    });
  }

  if (themeToggle) {
    themeToggle.addEventListener('click', () => {
      const next = root.dataset.theme === 'dark' ? 'light' : 'dark';
      applyTheme(next);
    });
  }

  if (document.querySelector('[data-appointment-board]') || document.querySelector('[data-system-settings-page]')) {
    document.body.classList.add('toast-upper');
  }

  const eyeSvg = `
    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
      <path d="M12 5c5.5 0 9.9 3.4 11.5 7-1.6 3.6-6 7-11.5 7S2.1 15.6.5 12C2.1 8.4 6.5 5 12 5Zm0 2.2A4.8 4.8 0 1 0 12 19a4.8 4.8 0 0 0 0-9.6Z"/>
    </svg>`;
  const eyeOffSvg = `
    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
      <path d="M3 3 21 21"/>
      <path d="M2.5 12s3.6-7 9.5-7c2.1 0 3.9.6 5.3 1.5L18.9 8A18.7 18.7 0 0 1 22 12c-1.6 3.6-6 7-10 7-1.7 0-3.3-.4-4.8-1.1l1.9-1.9A4.8 4.8 0 0 0 12 19a4.8 4.8 0 1 0-4.8-4.8c0 .7.1 1.3.4 1.9L5.8 17C3.4 15.2 2.5 12 2.5 12Zm9.5-4.8a4.8 4.8 0 0 1 4.8 4.8c0 .4 0 .7-.1 1.1l-2-2A2.8 2.8 0 0 0 12 9.2c-.4 0-.8.1-1.2.2L9 9a4.7 4.7 0 0 1 3-1.8Z"/>
    </svg>`;

  const syncPasswordToggleGroup = (group) => {
    const input = group.querySelector('input[type="password"], input[type="text"]');
    const button = group.querySelector('[data-password-toggle-button]');
    if (!input || !button) return;

    const isHidden = input.type === 'password';
    button.type = 'button';
    button.innerHTML = isHidden ? eyeSvg : eyeOffSvg;
    button.setAttribute('aria-pressed', String(!isHidden));
    button.setAttribute('aria-label', isHidden ? 'Show password' : 'Hide password');
    button.title = isHidden ? 'Show password' : 'Hide password';
  };

  const initPasswordToggleGroup = (group) => {
    if (!(group instanceof HTMLElement)) return;
    if (group.dataset.passwordToggleReady === 'true') {
      syncPasswordToggleGroup(group);
      return;
    }

    const input = group.querySelector('input[type="password"], input[type="text"]');
    const button = group.querySelector('[data-password-toggle-button]');
    if (!input || !button) return;

    group.dataset.passwordToggleReady = 'true';
    syncPasswordToggleGroup(group);
  };

  document.querySelectorAll('[data-password-toggle]').forEach(initPasswordToggleGroup);

  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-password-toggle-button]');
    if (!(button instanceof HTMLElement)) return;

    const group = button.closest('[data-password-toggle]');
    if (!(group instanceof HTMLElement)) return;

    const input = group.querySelector('input[type="password"], input[type="text"]');
    if (!(input instanceof HTMLInputElement)) return;

    event.preventDefault();
    input.type = input.type === 'password' ? 'text' : 'password';
    syncPasswordToggleGroup(group);
    input.focus();
  });

  const passwordToggleObserver = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      mutation.addedNodes.forEach((node) => {
        if (!(node instanceof HTMLElement)) return;
        if (node.matches('[data-password-toggle]')) {
          initPasswordToggleGroup(node);
        }
        node.querySelectorAll?.('[data-password-toggle]').forEach(initPasswordToggleGroup);
      });
    });
  });

  passwordToggleObserver.observe(document.body, {
    childList: true,
    subtree: true,
  });

  document.querySelectorAll('.toast').forEach((toast) => {
    const isError = toast.classList.contains('toast-error');
    const dismissAfter = isError ? 1400 : 1000;
    const removeAfter = 180;
    window.setTimeout(() => {
      toast.classList.add('is-dismissing');
      window.setTimeout(() => toast.remove(), removeAfter);
    }, dismissAfter);
  });

  document.querySelectorAll('form').forEach((form) => {
    if (form.hasAttribute('data-no-loading')) {
      return;
    }
    const submit = form.querySelector('button[type="submit"]');
    form.addEventListener('submit', () => {
      if (submit) {
        submit.dataset.originalText = submit.textContent;
        submit.textContent = 'Working...';
        submit.disabled = true;
      }
      form.classList.add('loading');
    });
  });

  document.querySelectorAll('[data-confirm]').forEach((el) => {
    el.addEventListener('click', (event) => {
      const message = el.getAttribute('data-confirm') || 'Are you sure?';
      const requiredText = (el.getAttribute('data-confirm-text') || '').trim();
      if (requiredText) {
        const entered = window.prompt(`${message}\n\nType ${requiredText} to continue:`, '');
        if ((entered || '').trim() !== requiredText) {
          event.preventDefault();
          return;
        }
        return;
      }
      if (!confirm(message)) {
        event.preventDefault();
      }
    });
  });

  const clientNoteModal = document.querySelector('[data-client-note-modal]');
  if (clientNoteModal instanceof HTMLElement) {
    const noteText = clientNoteModal.querySelector('[data-client-note-text]');
    const closeButtons = clientNoteModal.querySelectorAll('[data-client-note-close]');
    let lastNoteTrigger = null;

    const openClientNoteModal = (message, trigger) => {
      if (noteText) {
        noteText.textContent = message || 'No notes were added for this appointment.';
      }
      lastNoteTrigger = trigger || null;
      clientNoteModal.classList.add('is-open');
      clientNoteModal.setAttribute('aria-hidden', 'false');
      document.body.classList.add('has-modal-open');
      const closeButton = clientNoteModal.querySelector('[data-client-note-close]');
      closeButton?.focus();
    };

    const closeClientNoteModal = () => {
      clientNoteModal.classList.remove('is-open');
      clientNoteModal.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('has-modal-open');
      lastNoteTrigger?.focus();
    };

    document.addEventListener('click', (event) => {
      const trigger = event.target.closest('[data-client-note-open]');
      if (!(trigger instanceof HTMLElement)) return;
      event.preventDefault();
      openClientNoteModal(trigger.dataset.note || '', trigger);
    });

    closeButtons.forEach((button) => {
      button.addEventListener('click', closeClientNoteModal);
    });

    clientNoteModal.addEventListener('click', (event) => {
      if (event.target === clientNoteModal) {
        closeClientNoteModal();
      }
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && clientNoteModal.classList.contains('is-open')) {
        closeClientNoteModal();
      }
    });
  }

  const apiBase = document.body.dataset.apiBase;
  if (apiBase) {
    fetch(`${apiBase.replace(/\/$/, '')}/health`, { credentials: 'omit' })
      .then((res) => (res.ok ? res.json() : null))
      .then((data) => {
        if (data && data.status) {
          console.debug('LEXSHIELD API health:', data.status);
        }
      })
      .catch(() => {
        console.debug('LEXSHIELD API is not reachable from the browser right now.');
      });
  }

  const appointmentBoard = document.querySelector('[data-appointment-board]');
  if (appointmentBoard) {
    const endpoint = appointmentBoard.dataset.endpoint || window.location.href;
    const results = appointmentBoard.querySelector('[data-appointment-results]');
    const pagination = appointmentBoard.querySelector('[data-appointment-pagination]');
    const summary = appointmentBoard.querySelector('[data-appointment-summary]');
    const filters = appointmentBoard.querySelector('[data-appointment-filters]');
    const searchInput = filters ? filters.querySelector('[data-appointment-search]') : null;
    const statusInput = filters ? filters.querySelector('[data-appointment-status]') : null;
    const pageInput = filters ? filters.querySelector('[data-appointment-page-input]') : null;
    let searchTimer = null;
    let activeRequest = 0;

    const readState = () => ({
      q: searchInput ? searchInput.value.trim() : '',
      status: statusInput ? statusInput.value : 'all',
      page: pageInput ? pageInput.value : '1',
    });

    const readStateFromUrl = () => {
      const params = new URLSearchParams(window.location.search);
      return {
        q: params.get('q') || '',
        status: params.get('status') || 'all',
        page: params.get('page') || '1',
      };
    };

    const syncInputs = (state) => {
      if (searchInput && searchInput.value !== (state.q || '')) searchInput.value = state.q || '';
      if (statusInput && statusInput.value !== (state.status || 'all')) statusInput.value = state.status || 'all';
      if (pageInput) pageInput.value = String(state.page || '1');
    };

    const syncUrl = (state, mode) => {
      const url = new URL(window.location.href);
      if (state.q) url.searchParams.set('q', state.q); else url.searchParams.delete('q');
      if (state.status && state.status !== 'all') url.searchParams.set('status', state.status); else url.searchParams.delete('status');
      if (state.page && String(state.page) !== '1') url.searchParams.set('page', String(state.page)); else url.searchParams.delete('page');
      url.searchParams.delete('format');
      if (mode === 'push') {
        history.pushState({}, '', url);
      } else {
        history.replaceState({}, '', url);
      }
    };

    const buildUrl = (state) => {
      const url = new URL(endpoint, window.location.href);
      url.searchParams.set('format', 'json');
      if (state.q) url.searchParams.set('q', state.q); else url.searchParams.delete('q');
      if (state.status && state.status !== 'all') url.searchParams.set('status', state.status); else url.searchParams.delete('status');
      if (state.page && String(state.page) !== '1') url.searchParams.set('page', String(state.page)); else url.searchParams.delete('page');
      return url;
    };

    const setBusy = (isBusy) => {
      appointmentBoard.classList.toggle('is-loading', isBusy);
      if (results) {
        results.setAttribute('aria-busy', isBusy ? 'true' : 'false');
      }
    };

    const fetchAppointments = async (state, options = {}) => {
      if (!endpoint) return;
      const requestId = ++activeRequest;
      const nextState = {
        q: state.q || '',
        status: state.status || 'all',
        page: state.page || '1',
      };
      const url = buildUrl(nextState);
      setBusy(true);
      try {
        const response = await fetch(url.toString(), {
          headers: { Accept: 'application/json' },
          credentials: 'same-origin',
        });
        if (!response.ok) {
          throw new Error('Request failed');
        }
        const data = await response.json();
        if (requestId !== activeRequest) {
          return;
        }
        if (results && data.resultsHtml !== undefined) {
          results.innerHTML = data.resultsHtml;
        }
        if (pagination && data.paginationHtml !== undefined) {
          pagination.innerHTML = data.paginationHtml;
        }
        if (summary && data.summaryText !== undefined) {
          summary.textContent = data.summaryText;
        }
        const syncedState = data.state || nextState;
        syncInputs(syncedState);
        syncUrl(syncedState, options.push ? 'push' : 'replace');
      } catch (error) {
        console.debug('Unable to refresh appointments right now.');
      } finally {
        if (requestId === activeRequest) {
          setBusy(false);
        }
      }
    };

    if (filters) {
      filters.addEventListener('submit', (event) => {
        event.preventDefault();
        fetchAppointments({
          ...readState(),
          page: '1',
        }, { push: true });
      });
    }

    if (searchInput) {
      searchInput.addEventListener('input', () => {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(() => {
          fetchAppointments({
            ...readState(),
            page: '1',
          }, { push: false });
        }, 220);
      });
    }

    if (statusInput) {
      statusInput.addEventListener('change', () => {
        fetchAppointments({
          ...readState(),
          page: '1',
        }, { push: true });
      });
    }

    if (pageInput) {
      pageInput.addEventListener('change', () => {
        fetchAppointments(readState(), { push: true });
      });
    }

    appointmentBoard.addEventListener('click', (event) => {
      const pageLink = event.target.closest('[data-appointment-page]');
      if (!pageLink) return;
      if (pageLink.getAttribute('aria-disabled') === 'true') {
        event.preventDefault();
        return;
      }
      if (pageLink.tagName !== 'A') {
        return;
      }
      event.preventDefault();
      fetchAppointments({
        ...readState(),
        page: pageLink.dataset.page || '1',
      }, { push: true });
    });

    window.addEventListener('popstate', () => {
      const state = readStateFromUrl();
      syncInputs(state);
      fetchAppointments(state, { push: false });
    });
  }

})();
