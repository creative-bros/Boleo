@php
    $hasBillingSearchContext = filled(request('q')) || filled(request('condominium')) || request()->has('unit') || request()->has('account');
    $hasBillingResidentSearchContext = filled(request('q')) || request()->has('unit') || request()->has('account');
@endphp

<section class="section-stack">
    <div class="section-intro">
        <div>
            <p class="section-intro__eyebrow">Consulta de cobranza</p>
            <h3 class="section-intro__title">Busqueda de cuenta y reportes</h3>
        </div>
        <p class="section-intro__note">Primero localiza el condominio o la unidad, después usa los reportes y finalmente revisa el detalle del residente.</p>
    </div>

    <section class="panel">
        <div class="panel__header">
            <h3>Busqueda de Cobranza</h3>
            <span>{{ $condominiumName }}</span>
        </div>
        <form class="form-grid" method="GET" action="{{ route('billing') }}">
            <label class="field">
                <span>Condominio</span>
                <input type="text" name="condominium" value="{{ old('condominium', $condominiumQuery) }}" placeholder="Nombre del condominio">
            </label>
            <label class="field">
                <span>Departamento o residente</span>
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Buscar por departamento, torre o residente">
            </label>
            <div class="form-actions">
                <button class="button button--ghost" type="submit">Buscar</button>
            </div>
        </form>

        @if ($hasBillingSearchContext)
            @php
                $quickAccountRouteParams = array_filter([
                    'unit' => $selectedUnitId,
                    'account' => $selectedImportedAccount?->id,
                    'q' => request('q'),
                    'condominium' => $condominiumQuery,
                    'receipt_year' => $receiptYear,
                ], fn ($value) => filled($value));
            @endphp
            <div class="billing-search-result">
                @if (blank($account['name']))
                    <div>
                        <span class="billing-search-result__eyebrow">Sin resultado seleccionado</span>
                        <strong>No encontramos información de ese residente o departamento.</strong>
                        <p>Revisa el nombre, departamento o condominio y vuelve a buscar.</p>
                    </div>
                @else
                    <div class="billing-search-result__main">
                        <div class="avatar">{{ substr($account['name'], 0, 1) }}</div>
                        <div>
                            <span class="billing-search-result__eyebrow">Resultado encontrado</span>
                            <strong>{{ $account['name'] }}</strong>
                            <p>{{ $account['location'] ?: 'Sin departamento vinculado' }}{{ $account['email'] ? ' | '.$account['email'] : ' | Sin correo vinculado' }}</p>
                            @if (! empty($account['source']))
                                <p>{{ $account['source'] }}</p>
                            @endif
                        </div>
                    </div>
                    <div class="billing-search-result__stats">
                        <span>
                            <small>Saldo</small>
                            <strong>{{ $account['balance'] }}</strong>
                        </span>
                        <span>
                            <small>Estatus</small>
                            <strong>{{ $account['status'] }}</strong>
                        </span>
                        <span>
                            <small>Pagado</small>
                            <strong>{{ $account['paid'] }}</strong>
                        </span>
                    </div>
                    <div class="billing-search-result__actions">
                        <a class="button button--primary button--small" href="{{ route('billing', $quickAccountRouteParams) }}#recibos-condomino">Ver cuenta</a>
                        @if ($selectedUnitId)
                            <a class="button button--ghost button--small" href="{{ route('billing', $quickAccountRouteParams) }}#recibos-condomino">Recibos</a>
                        @endif
                        @if ($selectedImportedAccount)
                            <a class="button button--ghost button--small" href="{{ route('billing.letters.show', $selectedImportedAccount) }}">Carta</a>
                        @elseif ($selectedUnitId)
                            <a class="button button--ghost button--small" href="{{ route('billing.letters.unit', array_filter(['unit' => $selectedUnitId, 'month' => request('month')], fn ($value) => filled($value))) }}">Carta</a>
                        @endif
                    </div>
                    @if ($hasBillingResidentSearchContext && ! empty($reportCommands))
                        <div class="billing-search-result__actions billing-search-result__actions--reports">
                            @foreach ($reportCommands as $command)
                                <a
                                    class="button button--ghost button--small"
                                    @if ($command['raw_href'] ?? false)
                                        href="{!! $command['href'] !!}"
                                    @else
                                        href="{{ $command['href'] }}"
                                    @endif
                                >
                                    {{ $command['label'] }}
                                </a>
                            @endforeach
                        </div>
                    @endif
                @endif
            </div>
        @endif
    </section>

