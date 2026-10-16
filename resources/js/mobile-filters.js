const MOBILE_FILTER_QUERY = '(max-width: 767px)';
const mobileFilterMedia = window.matchMedia(MOBILE_FILTER_QUERY);

const escapeHtml = (value) => String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

const isFilterControl = (control) => control instanceof HTMLInputElement
    || control instanceof HTMLSelectElement
    || control instanceof HTMLTextAreaElement;

const getControls = (container) => [...container.querySelectorAll('input[name], select[name], textarea[name]')]
    .filter(isFilterControl);

const getControlLabel = (control) => control.dataset.filterLabel
    || control.closest('[data-filter-label]')?.dataset.filterLabel
    || control.getAttribute('aria-label')
    || control.closest('label')?.textContent?.replace(/\s+/g, ' ').trim()
    || control.name;

const getControlValueLabel = (control) => {
    if (control instanceof HTMLSelectElement) {
        return control.selectedOptions[0]?.textContent?.trim() || control.value;
    }

    if (control.dataset.selectedLabel) {
        return control.dataset.selectedLabel;
    }

    return control.value;
}

const formatDate = (value, timezone, options = { day: 'numeric', month: 'long', year: 'numeric' }) => {
    if (! value) {
        return '';
    }

    return new Intl.DateTimeFormat('id-ID', { ...options, timeZone: timezone })
        .format(new Date(`${value}T12:00:00Z`));
};

const getCalendarDate = (timezone) => {
    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone: timezone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).formatToParts(new Date()).reduce((result, part) => {
        if (part.type !== 'literal') {
            result[part.type] = Number(part.value);
        }

        return result;
    }, {});

    return new Date(Date.UTC(parts.year, parts.month - 1, parts.day));
};

const toDateValue = (date) => date.toISOString().slice(0, 10);

const datePresets = (timezone) => {
    const today = getCalendarDate(timezone);
    const year = today.getUTCFullYear();
    const month = today.getUTCMonth();
    const startOfMonth = new Date(Date.UTC(year, month, 1));
    const endOfMonth = new Date(Date.UTC(year, month + 1, 0));
    const startOfLastMonth = new Date(Date.UTC(year, month - 1, 1));
    const endOfLastMonth = new Date(Date.UTC(year, month, 0));
    const lastSevenStart = new Date(today);
    lastSevenStart.setUTCDate(lastSevenStart.getUTCDate() - 6);

    return {
        all: ['', ''],
        'last-7-days': [toDateValue(lastSevenStart), toDateValue(today)],
        'this-month': [toDateValue(startOfMonth), toDateValue(endOfMonth)],
        'last-month': [toDateValue(startOfLastMonth), toDateValue(endOfLastMonth)],
    };
};

const getDateControls = (container) => ({
    from: container.querySelector('input[name="date_from"]'),
    to: container.querySelector('input[name="date_to"]'),
});

const formatDateRange = (from, to, timezone) => {
    if (! from && ! to) {
        return '';
    }

    if (from && to) {
        const fromDate = new Date(`${from}T12:00:00Z`);
        const toDate = new Date(`${to}T12:00:00Z`);
        const sameMonth = fromDate.getUTCMonth() === toDate.getUTCMonth()
            && fromDate.getUTCFullYear() === toDate.getUTCFullYear();

        if (sameMonth) {
            return `${formatDate(from, timezone, { day: 'numeric' })} – ${formatDate(to, timezone)}`;
        }

        return `${formatDate(from, timezone)} – ${formatDate(to, timezone)}`;
    }

    return formatDate(from || to, timezone);
};

const captureControlState = (container) => getControls(container).map((control) => ({
    control,
    value: control.value,
    checked: control instanceof HTMLInputElement ? control.checked : false,
    selectedLabel: control.dataset.selectedLabel || '',
}));

const restoreControlState = (state) => state.forEach(({ control, value, checked, selectedLabel }) => {
    control.value = value;

    if (control instanceof HTMLInputElement) {
        control.checked = checked;
    }

    if (selectedLabel) {
        control.dataset.selectedLabel = selectedLabel;
    } else {
        delete control.dataset.selectedLabel;
    }
});

