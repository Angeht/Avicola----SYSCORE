const sidebar = document.querySelector('[data-sidebar]');
const sidebarBackdrop = document.querySelector('[data-sidebar-backdrop]');
const sidebarToggle = document.querySelector('[data-sidebar-toggle]');

const setSidebarOpen = (isOpen) => {
    if (!sidebar || !sidebarBackdrop || !sidebarToggle) {
        return;
    }

    sidebar.dataset.open = String(isOpen);
    sidebarBackdrop.dataset.open = String(isOpen);
    sidebarToggle.setAttribute('aria-expanded', String(isOpen));
    document.body.classList.toggle('overflow-hidden', isOpen);
};

sidebarToggle?.addEventListener('click', () => {
    setSidebarOpen(sidebar?.dataset.open !== 'true');
});

sidebarBackdrop?.addEventListener('click', () => setSidebarOpen(false));

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        setSidebarOpen(false);
    }
});

document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const inputId = button.getAttribute('aria-controls');
        const input = inputId ? document.getElementById(inputId) : null;

        if (!(input instanceof HTMLInputElement)) {
            return;
        }

        const isVisible = input.type === 'text';
        input.type = isVisible ? 'password' : 'text';
        button.setAttribute('aria-pressed', String(!isVisible));
        button.querySelector('[data-show-label]')?.classList.toggle('hidden', !isVisible);
        button.querySelector('[data-hide-label]')?.classList.toggle('hidden', isVisible);
    });
});

document.querySelectorAll('[data-dismiss]').forEach((button) => {
    button.addEventListener('click', () => button.closest('[role="status"]')?.remove());
});

document.querySelectorAll('[data-digits-only]').forEach((input) => {
    if (!(input instanceof HTMLInputElement)) {
        return;
    }

    const keepOnlyDigits = () => {
        input.value = input.value.replace(/[^0-9]/g, '').slice(0, input.maxLength);
    };

    input.addEventListener('input', keepOnlyDigits);
    keepOnlyDigits();
});

document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        const message = form.dataset.confirm;

        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    });
});

document.querySelectorAll('[data-document-type]').forEach((select) => {
    const form = select.closest('form');
    const documentNumber = form?.querySelector('[data-document-number]');

    if (!(select instanceof HTMLSelectElement) || !(documentNumber instanceof HTMLInputElement)) {
        return;
    }

    const synchronizeDocumentLimit = () => {
        const selectedOption = select.options[select.selectedIndex];
        const maximumLength = Number.parseInt(selectedOption?.dataset.max ?? '20', 10);

        documentNumber.maxLength = Number.isNaN(maximumLength) ? 20 : maximumLength;
        documentNumber.disabled = select.value === '';

        if (documentNumber.disabled) {
            documentNumber.value = '';
        }
    };

    select.addEventListener('change', synchronizeDocumentLimit);
    synchronizeDocumentLimit();
});