</section>

@if ($canManage)
    <section class="section-stack" id="recibos-condominio">
        <div class="section-intro">
            <div>
                <p class="section-intro__eyebrow">Recibos por condominio</p>
                <h3 class="section-intro__title">Crear recibos por mes</h3>
            </div>
            <p class="section-intro__note">Selecciona el condominio y captura el monto de cada mes.</p>
        </div>

        <section class="panel">
            <div class="panel__header">
                <h3>{{ $condominiumName ?: 'Condominio sin nombre' }}</h3>
                <div class="billing-row-actions__group">
                    <span>{{ $condominiumReceiptUnitCount }} unidad(es)</span>
                    <button class="button button--primary button--small" type="button" data-condominium-receipt-open>Crear nuevo recibo</button>
                    <button class="button button--ghost button--small" type="button" data-condominium-receipt-delete-open>Borrar mes</button>
                </div>
            </div>

            @php
                $receiptCreatorMode = old('receipt_mode', 'single');
                $showReceiptCreator = old('receipt_mode') !== null;
                $receiptDefaultPeriod = old('period', $condominiumReceiptDefaultPeriod);
                $receiptRangeStart = old('start_period', $condominiumReceiptDefaultPeriod);
                $receiptRangeEnd = old('end_period', $condominiumReceiptDefaultPeriod);
                $condominiumReceiptAmountValue = old('condominium_amount_due', $condominiumReceiptDefaultAmount);
            @endphp
            <form
                class="condominium-receipt-form"
                method="POST"
                action="{{ route('billing.receipts.condominium.store') }}"
                data-condominium-receipt-form
                @if (! $showReceiptCreator) hidden @endif
            >
                @csrf
                <div class="form-grid condominium-receipt-form-grid">
                    <label class="field">
                        <span>Condominio</span>
                        <select class="select-field" name="condominium_profile_id" required>
                            @foreach ($condominiumProfiles as $condominiumOption)
                                <option value="{{ $condominiumOption->id }}" @selected((string) old('condominium_profile_id', $selectedCondominiumProfileId) === (string) $condominiumOption->id)>
                                    {{ $condominiumOption->commercial_name ?: 'Condominio #'.$condominiumOption->id }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <label class="field">
                        <span>Seleccion</span>
                        <select class="select-field" name="receipt_mode" data-condominium-receipt-mode required>
                            <option value="single" @selected($receiptCreatorMode === 'single')>Un mes</option>
                            <option value="range" @selected($receiptCreatorMode === 'range')>Varios meses</option>
                        </select>
                    </label>
                    <label class="field" data-condominium-receipt-single @if ($receiptCreatorMode !== 'single') hidden @endif>
                        <span>Periodo</span>
                        <input type="month" name="period" value="{{ $receiptDefaultPeriod }}" @disabled($receiptCreatorMode !== 'single') required>
                    </label>
                    <label class="field" data-condominium-receipt-range @if ($receiptCreatorMode !== 'range') hidden @endif>
                        <span>Periodo inicial</span>
                        <input type="month" name="start_period" value="{{ $receiptRangeStart }}" @disabled($receiptCreatorMode !== 'range') required>
                    </label>
                    <label class="field" data-condominium-receipt-range @if ($receiptCreatorMode !== 'range') hidden @endif>
                        <span>Periodo final</span>
                        <input type="month" name="end_period" value="{{ $receiptRangeEnd }}" @disabled($receiptCreatorMode !== 'range') required>
                    </label>
                    <label class="field">
                        <span>Monto</span>
                        <input type="number" step="0.01" min="0.01" name="condominium_amount_due" value="{{ $condominiumReceiptAmountValue }}" required>
                    </label>
                    <label class="field field--full">
                        <span>Comentarios</span>
                        <input type="text" name="notes" value="{{ old('notes') }}" placeholder="Sin comentarios">
                    </label>
                </div>

                <div class="form-actions condominium-receipt-actions">
                    <button class="button button--primary" type="submit">Agregar</button>
                    <button class="button button--ghost" type="button" data-condominium-receipt-close>Cancelar</button>
                </div>
            </form>

            <form
                id="condominium-receipt-delete-form"
                class="condominium-receipt-form"
                method="POST"
                action="{{ route('billing.receipts.condominium.delete-month') }}"
                data-condominium-receipt-delete-form
                hidden
            >
                @csrf
                @method('DELETE')
                <div class="form-grid condominium-receipt-form-grid">
                    <label class="field">
                        <span>Condominio</span>
                        <select class="select-field" name="condominium_profile_id" required>
                            @foreach ($condominiumProfiles as $condominiumOption)
                                <option value="{{ $condominiumOption->id }}" @selected((string) old('condominium_profile_id', $selectedCondominiumProfileId) === (string) $condominiumOption->id)>
                                    {{ $condominiumOption->commercial_name ?: 'Condominio #'.$condominiumOption->id }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <label class="field">
                        <span>Mes a borrar</span>
                        <input type="month" name="period" value="{{ old('period', $condominiumReceiptDeletePeriod) }}" required>
                    </label>
                </div>
                <div class="form-actions condominium-receipt-actions">
                    <button
                        class="button button--danger"
                        type="button"
                        data-confirm-submit="condominium-receipt-delete-form"
                        data-confirm-title="¿Borrar este mes?"
                        data-confirm-text="Se eliminarán los recibos sin pagos ni abonos de ese mes para el condominio seleccionado."
                        data-confirm-button-text="Sí, borrar"
                    >Borrar</button>
                    <button class="button button--ghost" type="button" data-condominium-receipt-delete-close>Cancelar</button>
                </div>
            </form>
        </section>
    </section>
@endif

<section class="section-stack" id="recibos-condomino">
    <div class="section-intro">
        <div>
            <p class="section-intro__eyebrow">Recibos por condomino</p>
            <h3 class="section-intro__title">{{ $usesImportedStatement ? 'Estado importado del Excel' : 'Pagados, parciales y pendientes' }}</h3>
        </div>
        <p class="section-intro__note">{{ $usesImportedStatement ? 'La tabla viene de la base importada del residente. Exigible es lo que paga de mantenimiento cada mes.' : 'Cada recibo guarda mes, año, cantidad a pagar, abonos y notas. El estatus se calcula con base en lo abonado.' }}</p>
    </div>
    
    <section class="panel">
        <div class="panel__header">
            <h3>{{ $account['name'] ? 'Recibos de '.$account['name'] : 'Recibos del condomino' }}</h3>
            <span>Pendiente: ${{ number_format((float) $receiptSummary['pending_amount'], 2) }}</span>
        </div>

        @if (blank($account['name']))
            <div class="empty-state">
                <strong>No hay condomino seleccionado</strong>
                <p>Selecciona un dueño desde la lista para ver su estado importado o administrar recibos.</p>
            </div>
        @elseif ($usesImportedStatement)
            <div class="mini-stats mini-stats--five">
                <div class="mini-stat">
                    <span>Total registros</span>
                    <strong>{{ $receiptSummary['total'] }}</strong>
                </div>
                <div class="mini-stat">
                    <span>Pagados</span>
                    <strong>{{ $receiptSummary['paid_count'] }}</strong>
                </div>
                <div class="mini-stat">
                    <span>Parciales</span>
                    <strong>{{ $receiptSummary['partial_count'] }}</strong>
                </div>
                <div class="mini-stat">
                    <span>Pendientes</span>
                    <strong>{{ $receiptSummary['pending_count'] }}</strong>
                </div>
                <div class="mini-stat">
                    <span>Adeudo</span>
                    <strong>${{ number_format((float) $receiptSummary['pending_amount'], 2) }}</strong>
                </div>
            </div>

            @if ($canManage && $selectedUnitId && $selectedImportedAccount)
                <form id="billing-statement-bulk-apply" class="bulk-selection-form" method="GET" action="{{ route('billing.statement.bulk-apply-form') }}">
                    <input type="hidden" name="unit" value="{{ $selectedUnitId }}">
                    <input type="hidden" name="account" value="{{ $selectedImportedAccount->id }}">
                    <input type="hidden" name="receipt_year" value="{{ $receiptYear }}">
                    <input type="hidden" name="condominium_profile_id" value="{{ $selectedCondominiumProfileId }}">
                </form>
                <form id="billing-statement-bulk-unapply" class="bulk-selection-form" method="POST" action="{{ route('billing.statement.bulk-unapply') }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="unit" value="{{ $selectedUnitId }}">
                    <input type="hidden" name="account" value="{{ $selectedImportedAccount->id }}">
                    <input type="hidden" name="receipt_year" value="{{ $receiptYear }}">
                    <input type="hidden" name="condominium_profile_id" value="{{ $selectedCondominiumProfileId }}">
                </form>
                <button
                    id="billing-statement-bulk-trigger"
                    class="button button--success button--small bulk-selection-trigger"
                    type="submit"
                    form="billing-statement-bulk-apply"
                    data-bulk-action-button="billing-statement"
                    data-bulk-action-type="apply"
                    hidden
                >Abonar selección <span data-bulk-action-total>$0.00</span></button>
                <button
                    id="billing-statement-bulk-unapply-trigger"
                    class="button button--danger button--small bulk-selection-trigger"
                    type="button"
                    data-confirm-submit="billing-statement-bulk-unapply"
                    data-bulk-action-button="billing-statement"
                    data-bulk-action-type="unapply"
                    data-bulk-selection-field="selected_rows"
                    data-confirm-title="¿Desaplicar pagos seleccionados?"
                    data-confirm-text="Se borrarán los pagos aplicados de los registros marcados."
                    data-confirm-button-text="Sí, desaplicar"
                    hidden
                >Desaplicar selección <span data-bulk-action-total>$0.00</span></button>
            @endif

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            @if ($canManage && $selectedUnitId && $selectedImportedAccount)
                                <th class="bulk-select-cell">
                                    <input class="bulk-select" type="checkbox" data-select-all="billing-statement" aria-label="Seleccionar todos los registros disponibles">
                                </th>
                            @endif
                            <th>Nombre</th>
                            <th>ESTATUS</th>
                            <th>EXIGIBLE</th>
                            <th>PAGADO</th>
                            <th>ADEUDO</th>
                            <th>Comentarios</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($excelStatementRows as $row)
                            @php
                                $excelRowUnapplyFormId = 'excel-receipt-unapply-'.($row['period_year'] ?? 'na').'-'.($row['period_month'] ?? 'na');
                                $importedPaymentUnapplyFormId = 'imported-payment-unapply-'.md5(($row['payload_key'] ?? '').($row['name'] ?? ''));
                                $periodReceiptParams = [
                                    'unit' => $selectedUnitId,
                                    'year' => $row['period_year'] ?? null,
                                    'month' => $row['period_month'] ?? null,
                                    'amount' => $row['exigible_raw'] ?? null,
                                    'condominium_profile_id' => $selectedCondominiumProfileId,
                                ];
                                $importedPaymentParams = [
                                    'unit' => $selectedUnitId,
                                    'account' => $selectedImportedAccount?->id,
                                    'key' => $row['payload_key'] ?? null,
                                    'concept' => $row['name'] ?? null,
                                    'amount' => $row['debt_raw'] ?? $row['exigible_raw'] ?? null,
                                    'receipt_year' => $receiptYear,
                                    'condominium_profile_id' => $selectedCondominiumProfileId,
                                ];
                                $statementRowSelectionValue = filled($row['period_year'] ?? null) && filled($row['period_month'] ?? null)
                                    ? 'period:'.((int) $row['period_year']).':'.((int) $row['period_month'])
                                    : 'imported:'.md5(trim((string) ($row['payload_key'] ?? '')).'|'.trim((string) ($row['name'] ?? '')));
                                $statementRowPendingAmount = filled($row['period_year'] ?? null) && filled($row['period_month'] ?? null)
                                    ? max((float) ($row['debt_raw'] ?? 0), max((float) ($row['exigible_raw'] ?? 0) - (float) ($row['paid_raw'] ?? $row['receipt_paid_raw'] ?? 0), 0))
                                    : max((float) ($row['debt_raw'] ?? 0), 0);
                                $statementRowUnapplyAmount = filled($row['period_year'] ?? null) && filled($row['period_month'] ?? null)
                                    ? max((float) ($row['receipt_paid_raw'] ?? 0), 0)
                                    : max((float) ($row['imported_payment_paid_raw'] ?? 0), 0);
                                $canSelectStatementRow = $canManage
                                    && $selectedUnitId
                                    && $selectedImportedAccount
                                    && ($statementRowPendingAmount > 0 || $statementRowUnapplyAmount > 0);
                            @endphp
                            <tr>
                                @if ($canManage && $selectedUnitId && $selectedImportedAccount)
                                    <td class="bulk-select-cell">
                                        <input
                                            class="bulk-select"
                                            type="checkbox"
                                            name="selected_rows[]"
                                            value="{{ $statementRowSelectionValue }}"
                                            form="billing-statement-bulk-apply"
                                            data-select-group="billing-statement"
                                            data-select-apply-amount="{{ number_format((float) $statementRowPendingAmount, 2, '.', '') }}"
                                            data-select-unapply-amount="{{ number_format((float) $statementRowUnapplyAmount, 2, '.', '') }}"
                                            aria-label="Seleccionar {{ $row['name'] }}"
                                            @disabled(! $canSelectStatementRow)
                                        >
                                    </td>
                                @endif
                                <td>{{ $row['name'] }}</td>
                                <td>{{ $row['status'] }}</td>
                                <td>{{ $row['exigible'] }}</td>
                                <td>{{ $row['paid'] }}</td>
                                <td>
                                    <div class="billing-row-actions">
                                        <span>{{ $row['debt'] }}</span>
                                        @if ($canManage && $selectedUnitId && filled($row['period_year'] ?? null) && filled($row['period_month'] ?? null) && (($row['status_key'] ?? null) !== 'pagado' || ($row['receipt_paid_raw'] ?? 0) > 0))
                                            <div class="billing-row-actions__group">
                                                @if (($row['status_key'] ?? null) !== 'pagado')
                                                    <a class="button button--primary button--small" href="{!! route('billing.receipts.apply-period-form', $periodReceiptParams) !!}">
                                                        Aplicar pago
                                                    </a>
                                                @endif
                                                @if (($row['receipt_paid_raw'] ?? 0) > 0)
                                                    <form id="{{ $excelRowUnapplyFormId }}" method="POST" action="{{ route('billing.receipts.unapply', $row['receipt_id']) }}" class="inline-form">
                                                        @csrf
                                                        @method('PATCH')
                                                    </form>
                                                    <button
                                                        class="button button--ghost button--small"
                                                        type="button"
                                                        data-confirm-submit="{{ $excelRowUnapplyFormId }}"
                                                        data-confirm-title="¿Desaplicar este pago?"
                                                        data-confirm-text="Se borrará el pago registrado y el comentario de este periodo."
                                                        data-confirm-button-text="Sí, desaplicar"
                                                    >Desaplicar pago</button>
                                                @endif
                                            </div>
                                        @elseif ($canManage && $selectedUnitId && ((float) ($row['debt_raw'] ?? 0) > 0 || (float) ($row['imported_payment_paid_raw'] ?? 0) > 0))
                                            <div class="billing-row-actions__group">
                                                @if ((float) ($row['debt_raw'] ?? 0) > 0)
                                                    <a class="button button--primary button--small" href="{!! route('billing.imported-payments.apply-form', array_filter($importedPaymentParams, fn ($value) => filled($value))) !!}">
                                                        Aplicar pago
                                                    </a>
                                                @endif
                                                @if ((float) ($row['imported_payment_paid_raw'] ?? 0) > 0)
                                                    <form id="{{ $importedPaymentUnapplyFormId }}" method="POST" action="{{ route('billing.imported-payments.unapply') }}" class="inline-form">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="unit" value="{{ $selectedUnitId }}">
                                                        <input type="hidden" name="account" value="{{ $selectedImportedAccount?->id }}">
                                                        <input type="hidden" name="key" value="{{ $row['payload_key'] ?? '' }}">
                                                        <input type="hidden" name="concept" value="{{ $row['name'] ?? '' }}">
                                                        <input type="hidden" name="receipt_year" value="{{ $receiptYear }}">
                                                        <input type="hidden" name="condominium_profile_id" value="{{ $selectedCondominiumProfileId }}">
                                                    </form>
                                                    <button
                                                        class="button button--ghost button--small"
                                                        type="button"
                                                        data-confirm-submit="{{ $importedPaymentUnapplyFormId }}"
                                                        data-confirm-title="¿Desaplicar este pago?"
                                                        data-confirm-text="Se borrará el pago registrado para este concepto importado."
                                                        data-confirm-button-text="Sí, desaplicar"
                                                    >Desaplicar pago</button>
                                                @elseif ((float) ($row['debt_raw'] ?? 0) > 0)
                                                    <button class="button button--ghost button--small" type="button" disabled title="Sin pagos aplicados">Desaplicar pago</button>
                                                @endif
                                            </div>
                                        @endif
                                        @if ($canManage && $selectedUnitId && $selectedImportedAccount)
                                            <span class="bulk-action-slot" data-bulk-action-slot="billing-statement"></span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    @if ($canManage && $selectedUnitId && filled($row['period_year'] ?? null) && filled($row['period_month'] ?? null))
                                        <form class="form-grid form-grid--inline" method="POST" action="{{ route('billing.receipts.update-period-notes') }}">
                                            @csrf
                                            <input type="hidden" name="unit" value="{{ $selectedUnitId }}">
                                            <input type="hidden" name="year" value="{{ $row['period_year'] }}">
                                            <input type="hidden" name="month" value="{{ $row['period_month'] }}">
                                            <input type="hidden" name="amount" value="{{ $row['exigible_raw'] ?? '' }}">
                                            <input type="hidden" name="condominium_profile_id" value="{{ $selectedCondominiumProfileId }}">
                                            <input type="text" name="notes" value="{{ $row['receipt_notes'] }}" placeholder="Sin comentarios">
                                            <button class="button button--ghost button--small" type="submit">Guardar</button>
                                        </form>
                                    @else
                                        {{ $row['receipt_notes'] ?? 'Sin comentarios' }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @elseif (! $selectedUnitId)
            <div class="empty-state">
                <strong>Cuenta de base histórica sin unidad vinculada</strong>
                <p>La información de cobranza está disponible arriba. Vincula el registro a una unidad para administrar recibos mensuales.</p>
            </div>
        @else
            <div class="mini-stats mini-stats--five">
                <div class="mini-stat">
                    <span>Total recibos</span>
                    <strong>{{ $receiptSummary['total'] }}</strong>
                </div>
                <div class="mini-stat">
                    <span>Pagados</span>
                    <strong>{{ $receiptSummary['paid_count'] }}</strong>
                </div>
                <div class="mini-stat">
                    <span>Parciales</span>
                    <strong>{{ $receiptSummary['partial_count'] }}</strong>
                </div>
                <div class="mini-stat">
                    <span>Pendientes</span>
                    <strong>{{ $receiptSummary['pending_count'] }}</strong>
                </div>
                <div class="mini-stat">
                    <span>Saldo recibos</span>
                    <strong>${{ number_format((float) $receiptSummary['pending_amount'], 2) }}</strong>
                </div>
            </div>

            <div class="billing-receipts-toolbar">
                <div class="form-block-title">
                    <span>Captura de recibo mensual</span>
                    <small>La cuota total de esta unidad se paga cada mes.</small>
                    <small>Recuerda que la cuota mensual se paga cada mes.</small>
                </div>
                @if ($canManage)
                    <form class="form-grid form-grid--inline" method="POST" action="{{ route('billing.receipts.store') }}">
                        @csrf
                        <input type="hidden" name="unit_id" value="{{ $selectedUnitId }}">
                        <label class="field">
                            <span>Año</span>
                            <input type="number" min="2017" max="2100" name="period_year" value="{{ $receiptYear }}">
                        </label>
                        <label class="field">
                            <span>Mes</span>
                            <input type="number" min="1" max="12" name="period_month" value="{{ now()->month }}">
                        </label>
                        <label class="field">
                            <span>Cantidad a pagar</span>
                            <input type="number" step="0.01" min="0.01" name="amount_due" value="{{ number_format((float) $receiptDefaultAmount, 2, '.', '') }}">
                        </label>
                        <label class="field">
                            <span>Abonado</span>
                            <input type="number" step="0.01" min="0" name="amount_paid" value="0.00">
                        </label>
                        <label class="field field--full">
                            <span>Comentarios</span>
                            <input type="text" name="notes" value="" placeholder="Sin comentarios">
                        </label>
                        <div class="form-actions">
                            <button class="button button--primary button--small" type="submit">Guardar recibo</button>
                        </div>
                    </form>
                @endif
            </div>

        <div class="table-wrap">
            @if (empty($residentReceipts))
                <div class="empty-state">
                    <strong>No hay recibos registrados</strong>
                        <p>Agrega el primer recibo mensual para comenzar el control de pagados, parciales y pendientes.</p>
                    </div>
                @else
                    @if ($canManage)
                        <form id="resident-receipts-bulk-apply" class="bulk-selection-form" method="GET" action="{{ route('billing.receipts.bulk-apply-form') }}">
                            <input type="hidden" name="receipt_year" value="{{ $receiptYear }}">
                        </form>
                        <form id="resident-receipts-bulk-unapply" class="bulk-selection-form" method="POST" action="{{ route('billing.receipts.bulk-unapply') }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="receipt_year" value="{{ $receiptYear }}">
                        </form>
                        <button
                            id="resident-receipts-bulk-trigger"
                            class="button button--success button--small bulk-selection-trigger"
                            type="submit"
                            form="resident-receipts-bulk-apply"
                            data-bulk-action-button="resident-receipts"
                            data-bulk-action-type="apply"
                            hidden
                        >Abonar selección <span data-bulk-action-total>$0.00</span></button>
                        <button
                            id="resident-receipts-bulk-unapply-trigger"
                            class="button button--danger button--small bulk-selection-trigger"
                            type="button"
                            data-confirm-submit="resident-receipts-bulk-unapply"
                            data-bulk-action-button="resident-receipts"
                            data-bulk-action-type="unapply"
                            data-bulk-selection-field="receipt_ids"
                            data-confirm-title="¿Desaplicar recibos seleccionados?"
                            data-confirm-text="Se borrarán los pagos aplicados de los recibos marcados."
                            data-confirm-button-text="Sí, desaplicar"
                            hidden
                        >Desaplicar selección <span data-bulk-action-total>$0.00</span></button>
                    @endif
                    <table>
                        <thead>
                            <tr>
                                @if ($canManage)
                                    <th class="bulk-select-cell">
                                        <input class="bulk-select" type="checkbox" data-select-all="resident-receipts" aria-label="Seleccionar todos los recibos disponibles">
                                    </th>
                                @endif
                                <th>Nombre</th>
                                <th>ESTATUS</th>
                                <th>EXIGIBLE</th>
                                <th>PAGADO</th>
                                <th>ADEUDO</th>
                                <th>Comentarios</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($residentReceipts as $receipt)
                                @php
                                    $receiptUnapplyFormId = 'resident-receipt-unapply-'.$receipt['id'];
                                    $receiptApplyAmount = max((float) $receipt['pending_raw'], 0);
                                    $receiptUnapplyAmount = max((float) $receipt['amount_paid_raw'], 0);
                                    $canSelectReceipt = $canManage && ($receiptApplyAmount > 0 || $receiptUnapplyAmount > 0);
                                @endphp
                                <tr>
                                    @if ($canManage)
                                        <td class="bulk-select-cell">
                                            <input
                                                class="bulk-select"
                                                type="checkbox"
                                                name="receipt_ids[]"
                                                value="{{ $receipt['id'] }}"
                                                form="resident-receipts-bulk-apply"
                                                data-select-group="resident-receipts"
                                                data-select-apply-amount="{{ number_format($receiptApplyAmount, 2, '.', '') }}"
                                                data-select-unapply-amount="{{ number_format($receiptUnapplyAmount, 2, '.', '') }}"
                                                aria-label="Seleccionar {{ $receipt['period_label'] }}"
                                                @disabled(! $canSelectReceipt)
                                            >
                                        </td>
                                    @endif
                                    <td>{{ $receipt['period_short_label'] }}</td>
                                    <td>{{ mb_strtoupper($receipt['status_label'], 'UTF-8') }}</td>
                                    <td>{{ $receipt['amount_due'] }}</td>
                                    <td>{{ $receipt['amount_paid'] }}</td>
                                    <td>
                                        <div class="billing-row-actions">
                                            <span>{{ (float) $receipt['pending_raw'] > 0 ? $receipt['pending'] : '-' }}</span>
                                            @if ($canManage && ($receipt['status'] !== 'pagado' || (float) $receipt['amount_paid_raw'] > 0))
                                                <div class="billing-row-actions__group">
                                                    @if ($receipt['status'] !== 'pagado')
                                                        <a class="button button--primary button--small" href="{{ $receipt['apply_url'] }}">Aplicar pago</a>
                                                    @endif
                                                    @if ((float) $receipt['amount_paid_raw'] > 0)
                                                        <form id="{{ $receiptUnapplyFormId }}" method="POST" action="{{ route('billing.receipts.unapply', $receipt['id']) }}" class="inline-form">
                                                            @csrf
                                                            @method('PATCH')
                                                        </form>
                                                        <button
                                                            class="button button--ghost button--small"
                                                            type="button"
                                                            data-confirm-submit="{{ $receiptUnapplyFormId }}"
                                                            data-confirm-title="¿Desaplicar este pago?"
                                                            data-confirm-text="Se borrará el pago registrado y el comentario de este recibo."
                                                            data-confirm-button-text="Sí, desaplicar"
                                                        >Desaplicar pago</button>
                                                    @elseif ((float) $receipt['pending_raw'] > 0)
                                                        <button class="button button--ghost button--small" type="button" disabled title="Sin pagos aplicados">Desaplicar pago</button>
                                                    @endif
                                                </div>
                                            @endif
                                            @if ($canManage)
                                                <span class="bulk-action-slot" data-bulk-action-slot="resident-receipts"></span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        @if ($canManage)
                                            <form class="form-grid form-grid--inline" method="POST" action="{{ route('billing.receipts.update', $receipt['id']) }}">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="amount_due" value="{{ number_format((float) $receipt['amount_due_raw'], 2, '.', '') }}">
                                                <input type="text" name="notes" value="{{ $receipt['notes'] }}" placeholder="Sin comentarios">
                                                <button class="button button--ghost button--small" type="submit">Guardar</button>
                                            </form>
                                        @else
                                            {{ $receipt['notes'] ?: 'Sin comentarios' }}
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @endif
    </section>
</section>