const clearControlState = (container) => getControls(container).forEach((control) => {
    if (control instanceof HTMLSelectElement) {
        const emptyOption = [...control.options].find((option) => option.value === '');
        control.value = emptyOption ? '' : control.options[0]?.value || '';
    } else if (control instanceof HTMLInputElement && ['checkbox', 'radio'].includes(control.type)) {
        control.checked = false;
    } else {
        control.value = '';
    }

    delete control.dataset.selectedLabel;
});

const readFilterEntries = (container, timezone) => {
    const entries = [];
    const dateControls = getDateControls(container);
    const dateFrom = dateControls.from?.value || '';
    const dateTo = dateControls.to?.value || '';
    const dateNames = new Set(['date_from', 'date_to']);

    if (dateFrom || dateTo) {
        entries.push({
            label: dateControls.from?.dataset.filterLabel || 'Tanggal',
            value: formatDateRange(dateFrom, dateTo, timezone),
            names: ['date_from', 'date_to'],
        });
    }

    getControls(container).forEach((control) => {
        if (dateNames.has(control.name)) {
            return;
        }

        if (control instanceof HTMLInputElement && ['checkbox', 'radio'].includes(control.type) && ! control.checked) {
            return;
        }

        if (! control.value) {
            return;
        }

        entries.push({
            label: getControlLabel(control),
            value: control instanceof HTMLInputElement && control.type === 'checkbox'
                ? 'Aktif'
                : getControlValueLabel(control),
            names: [control.name],
        });
    });

    return entries;
};

const updateActiveFilterLinks = (toolbar, entries) => {
    const activeFilters = toolbar.querySelector('[data-mobile-active-filters]');

    if (! activeFilters) {
        return;
    }

    activeFilters.replaceChildren();

    if (! entries.length) {
        return;
    }

    const currentUrl = new URL(window.location.href);

    entries.forEach((entry) => {
        const linkUrl = new URL(currentUrl);
        entry.names.forEach((name) => linkUrl.searchParams.delete(name));

        const chip = document.createElement('a');
        chip.className = 'mobile-active-filter-chip';
        chip.href = linkUrl.toString();
        chip.innerHTML = `<span>${escapeHtml(entry.label)}: ${escapeHtml(entry.value)}</span><span aria-hidden="true">×</span>`;
        activeFilters.append(chip);
    });

    const clearAll = document.createElement('a');
    clearAll.className = 'mobile-active-filter-clear';
    clearAll.href = toolbar.dataset.resetHref || currentUrl.pathname;
    clearAll.textContent = 'Hapus semua';
    activeFilters.append(clearAll);
};

const updateDateUi = (toolbar, mobileFilters) => {
    const dateControls = getDateControls(mobileFilters);
    const timezone = mobileFilters.querySelector('[data-business-timezone]')?.dataset.businessTimezone || 'Asia/Jakarta';
    const values = [dateControls.from?.value || '', dateControls.to?.value || ''];
    const presets = datePresets(timezone);
    const customFields = mobileFilters.querySelector('[data-mobile-date-fields]');
    const summary = mobileFilters.querySelector('[data-mobile-date-summary]');
    let selectedPreset = Object.entries(presets).find(([, presetValues]) => presetValues[0] === values[0] && presetValues[1] === values[1])?.[0] || '';

    if (! values[0] && ! values[1]) {
        selectedPreset = 'all';
    }

    mobileFilters.querySelectorAll('[data-mobile-date-preset]').forEach((button) => {
        const selected = button.dataset.mobileDatePreset === selectedPreset;
        button.setAttribute('aria-pressed', selected.toString());
        button.classList.toggle('is-active', selected);
    });

    if (customFields) {
        customFields.hidden = selectedPreset !== 'custom';
    }

    if (summary) {
        summary.hidden = ! values[0] && ! values[1];
        summary.textContent = formatDateRange(values[0], values[1], timezone);
    }
};

