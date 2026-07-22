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
                        <a class="button button--primary button--small" href="{{ route('billing', $quickAccountRouteParams) }}#estado-cuenta-residente">Ver cuenta</a>
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
                                <a class="button button--ghost button--small" href="{{ $command['href'] }}">
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

<section class="section-stack" id="estado-cuenta-residente">
    <div class="section-intro">
        <div>
            <p class="section-intro__eyebrow">Estado de cuenta</p>
            <h3 class="section-intro__title">Perfil del residente y saldo del periodo</h3>
        </div>
        <p class="section-intro__note">Aquí se concentra la información del residente seleccionado, cuánto debe pagar, cuánto ha pagado y su saldo pendiente.</p>
    </div>
        <article class="panel panel--primary compact-panel billing-actions-panel">
            <div class="billing-actions-panel__summary">
                <span>Estado de Cuenta</span>
                <strong>{{ $account['balance'] }}</strong>
                <p>Saldo pendiente del periodo | {{ $account['status'] }}</p>
            </div>
            <small>{{ $debtorsCount }} unidad(es) con saldo pendiente este mes.</small>
            @if ($canManage && $selectedReceiptApplyUrl)
                <div class="billing-actions-panel__commands">
                    <a class="button button--ghost-light" href="{{ $selectedReceiptApplyUrl }}">Aplicar pago</a>
                </div>
            @endif
        </article>
    </section>