document.querySelectorAll('[data-cash-close]').forEach((form) => {
    const countedCashInput = form.querySelector('[data-counted-cash]');
    const differenceOutput = form.querySelector('[data-cash-difference]');
    const differencePanel = form.querySelector('[data-cash-difference-panel]');
    const expectedCash = Number.parseFloat(form.dataset.expectedCash ?? '0');

    if (!(countedCashInput instanceof HTMLInputElement) || !(differenceOutput instanceof HTMLElement) || !(differencePanel instanceof HTMLElement)) {
        return;
    }

    const synchronizeDifference = () => {
        const countedCash = Number.parseFloat(countedCashInput.value || '0');
        const difference = Math.round(((Number.isNaN(countedCash) ? 0 : countedCash) - expectedCash) * 100) / 100;
        const isBalanced = difference === 0;

        differenceOutput.textContent = `S/ ${difference.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        differenceOutput.classList.toggle('text-signal', isBalanced);
        differenceOutput.classList.toggle('text-danger', !isBalanced);
        differencePanel.classList.toggle('border-signal', isBalanced);
        differencePanel.classList.toggle('bg-signal-soft', isBalanced);
        differencePanel.classList.toggle('border-danger', !isBalanced);
        differencePanel.classList.toggle('bg-danger-soft', !isBalanced);
    };

    countedCashInput.addEventListener('input', synchronizeDifference);
    synchronizeDifference();
});

document.querySelectorAll('[data-load-form]').forEach((form) => {
    const weighingsContainer = form.querySelector('[data-weighings]');
    const weighingTemplate = form.querySelector('[data-weighing-template]');
    const addWeighingButton = form.querySelector('[data-add-weighing]');
    const totalWeighingsOutput = form.querySelector('[data-total-weighings]');
    const totalBirdsOutput = form.querySelector('[data-total-birds]');
    const totalNetWeightOutput = form.querySelector('[data-total-net-weight]');
    const totalCostOutput = form.querySelector('[data-total-cost]');
    const emptyWeighingsState = form.querySelector('[data-weighings-empty]');
    const saveWeighingsButton = form.querySelector('[data-save-weighings]');

    if (!(weighingsContainer instanceof HTMLElement) || !(weighingTemplate instanceof HTMLTemplateElement) || !(addWeighingButton instanceof HTMLButtonElement)) {
        return;
    }

    const numberValue = (value) => {
        const parsedValue = Number.parseFloat(String(value || '0').replace(',', '.'));

        return Number.isNaN(parsedValue) ? 0 : parsedValue;
    };

    const formatKilograms = (value) => `${value.toLocaleString('es-PE', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 3,
    })} kg`;
    const formatTareInput = (value) => value.toFixed(3).replace(/\.?0+$/, '');
    const formatMoney = (value) => `S/ ${value.toLocaleString('es-PE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
    const costPerKilogram = numberValue(form.dataset.costPerKg);
    const existingNetWeight = numberValue(form.dataset.existingNetWeight);

    const synchronizeTotals = () => {
        const rows = [...weighingsContainer.querySelectorAll('[data-weighing-row]')];
        const birds = rows.reduce((total, row) => total + Math.max(0, Math.trunc(numberValue(row.querySelector('[data-birds]')?.value))), 0);
        const netWeight = rows.reduce((total, row) => {
            const crates = Math.max(0, Math.trunc(numberValue(row.querySelector('[data-crates]')?.value)));
            const grossWeight = numberValue(row.querySelector('[data-gross-weight]')?.value);
            const tare = numberValue(row.querySelector('[data-tare]')?.value);

            return total + (grossWeight - (crates * tare));
        }, 0);

        if (totalWeighingsOutput instanceof HTMLElement) {
            totalWeighingsOutput.textContent = String(rows.length);
        }

        if (totalBirdsOutput instanceof HTMLElement) {
            totalBirdsOutput.textContent = birds.toLocaleString('es-PE');
        }

        if (totalNetWeightOutput instanceof HTMLElement) {
            totalNetWeightOutput.textContent = formatKilograms(netWeight);
            totalNetWeightOutput.classList.toggle('text-danger', netWeight < 0);
        }

        if (totalCostOutput instanceof HTMLElement) {
            const accumulatedNetWeight = Math.max(0, existingNetWeight + netWeight);

            totalCostOutput.textContent = formatMoney(accumulatedNetWeight * costPerKilogram);
        }

        rows.forEach((row, index) => {
            const numberOutput = row.querySelector('[data-weighing-number]');

            if (numberOutput instanceof HTMLElement) {
                numberOutput.textContent = String(index + 1);
            }

            row.querySelectorAll('[name^="pesajes["]').forEach((field) => {
                field.name = field.name.replace(/pesajes\[[^\]]+\]/, `pesajes[${index}]`);
            });

            row.querySelectorAll('[id^="pesajes-"]').forEach((field) => {
                const oldId = field.id;
                const newId = oldId.replace(/^pesajes-[^-]+-/, `pesajes-${index}-`);
                const label = row.querySelector(`label[for="${oldId}"]`);

                field.id = newId;

                if (label instanceof HTMLLabelElement) {
                    label.htmlFor = newId;
                }
            });
        });

        addWeighingButton.disabled = rows.length >= 50;
        emptyWeighingsState?.classList.toggle('hidden', rows.length > 0);

        if (saveWeighingsButton instanceof HTMLButtonElement) {
            saveWeighingsButton.disabled = rows.length === 0;
        }
    };

    const synchronizeRow = (row) => {
        if (!(row instanceof HTMLElement)) {
            return;
        }

        const cratesInput = row.querySelector('[data-crates]');
        const crateTypeSelect = row.querySelector('[data-crate-type]');
        const tareInput = row.querySelector('[data-tare]');
        const totalTareOutput = row.querySelector('[data-total-tare]');
        const netWeightOutput = row.querySelector('[data-net-weight]');

        if (!(cratesInput instanceof HTMLInputElement) || !(crateTypeSelect instanceof HTMLSelectElement) || !(tareInput instanceof HTMLInputElement)) {
            return;
        }

        const crates = Math.max(0, Math.trunc(numberValue(cratesInput.value)));
        const grossWeight = numberValue(row.querySelector('[data-gross-weight]')?.value);
        const tare = numberValue(tareInput.value);
        const totalTare = crates * tare;
        const netWeight = grossWeight - totalTare;

        if (totalTareOutput instanceof HTMLElement) {
            totalTareOutput.textContent = formatKilograms(totalTare);
        }

        if (netWeightOutput instanceof HTMLElement) {
            netWeightOutput.textContent = formatKilograms(netWeight);
            netWeightOutput.classList.toggle('text-danger', netWeight < 0);
            netWeightOutput.classList.toggle('text-signal', netWeight > 0);
        }

        synchronizeTotals();
    };

    const weighingRowFromEvent = (event) => event.target instanceof Element
        ? event.target.closest('[data-weighing-row]')
        : null;

    const applyReferenceTare = (target, row) => {
        if (!(target instanceof HTMLSelectElement) || !target.matches('[data-crate-type]') || !(row instanceof HTMLElement)) {
            return;
        }

        const tareInput = row.querySelector('[data-tare]');
        const selectedOption = target.options[target.selectedIndex];
        const referenceTare = numberValue(selectedOption?.getAttribute('data-reference-tare'));

        if (tareInput instanceof HTMLInputElement) {
            tareInput.value = target.value !== ''
                ? formatTareInput(referenceTare)
                : '0';
        }
    };

    weighingsContainer.addEventListener('input', (event) => {
        const row = weighingRowFromEvent(event);

        applyReferenceTare(event.target, row);
        synchronizeRow(row);
    });

    weighingsContainer.addEventListener('change', (event) => {
        const row = weighingRowFromEvent(event);

        if (!(row instanceof HTMLElement)) {
            return;
        }

        applyReferenceTare(event.target, row);
        synchronizeRow(row);
    });

    weighingsContainer.addEventListener('focusout', (event) => {
        const row = weighingRowFromEvent(event);

        if (!(event.target instanceof HTMLInputElement) || !event.target.matches('[data-tare]') || event.target.value.trim() === '') {
            return;
        }

        event.target.value = formatTareInput(numberValue(event.target.value));
        synchronizeRow(row);
    });

    weighingsContainer.addEventListener('click', (event) => {
        if (!(event.target instanceof Element)) {
            return;
        }

        const removeButton = event.target.closest('[data-remove-weighing]');

        if (!(removeButton instanceof HTMLButtonElement) || !weighingsContainer.contains(removeButton)) {
            return;
        }

        removeButton.closest('[data-weighing-row]')?.remove();
        synchronizeTotals();
    });

    weighingsContainer.querySelectorAll('[data-weighing-row]').forEach(synchronizeRow);

    addWeighingButton.addEventListener('click', () => {
        const index = weighingsContainer.querySelectorAll('[data-weighing-row]').length;

        if (index >= 50) {
            return;
        }

        const fragment = weighingTemplate.content.cloneNode(true);
        const temporaryContainer = document.createElement('div');
        temporaryContainer.append(fragment);
        temporaryContainer.innerHTML = temporaryContainer.innerHTML.replaceAll('__INDEX__', String(index));
        const row = temporaryContainer.firstElementChild;

        if (!(row instanceof HTMLElement)) {
            return;
        }

        weighingsContainer.append(row);
        synchronizeRow(row);
        row.querySelector('input:not([disabled]), select:not([disabled])')?.focus();
        synchronizeTotals();
    });

    synchronizeTotals();
});

document.querySelectorAll('[data-edit-weighing-form]').forEach((form) => {
    const cratesInput = form.querySelector('[data-crates]');
    const crateTypeSelect = form.querySelector('[data-crate-type]');
    const tareInput = form.querySelector('[data-tare]');
    const grossWeightInput = form.querySelector('[data-gross-weight]');
    const totalTareOutput = form.querySelector('[data-edit-total-tare]');
    const netWeightOutput = form.querySelector('[data-edit-net-weight]');

    if (!(cratesInput instanceof HTMLInputElement)
        || !(crateTypeSelect instanceof HTMLSelectElement)
        || !(tareInput instanceof HTMLInputElement)
        || !(grossWeightInput instanceof HTMLInputElement)) {
        return;
    }

    const numberValue = (value) => {
        const parsedValue = Number.parseFloat(String(value || '0').replace(',', '.'));

        return Number.isNaN(parsedValue) ? 0 : parsedValue;
    };
    const formatKilograms = (value) => `${value.toLocaleString('es-PE', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 3,
    })} kg`;
    const formatTareInput = (value) => value.toFixed(3).replace(/\.?0+$/, '');

    const synchronizePreview = () => {
        const crates = Math.max(0, Math.trunc(numberValue(cratesInput.value)));
        const tare = crates > 0 ? numberValue(tareInput.value) : 0;
        const grossWeight = numberValue(grossWeightInput.value);
        const totalTare = crates * tare;
        const netWeight = grossWeight - totalTare;

        crateTypeSelect.required = crates > 0;
        tareInput.required = crates > 0;

        totalTareOutput?.replaceChildren(formatKilograms(totalTare));
        netWeightOutput?.replaceChildren(formatKilograms(netWeight));
        netWeightOutput?.classList.toggle('text-danger', netWeight <= 0);
        netWeightOutput?.classList.toggle('text-ink-950', netWeight > 0);
    };

    crateTypeSelect.addEventListener('change', () => {
        const selectedOption = crateTypeSelect.options[crateTypeSelect.selectedIndex];
        const referenceTare = numberValue(selectedOption?.dataset.referenceTare);

        tareInput.value = crateTypeSelect.value === '' ? '0' : formatTareInput(referenceTare);
        synchronizePreview();
    });
    [cratesInput, tareInput, grossWeightInput].forEach((input) => input.addEventListener('input', synchronizePreview));
    tareInput.addEventListener('blur', () => {
        if (tareInput.value.trim() !== '') {
            tareInput.value = formatTareInput(numberValue(tareInput.value));
            synchronizePreview();
        }
    });
    synchronizePreview();
});

document.querySelectorAll('[data-provider-payment-form]').forEach((form) => {
    const providerSelect = form.querySelector('[data-payment-provider]');
    const paymentMethodSelect = form.querySelector('[data-payment-method]');
    const amountInput = form.querySelector('[data-payment-amount]');
    const fullBalanceButton = form.querySelector('[data-use-full-balance]');
    const cashRequirement = form.querySelector('[data-cash-requirement]');
    const previewProvider = document.querySelector('[data-preview-provider]');
    const previewLoads = document.querySelector('[data-preview-loads]');
    const previewAccount = document.querySelector('[data-preview-account]');
    const previewBalance = document.querySelector('[data-preview-balance]');
    const previewRemaining = document.querySelector('[data-preview-remaining]');
    const providerLoadRows = form.querySelectorAll('[data-provider-load-row]');
    const providerLoadCount = form.querySelector('[data-provider-load-count]');
    const providerLoadsEmpty = form.querySelector('[data-provider-loads-empty]');
    const discountToggle = form.querySelector('[data-provider-discount-toggle]');
    const discountFields = form.querySelector('[data-provider-discount-fields]');
    const discountAmountInput = form.querySelector('[data-provider-discount-amount]');
    const discountReasonInput = form.querySelector('[data-provider-discount-reason]');
    const discountPreview = document.querySelector('[data-provider-discount-preview]');
    const discountPreviewAmount = document.querySelector('[data-provider-discount-preview-amount]');

    if (!(providerSelect instanceof HTMLSelectElement) || !(paymentMethodSelect instanceof HTMLSelectElement) || !(amountInput instanceof HTMLInputElement)) {
        return;
    }

    const numberValue = (value) => {
        const parsedValue = Number.parseFloat(String(value || '0').replace(',', '.'));

        return Number.isNaN(parsedValue) ? 0 : parsedValue;
    };
    const formatMoney = (value) => `S/ ${value.toLocaleString('es-PE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;

    const synchronizePayment = () => {
        const selectedProvider = providerSelect.options[providerSelect.selectedIndex];
        const selectedMethod = paymentMethodSelect.options[paymentMethodSelect.selectedIndex];
        const balance = numberValue(selectedProvider?.dataset.balance);
        const amount = Math.max(0, numberValue(amountInput.value));
        const hasDiscount = discountToggle instanceof HTMLInputElement && discountToggle.checked;
        const discount = hasDiscount ? Math.max(0, numberValue(discountAmountInput?.value)) : 0;
        const remaining = Math.round((balance - amount - discount) * 100) / 100;
        const hasProvider = providerSelect.value !== '';
        const isCash = selectedMethod?.dataset.cash === 'true';
        const hasOpenCash = form.dataset.hasOpenCash === 'true';

        amountInput.max = hasProvider ? Math.max(0, balance - discount).toFixed(2) : '999999999999.99';
        if (discountAmountInput instanceof HTMLInputElement) {
            discountAmountInput.max = hasProvider ? Math.max(0, balance - amount).toFixed(2) : '999999999999.99';
            discountAmountInput.disabled = !hasDiscount;
            discountAmountInput.required = hasDiscount;
        }
        if (discountReasonInput instanceof HTMLInputElement) {
            discountReasonInput.disabled = !hasDiscount;
            discountReasonInput.required = hasDiscount;
        }
        discountFields?.classList.toggle('hidden', !hasDiscount);
        discountPreview?.classList.toggle('hidden', !hasDiscount);
        discountPreviewAmount?.replaceChildren(formatMoney(discount));

        if (previewProvider instanceof HTMLElement) {
            previewProvider.textContent = hasProvider ? selectedProvider.dataset.name || 'Proveedor seleccionado' : 'Selecciona un proveedor';
        }

        if (previewLoads instanceof HTMLElement) {
            previewLoads.textContent = hasProvider ? `${selectedProvider.dataset.loads || '0'} pendiente(s)` : '—';
        }

        if (previewAccount instanceof HTMLElement) {
            previewAccount.textContent = hasProvider && selectedProvider.dataset.account
                ? selectedProvider.dataset.account
                : 'No registrada';
        }

        if (previewBalance instanceof HTMLElement) {
            previewBalance.textContent = formatMoney(balance);
        }

        if (previewRemaining instanceof HTMLElement) {
            previewRemaining.textContent = formatMoney(remaining);
            previewRemaining.classList.toggle('text-danger', remaining < 0);
            previewRemaining.classList.toggle('text-hazard', remaining >= 0);
        }

        let visibleLoads = 0;
        providerLoadRows.forEach((row) => {
            const isVisible = hasProvider && row.getAttribute('data-provider-id') === providerSelect.value;

            row.classList.toggle('hidden', !isVisible);
            visibleLoads += isVisible ? 1 : 0;
        });
        providerLoadCount?.replaceChildren(`${visibleLoads} ${visibleLoads === 1 ? 'carga' : 'cargas'}`);
        providerLoadsEmpty?.classList.toggle('hidden', visibleLoads > 0);

        cashRequirement?.classList.toggle('hidden', !isCash || hasOpenCash);
    };

    providerSelect.addEventListener('change', synchronizePayment);
    paymentMethodSelect.addEventListener('change', synchronizePayment);
    amountInput.addEventListener('input', synchronizePayment);
    discountToggle?.addEventListener('change', synchronizePayment);
    discountAmountInput?.addEventListener('input', synchronizePayment);
    fullBalanceButton?.addEventListener('click', () => {
        const selectedProvider = providerSelect.options[providerSelect.selectedIndex];

        if (providerSelect.value === '') {
            providerSelect.focus();

            return;
        }

        const discount = discountToggle instanceof HTMLInputElement && discountToggle.checked
            ? Math.max(0, numberValue(discountAmountInput?.value))
            : 0;
        amountInput.value = Math.max(0, numberValue(selectedProvider?.dataset.balance) - discount).toFixed(2);
        amountInput.focus();
        synchronizePayment();
    });

    synchronizePayment();
});

document.querySelectorAll('[data-sale-form]').forEach((form) => {
    const detailsContainer = form.querySelector('[data-sale-details]');
    const detailTemplate = form.querySelector('[data-sale-detail-template]');
    const addDetailButton = form.querySelector('[data-add-sale-detail]');
    const totalDetailsOutput = form.querySelector('[data-sale-total-details]');
    const totalBirdsOutput = form.querySelector('[data-sale-total-birds]');
    const totalKilogramsOutput = form.querySelector('[data-sale-total-kilograms]');
    const grandTotalOutput = form.querySelector('[data-sale-grand-total]');
    const canEditPrice = form.dataset.canEditPrice === 'true';

    if (!(detailsContainer instanceof HTMLElement) || !(detailTemplate instanceof HTMLTemplateElement) || !(addDetailButton instanceof HTMLButtonElement)) {
        return;
    }

    const numberValue = (value) => {
        const parsedValue = Number.parseFloat(String(value || '0').replace(',', '.'));

        return Number.isNaN(parsedValue) ? 0 : parsedValue;
    };
    const formatKilograms = (value) => `${value.toLocaleString('es-PE', { minimumFractionDigits: 3, maximumFractionDigits: 3 })} kg`;
    const formatMoney = (value) => `S/ ${value.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    const formatUnitPriceInput = (value) => numberValue(value).toFixed(4).replace(/0{1,2}$/, '');
    const detailIndex = (detail) => detail.querySelector('[data-sale-product]')?.name.match(/^detalles\[([^\]]+)\]/)?.[1] ?? '0';
    const weighingIndex = (weighing) => weighing.querySelector('[data-sale-crates]')?.name.match(/\[pesajes\]\[([^\]]+)\]/)?.[1] ?? '0';
    const detailSaleMode = (detail) => {
        const productSelect = detail.querySelector('[data-sale-product]');

        return productSelect instanceof HTMLSelectElement && productSelect.value !== ''
            ? productSelect.options[productSelect.selectedIndex]?.dataset.saleMode || 'PESAJE_VIVO'
            : 'PESAJE_VIVO';
    };

    let nextDetailIndex = [...detailsContainer.querySelectorAll('[data-sale-detail]')]
        .reduce((maximum, detail) => Math.max(maximum, Number.parseInt(detailIndex(detail), 10) || 0), -1) + 1;

    const weighingValues = (weighing) => {
        const crates = Math.max(0, Math.trunc(numberValue(weighing.querySelector('[data-sale-crates]')?.value)));
        const birds = Math.max(0, Math.trunc(numberValue(weighing.querySelector('[data-sale-birds]')?.value)));
        const grossWeight = numberValue(weighing.querySelector('[data-sale-gross-weight]')?.value);
        const tare = numberValue(weighing.querySelector('[data-sale-tare]')?.value);

        return { birds, netWeight: grossWeight - (crates * tare) };
    };

    const synchronizeAll = () => {
        const details = [...detailsContainer.querySelectorAll('[data-sale-detail]')];
        let totalBirds = 0;
        let totalKilograms = 0;
        let grandTotal = 0;

        details.forEach((detail, index) => {
            const rows = [...detail.querySelectorAll('[data-sale-weighing]')];
            const birds = rows.reduce((total, row) => total + weighingValues(row).birds, 0);
            const kilograms = rows.reduce((total, row) => total + weighingValues(row).netWeight, 0);
            const priceInput = detail.querySelector('[data-sale-price]');
            const price = numberValue(priceInput?.value);
            const subtotal = Math.round(kilograms * price * 100) / 100;
            const productSelect = detail.querySelector('[data-sale-product]');
            const selectedOption = productSelect instanceof HTMLSelectElement ? productSelect.options[productSelect.selectedIndex] : null;
            const isWeightOnly = selectedOption?.dataset.saleMode === 'SOLO_PESO';
            const availableBirds = numberValue(selectedOption?.dataset.birds);
            const availableKilograms = numberValue(selectedOption?.dataset.kilograms);
            const hasAvailableStock = isWeightOnly
                ? availableKilograms > 0
                : availableBirds > 0 && availableKilograms > 0;
            const exceedsStock = productSelect?.value !== '' && (
                !hasAvailableStock
                || (!isWeightOnly && birds > availableBirds)
                || kilograms > availableKilograms
            );
            const stockOutput = detail.querySelector('[data-sale-stock]');
            const birdsLabel = detail.querySelector('[data-detail-birds-label]');

            detail.querySelector('[data-sale-detail-number]')?.replaceChildren(String(index + 1));
            birdsLabel?.replaceChildren(isWeightOnly ? 'Modalidad' : 'Aves del producto');
            detail.querySelector('[data-detail-birds]')?.replaceChildren(isWeightOnly ? 'Solo kg' : birds.toLocaleString('es-PE'));
            detail.querySelector('[data-detail-kilograms]')?.replaceChildren(formatKilograms(kilograms));
            detail.querySelector('[data-detail-total]')?.replaceChildren(formatMoney(subtotal));

            if (stockOutput instanceof HTMLElement && selectedOption) {
                stockOutput.textContent = productSelect?.value === ''
                    ? 'Selecciona un producto para ver su disponibilidad.'
                    : isWeightOnly
                        ? `Disponible: ${formatKilograms(availableKilograms)} · venta solo por peso.`
                        : `Disponible: ${availableBirds.toLocaleString('es-PE')} aves · ${formatKilograms(availableKilograms)}`;
                stockOutput.classList.toggle('text-danger', exceedsStock);
                stockOutput.classList.toggle('font-semibold', exceedsStock);
                stockOutput.classList.toggle('text-steel-500', !exceedsStock);
            }

            detail.querySelector('[data-remove-sale-detail]')?.toggleAttribute('disabled', details.length === 1);
            rows.forEach((row, rowIndex) => {
                row.querySelector('[data-sale-weighing-number]')?.replaceChildren(String(rowIndex + 1));
                row.querySelector('[data-remove-sale-weighing]')?.toggleAttribute('disabled', rows.length === 1);
            });

            totalBirds += birds;
            totalKilograms += kilograms;
            grandTotal += subtotal;
        });

        const selectedPrices = details
            .map((detail) => detail.querySelector('[data-sale-product]')?.value)
            .filter(Boolean);
        details.forEach((detail) => {
            const select = detail.querySelector('[data-sale-product]');

            if (!(select instanceof HTMLSelectElement)) {
                return;
            }

            [...select.options].forEach((option) => {
                option.disabled = option.value !== '' && option.value !== select.value && selectedPrices.includes(option.value);
            });
        });

        totalDetailsOutput?.replaceChildren(String(details.length));
        totalBirdsOutput?.replaceChildren(totalBirds.toLocaleString('es-PE'));
        totalKilogramsOutput?.replaceChildren(formatKilograms(totalKilograms));
        grandTotalOutput?.replaceChildren(formatMoney(grandTotal));
        addDetailButton.disabled = details.length >= Math.min(20, detailTemplate.content.querySelectorAll('[data-sale-product] option').length - 1);
    };

    const bindWeighing = (detail, weighing) => {
        if (!(weighing instanceof HTMLElement) || weighing.dataset.bound === 'true') {
            return;
        }

        weighing.dataset.bound = 'true';
        const cratesInput = weighing.querySelector('[data-sale-crates]');
        const crateTypeSelect = weighing.querySelector('[data-sale-crate-type]');
        const tareInput = weighing.querySelector('[data-sale-tare]');
        const birdsInput = weighing.querySelector('[data-sale-birds]');
        const fieldsContainer = weighing.querySelector('[data-sale-weighing-fields]');
        const observationField = weighing.querySelector('[data-sale-observation-field]');
        const weighingDescription = weighing.querySelector('[data-sale-weighing-description]');
        const weightLabel = weighing.querySelector('[data-sale-weight-label]');
        const netWeightOutput = weighing.querySelector('[data-sale-net-weight]');

        if (!(cratesInput instanceof HTMLInputElement) || !(crateTypeSelect instanceof HTMLSelectElement) || !(tareInput instanceof HTMLInputElement) || !(birdsInput instanceof HTMLInputElement) || !(fieldsContainer instanceof HTMLElement) || !(netWeightOutput instanceof HTMLElement)) {
            return;
        }

        const synchronizeWeighing = () => {
            const isWeightOnly = detailSaleMode(detail) === 'SOLO_PESO';
            const crates = Math.max(0, Math.trunc(numberValue(cratesInput.value)));
            const usesCrates = crates > 0;

            weighing.querySelectorAll('[data-sale-live-field]').forEach((field) => field.classList.toggle('hidden', isWeightOnly));
            fieldsContainer.classList.toggle('xl:grid-cols-5', !isWeightOnly);
            fieldsContainer.classList.toggle('xl:grid-cols-4', isWeightOnly);
            observationField?.classList.toggle('xl:col-span-4', !isWeightOnly);
            weighingDescription?.replaceChildren(isWeightOnly ? 'Registro directo en kilogramos' : 'Peso bruto menos tara');
            weightLabel?.replaceChildren(isWeightOnly ? 'Peso vendido kg' : 'Peso bruto kg');

            crateTypeSelect.disabled = isWeightOnly || !usesCrates;
            tareInput.disabled = isWeightOnly || !usesCrates;
            birdsInput.min = isWeightOnly ? '0' : '1';

            if (isWeightOnly) {
                cratesInput.value = '0';
                birdsInput.value = '0';
                crateTypeSelect.value = '';
                tareInput.value = '0.000';
            } else if (!usesCrates) {
                crateTypeSelect.value = '';
                tareInput.value = '0.000';
            }

            const { netWeight } = weighingValues(weighing);
            netWeightOutput.textContent = formatKilograms(netWeight);
            netWeightOutput.classList.toggle('text-danger', netWeight < 0);
            synchronizeAll();
        };

        weighing.querySelectorAll('input, select').forEach((field) => {
            field.addEventListener('input', synchronizeWeighing);
            field.addEventListener('change', synchronizeWeighing);
        });
        weighing.addEventListener('sale-product-changed', synchronizeWeighing);
        crateTypeSelect.addEventListener('change', () => {
            if (crateTypeSelect.value !== '') {
                tareInput.value = crateTypeSelect.options[crateTypeSelect.selectedIndex]?.dataset.tare || '0.000';
            }

            synchronizeWeighing();
        });
        weighing.querySelector('[data-remove-sale-weighing]')?.addEventListener('click', () => {
            if (detail.querySelectorAll('[data-sale-weighing]').length <= 1) {
                return;
            }

            weighing.remove();
            synchronizeAll();
        });

        synchronizeWeighing();
    };

    const bindDetail = (detail) => {
        if (!(detail instanceof HTMLElement) || detail.dataset.bound === 'true') {
            return;
        }

        detail.dataset.bound = 'true';
        const productSelect = detail.querySelector('[data-sale-product]');
        const priceInput = detail.querySelector('[data-sale-price]');
        const reasonPanel = detail.querySelector('[data-price-reason-panel]');
        const reasonInput = detail.querySelector('[data-sale-price-reason]');
        const weighingsContainer = detail.querySelector('[data-sale-weighings]');
        const weighingTemplate = detail.querySelector('[data-sale-weighing-template]');
        const addWeighingButton = detail.querySelector('[data-add-sale-weighing]');

        if (!(productSelect instanceof HTMLSelectElement) || !(priceInput instanceof HTMLInputElement) || !(weighingsContainer instanceof HTMLElement) || !(weighingTemplate instanceof HTMLTemplateElement) || !(addWeighingButton instanceof HTMLButtonElement)) {
            return;
        }

        let nextWeighingIndex = [...weighingsContainer.querySelectorAll('[data-sale-weighing]')]
            .reduce((maximum, weighing) => Math.max(maximum, Number.parseInt(weighingIndex(weighing), 10) || 0), -1) + 1;

        const synchronizePrice = () => {
            const selectedOption = productSelect.options[productSelect.selectedIndex];
            const referencePrice = numberValue(selectedOption?.dataset.price);

            const hasAdjustment = productSelect.value !== '' && Math.round(numberValue(priceInput.value) * 10000) !== Math.round(referencePrice * 10000);

            reasonPanel?.classList.toggle('hidden', !canEditPrice || !hasAdjustment);

            if (reasonInput instanceof HTMLInputElement) {
                reasonInput.disabled = !canEditPrice || !hasAdjustment;
                reasonInput.required = canEditPrice && hasAdjustment;
            }

            synchronizeAll();
        };

        productSelect.addEventListener('change', () => {
            const referencePrice = numberValue(productSelect.options[productSelect.selectedIndex]?.dataset.price);
            priceInput.value = productSelect.value === '' ? '' : formatUnitPriceInput(referencePrice);
            weighingsContainer.querySelectorAll('[data-sale-weighing]').forEach((weighing) => {
                weighing.dispatchEvent(new Event('sale-product-changed'));
            });
            synchronizePrice();
        });
        priceInput.addEventListener('input', synchronizePrice);
        detail.querySelector('[data-remove-sale-detail]')?.addEventListener('click', () => {
            if (detailsContainer.querySelectorAll('[data-sale-detail]').length <= 1) {
                return;
            }

            detail.remove();
            synchronizeAll();
        });
        addWeighingButton.addEventListener('click', () => {
            if (weighingsContainer.querySelectorAll('[data-sale-weighing]').length >= 50) {
                return;
            }

            const temporaryContainer = document.createElement('div');
            temporaryContainer.innerHTML = weighingTemplate.innerHTML.replaceAll('__WEIGHING__', String(nextWeighingIndex));
            nextWeighingIndex += 1;
            const weighing = temporaryContainer.firstElementChild;

            if (!(weighing instanceof HTMLElement)) {
                return;
            }

            weighingsContainer.append(weighing);
            bindWeighing(detail, weighing);
            const focusTarget = detailSaleMode(detail) === 'SOLO_PESO'
                ? weighing.querySelector('[data-sale-gross-weight]')
                : weighing.querySelector('input:not([disabled]), select:not([disabled])');

            focusTarget?.focus();
        });

        weighingsContainer.querySelectorAll('[data-sale-weighing]').forEach((weighing) => bindWeighing(detail, weighing));
        synchronizePrice();
    };

    detailsContainer.querySelectorAll('[data-sale-detail]').forEach(bindDetail);
    addDetailButton.addEventListener('click', () => {
        const temporaryContainer = document.createElement('div');
        temporaryContainer.innerHTML = detailTemplate.innerHTML.replaceAll('__DETAIL__', String(nextDetailIndex));
        nextDetailIndex += 1;
        const detail = temporaryContainer.firstElementChild;

        if (!(detail instanceof HTMLElement)) {
            return;
        }

        detailsContainer.append(detail);
        bindDetail(detail);
        detail.querySelector('[data-sale-product]')?.focus();
        synchronizeAll();
    });

    synchronizeAll();
});