const updatePickerUi = (mobileFilters) => {
    mobileFilters.querySelectorAll('[data-picker-input]').forEach((input) => {
        const rowValue = mobileFilters.querySelector(`[data-picker-value="${CSS.escape(input.name)}"]`);
        const option = mobileFilters.querySelector(`[data-picker-panel="${CSS.escape(input.name)}"] [data-picker-option][data-value="${CSS.escape(input.value)}"]`);
        const label = input.dataset.selectedLabel || option?.dataset.label || rowValue?.textContent?.trim() || '';

        if (rowValue) {
            rowValue.textContent = label;
            rowValue.classList.toggle('is-active', Boolean(input.value));
        }

        mobileFilters.querySelectorAll(`[data-picker-panel="${CSS.escape(input.name)}"] [data-picker-option]`).forEach((pickerOption) => {
            pickerOption.setAttribute('aria-checked', (pickerOption.dataset.value === input.value).toString());
            pickerOption.classList.toggle('is-selected', pickerOption.dataset.value === input.value);
        });
    });
};

const updateToolbarUi = (toolbar) => {
    const mobileFilters = toolbar.querySelector('[data-toolbar-mobile-filters]');

    if (! mobileFilters) {
        return;
    }

    const timezone = mobileFilters.querySelector('[data-business-timezone]')?.dataset.businessTimezone || 'Asia/Jakarta';
    const entries = readFilterEntries(mobileFilters, timezone);
    const count = entries.length;
    const countBadge = toolbar.querySelector('[data-filter-count]');
    const selectedLabel = toolbar.querySelector('[data-filter-selected-label]');
    const applyButton = toolbar.querySelector('[data-mobile-filter-apply]');

    if (countBadge) {
        countBadge.textContent = count.toString();
        countBadge.hidden = count === 0;
    }

    if (selectedLabel) {
        selectedLabel.textContent = `${count} filter dipilih`;
    }

    if (applyButton) {
        applyButton.textContent = count ? `Terapkan ${count} filter` : 'Terapkan';
    }

    updateActiveFilterLinks(toolbar, entries);
    updateDateUi(toolbar, mobileFilters);
    updatePickerUi(mobileFilters);

    mobileFilters.querySelectorAll('[data-mobile-filter-chip]').forEach((button) => {
        const input = mobileFilters.querySelector(`[name="${CSS.escape(button.dataset.mobileFilterChip)}"]`);
        const selected = input?.value === button.dataset.value;
        button.setAttribute('aria-pressed', selected.toString());
        button.classList.toggle('is-active', selected);
    });

    mobileFilters.querySelectorAll('[data-mobile-switch]').forEach((button) => {
        const input = mobileFilters.querySelector(`[name="${CSS.escape(button.dataset.mobileSwitch)}"]`);
        const selected = Boolean(input?.checked);
        button.setAttribute('aria-checked', selected.toString());
        button.classList.toggle('is-active', selected);
    });
};

const setDatePreset = (mobileFilters, presetName) => {
    const dateControls = getDateControls(mobileFilters);
    const timezone = mobileFilters.querySelector('[data-business-timezone]')?.dataset.businessTimezone || 'Asia/Jakarta';
    const values = datePresets(timezone)[presetName] || ['', ''];

    if (dateControls.from) {
        dateControls.from.value = values[0];
    }

    if (dateControls.to) {
        dateControls.to.value = values[1];
    }
};

const openPicker = (toolbar, name) => {
    const sheet = toolbar.querySelector('[data-mobile-filter-sheet]');
    const main = toolbar.querySelector('[data-mobile-filter-main]');
    const panel = toolbar.querySelector(`[data-picker-panel="${CSS.escape(name)}"]`);

    if (! sheet || ! main || ! panel) {
        return;
    }

    sheet.classList.add('is-picker-open');
    toolbar.querySelector('[data-mobile-filter-header]').hidden = true;
    toolbar.querySelector('[data-mobile-filter-body]').classList.add('is-picker-body');
    toolbar.querySelector('[data-mobile-filter-sheet-footer]').hidden = true;
    main.hidden = true;
    panel.hidden = false;
    panel.classList.add('is-active');
    panel.querySelector('[data-picker-search]')?.focus();
};

const closePicker = (toolbar) => {
    const sheet = toolbar.querySelector('[data-mobile-filter-sheet]');
    const main = toolbar.querySelector('[data-mobile-filter-main]');
    const panel = toolbar.querySelector('[data-picker-panel].is-active');

    if (! sheet || ! main) {
        return;
    }

    sheet.classList.remove('is-picker-open');
    toolbar.querySelector('[data-mobile-filter-header]').hidden = false;
    toolbar.querySelector('[data-mobile-filter-body]').classList.remove('is-picker-body');
    toolbar.querySelector('[data-mobile-filter-sheet-footer]').hidden = false;
    main.hidden = false;

    if (panel) {
        panel.hidden = true;
        panel.classList.remove('is-active');
    }
};

