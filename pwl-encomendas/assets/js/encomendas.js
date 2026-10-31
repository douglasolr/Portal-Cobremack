(function () {
    'use strict';

    document.addEventListener('submit', function (event) {
        var createForm = event.target.closest('[data-pwl-package-create]');
        if (!createForm) return;
        if (createForm.dataset.pwlSubmitting === '1') {
            event.preventDefault();
            return;
        }
        createForm.dataset.pwlSubmitting = '1';
        Array.prototype.forEach.call(createForm.querySelectorAll('button[type="submit"], input[type="submit"]'), function (button) {
            button.disabled = true;
            if (button.tagName === 'BUTTON') {
                button.dataset.pwlOriginalText = button.textContent;
                button.textContent = 'Registrando…';
            }
        });
    }, true);

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        var modal = document.querySelector('[data-pwl-promote-modal]:not([hidden])');
        if (modal) modal.hidden = true;
    });

    document.addEventListener('change', function (event) {
        var noticeSelect = event.target.closest('[data-pwl-advance-notice-select]');
        if (!noticeSelect) return;
        var noticeForm = noticeSelect.closest('form');
        var noticeOption = noticeSelect.options[noticeSelect.selectedIndex];
        fillFromNotice(noticeForm, noticeSelect.value, noticeOption);
    });

    document.addEventListener('click', function (event) {
        var promoteButton = event.target.closest('[data-pwl-promote-notice]');
        if (promoteButton) {
            var noticeRow = promoteButton.closest('[data-pwl-advance-notice]');
            var promoteModal = document.querySelector('[data-pwl-promote-modal]');
            if (!noticeRow || !promoteModal) return;
            var setValue = function (selector, value) {
                var target = promoteModal.querySelector(selector);
                if (target) target.textContent = value || 'Não informado';
            };
            var noticePerson = noticeRow.querySelector('.pwl-encomendas-notice-person, .pwl-encomendas-advance-notice-card__top strong');
            setValue('[data-pwl-promote-person]', noticePerson ? noticePerson.textContent : '');
            setValue('[data-pwl-promote-sender]', noticeRow.dataset.sender);
            setValue('[data-pwl-promote-description]', noticeRow.dataset.description);
            setValue('[data-pwl-promote-tracking]', noticeRow.dataset.tracking);
            setValue('[data-pwl-promote-expected]', noticeRow.dataset.expected);
            setValue('[data-pwl-promote-authorized]', noticeRow.dataset.authorized);
            setValue('[data-pwl-promote-passphrase]', noticeRow.dataset.passphrase);
            var carrierLogo = promoteModal.querySelector('[data-pwl-promote-logo]');
            if (carrierLogo) {
                carrierLogo.src = noticeRow.dataset.carrierLogo || '';
                carrierLogo.hidden = !noticeRow.dataset.carrierLogo;
            }
            promoteModal.querySelector('[data-pwl-promote-recipient]').value = 'person:' + noticeRow.dataset.personId;
            promoteModal.querySelector('[data-pwl-promote-sender-input]').value = noticeRow.dataset.sender;
            promoteModal.querySelector('[data-pwl-promote-tracking-input]').value = noticeRow.dataset.tracking;
            var packagingSelect = promoteModal.querySelector('[data-pwl-promote-packaging]');
            var noticeDescription = noticeRow.dataset.description || '';
            var matchingPackaging = Array.from(packagingSelect.options).find(function (option) { return option.value.toLowerCase() === noticeDescription.toLowerCase(); });
            packagingSelect.value = matchingPackaging ? matchingPackaging.value : (Array.from(packagingSelect.options).some(function (option) { return option.value === 'Outros'; }) ? 'Outros' : '');
            promoteModal.querySelector('[data-pwl-promote-notes-input]').value = matchingPackaging ? '' : noticeDescription;
            promoteModal.querySelector('[data-pwl-promote-notice-id]').value = noticeRow.dataset.pwlAdvanceNotice;
            promoteModal.hidden = false;
            promoteModal.querySelector('[data-pwl-promote-cancel]').focus();
            return;
        }
        var promoteCancel = event.target.closest('[data-pwl-promote-cancel]');
        if (promoteCancel) {
            var activeModal = promoteCancel.closest('[data-pwl-promote-modal]');
            if (activeModal) activeModal.hidden = true;
            return;
        }
        var promoteBackdrop = event.target.matches('[data-pwl-promote-modal]');
        if (promoteBackdrop) event.target.hidden = true;

        var noticeFillButton = event.target.closest('[data-pwl-advance-notice-fill]');
        if (noticeFillButton) {
            var noticeCard = noticeFillButton.closest('[data-pwl-advance-notice]');
            var noticeCreateForm = document.querySelector('.pwl-encomendas-registration-card:not(.pwl-encomendas-notice-form-card) form');
            if (!noticeCard || !noticeCreateForm) return;
            fillFromNotice(noticeCreateForm, noticeFillButton.getAttribute('data-pwl-advance-notice-fill'), noticeCard);
            noticeCreateForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
        var passwordGenerate = event.target.closest('[data-pwl-password-generate]');
        if (passwordGenerate) {
            var generatedForm = passwordGenerate.closest('form');
            var generatedInput = generatedForm && generatedForm.querySelector('input[name="passphrase"]');
            if (generatedInput) {
                var words = ['cobre', 'azul', 'fio', 'cabo', 'norte', 'prata', 'lume', 'faixa', 'portal', 'brisa'];
                var randomValues = new Uint32Array(3);
                if (window.crypto && window.crypto.getRandomValues) {
                    window.crypto.getRandomValues(randomValues);
                } else {
                    randomValues = [Math.random() * 4294967296, Math.random() * 4294967296, Math.random() * 4294967296];
                }
                generatedInput.value = words[randomValues[0] % words.length] + '-' + words[randomValues[1] % words.length] + '-' + String(10 + (randomValues[2] % 90));
                generatedInput.type = 'text';
                var showToggle = generatedForm.querySelector('[data-pwl-password-toggle]');
                if (showToggle) showToggle.textContent = 'Ocultar';
                generatedInput.focus();
            }
            return;
        }
        var passwordToggle = event.target.closest('[data-pwl-password-toggle]');
        if (!passwordToggle) return;
        var passwordInput = passwordToggle.parentNode.querySelector('input[name="passphrase"]');
        if (passwordInput) {
            var showPassword = passwordInput.type === 'password';
            passwordInput.type = showPassword ? 'text' : 'password';
            passwordToggle.textContent = showPassword ? 'Ocultar' : 'Mostrar';
        }
    });

    function fillFromNotice(form, noticeId, source) {
        if (!form || !source) return;
        var hiddenId = form.querySelector('[data-pwl-advance-notice-id]');
        if (hiddenId) hiddenId.value = noticeId || '';
        var noticeSelect = form.querySelector('[data-pwl-advance-notice-select]');
        if (noticeSelect) noticeSelect.value = noticeId || '';
        if (!noticeId) return;
        var personId = source.getAttribute('data-person') || source.getAttribute('data-person-id') || '';
        var recipient = form.querySelector('[name="recipient_person_id"]');
        if (recipient && personId) {
            recipient.value = 'person:' + personId;
            recipient.dispatchEvent(new Event('change', { bubbles: true }));
        }
        var sender = source.getAttribute('data-sender') || '';
        var senderSelect = form.querySelector('[name="sender"]');
        if (senderSelect && sender) senderSelect.value = sender;
        var trackingInput = form.querySelector('[name="tracking_code"]');
        var tracking = source.getAttribute('data-tracking') || '';
        if (trackingInput && tracking) trackingInput.value = tracking;
        var descriptionInput = form.querySelector('[name="description"]');
        var description = source.getAttribute('data-description') || '';
        if (descriptionInput && description) descriptionInput.value = description;
    }

    function getCellValue(row, index) {
        var cell = row.cells[index];
        if (!cell) {
            return '';
        }
        var value = cell.getAttribute('data-sort-value');
        return (value === null ? cell.textContent : value).trim();
    }

    document.addEventListener('click', function (event) {
        var sortButton = event.target.closest('.pwl-encomendas-sort');
        if (!sortButton) {
            return;
        }

        event.preventDefault();
        var button = sortButton;
        var table = button.closest('.pwl-encomendas-table');
        var tbody = table && table.tBodies.length ? table.tBodies[0] : null;
        var header = button.closest('th');
        if (!table || !tbody || !header) {
            return;
        }

        var key = button.getAttribute('data-sort');
        var direction = table.getAttribute('data-sort-key') === key && table.getAttribute('data-sort-direction') === 'asc' ? 'desc' : 'asc';
        var columnIndex = header.cellIndex;
        var rows = Array.prototype.slice.call(tbody.rows);

        rows.sort(function (rowA, rowB) {
            var valueA = getCellValue(rowA, columnIndex);
            var valueB = getCellValue(rowB, columnIndex);
            if (!valueA && valueB) {
                return 1;
            }
            if (valueA && !valueB) {
                return -1;
            }
            var result = valueA.localeCompare(valueB, document.documentElement.lang || 'pt-BR', { numeric: true, sensitivity: 'base' });
            return 'asc' === direction ? result : -result;
        });

        var fragment = document.createDocumentFragment();
        rows.forEach(function (row) {
            fragment.appendChild(row);
        });
        tbody.appendChild(fragment);

        table.setAttribute('data-sort-key', key);
        table.setAttribute('data-sort-direction', direction);
        Array.prototype.forEach.call(table.tHead.rows[0].cells, function (cell) {
            var sortButton = cell.querySelector('.pwl-encomendas-sort');
            if (!sortButton) {
                cell.setAttribute('aria-sort', 'none');
                return;
            }
            if (sortButton === button) {
                cell.setAttribute('aria-sort', 'asc' === direction ? 'ascending' : 'descending');
                var indicator = button.querySelector('span');
                if (indicator) {
                    indicator.textContent = 'asc' === direction ? '↑' : '↓';
                }
            } else {
                cell.setAttribute('aria-sort', 'none');
                var otherIndicator = sortButton.querySelector('span');
                if (otherIndicator) {
                    otherIndicator.textContent = '↕';
                }
            }
        });
    });

    document.addEventListener('change', function (event) {
        var periodFilter = event.target.closest('[data-pwl-period-filter]');
        if (periodFilter) {
            var customDateField = periodFilter.closest('form').querySelector('[data-pwl-custom-date]');
            var customDateInput = customDateField && customDateField.querySelector('input[type="date"]');
            if (customDateField) {
                customDateField.hidden = periodFilter.value !== 'custom';
            }
            if (customDateInput) {
                customDateInput.required = periodFilter.value === 'custom';
                if (periodFilter.value === 'custom') {
                    customDateInput.focus();
                }
            }
            return;
        }
        if (event.target.matches('[data-pwl-delete-all]')) {
            var deleteTable = event.target.closest('.pwl-encomendas-table');
            if (deleteTable) {
                Array.prototype.forEach.call(deleteTable.querySelectorAll('[data-pwl-delete-item]'), function (checkbox) {
                    checkbox.checked = event.target.checked;
                });
                updateDeleteSelection(deleteTable);
            }
            return;
        }
        if (event.target.matches('[data-pwl-delete-item]')) {
            updateDeleteSelection(event.target.closest('.pwl-encomendas-table'));
            return;
        }
        var select = event.target.closest('[data-pwl-pickup-person]');
        if (!select) {
            return;
        }

        var form = select.closest('form');
        var manualField = form && form.querySelector('.pwl-encomendas-pickup-other');
        var manualInput = manualField && manualField.querySelector('[name="picked_up_by_name"]');
        if (!manualField || !manualInput) {
            return;
        }

        var isOther = select.value === 'other';
        manualField.hidden = !isOther;
        manualInput.disabled = !isOther;
        manualInput.required = isOther;
        if (!isOther) {
            manualInput.value = '';
        }
    });

    document.addEventListener('click', function (event) {
        var openButton = event.target.closest('[data-pwl-pickup-open]');
        if (!openButton) {
            return;
        }

        var form = openButton.closest('form');
        var fields = form && form.querySelector('[data-pwl-pickup-fields]');
        var personSelect = fields && fields.querySelector('[data-pwl-pickup-person]');
        if (!fields || !personSelect) {
            return;
        }

        fields.hidden = false;
        openButton.hidden = true;
        personSelect.focus();
    });

    function initializePickupSelectors() {
        Array.prototype.forEach.call(document.querySelectorAll('[data-pwl-pickup-person]'), function (select) {
            select.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    initializePickupSelectors();
    document.addEventListener('pwl:encomendas:tab-loaded', initializePickupSelectors);

    function updateDeleteSelection(table) {
        if (!table) {
            return;
        }
        var checkboxes = Array.prototype.slice.call(table.querySelectorAll('[data-pwl-delete-item]'));
        var selected = checkboxes.filter(function (checkbox) { return checkbox.checked; }).length;
        var selectAll = table.querySelector('[data-pwl-delete-all]');
        var form = document.getElementById('pwl-encomendas-delete-form');
        var openButton = form && form.querySelector('[data-pwl-delete-open]');
        var count = form && form.querySelector('[data-pwl-delete-count]');
        if (selectAll) {
            selectAll.checked = checkboxes.length > 0 && selected === checkboxes.length;
            selectAll.indeterminate = selected > 0 && selected < checkboxes.length;
        }
        if (openButton) {
            openButton.disabled = selected === 0;
        }
        if (count) {
            count.textContent = selected === 1 ? '1 encomenda selecionada.' : selected + ' encomendas selecionadas.';
        }
    }

    document.addEventListener('click', function (event) {
        var openDelete = event.target.closest('[data-pwl-delete-open]');
        var cancelDelete = event.target.closest('[data-pwl-delete-cancel]');
        if (!openDelete && !cancelDelete) {
            return;
        }
        var form = (openDelete || cancelDelete).closest('#pwl-encomendas-delete-form');
        var confirmation = form && form.querySelector('[data-pwl-delete-confirm]');
        var input = confirmation && confirmation.querySelector('[name="delete_confirmation"]');
        if (!confirmation || !input) {
            return;
        }
        confirmation.hidden = !!cancelDelete;
        input.disabled = !!cancelDelete;
        if (cancelDelete) {
            input.value = '';
            var openButton = form.querySelector('[data-pwl-delete-open]');
            if (openButton) {
                openButton.hidden = false;
            }
        } else {
            openDelete.hidden = true;
            input.focus();
        }
    });

    document.addEventListener('submit', function (event) {
        if (!event.target.matches('#pwl-encomendas-delete-form')) {
            return;
        }
        var checked = document.querySelectorAll('.pwl-encomendas-table [data-pwl-delete-item]:checked');
        var input = event.target.querySelector('[name="delete_confirmation"]');
        if (!checked.length || !input || input.value !== 'EXCLUIR') {
            event.preventDefault();
            if (input) {
                input.setCustomValidity(input.value === 'EXCLUIR' ? '' : 'Digite EXCLUIR exatamente como aparece.');
                input.reportValidity();
            }
            return;
        }
    });

    document.addEventListener('input', function (event) {
        if (event.target.matches('[data-pwl-group-search]')) {
            var query = normalizeSearch(event.target.value);
            var groupForm = event.target.closest('fieldset');
            if (!groupForm) return;
            Array.prototype.forEach.call(groupForm.querySelectorAll('[data-pwl-group-member]'), function (member) {
                member.hidden = query !== '' && normalizeSearch(member.textContent).indexOf(query) === -1;
            });
            return;
        }
        if (event.target.matches('#pwl-encomendas-delete-form [name="delete_confirmation"]')) {
            event.target.setCustomValidity('');
        }
    });

    function normalizeSearch(value) {
        return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('pt-BR').trim();
    }
}());

/* Stitch-inspired registration overlay, scoped to Gestão only. Original form submits unchanged. */
document.addEventListener('DOMContentLoaded', function () {
  const root = document.querySelector('.cm-stitch-gestao');
  if (!root) return;
  const trigger = root.querySelector('[data-cm-open-registration]');
  const panel = root.querySelector('.pwl-encomendas-management-main > .pwl-encomendas-registration-card');
  if (!trigger || !panel) return;
  const overlay = document.createElement('div');
  overlay.className = 'cm-stitch-overlay';
  overlay.hidden = true;
  const close = document.createElement('button');
  close.type = 'button';
  close.className = 'cm-stitch-close';
  close.textContent = '✕';
  close.setAttribute('aria-label', 'Fechar registro de encomenda');
  const header = panel.querySelector('.pwl-card__header');
  if (header) header.appendChild(close);
  root.appendChild(overlay);
  // Moldura acompanha as dimensoes reais do formulario, inclusive ao mudar de viewport.
  const syncGlass = () => {
    if (!panel.classList.contains('cm-stitch-visible')) return;
    const rect = panel.getBoundingClientRect();
    const space = parseFloat(getComputedStyle(panel).getPropertyValue('--pwl-space-7')) || 28;
    const margin = Math.min(space, Math.max(8, window.innerWidth * 0.025));
    overlay.style.setProperty('--cm-stitch-glass-width', `${Math.min(window.innerWidth - 8, rect.width + 2 * margin)}px`);
    overlay.style.setProperty('--cm-stitch-glass-height', `${Math.min(window.innerHeight - 8, rect.height + 2 * margin)}px`);
  };
  if (typeof ResizeObserver !== 'undefined') new ResizeObserver(syncGlass).observe(panel);
  window.addEventListener('resize', syncGlass);
  const hide = () => {panel.classList.remove('cm-stitch-visible');overlay.hidden=true;document.body.style.overflow='';trigger.focus();};
  trigger.addEventListener('click', () => {overlay.hidden=false;panel.classList.add('cm-stitch-visible');syncGlass();document.body.style.overflow='hidden';panel.querySelector('select, input, button')?.focus();});
  close.addEventListener('click', hide);
  overlay.addEventListener('click', hide);
  document.addEventListener('keydown', (e) => {if (e.key === 'Escape' && panel.classList.contains('cm-stitch-visible')) hide();});
});

// v1.4.19: cancelamento do popup de registro, sem alterar a submissao existente.
document.addEventListener('click',function(e){if(e.target.closest('[data-cm-cancel-registration]')){document.querySelector('.cm-stitch-gestao .cm-stitch-close')?.click();}});

/* 1.5.1: aviso antecipado em popup independente, preservando registro original. */
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.cm-stitch-gestao').forEach(function(root) {
    const trigger = root.querySelector('[data-pwl-open-notice]');
    const panel = root.querySelector('.pwl-encomendas-notice-form-card');
    if (!trigger || !panel) return;
    const overlay = document.createElement('div');
    overlay.className = 'cm-stitch-overlay cm-stitch-notice-overlay';
    overlay.hidden = true;
    const close = document.createElement('button');
    close.type = 'button'; close.className = 'cm-stitch-close';
    close.textContent = '✕'; close.setAttribute('aria-label', 'Fechar aviso à Portaria');
    panel.querySelector('.pwl-card__header')?.appendChild(close);
    root.appendChild(overlay);
    const hide = function() {
      panel.classList.remove('cm-stitch-visible'); overlay.hidden = true;
      document.body.style.overflow = ''; trigger.focus();
    };
    trigger.addEventListener('click', function() {
      overlay.hidden = false; panel.classList.add('cm-stitch-visible');
      document.body.style.overflow = 'hidden';
      panel.querySelector('select, input, textarea')?.focus();
    });
    close.addEventListener('click', hide);
    overlay.addEventListener('click', hide);
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape' && panel.classList.contains('cm-stitch-visible')) hide();
    });
  });
});