document.querySelectorAll('[data-collection-form]').forEach((form) => {
    const clientSelect = form.querySelector('[data-collection-client]');
    const paymentMethodSelect = form.querySelector('[data-collection-payment-method]');
    const totalInput = form.querySelector('[data-collection-total]');
    const useClientDebtButton = form.querySelector('[data-use-client-debt]');
    const cashRequirement = form.querySelector('[data-collection-cash-requirement]');
    const currentDebt = form.querySelector('[data-collection-current-debt]');
    const pendingSalesOutput = form.querySelector('[data-collection-pending-sales]');
    const previewDebt = document.querySelector('[data-collection-preview-debt]');
    const previewPayment = document.querySelector('[data-collection-preview-payment]');
    const previewRemaining = document.querySelector('[data-collection-preview-remaining]');
    const previewClient = document.querySelector('[data-collection-preview-client]');
    const previewMessage = document.querySelector('[data-collection-preview-message]');
    const roundingPanel = form.querySelector('[data-collection-rounding-panel]');
    const roundingInput = form.querySelector('[data-collection-rounding]');
    const roundingCashOutput = form.querySelector('[data-collection-rounding-cash]');
    const roundingAmountOutput = form.querySelector('[data-collection-rounding-amount]');

    if (!(clientSelect instanceof HTMLSelectElement)
        || !(paymentMethodSelect instanceof HTMLSelectElement)
        || !(totalInput instanceof HTMLInputElement)
        || !(useClientDebtButton instanceof HTMLButtonElement)) {
        return;
    }

    const numberValue = (value) => {
        const parsedValue = Number.parseFloat(String(value || '0').replace(',', '.'));

        return Number.isNaN(parsedValue) ? 0 : parsedValue;
    };
    const formatMoney = (value) => `S/ ${value.toLocaleString('es-PE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;

    const synchronizeAll = () => {
        const selectedClient = clientSelect.options[clientSelect.selectedIndex];
        const debt = numberValue(selectedClient?.dataset.debt);
        const pendingSales = numberValue(selectedClient?.dataset.pendingSales);
        const payment = Math.max(0, numberValue(totalInput.value));
        const remaining = Math.round((debt - payment) * 100) / 100;
        const selectedMethod = paymentMethodSelect.options[paymentMethodSelect.selectedIndex];
        const isCash = selectedMethod?.dataset.cash === 'true';
        const canRound = form.dataset.canRound === 'true';
        const roundingAvailable = canRound && isCash && remaining >= 0.01 && remaining <= 0.10;
        const roundsBalance = roundingAvailable && roundingInput instanceof HTMLInputElement && roundingInput.checked;

        totalInput.max = clientSelect.value === '' ? '999999999999.99' : debt.toFixed(2);
        useClientDebtButton.disabled = clientSelect.value === '' || debt <= 0;
        currentDebt?.replaceChildren(formatMoney(debt));
        pendingSalesOutput?.replaceChildren(Math.trunc(pendingSales).toLocaleString('es-PE'));
        previewDebt?.replaceChildren(formatMoney(debt));
        previewPayment?.replaceChildren(formatMoney(payment));
        previewRemaining?.replaceChildren(formatMoney(roundsBalance ? 0 : Math.max(0, remaining)));
        previewRemaining?.classList.toggle('text-danger', remaining < 0);
        previewRemaining?.classList.toggle('text-hazard', remaining >= 0);
        previewClient?.replaceChildren(clientSelect.value === '' ? 'Selecciona un cliente' : selectedClient.textContent.split(' · ')[0]);
        roundingPanel?.classList.toggle('hidden', !roundingAvailable);
        roundingCashOutput?.replaceChildren(formatMoney(payment));
        roundingAmountOutput?.replaceChildren(formatMoney(Math.max(0, remaining)));

        if (roundingInput instanceof HTMLInputElement) {
            roundingInput.disabled = !roundingAvailable;

            if (!roundingAvailable) {
                roundingInput.checked = false;
            }
        }

        if (previewMessage instanceof HTMLElement) {
            previewMessage.textContent = clientSelect.value === ''
                ? 'Selecciona un cliente para consultar su deuda.'
                : remaining < 0
                    ? 'El monto recibido supera la deuda actual del cliente.'
                    : payment === 0
                        ? 'Ingresa el monto recibido para calcular el saldo restante.'
                        : roundsBalance
                            ? `Se recibirán ${formatMoney(payment)} y ${formatMoney(remaining)} cerrarán la cuenta como redondeo.`
                            : remaining === 0
                            ? 'Este pago cancelará toda la deuda del cliente.'
                            : `Después del abono quedarán ${formatMoney(remaining)} pendientes.`;
            previewMessage.classList.toggle('text-danger', remaining < 0);
            previewMessage.classList.toggle('text-steel-300', remaining >= 0);
        }

        cashRequirement?.classList.toggle('hidden', !isCash || form.dataset.hasOpenCash === 'true');
    };

    clientSelect.addEventListener('change', () => {
        if (totalInput.value !== '') {
            totalInput.value = '';
        }

        synchronizeAll();
    });
    useClientDebtButton.addEventListener('click', () => {
        const selectedClient = clientSelect.options[clientSelect.selectedIndex];
        const debt = numberValue(selectedClient?.dataset.debt);

        if (clientSelect.value === '' || debt <= 0) {
            clientSelect.focus();

            return;
        }

        totalInput.value = debt.toFixed(2);
        totalInput.focus();
        synchronizeAll();
    });
    paymentMethodSelect.addEventListener('change', synchronizeAll);
    totalInput.addEventListener('input', synchronizeAll);
    roundingInput?.addEventListener('change', synchronizeAll);

    synchronizeAll();
});

document.querySelectorAll('[data-commercial-adjustment-form]').forEach((form) => {
    const typeSelect = form.querySelector('[data-commercial-adjustment-type]');
    const discountFields = form.querySelector('[data-commercial-discount-fields]');
    const discountInputs = discountFields?.querySelectorAll('input, select, textarea') || [];
    const newBalanceInput = form.querySelector('[data-commercial-new-balance]');
    const returnFields = form.querySelector('[data-commercial-return-fields]');
    const returnInputs = returnFields?.querySelectorAll('input, select, textarea') || [];
    const returnProduct = form.querySelector('[data-commercial-return-product]');
    const returnKilograms = form.querySelector('[data-commercial-return-kilograms]');
    const previewAmount = document.querySelector('[data-commercial-preview-amount]');
    const previewRemaining = document.querySelector('[data-commercial-preview-remaining]');

    if (!(typeSelect instanceof HTMLSelectElement) || !(newBalanceInput instanceof HTMLInputElement)) {
        return;
    }

    const numberValue = (value) => {
        const parsedValue = Number.parseFloat(String(value || '0').replace(',', '.'));

        return Number.isNaN(parsedValue) ? 0 : parsedValue;
    };
    const formatMoney = (value) => `S/ ${value.toLocaleString('es-PE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;

    const synchronizeAdjustment = () => {
        const isReturn = typeSelect.value === 'DEVOLUCION';
        const balance = numberValue(form.dataset.balance);
        const hasNewBalance = newBalanceInput.value.trim() !== '';
        const selectedReturnProduct = returnProduct instanceof HTMLSelectElement
            ? returnProduct.options[returnProduct.selectedIndex]
            : null;
        const returnUnitPrice = numberValue(selectedReturnProduct?.dataset.unitPrice || form.dataset.returnUnitPrice);
        const returnWeight = returnKilograms instanceof HTMLInputElement
            ? Math.max(0, numberValue(returnKilograms.value))
            : 0;
        const amount = isReturn
            ? Math.round((returnWeight * returnUnitPrice) * 100) / 100
            : hasNewBalance
                ? Math.max(0, Math.round((balance - numberValue(newBalanceInput.value)) * 100) / 100)
                : 0;
        const remaining = Math.round((balance - amount) * 100) / 100;

        discountFields?.classList.toggle('hidden', isReturn);
        discountInputs.forEach((input) => {
            input.disabled = isReturn;
        });
        returnFields?.classList.toggle('hidden', !isReturn);
        returnInputs.forEach((input) => {
            input.disabled = !isReturn;
        });
        previewAmount?.replaceChildren(formatMoney(amount));
        previewRemaining?.replaceChildren(formatMoney(Math.max(0, remaining)));
        previewRemaining?.classList.toggle('text-danger', remaining < 0);
        previewRemaining?.classList.toggle('text-hazard', remaining >= 0);
    };

    typeSelect.addEventListener('change', synchronizeAdjustment);
    newBalanceInput.addEventListener('input', synchronizeAdjustment);
    returnProduct?.addEventListener('change', synchronizeAdjustment);
    returnKilograms?.addEventListener('input', synchronizeAdjustment);
    synchronizeAdjustment();
});

document.querySelectorAll('[data-adjustment-form]').forEach((form) => {
    const productSelect = form.querySelector('[data-adjustment-product]');
    const typeSelect = form.querySelector('[data-adjustment-type]');
    const birdsInput = form.querySelector('[data-adjustment-birds]');
    const kilogramsInput = form.querySelector('[data-adjustment-kilograms]');
    const initialWarning = form.querySelector('[data-adjustment-initial-warning]');
    const previewPanel = document.querySelector('[data-adjustment-preview-panel]');
    const previewProduct = document.querySelector('[data-adjustment-preview-product]');
    const currentBirdsOutput = document.querySelector('[data-adjustment-current-birds]');
    const currentKilogramsOutput = document.querySelector('[data-adjustment-current-kilograms]');
    const resultBirdsOutput = document.querySelector('[data-adjustment-result-birds]');
    const resultKilogramsOutput = document.querySelector('[data-adjustment-result-kilograms]');
    const previewMessage = document.querySelector('[data-adjustment-preview-message]');

    if (!(productSelect instanceof HTMLSelectElement)
        || !(typeSelect instanceof HTMLSelectElement)
        || !(birdsInput instanceof HTMLInputElement)
        || !(kilogramsInput instanceof HTMLInputElement)
        || !(previewPanel instanceof HTMLElement)) {
        return;
    }

    const numberValue = (value) => {
        const parsedValue = Number.parseFloat(String(value || '0').replace(',', '.'));

        return Number.isNaN(parsedValue) ? 0 : parsedValue;
    };
    const formatBirds = (value) => Math.trunc(value).toLocaleString('es-PE');
    const formatKilograms = (value) => `${value.toLocaleString('es-PE', {
        minimumFractionDigits: 3,
        maximumFractionDigits: 3,
    })} kg`;

    const synchronizePreview = () => {
        const productOption = productSelect.value === '' ? null : productSelect.options[productSelect.selectedIndex];
        const typeOption = typeSelect.value === '' ? null : typeSelect.options[typeSelect.selectedIndex];
        const currentBirds = numberValue(productOption?.dataset.birds);
        const currentKilograms = numberValue(productOption?.dataset.kilograms);
        const adjustmentBirds = Math.max(0, Math.trunc(numberValue(birdsInput.value)));
        const adjustmentKilograms = Math.max(0, numberValue(kilogramsInput.value));
        const isOutgoing = typeOption?.dataset.nature === 'SALIDA';
        const direction = isOutgoing ? -1 : 1;
        const resultingBirds = currentBirds + (direction * adjustmentBirds);
        const resultingKilograms = Math.round((currentKilograms + (direction * adjustmentKilograms)) * 1000) / 1000;
        const wouldBeNegative = resultingBirds < 0 || resultingKilograms < 0;

        previewProduct?.replaceChildren(productOption?.textContent.split(' · ')[0] || 'Selecciona un producto y el tipo de ajuste.');
        currentBirdsOutput?.replaceChildren(formatBirds(currentBirds));
        currentKilogramsOutput?.replaceChildren(formatKilograms(currentKilograms));
        resultBirdsOutput?.replaceChildren(formatBirds(resultingBirds));
        resultKilogramsOutput?.replaceChildren(formatKilograms(resultingKilograms));
        initialWarning?.classList.toggle('hidden', typeOption?.dataset.code !== 'SALDO_INICIAL');

        [resultBirdsOutput, resultKilogramsOutput].forEach((output) => {
            output?.classList.toggle('text-danger', wouldBeNegative);
            output?.classList.toggle('text-hazard', !wouldBeNegative);
        });
        previewPanel.classList.toggle('ring-2', wouldBeNegative);
        previewPanel.classList.toggle('ring-danger', wouldBeNegative);

        if (previewMessage instanceof HTMLElement) {
            previewMessage.textContent = productOption === null || typeOption === null
                ? 'Selecciona un producto y el tipo de ajuste para calcular el saldo.'
                : wouldBeNegative
                    ? 'La salida supera la existencia disponible y no podrá registrarse.'
                    : adjustmentBirds === 0 && adjustmentKilograms === 0
                        ? 'Ingresa al menos una cantidad de aves o un peso mayor que cero.'
                        : `Este movimiento ${isOutgoing ? 'descontará' : 'sumará'} mercadería al saldo actual.`;
            previewMessage.classList.toggle('text-danger', wouldBeNegative);
            previewMessage.classList.toggle('text-steel-300', !wouldBeNegative);
        }
    };

    [productSelect, typeSelect, birdsInput, kilogramsInput].forEach((field) => {
        field.addEventListener('input', synchronizePreview);
        field.addEventListener('change', synchronizePreview);
    });

    synchronizePreview();
});

document.querySelectorAll('[data-beneficiary-form]').forEach((form) => {
    const loadSelect = form.querySelector('[data-beneficiary-load]');
    const birdsInput = form.querySelector('[data-beneficiary-birds]');
    const sourceWeightInput = form.querySelector('[data-beneficiary-source-weight]');
    const resultWeightInput = form.querySelector('[data-beneficiary-result-weight]');
    const loadMetaOutput = document.querySelector('[data-beneficiary-load-meta]');
    const availableBirdsOutput = document.querySelector('[data-beneficiary-available-birds]');
    const availableWeightOutput = document.querySelector('[data-beneficiary-available-weight]');
    const resultOutput = document.querySelector('[data-beneficiary-result-output]');
    const lossOutput = document.querySelector('[data-beneficiary-loss-output]');
    const yieldOutput = document.querySelector('[data-beneficiary-yield-output]');
    const messageOutput = document.querySelector('[data-beneficiary-message]');

    if (!(loadSelect instanceof HTMLSelectElement)
        || !(birdsInput instanceof HTMLInputElement)
        || !(sourceWeightInput instanceof HTMLInputElement)
        || !(resultWeightInput instanceof HTMLInputElement)) {
        return;
    }

    const numberValue = (value) => {
        const parsedValue = Number.parseFloat(String(value || '0').replace(',', '.'));

        return Number.isNaN(parsedValue) ? 0 : parsedValue;
    };
    const formatKilograms = (value) => `${value.toLocaleString('es-PE', {
        minimumFractionDigits: 3,
        maximumFractionDigits: 3,
    })} kg`;

    const synchronizeBeneficiaryPreview = () => {
        const loadOption = loadSelect.value === '' ? null : loadSelect.options[loadSelect.selectedIndex];
        const availableBirds = Math.max(0, Math.trunc(numberValue(loadOption?.dataset.availableBirds)));
        const availableWeight = Math.max(0, numberValue(loadOption?.dataset.availableKilograms));
        const birds = Math.max(0, Math.trunc(numberValue(birdsInput.value)));
        const sourceWeight = Math.max(0, numberValue(sourceWeightInput.value));
        const resultWeight = Math.max(0, numberValue(resultWeightInput.value));
        const loss = Math.round(Math.max(0, sourceWeight - resultWeight) * 1000) / 1000;
        const processYield = sourceWeight > 0 ? (resultWeight / sourceWeight) * 100 : 0;
        const exceedsAvailability = birds > availableBirds || sourceWeight > availableWeight;
        const invalidResult = resultWeight > sourceWeight;

        birdsInput.max = String(availableBirds || 2000000000);
        sourceWeightInput.max = availableWeight > 0 ? availableWeight.toFixed(3) : '999999999.999';
        resultWeightInput.max = sourceWeight > 0 ? sourceWeight.toFixed(3) : '999999999.999';

        loadMetaOutput?.replaceChildren(loadOption
            ? `${loadOption.dataset.loadNumber} · ${loadOption.dataset.product} · ${loadOption.dataset.provider}`
            : 'Selecciona una carga para consultar su saldo procesable.');
        availableBirdsOutput?.replaceChildren(`${availableBirds.toLocaleString('es-PE')} aves`);
        availableWeightOutput?.replaceChildren(formatKilograms(availableWeight));
        resultOutput?.replaceChildren(formatKilograms(resultWeight));
        lossOutput?.replaceChildren(formatKilograms(loss));
        yieldOutput?.replaceChildren(`${processYield.toLocaleString('es-PE', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        })}%`);

        if (messageOutput instanceof HTMLElement) {
            messageOutput.textContent = loadOption === null
                ? 'Selecciona una carga para comenzar.'
                : exceedsAvailability
                    ? 'Las aves o el peso vivo superan el saldo procesable de la carga.'
                    : invalidResult
                        ? 'El peso beneficiado no puede superar el peso vivo procesado.'
                        : sourceWeight === 0 || resultWeight === 0
                            ? 'Completa los pesos para calcular merma y rendimiento.'
                            : `${formatKilograms(resultWeight)} ingresarán al stock disponible para venta.`;
            messageOutput.classList.toggle('text-danger', exceedsAvailability || invalidResult);
            messageOutput.classList.toggle('text-steel-300', !exceedsAvailability && !invalidResult);
        }
    };

    [loadSelect, birdsInput, sourceWeightInput, resultWeightInput].forEach((field) => {
        field.addEventListener('input', synchronizeBeneficiaryPreview);
        field.addEventListener('change', synchronizeBeneficiaryPreview);
    });

    synchronizeBeneficiaryPreview();
});

document.querySelectorAll('[data-reconciliation-form]').forEach((form) => {
    const productSelect = form.querySelector('[data-reconciliation-product]');
    const physicalBirdsInput = form.querySelector('[data-reconciliation-physical-birds]');
    const physicalKilogramsInput = form.querySelector('[data-reconciliation-physical-kilograms]');
    const observationInput = form.querySelector('[data-reconciliation-observation]');
    const productNameOutput = document.querySelector('[data-reconciliation-product-name]');
    const systemBirdsOutput = document.querySelector('[data-reconciliation-system-birds]');
    const systemKilogramsOutput = document.querySelector('[data-reconciliation-system-kilograms]');
    const physicalBirdsOutput = document.querySelector('[data-reconciliation-preview-physical-birds]');
    const physicalKilogramsOutput = document.querySelector('[data-reconciliation-preview-physical-kilograms]');
    const differenceBirdsOutput = document.querySelector('[data-reconciliation-difference-birds]');
    const differenceKilogramsOutput = document.querySelector('[data-reconciliation-difference-kilograms]');
    const messageOutput = document.querySelector('[data-reconciliation-message]');
    const previewPanel = document.querySelector('[data-reconciliation-preview-panel]');

    if (!(productSelect instanceof HTMLSelectElement)
        || !(physicalBirdsInput instanceof HTMLInputElement)
        || !(physicalKilogramsInput instanceof HTMLInputElement)) {
        return;
    }

    const numberValue = (value) => {
        const parsedValue = Number.parseFloat(String(value || '0').replace(',', '.'));

        return Number.isNaN(parsedValue) ? 0 : parsedValue;
    };
    const formatBirds = (value) => Math.trunc(value).toLocaleString('es-PE');
    const formatKilograms = (value) => value.toLocaleString('es-PE', {
        minimumFractionDigits: 3,
        maximumFractionDigits: 3,
    });

    const synchronizeReconciliation = () => {
        const productOption = productSelect.value === '' ? null : productSelect.options[productSelect.selectedIndex];
        const systemBirds = numberValue(productOption?.dataset.birds);
        const systemKilograms = numberValue(productOption?.dataset.kilograms);
        const physicalBirds = Math.max(0, Math.trunc(numberValue(physicalBirdsInput.value)));
        const physicalKilograms = Math.max(0, numberValue(physicalKilogramsInput.value));
        const birdDifference = physicalBirds - systemBirds;
        const kilogramDifference = Math.round((physicalKilograms - systemKilograms) * 1000) / 1000;
        const isBalanced = birdDifference === 0 && kilogramDifference === 0;
        const hasMixedDirections = (birdDifference > 0 && kilogramDifference < 0)
            || (birdDifference < 0 && kilogramDifference > 0);
        const isExtraordinary = form.querySelector('[data-reconciliation-type]:checked')?.value === 'EXTRAORDINARIA';

        productNameOutput?.replaceChildren(productOption?.textContent.split(' · ')[0] || 'Selecciona el producto conciliado.');
        systemBirdsOutput?.replaceChildren(formatBirds(systemBirds));
        systemKilogramsOutput?.replaceChildren(`${formatKilograms(systemKilograms)} kg`);
        physicalBirdsOutput?.replaceChildren(formatBirds(physicalBirds));
        physicalKilogramsOutput?.replaceChildren(`${formatKilograms(physicalKilograms)} kg`);
        differenceBirdsOutput?.replaceChildren(`${birdDifference > 0 ? '+' : ''}${formatBirds(birdDifference)} aves`);
        differenceKilogramsOutput?.replaceChildren(`${kilogramDifference > 0 ? '+' : ''}${formatKilograms(kilogramDifference)} kg`);

        [differenceBirdsOutput, differenceKilogramsOutput].forEach((output) => {
            output?.classList.toggle('text-signal', isBalanced);
            output?.classList.toggle('text-danger', !isBalanced);
        });
        previewPanel?.classList.toggle('ring-2', !isBalanced);
        previewPanel?.classList.toggle('ring-hazard', !isBalanced);

        if (messageOutput instanceof HTMLElement) {
            messageOutput.textContent = productOption === null
                ? 'Selecciona un producto para comparar el conteo.'
                : isBalanced
                    ? 'El conteo coincide y no generará ajustes.'
                    : hasMixedDirections
                        ? 'Se generarán dos ajustes porque las diferencias tienen sentidos opuestos.'
                        : `Se generará un ajuste ${birdDifference > 0 || kilogramDifference > 0 ? 'positivo' : 'negativo'} para cuadrar el saldo.`;
        }

        if (observationInput instanceof HTMLTextAreaElement) {
            observationInput.required = isExtraordinary;
        }
    };

    productSelect.addEventListener('change', () => {
        const productOption = productSelect.value === '' ? null : productSelect.options[productSelect.selectedIndex];
        physicalBirdsInput.value = productOption?.dataset.birds || '0';
        physicalKilogramsInput.value = Number.parseFloat(productOption?.dataset.kilograms || '0').toFixed(3);
        synchronizeReconciliation();
    });
    [physicalBirdsInput, physicalKilogramsInput].forEach((input) => input.addEventListener('input', synchronizeReconciliation));
    form.querySelectorAll('[data-reconciliation-type]').forEach((input) => input.addEventListener('change', synchronizeReconciliation));
    synchronizeReconciliation();
});

document.querySelectorAll('[data-advance-application-form]').forEach((form) => {
    const applicationsContainer = form.querySelector('[data-advance-applications]');
    const applicationTemplate = form.querySelector('[data-advance-application-template]');
    const addApplicationButton = form.querySelector('[data-add-advance-application]');
    const appliedOutput = document.querySelector('[data-advance-preview-applied]');
    const remainingOutput = document.querySelector('[data-advance-preview-remaining]');
    const messageOutput = document.querySelector('[data-advance-preview-message]');
    const availableAmount = Number.parseFloat(form.dataset.remaining || '0');

    if (!(applicationsContainer instanceof HTMLElement)
        || !(applicationTemplate instanceof HTMLTemplateElement)
        || !(addApplicationButton instanceof HTMLButtonElement)) {
        return;
    }

    const numberValue = (value) => {
        const parsedValue = Number.parseFloat(String(value || '0').replace(',', '.'));

        return Number.isNaN(parsedValue) ? 0 : parsedValue;
    };
    const formatMoney = (value) => `S/ ${value.toLocaleString('es-PE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
    const applicationIndex = (application) => application.querySelector('[data-collection-sale]')?.name.match(/^aplicaciones\[([^\]]+)\]/)?.[1] ?? '0';

    let nextApplicationIndex = [...applicationsContainer.querySelectorAll('[data-collection-application]')]
        .reduce((maximum, application) => Math.max(maximum, Number.parseInt(applicationIndex(application), 10) || 0), -1) + 1;

    const synchronizeAll = () => {
        const applications = [...applicationsContainer.querySelectorAll('[data-collection-application]')];
        const selectedSaleIds = applications
            .map((application) => application.querySelector('[data-collection-sale]')?.value)
            .filter(Boolean);
        let applied = 0;

        applications.forEach((application, index) => {
            const saleSelect = application.querySelector('[data-collection-sale]');
            const amountInput = application.querySelector('[data-collection-applied-amount]');
            const meta = application.querySelector('[data-collection-sale-meta]');

            if (!(saleSelect instanceof HTMLSelectElement) || !(amountInput instanceof HTMLInputElement)) {
                return;
            }

            [...saleSelect.options].forEach((option) => {
                option.disabled = option.value !== '' && option.value !== saleSelect.value && selectedSaleIds.includes(option.value);
            });
            const selectedOption = saleSelect.value === '' ? null : saleSelect.options[saleSelect.selectedIndex];
            const saleBalance = numberValue(selectedOption?.dataset.balance);
            amountInput.max = selectedOption ? Math.min(saleBalance, availableAmount).toFixed(2) : availableAmount.toFixed(2);
            applied += Math.max(0, numberValue(amountInput.value));
            application.querySelector('[data-collection-application-number]')?.replaceChildren(String(index + 1));
            application.querySelector('[data-remove-collection-application]')?.toggleAttribute('disabled', applications.length === 1);

            if (meta instanceof HTMLElement) {
                meta.textContent = selectedOption
                    ? `${selectedOption.dataset.date} · saldo ${formatMoney(saleBalance)}`
                    : 'Selecciona una venta para consultar su saldo.';
            }
        });

        applied = Math.round(applied * 100) / 100;
        const remaining = Math.round((availableAmount - applied) * 100) / 100;
        const exceedsAvailable = remaining < 0;

        appliedOutput?.replaceChildren(formatMoney(applied));
        remainingOutput?.replaceChildren(formatMoney(remaining));
        remainingOutput?.classList.toggle('text-danger', exceedsAvailable);
        remainingOutput?.classList.toggle('text-hazard', !exceedsAvailable);
        const saleOptionCount = Math.max(
            0,
            applicationTemplate.content.querySelectorAll('[data-collection-sale] option').length - 1,
        );

        addApplicationButton.disabled = applications.length >= 50
            || selectedSaleIds.length >= saleOptionCount;

        if (messageOutput instanceof HTMLElement) {
            messageOutput.textContent = exceedsAvailable
                ? 'La distribución supera el saldo disponible del abono.'
                : applied > 0
                    ? `Se aplicarán ${formatMoney(applied)} y quedarán ${formatMoney(remaining)} disponibles.`
                    : 'Selecciona una venta y define el importe a aplicar.';
            messageOutput.classList.toggle('text-danger', exceedsAvailable);
            messageOutput.classList.toggle('text-steel-300', !exceedsAvailable);
        }
    };

    const bindApplication = (application) => {
        if (!(application instanceof HTMLElement) || application.dataset.advanceBound === 'true') {
            return;
        }

        application.dataset.advanceBound = 'true';
        const saleSelect = application.querySelector('[data-collection-sale]');
        const amountInput = application.querySelector('[data-collection-applied-amount]');

        if (!(saleSelect instanceof HTMLSelectElement) || !(amountInput instanceof HTMLInputElement)) {
            return;
        }

        saleSelect.addEventListener('change', () => {
            const selectedOption = saleSelect.value === '' ? null : saleSelect.options[saleSelect.selectedIndex];
            const otherApplied = [...applicationsContainer.querySelectorAll('[data-collection-applied-amount]')]
                .filter((input) => input !== amountInput)
                .reduce((total, input) => total + Math.max(0, numberValue(input.value)), 0);
            const availableForRow = Math.max(0, availableAmount - otherApplied);
            amountInput.value = selectedOption
                ? Math.min(numberValue(selectedOption.dataset.balance), availableForRow).toFixed(2)
                : '';
            synchronizeAll();
        });
        amountInput.addEventListener('input', synchronizeAll);
        application.querySelector('[data-use-sale-balance]')?.addEventListener('click', () => {
            const selectedOption = saleSelect.value === '' ? null : saleSelect.options[saleSelect.selectedIndex];

            if (!selectedOption) {
                saleSelect.focus();

                return;
            }

            const otherApplied = [...applicationsContainer.querySelectorAll('[data-collection-applied-amount]')]
                .filter((input) => input !== amountInput)
                .reduce((total, input) => total + Math.max(0, numberValue(input.value)), 0);
            amountInput.value = Math.min(numberValue(selectedOption.dataset.balance), Math.max(0, availableAmount - otherApplied)).toFixed(2);
            synchronizeAll();
        });
        application.querySelector('[data-remove-collection-application]')?.addEventListener('click', () => {
            if (applicationsContainer.querySelectorAll('[data-collection-application]').length <= 1) {
                return;
            }

            application.remove();
            synchronizeAll();
        });
    };

    applicationsContainer.querySelectorAll('[data-collection-application]').forEach(bindApplication);
    addApplicationButton.addEventListener('click', () => {
        const temporaryContainer = document.createElement('div');
        temporaryContainer.innerHTML = applicationTemplate.innerHTML.replaceAll('__APPLICATION__', String(nextApplicationIndex));
        nextApplicationIndex += 1;
        const application = temporaryContainer.firstElementChild;

        if (!(application instanceof HTMLElement)) {
            return;
        }

        applicationsContainer.append(application);
        bindApplication(application);
        application.querySelector('[data-collection-sale]')?.focus();
        synchronizeAll();
    });
    synchronizeAll();
});
