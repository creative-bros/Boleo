<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Boleo' }}</title>
    @php
        $assetUrl = static function (string $path): string {
            $fullPath = public_path($path);

            if (! file_exists($fullPath)) {
                return asset($path);
            }

            return asset($path).'?v='.filemtime($fullPath);
        };
    @endphp
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ $assetUrl('css/boleo.css') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="{{ $bodyClass ?? '' }}">
    @yield('content')

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const syncBulkFormSelection = (button, form) => {
                const groupName = button.dataset.bulkActionButton;
                const fieldName = button.dataset.bulkSelectionField;

                if (!groupName || !fieldName) {
                    return;
                }

                form.querySelectorAll('[data-synced-bulk-input]').forEach((input) => input.remove());

                document.querySelectorAll(`[data-select-group="${groupName}"]`).forEach((checkbox) => {
                    if (!checkbox.checked || checkbox.disabled) {
                        return;
                    }

                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = `${fieldName}[]`;
                    input.value = checkbox.value;
                    input.setAttribute('data-synced-bulk-input', 'true');
                    form.appendChild(input);
                });
            };

            document.querySelectorAll('[data-confirm-submit]').forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.preventDefault();

                    const form = document.getElementById(button.getAttribute('data-confirm-submit'));

                    if (!form) {
                        return;
                    }

                    syncBulkFormSelection(button, form);

                    Swal.fire({
                        title: button.dataset.confirmTitle || '¿Estás seguro?',
                        text: button.dataset.confirmText || 'No podrás revertir esta acción.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#1f5c4f',
                        cancelButtonColor: '#d33',
                        confirmButtonText: button.dataset.confirmButtonText || 'Sí, continuar',
                        cancelButtonText: 'Cancelar',
                    }).then((result) => {
                        if (result.isConfirmed) {
                            form.submit();
                        }
                    });
                });
            });

            const bulkSelectionOrder = {};
            const currencyFormatter = new Intl.NumberFormat('es-MX', {
                style: 'currency',
                currency: 'MXN',
            });

            document.querySelectorAll('[data-condominium-receipt-form]').forEach((form) => {
                const section = form.closest('#recibos-condominio');
                const openButton = section?.querySelector('[data-condominium-receipt-open]');
                const closeButton = form.querySelector('[data-condominium-receipt-close]');
                const deleteForm = section?.querySelector('[data-condominium-receipt-delete-form]');
                const modeSelect = form.querySelector('[data-condominium-receipt-mode]');
                const singleFields = Array.from(form.querySelectorAll('[data-condominium-receipt-single]'));
                const rangeFields = Array.from(form.querySelectorAll('[data-condominium-receipt-range]'));
                const syncMode = () => {
                    const mode = modeSelect?.value || 'single';

                    singleFields.forEach((field) => {
                        const disabled = mode !== 'single';
                        field.hidden = disabled;
                        field.querySelectorAll('input, select, textarea').forEach((input) => {
                            input.disabled = disabled;
                        });
                    });

                    rangeFields.forEach((field) => {
                        const disabled = mode !== 'range';
                        field.hidden = disabled;
                        field.querySelectorAll('input, select, textarea').forEach((input) => {
                            input.disabled = disabled;
                        });
                    });
                };

                openButton?.addEventListener('click', () => {
                    form.hidden = false;
                    if (deleteForm) {
                        deleteForm.hidden = true;
                    }
                    syncMode();
                    form.querySelector('select, input')?.focus();
                });

                closeButton?.addEventListener('click', () => {
                    form.hidden = true;
                });

                modeSelect?.addEventListener('change', syncMode);
                syncMode();
            });

            document.querySelectorAll('[data-condominium-receipt-delete-form]').forEach((form) => {
                const section = form.closest('#recibos-condominio');
                const openButton = section?.querySelector('[data-condominium-receipt-delete-open]');
                const closeButton = form.querySelector('[data-condominium-receipt-delete-close]');
                const createForm = section?.querySelector('[data-condominium-receipt-form]');

                openButton?.addEventListener('click', () => {
                    form.hidden = false;
                    if (createForm) {
                        createForm.hidden = true;
                    }
                    form.querySelector('select, input')?.focus();
                });

                closeButton?.addEventListener('click', () => {
                    form.hidden = true;
                });
            });

            const syncBulkAction = (groupName, checkboxes) => {
                const buttons = Array.from(document.querySelectorAll(`[data-bulk-action-button="${groupName}"]`));

                if (buttons.length === 0) {
                    return;
                }

                const selected = checkboxes.filter((checkbox) => checkbox.checked && !checkbox.disabled);
                const selectedValues = new Set(selected.map((checkbox) => checkbox.value));
                bulkSelectionOrder[groupName] = (bulkSelectionOrder[groupName] || []).filter((value) => selectedValues.has(value));

                selected.forEach((checkbox) => {
                    if (!bulkSelectionOrder[groupName].includes(checkbox.value)) {
                        bulkSelectionOrder[groupName].push(checkbox.value);
                    }
                });

                const lastValue = bulkSelectionOrder[groupName][bulkSelectionOrder[groupName].length - 1];
                const lastCheckbox = selected.find((checkbox) => checkbox.value === lastValue) || selected[selected.length - 1];
                const slot = lastCheckbox?.closest('tr')?.querySelector(`[data-bulk-action-slot="${groupName}"]`);

                buttons.forEach((button) => {
                    const actionType = button.dataset.bulkActionType || 'apply';
                    const amountDatasetKey = actionType === 'unapply' ? 'selectUnapplyAmount' : 'selectApplyAmount';
                    const total = selected.reduce((sum, checkbox) => sum + Number(checkbox.dataset[amountDatasetKey] || 0), 0);

                    if (!slot || total <= 0) {
                        button.hidden = true;
                        return;
                    }

                    const totalLabel = button.querySelector('[data-bulk-action-total]');

                    if (totalLabel) {
                        totalLabel.textContent = currencyFormatter.format(total);
                    }

                    slot.appendChild(button);
                    button.hidden = false;
                });
            };

            document.querySelectorAll('[data-select-all]').forEach((toggle) => {
                const groupName = toggle.getAttribute('data-select-all');
                const checkboxes = Array.from(document.querySelectorAll(`[data-select-group="${groupName}"]`));
                const syncToggleState = () => {
                    const selectable = checkboxes.filter((checkbox) => !checkbox.disabled);
                    const selected = selectable.filter((checkbox) => checkbox.checked);

                    toggle.checked = selectable.length > 0 && selected.length === selectable.length;
                    toggle.indeterminate = selected.length > 0 && selected.length < selectable.length;
                    toggle.disabled = selectable.length === 0;
                    syncBulkAction(groupName, checkboxes);
                };

                toggle.addEventListener('change', () => {
                    bulkSelectionOrder[groupName] = [];

                    checkboxes.forEach((checkbox) => {
                        if (!checkbox.disabled) {
                            checkbox.checked = toggle.checked;

                            if (toggle.checked) {
                                bulkSelectionOrder[groupName].push(checkbox.value);
                            }
                        }
                    });

                    syncToggleState();
                });

                checkboxes.forEach((checkbox) => checkbox.addEventListener('change', () => {
                    bulkSelectionOrder[groupName] = (bulkSelectionOrder[groupName] || []).filter((value) => value !== checkbox.value);

                    if (checkbox.checked) {
                        bulkSelectionOrder[groupName].push(checkbox.value);
                    }

                    syncToggleState();
                }));
                syncToggleState();
            });
        });
    </script>
</body>
</html>