const lockPageScroll = (locked) => {
    document.documentElement.classList.toggle('mobile-filter-open', locked);
    document.body.classList.toggle('mobile-filter-open', locked);
};

const closeFilterSheet = (toolbar, restore = true) => {
    const sheet = toolbar.querySelector('[data-mobile-filter-sheet]');
    const backdrop = toolbar.querySelector('[data-mobile-filter-backdrop]');
    const mobileFilters = toolbar.querySelector('[data-toolbar-mobile-filters]');

    if (! sheet || ! mobileFilters) {
        return;
    }

    if (sheet.classList.contains('is-picker-open')) {
        closePicker(toolbar);
    }

    if (restore && toolbar._mobileFilterSnapshot) {
        restoreControlState(toolbar._mobileFilterSnapshot);
    }

    sheet.hidden = true;
    backdrop.hidden = true;
    sheet.classList.remove('is-open');
    toolbar.querySelector('[data-mobile-filter-open]')?.setAttribute('aria-expanded', 'false');
    lockPageScroll(false);
    updateToolbarUi(toolbar);

    if (document.activeElement !== document.body) {
        toolbar.querySelector('[data-mobile-filter-open]')?.focus();
    }
};

const openFilterSheet = (toolbar) => {
    const sheet = toolbar.querySelector('[data-mobile-filter-sheet]');
    const backdrop = toolbar.querySelector('[data-mobile-filter-backdrop]');
    const mobileFilters = toolbar.querySelector('[data-toolbar-mobile-filters]');

    if (! sheet || ! mobileFilters || ! mobileFilterMedia.matches) {
        return;
    }

    toolbar._mobileFilterSnapshot = captureControlState(mobileFilters);
    sheet.hidden = false;
    backdrop.hidden = false;
    sheet.classList.add('is-open');
    toolbar.querySelector('[data-mobile-filter-open]')?.setAttribute('aria-expanded', 'true');
    lockPageScroll(true);
    updateToolbarUi(toolbar);
    sheet.querySelector('[data-mobile-filter-close]')?.focus();
};