</section>

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

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
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
                            @endphp
                            <tr>
                                <td>{{ $row['name'] }}</td>
                                <td>{{ $row['status'] }}</td>
                                <td>{{ $row['exigible'] }}</td>
                                <td>{{ $row['paid'] }}</td>
                                <td>
                                    <div class="billing-row-actions">
                                        <span>{{ $row['debt'] }}</span>
                                        @if ($canManage && $selectedUnitId && filled($row['period_year'] ?? null) && filled($row['period_month'] ?? null) && ($row['status_key'] ?? null) !== 'pagado')
                                            <div class="billing-row-actions__group">
                                                <a class="button button--primary button--small" href="{{ route('billing.receipts.apply-period-form', ['unit' => $selectedUnitId, 'year' => $row['period_year'], 'month' => $row['period_month'], 'amount' => $row['exigible_raw'] ?? null]) }}">
                                                    Aplicar pago
                                                </a>
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
                                                @else
                                                    <button class="button button--ghost button--small" type="button" disabled title="Sin pagos aplicados">Desaplicar pago</button>
                                                @endif
                                            </div>
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
                <form class="form-grid form-grid--inline" method="GET" action="{{ route('billing') }}">
                    <input type="hidden" name="unit" value="{{ $selectedUnitId }}">
                    <input type="hidden" name="q" value="{{ request('q') }}">
                    <input type="hidden" name="condominium" value="{{ $condominiumQuery }}">
                    <label class="field">
                        <span>Año</span>
                        <select class="select-field" name="receipt_year">
                            @foreach ($receiptYears as $year)
                                <option value="{{ $year }}" @selected((string) $receiptYear === (string) $year)>{{ $year }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div class="form-actions">
                        <button class="button button--ghost" type="submit">Ver año</button>
                    </div>
                </form>

                @if ($selectedImportedAccount)
            @endif
        </div>

        <div class="table-wrap">
            @if (empty($residentReceipts))
                <div class="empty-state">
                    <strong>No hay recibos en {{ $receiptYear }}</strong>
                        <p>Agrega el primer recibo mensual para comenzar el control de pagados, parciales y pendientes.</p>
                    </div>
                @else
                    <table>
                        <thead>
                            <tr>
                                <th>Mes y año</th>
                                <th>Cantidad</th>
                                <th>Abonado</th>
                                <th>Pendiente</th>
                                <th>Estatus</th>
                                <th>Comentarios</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($residentReceipts as $receipt)
                                @php
                                    $receiptFormId = 'resident-receipt-'.$receipt['id'];
                                    $receiptDeleteFormId = 'resident-receipt-delete-'.$receipt['id'];
                                    $receiptUnapplyFormId = 'resident-receipt-unapply-'.$receipt['id'];
                                @endphp
                                <tr>
                                    <td>{{ $receipt['period_label'] }}</td>
                                    <td>{{ $receipt['amount_due'] }}</td>
                                    <td>
                                        {{ $receipt['amount_paid'] }}
                                        @if ($canManage && (float) $receipt['amount_paid_raw'] > 0)
                                            <span class="table-sub">Pago aplicado</span>
                                        @endif
                                    </td>
                                    <td>{{ $receipt['pending'] }}</td>
                                    <td><span class="badge {{ $receipt['status_badge'] }}">{{ $receipt['status_label'] }}</span></td>
                                    <td>{{ $receipt['notes'] ?: 'Sin comentarios' }}</td>
                                    <td>
                                        @if ($canManage && $receipt['status'] !== 'pagado')
                                            <a class="button button--primary button--small" href="{{ $receipt['apply_url'] }}">Aplicar pago</a>
                                            @if ((float) $receipt['amount_paid_raw'] > 0)
                                                <button
                                                    class="button button--ghost button--small"
                                                    type="button"
                                                    data-confirm-submit="{{ $receiptUnapplyFormId }}"
                                                    data-confirm-title="¿Desaplicar este pago?"
                                                    data-confirm-text="Se borrará el pago registrado y el comentario de este recibo."
                                                    data-confirm-button-text="Sí, desaplicar"
                                                >Desaplicar pago</button>
                                            @else
                                                <button class="button button--ghost button--small" type="button" disabled title="Sin pagos aplicados">Desaplicar pago</button>
                                            @endif
                                        @endif
                                        @if ($canManage)
                                            <form id="{{ $receiptFormId }}" method="POST" action="{{ route('billing.receipts.update', $receipt['id']) }}">
                                                @csrf
                                                @method('PATCH')
                                            </form>
                                            <form id="{{ $receiptDeleteFormId }}" method="POST" action="{{ route('billing.receipts.delete', $receipt['id']) }}" class="inline-form">
                                                @csrf
                                                @method('DELETE')
                                            </form>
                                            <form id="{{ $receiptUnapplyFormId }}" method="POST" action="{{ route('billing.receipts.unapply', $receipt['id']) }}" class="inline-form">
                                                @csrf
                                                @method('PATCH')
                                            </form>
                                            <details class="receipt-edit-details">
                                                <summary>Editar comentarios</summary>
                                                <div class="form-grid form-grid--receipt-payment">
                                                    <label class="field">
                                                        <span>Cantidad a pagar</span>
                                                        <input type="number" step="0.01" min="0.01" name="amount_due" value="{{ number_format((float) $receipt['amount_due_raw'], 2, '.', '') }}" form="{{ $receiptFormId }}">
                                                    </label>
                                                    <label class="field field--full">
                                                        <span>Comentarios</span>
                                                        <textarea name="notes" rows="2" form="{{ $receiptFormId }}">{{ $receipt['notes'] }}</textarea>
                                                    </label>
                                                    <div class="form-actions">
                                                        <button class="button button--primary button--small" type="submit" form="{{ $receiptFormId }}">Guardar</button>
                                                    </div>
                                                </div>
                                            </details>
                                            <button
                                                class="button button--ghost button--small"
                                                type="button"
                                                data-confirm-submit="{{ $receiptDeleteFormId }}"
                                                data-confirm-title="¿Eliminar este recibo?"
                                                data-confirm-text="Esta acción no se puede revertir."
                                                data-confirm-button-text="Sí, eliminar"
                                            >Eliminar</button>
                                        @endif
                                        <a class="button button--ghost button--small" href="{{ $receipt['pdf_url'] }}">PDF</a>
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
