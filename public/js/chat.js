(() => {
  const setModalInertSiblings = (modal, enabled) => {
    if (!(modal instanceof HTMLElement)) return;
    let current = modal;
    while (current && current !== document.body) {
      const parent = current.parentElement;
      if (!parent) break;
      Array.from(parent.children).forEach((sibling) => {
        if (!(sibling instanceof HTMLElement) || sibling === current) return;
        const currentCount = Number(sibling.dataset.lexInertCount || '0');
        if (enabled) {
          sibling.dataset.lexInertCount = String(currentCount + 1);
          sibling.inert = true;
          return;
        }
        const nextCount = Math.max(0, currentCount - 1);
        if (nextCount === 0) {
          delete sibling.dataset.lexInertCount;
          sibling.inert = false;
        } else {
          sibling.dataset.lexInertCount = String(nextCount);
        }
      });
      current = parent;
    }
  };

  const chatShell = document.querySelector('[data-chat-shell]');
  let genericModalReturnFocus = null;
  const openModal = (modal) => {
    if (!modal) return;
    genericModalReturnFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    setModalInertSiblings(modal, true);
    const firstField = modal.querySelector('input, select, textarea, button:not([data-modal-close])');
    if (firstField) {
      setTimeout(() => firstField.focus(), 50);
    }
  };

  const closeModal = (modal) => {
    if (!modal) return;
    const active = document.activeElement;
    if (active instanceof HTMLElement && modal.contains(active)) {
      active.blur();
    }
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    setModalInertSiblings(modal, false);
    if (genericModalReturnFocus instanceof HTMLElement && document.contains(genericModalReturnFocus)) {
      setTimeout(() => genericModalReturnFocus.focus(), 0);
    }
    genericModalReturnFocus = null;
  };

  const renderAttachmentPreview = (container, files, emptyText = 'No file selected') => {
    if (!container) return;
    container.innerHTML = '';
    if (!files || !files.length) {
      container.textContent = emptyText;
      return;
    }
    files.forEach((file) => {
      const item = document.createElement('div');
      item.className = 'attachment-chip';
      item.textContent = `${file.name} - ${Math.max(1, Math.round(file.size / 1024))} KB`;
      container.appendChild(item);
    });
  };

  const resetNewMessageModal = (modal) => {
    if (!modal || modal.id !== 'newMessageModal') return;
    const fileInput = modal.querySelector('[data-modal-attachment-input]');
    const fileName = modal.querySelector('[data-modal-attachment-name]');
    const errorBox = modal.querySelector('[data-modal-errors]');
    if (fileInput) fileInput.value = '';
    renderAttachmentPreview(fileName, []);
    if (errorBox) {
      errorBox.textContent = '';
      errorBox.hidden = true;
    }
  };

  if (chatShell) {
    const scrollArea = chatShell.querySelector('[data-chat-scroll]');
    if (scrollArea) {
      scrollArea.scrollTop = scrollArea.scrollHeight;
    }

    const searchInput = chatShell.querySelector('[data-conversation-search]');
    const items = Array.from(chatShell.querySelectorAll('[data-conversation-item]'));
    if (searchInput && items.length) {
      searchInput.addEventListener('input', () => {
        const query = searchInput.value.trim().toLowerCase();
        items.forEach((item) => {
          const text = item.textContent.toLowerCase();
          item.hidden = query !== '' && !text.includes(query);
        });
      });
    }

    const tabs = Array.from(chatShell.querySelectorAll('[data-filter-tab]'));
    const applyFilter = (filter) => {
      items.forEach((item) => {
        const unread = item.dataset.unread === '1';
        const important = item.dataset.important === '1';
        const show = filter === 'all' || (filter === 'unread' && unread) || (filter === 'important' && important);
        if (searchInput && searchInput.value.trim() !== '') {
          const query = searchInput.value.trim().toLowerCase();
          item.hidden = !show || !item.textContent.toLowerCase().includes(query);
        } else {
          item.hidden = !show;
        }
      });
    };
    if (tabs.length) {
      tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
          tabs.forEach((t) => t.classList.remove('is-active'));
          tab.classList.add('is-active');
          applyFilter(tab.dataset.filterTab || 'all');
        });
      });
      applyFilter('all');
    }

    document.querySelectorAll('[data-modal-close]').forEach((button) => {
      button.addEventListener('click', (event) => {
        event.preventDefault();
        const modal = button.closest('[data-modal]');
        closeModal(modal);
        resetNewMessageModal(modal);
      });
    });

    document.querySelectorAll('[data-modal]').forEach((modal) => {
      modal.addEventListener('click', (event) => {
        if (event.target === modal) {
          closeModal(modal);
          resetNewMessageModal(modal);
        }
      });
    });

    document.addEventListener('click', (event) => {
      const openTrigger = event.target.closest('[data-modal-open]');
      if (openTrigger) {
        event.preventDefault();
        const target = document.getElementById(openTrigger.dataset.modalOpen || '');
        if (!target) return;
        if (target.id === 'conversationInfoModal') {
          const data = openTrigger.dataset;
          const setText = (selector, value) => {
            const node = target.querySelector(selector);
            if (node) node.textContent = value || '';
          };
          setText('[data-info-name]', data.infoName);
          setText('[data-info-role]', data.infoRole);
          setText('[data-info-status]', data.infoStatus);
          setText('[data-info-case]', data.infoCase);
          setText('[data-info-id]', data.infoId);
          setText('[data-info-created]', data.infoCreated);
          setText('[data-info-activity]', data.infoActivity);
          const avatar = target.querySelector('[data-info-avatar]');
          if (avatar) avatar.textContent = (data.infoName || '?').slice(0, 2).toUpperCase();
        } else if (target.id === 'newMessageModal') {
          const data = openTrigger.dataset;
          const caseInput = target.querySelector('[data-default-case-input]');
          const recipientSelect = target.querySelector('[data-recipient-select]');
          const recipientRole = target.querySelector('[data-recipient-role]');
          const fileInput = target.querySelector('[data-modal-attachment-input]');
          const fileName = target.querySelector('[data-modal-attachment-name]');
          const errorBox = target.querySelector('[data-modal-errors]');
          if (caseInput && data.defaultCase) {
            caseInput.value = data.defaultCase;
          }
          if (recipientSelect && data.defaultRecipient) {
            recipientSelect.value = data.defaultRecipient;
          }
          if (recipientRole && data.defaultRole) {
            recipientRole.value = data.defaultRole;
          }
          if (recipientSelect) {
            recipientSelect.dispatchEvent(new Event('change', { bubbles: true }));
          }
          if (fileInput) fileInput.value = '';
          renderAttachmentPreview(fileName, []);
          if (errorBox) {
            errorBox.textContent = '';
            errorBox.hidden = true;
          }
        } else if (target.id === 'profileModal') {
          const data = openTrigger.dataset;
          const setText = (selector, value) => {
            const node = target.querySelector(selector);
            if (node) node.textContent = value || '';
          };
          setText('[data-profile-name]', data.profileName);
          setText('[data-profile-role]', data.profileRole);
          setText('[data-profile-status]', data.profileStatus);
          setText('[data-profile-case]', data.profileCase);
          setText('[data-profile-email]', data.profileEmail || 'Not provided');
          setText('[data-profile-note]', data.profileNote);
          const avatar = target.querySelector('[data-profile-avatar]');
          if (avatar) avatar.textContent = (data.profileAvatar || '?').slice(0, 2).toUpperCase();
        }
        openModal(target);
        return;
      }

      const closeTrigger = event.target.closest('[data-modal-close]');
      if (closeTrigger) {
        event.preventDefault();
        const modal = closeTrigger.closest('[data-modal]');
        closeModal(modal);
        resetNewMessageModal(modal);
        return;
      }

      const overlay = event.target.closest('[data-modal]');
      if (overlay && event.target === overlay) {
        closeModal(overlay);
        resetNewMessageModal(overlay);
      }
    });
  }

  clientDeleteModal = document.querySelector('[data-client-delete-modal]');
  if (clientDeleteModal) {
    const deleteForm = clientDeleteModal.querySelector('[data-client-delete-form]');
    const deleteNameField = clientDeleteModal.querySelector('[data-client-delete-name]');
    const deleteConfirmInput = clientDeleteModal.querySelector('[data-client-delete-confirm-text]');
    const deleteSubmit = clientDeleteModal.querySelector('[data-client-delete-submit]');
    const deleteError = clientDeleteModal.querySelector('[data-client-delete-error]');
    const deleteClientId = deleteForm ? deleteForm.querySelector('input[name="client_id"]') : null;
    const deleteConfirmationField = deleteForm ? deleteForm.querySelector('[data-client-delete-confirmation]') : null;
    let expectedClientName = '';

    const clearDeleteState = () => {
      expectedClientName = '';
      if (deleteNameField) deleteNameField.value = '';
      if (deleteConfirmInput) deleteConfirmInput.value = '';
      if (deleteConfirmationField) deleteConfirmationField.value = '';
      if (deleteError) deleteError.textContent = '';
    };

    const validateDeleteText = () => {
      const entered = (deleteConfirmInput?.value || '').trim();
      const normalized = entered.replace(/\s+/g, ' ').trim();
      const requiredPhrase = `${expectedClientName} DELETE`.trim();
      const matches = expectedClientName !== '' && normalized === requiredPhrase;
      if (deleteConfirmationField) {
        deleteConfirmationField.value = normalized;
      }
      if (deleteError) {
        deleteError.textContent = entered !== '' && !matches ? 'Type the exact client name followed by DELETE.' : '';
      }
      return matches;
    };

    document.querySelectorAll('[data-client-delete-open]').forEach((button) => {
      button.addEventListener('click', () => {
        if (deleteClientId) {
          deleteClientId.value = button.dataset.clientId || '';
        }
        clearDeleteState();
        expectedClientName = button.dataset.clientName || '';
        if (deleteNameField) {
          deleteNameField.value = expectedClientName;
        }
        openModal(clientDeleteModal);
        validateDeleteText();
        if (deleteConfirmInput) {
          setTimeout(() => deleteConfirmInput.focus(), 50);
        }
      });
    });

    clientDeleteModal.querySelectorAll('[data-client-delete-close]').forEach((button) => {
      button.addEventListener('click', (event) => {
        event.preventDefault();
        closeModal(clientDeleteModal);
        clearDeleteState();
      });
    });

    clientDeleteModal.addEventListener('click', (event) => {
      if (event.target === clientDeleteModal) {
        closeModal(clientDeleteModal);
        clearDeleteState();
      }
    });

    if (deleteConfirmInput) {
      deleteConfirmInput.addEventListener('input', validateDeleteText);
      deleteConfirmInput.addEventListener('keyup', validateDeleteText);
      deleteConfirmInput.addEventListener('change', validateDeleteText);
    }

    if (deleteForm) {
      deleteForm.addEventListener('submit', (event) => {
        if (!validateDeleteText()) {
          event.preventDefault();
          if (deleteError && (deleteConfirmInput?.value || '').trim() === '') {
            deleteError.textContent = 'Type the client name followed by DELETE to confirm deletion.';
          }
        }
      });
    }

    if (deleteSubmit && deleteForm) {
      deleteSubmit.addEventListener('click', (event) => {
        if (!validateDeleteText()) {
          event.preventDefault();
          return;
        }
        event.preventDefault();
        deleteForm.submit();
      });
    }
  }

  if (chatShell) {
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      document.querySelectorAll('[data-modal].is-open').forEach((modal) => {
        closeModal(modal);
        resetNewMessageModal(modal);
      });
      if (clientDeleteModal && clientDeleteModal.classList.contains('is-open')) {
        closeModal(clientDeleteModal);
      }
    }
  });

  const attachmentInput = chatShell.querySelector('[data-attachment-input]');
  const attachmentPreview = chatShell.querySelector('[data-attachment-preview]');
  if (attachmentInput && attachmentPreview) {
    attachmentInput.addEventListener('change', () => {
      attachmentPreview.innerHTML = '';
      const files = Array.from(attachmentInput.files || []);
      if (!files.length) {
        attachmentPreview.hidden = true;
        return;
      }
      files.forEach((file) => {
        const item = document.createElement('div');
        item.className = 'attachment-chip';
        item.textContent = `${file.name} - ${Math.max(1, Math.round(file.size / 1024))} KB`;
        attachmentPreview.appendChild(item);
      });
      attachmentPreview.hidden = false;
    });
  }

  const newMessageModal = document.querySelector('#newMessageModal');
  const newMessageForm = document.querySelector('[data-new-message-form]');
  if (newMessageModal && newMessageForm) {
    const caseInput = newMessageModal.querySelector('[data-default-case-input]');
    const recipientSelect = newMessageModal.querySelector('[data-recipient-select]');
    const messageInput = newMessageModal.querySelector('[data-message-input]');
    const errorBox = newMessageModal.querySelector('[data-modal-errors]');
    const recipientRole = newMessageModal.querySelector('[name="new_recipient_role"]');
    const attachmentButton = newMessageModal.querySelector('[data-modal-attachment-button]');
    const attachmentInputNew = newMessageModal.querySelector('[data-modal-attachment-input]');
    const attachmentName = newMessageModal.querySelector('[data-modal-attachment-name]');

    newMessageModal.querySelectorAll('[data-modal-close]').forEach((button) => {
      button.addEventListener('click', (event) => {
        event.preventDefault();
        closeModal(newMessageModal);
        resetNewMessageModal(newMessageModal);
      });
    });

    if (attachmentButton && attachmentInputNew && attachmentButton.tagName !== 'LABEL') {
      attachmentButton.addEventListener('click', () => attachmentInputNew.click());
    }
    if (attachmentInputNew) {
      attachmentInputNew.addEventListener('change', () => {
        renderAttachmentPreview(attachmentName, Array.from(attachmentInputNew.files || []));
      });
    }

    if (recipientSelect && recipientRole) {
      const syncRecipientRole = () => {
        const selected = recipientSelect.selectedOptions[0];
        recipientRole.value = selected?.dataset.role || recipientRole.value || '';
      };
      recipientSelect.addEventListener('change', syncRecipientRole);
      syncRecipientRole();
    }

    if (recipientSelect && caseInput) {
      const syncCaseToRecipient = () => {
        const selected = recipientSelect.selectedOptions[0];
        const linkedCaseId = selected?.dataset.caseId || '';
        caseInput.value = linkedCaseId;
      };
      recipientSelect.addEventListener('change', syncCaseToRecipient);
      syncCaseToRecipient();
    }

    newMessageForm.addEventListener('submit', (event) => {
      const errors = [];
      const recipient = recipientSelect?.value || '';
      const message = messageInput?.value.trim() || '';
      const hasAttachment = Boolean(attachmentInputNew?.files && attachmentInputNew.files.length > 0);
      if (!recipient) errors.push('Select a recipient first.');
      if (!message && !hasAttachment) errors.push('Message or attachment is required.');
      if (errorBox) {
        errorBox.textContent = errors.join(' ');
        errorBox.hidden = errors.length === 0;
      }
      if (errors.length) {
        event.preventDefault();
      }
    });
  }
  }
})();