const trapFocus = (event, sheet) => {
    if (event.key !== 'Tab') {
        return;
    }

    const focusable = [...sheet.querySelectorAll('button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), a[href]')]
        .filter((element) => ! element.hidden && element.offsetParent !== null);

    if (! focusable.length) {
        return;
    }

    const first = focusable[0];
    const last = focusable.at(-1);

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (! event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
};

const syncResponsiveControls = () => {
    document.querySelectorAll('[data-mobile-filter-form]').forEach((toolbar) => {
        const desktop = toolbar.querySelector('[data-toolbar-desktop-filters]');
        const mobile = toolbar.querySelector('[data-toolbar-mobile-filters]');
        const isMobile = mobileFilterMedia.matches;

        desktop?.querySelectorAll('input, select, textarea').forEach((control) => {
            control.disabled = isMobile;
        });
        mobile?.querySelectorAll('input, select, textarea').forEach((control) => {
            control.disabled = ! isMobile;
        });

        updateToolbarUi(toolbar);
    });
};

const initializeToolbar = (toolbar) => {
    const mobileFilters = toolbar.querySelector('[data-toolbar-mobile-filters]');

    if (! mobileFilters) {
        return;
    }

    toolbar.addEventListener('click', (event) => {
        const openButton = event.target.closest('[data-mobile-filter-open]');
        const closeButton = event.target.closest('[data-mobile-filter-close]');
        const resetButton = event.target.closest('[data-mobile-filter-reset]');
        const datePreset = event.target.closest('[data-mobile-date-preset]');
        const filterChip = event.target.closest('[data-mobile-filter-chip]');
        const switchButton = event.target.closest('[data-mobile-switch]');
        const pickerRow = event.target.closest('[data-open-picker]');
        const pickerBack = event.target.closest('[data-picker-back]');
        const pickerOption = event.target.closest('[data-picker-option]');
        const expandStatuses = event.target.closest('[data-expand-statuses]');

        if (openButton) {
            openFilterSheet(toolbar);
        } else if (closeButton || event.target.matches('[data-mobile-filter-backdrop]')) {
            closeFilterSheet(toolbar);
        } else if (resetButton) {
            clearControlState(mobileFilters);
            updateToolbarUi(toolbar);
        } else if (datePreset) {
            setDatePreset(mobileFilters, datePreset.dataset.mobileDatePreset);
            updateToolbarUi(toolbar);
        } else if (filterChip) {
            const input = mobileFilters.querySelector(`[name="${CSS.escape(filterChip.dataset.mobileFilterChip)}"]`);
            if (input) {
                input.value = filterChip.dataset.value;
                delete input.dataset.selectedLabel;
                updateToolbarUi(toolbar);
            }
        } else if (switchButton) {
            const input = mobileFilters.querySelector(`[name="${CSS.escape(switchButton.dataset.mobileSwitch)}"]`);
            if (input) {
                input.checked = ! input.checked;
                updateToolbarUi(toolbar);
            }
        } else if (pickerRow) {
            openPicker(toolbar, pickerRow.dataset.openPicker);
        } else if (pickerBack) {
            closePicker(toolbar);
        } else if (pickerOption) {
            const panel = pickerOption.closest('[data-picker-panel]');
            const input = mobileFilters.querySelector(`[name="${CSS.escape(panel.dataset.pickerPanel)}"]`);
            if (input) {
                input.value = pickerOption.dataset.value;
                input.dataset.selectedLabel = pickerOption.dataset.label;
                closePicker(toolbar);
                updateToolbarUi(toolbar);
                toolbar.querySelector(`[data-open-picker="${CSS.escape(input.name)}"]`)?.focus();
            }
        } else if (expandStatuses) {
            const secondary = mobileFilters.querySelector('[data-secondary-statuses]');
            const expanded = expandStatuses.getAttribute('aria-expanded') === 'true';
            if (secondary) {
                secondary.hidden = expanded;
                expandStatuses.setAttribute('aria-expanded', (! expanded).toString());
            }
        }
    });

    toolbar.addEventListener('input', (event) => {
        const search = event.target.closest('[data-picker-search]');
        if (! search) {
            updateToolbarUi(toolbar);
            return;
        }

        const query = search.value.trim().toLowerCase();
        search.closest('[data-picker-panel]')?.querySelectorAll('[data-picker-option], [data-picker-group]').forEach((option) => {
            const text = option.dataset.searchText || option.textContent || '';
            option.hidden = Boolean(query) && ! text.toLowerCase().includes(query);
        });
    });

    toolbar.addEventListener('change', (event) => {
        if (mobileFilterMedia.matches) {
            updateToolbarUi(toolbar);
            return;
        }

        if (event.target.closest('[data-toolbar-desktop-filters]')) {
            HTMLFormElement.prototype.submit.call(toolbar);
        }
    });

    toolbar.addEventListener('submit', () => {
        if (mobileFilterMedia.matches) {
            closeFilterSheet(toolbar, false);
        }
    });

    toolbar.querySelector('[data-mobile-filter-sheet]')?.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            closeFilterSheet(toolbar);
        } else {
            trapFocus(event, toolbar.querySelector('[data-mobile-filter-sheet]'));
        }
    });

    updateToolbarUi(toolbar);
};

const initializeMobileFilters = () => {
    document.querySelectorAll('[data-mobile-filter-form]').forEach(initializeToolbar);
    syncResponsiveControls();
    mobileFilterMedia.addEventListener('change', syncResponsiveControls);

    if (! window._oasisMobileFilterEscapeBound) {
        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') {
                return;
            }

            const openSheet = document.querySelector('[data-mobile-filter-sheet].is-open');
            const toolbar = openSheet?.closest('[data-mobile-filter-form]');

            if (! toolbar) {
                return;
            }

            event.preventDefault();
            closeFilterSheet(toolbar);
        }, true);
        window._oasisMobileFilterEscapeBound = true;
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeMobileFilters, { once: true });
} else {
    initializeMobileFilters();
}

export default initializeMobileFilters;
