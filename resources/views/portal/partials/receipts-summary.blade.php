<section class="section-stack">
    <div class="section-intro">
        <div>
            <p class="section-intro__eyebrow">{{ $receiptType === 'ordinarias' ? 'Recibos ordinarios' : 'Recibos extraordinarios' }}</p>
            <h3 class="section-intro__title">{{ $account->owner_name ?: 'Sin residente' }}</h3>
        </div>
        <p class="section-intro__note">{{ trim(($account->tower ?: '').' - '.($account->unit_number ?: ''), ' -') ?: 'Sin unidad' }}</p>
    </div>

    <section class="panel">
        <div class="panel__header">
            <h3>{{ $receiptType === 'ordinarias' ? 'Cuotas mensuales' : 'Cuotas extraordinarias' }}</h3>
            <span>Saldo pendiente: ${{ number_format((float) $total, 2) }}</span>
        </div>

        <div class="table-wrap">
            @if (empty($rows))
                <div class="empty-state">
                    <strong>{{ $receiptType === 'ordinarias' ? 'Sin cuotas ordinarias registradas' : 'Sin cuotas extraordinarias pendientes' }}</strong>
                    <p>
                        @if (! $account->unit_id)
                            Vincula esta cuenta a una unidad para poder aplicar, desaplicar o editar pagos.
                        @else
                            Esta cuenta no tiene {{ $receiptType === 'ordinarias' ? 'cuotas mensuales registradas' : 'cuotas extraordinarias con saldo pendiente' }}.
                        @endif
                    </p>
                </div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>{{ $receiptType === 'ordinarias' ? 'Periodo' : 'Concepto / periodo' }}</th>
                            <th>Estatus</th>
                            <th>Exigible</th>
                            <th>Pagado</th>
                            <th>Adeudo</th>
                            @if ($canManage)
                                <th>Acciones</th>
                                <th>Editar</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php
                                $isPeriodRow = filled($row['period_year'] ?? null) && filled($row['period_month'] ?? null);
                                $unapplyFormId = 'summary-unapply-'.$loop->index;
                                $periodReceiptParams = [
                                    'unit' => $row['unit_id'],
                                    'year' => $row['period_year'] ?? null,
                                    'month' => $row['period_month'] ?? null,
                                    'amount' => $row['exigible_raw'] ?? null,
                                    'condominium_profile_id' => $row['condominium_profile_id'],
                                    'summary_account' => $account->id,
                                    'summary_type' => $receiptType,
                                ];
                                $importedPaymentParams = [
                                    'unit' => $row['unit_id'],
                                    'account' => $row['account_id'],
                                    'key' => $row['payload_key'] ?? null,
                                    'concept' => $row['name'] ?? null,
                                    'receipt_year' => $row['receipt_year'],
                                    'condominium_profile_id' => $row['condominium_profile_id'],
                                    'summary_account' => $account->id,
                                    'summary_type' => $receiptType,
                                ];
                            @endphp
                            <tr>
                                <td>{{ $row['name'] }}</td>
                                <td>{{ mb_strtoupper((string) ($row['status'] ?? ''), 'UTF-8') }}</td>
                                <td>{{ $row['exigible'] }}</td>
                                <td>{{ $row['paid'] }}</td>
                                <td>{{ $row['debt'] }}</td>
                                @if ($canManage)
                                    <td>
                                        <div class="billing-row-actions__group">
                                            @if ($isPeriodRow)
                                                @if (($row['status_key'] ?? null) !== 'pagado')
                                                    <a class="button button--primary button--small" href="{!! route('billing.receipts.apply-period-form', $periodReceiptParams) !!}">
                                                        Aplicar pago
                                                    </a>
                                                @endif
                                                @if (($row['receipt_paid_raw'] ?? 0) > 0)
                                                    <form id="{{ $unapplyFormId }}" method="POST" action="{{ route('billing.receipts.unapply', $row['receipt_id']) }}" class="inline-form">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="summary_account" value="{{ $account->id }}">
                                                        <input type="hidden" name="summary_type" value="{{ $receiptType }}">
                                                    </form>
                                                    <button
                                                        class="button button--ghost button--small"
                                                        type="button"
                                                        data-confirm-submit="{{ $unapplyFormId }}"
                                                        data-confirm-title="¿Desaplicar este pago?"
                                                        data-confirm-text="Se borrará el pago registrado de este periodo."
                                                        data-confirm-button-text="Sí, desaplicar"
                                                    >Desaplicar pago</button>
                                                @endif
                                            @else
                                                @if ((float) ($row['debt_raw'] ?? 0) > 0)
                                                    <a class="button button--primary button--small" href="{!! route('billing.imported-payments.apply-form', array_filter($importedPaymentParams, fn ($value) => filled($value))) !!}">
                                                        Aplicar pago
                                                    </a>
                                                @endif
                                                @if ((float) ($row['imported_payment_paid_raw'] ?? 0) > 0)
                                                    <form id="{{ $unapplyFormId }}" method="POST" action="{{ route('billing.imported-payments.unapply') }}" class="inline-form">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="unit" value="{{ $row['unit_id'] }}">
                                                        <input type="hidden" name="account" value="{{ $row['account_id'] }}">
                                                        <input type="hidden" name="key" value="{{ $row['payload_key'] ?? '' }}">
                                                        <input type="hidden" name="concept" value="{{ $row['name'] ?? '' }}">
                                                        <input type="hidden" name="receipt_year" value="{{ $row['receipt_year'] }}">
                                                        <input type="hidden" name="condominium_profile_id" value="{{ $row['condominium_profile_id'] }}">
                                                        <input type="hidden" name="summary_account" value="{{ $account->id }}">
                                                        <input type="hidden" name="summary_type" value="{{ $receiptType }}">
                                                    </form>
                                                    <button
                                                        class="button button--ghost button--small"
                                                        type="button"
                                                        data-confirm-submit="{{ $unapplyFormId }}"
                                                        data-confirm-title="¿Desaplicar este pago?"
                                                        data-confirm-text="Se borrará el pago registrado para este concepto."
                                                        data-confirm-button-text="Sí, desaplicar"
                                                    >Desaplicar pago</button>
                                                @endif
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <details class="receipt-edit">
                                            <summary class="button button--ghost button--small">Editar</summary>
                                            @if ($isPeriodRow)
                                                <form class="form-grid form-grid--inline" method="POST" action="{{ route('billing.receipts.update-period') }}">
                                                    @csrf
                                                    <input type="hidden" name="unit" value="{{ $row['unit_id'] }}">
                                                    <input type="hidden" name="year" value="{{ $row['period_year'] }}">
                                                    <input type="hidden" name="month" value="{{ $row['period_month'] }}">
                                                    <input type="hidden" name="condominium_profile_id" value="{{ $row['condominium_profile_id'] }}">
                                                    <input type="hidden" name="summary_account" value="{{ $account->id }}">
                                                    <input type="hidden" name="summary_type" value="{{ $receiptType }}">
                                                    <label class="field">
                                                        <span>Exigible</span>
                                                        <input type="number" step="0.01" min="0.01" name="amount_due" value="{{ number_format((float) ($row['exigible_raw'] ?? 0), 2, '.', '') }}" required>
                                                    </label>
                                                    <label class="field">
                                                        <span>Abonado</span>
                                                        <input type="number" step="0.01" min="0" name="amount_paid" value="{{ number_format((float) ($row['receipt_paid_raw'] ?? $row['paid_raw'] ?? 0), 2, '.', '') }}">
                                                    </label>
                                                    <label class="field field--full">
                                                        <span>Comentarios</span>
                                                        <input type="text" name="notes" value="{{ $row['receipt_notes'] ?? '' }}" placeholder="Sin comentarios">
                                                    </label>
                                                    <button class="button button--primary button--small" type="submit">Guardar cambios</button>
                                                </form>
                                            @else
                                                <form class="form-grid form-grid--inline" method="POST" action="{{ route('billing.imported-payments.update') }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="unit" value="{{ $row['unit_id'] }}">
                                                    <input type="hidden" name="account" value="{{ $row['account_id'] }}">
                                                    <input type="hidden" name="key" value="{{ $row['payload_key'] ?? '' }}">
                                                    <input type="hidden" name="concept" value="{{ $row['name'] ?? '' }}">
                                                    <input type="hidden" name="receipt_year" value="{{ $row['receipt_year'] }}">
                                                    <input type="hidden" name="condominium_profile_id" value="{{ $row['condominium_profile_id'] }}">
                                                    <input type="hidden" name="summary_account" value="{{ $account->id }}">
                                                    <input type="hidden" name="summary_type" value="{{ $receiptType }}">
                                                    <label class="field">
                                                        <span>Adeudo</span>
                                                        <input type="number" step="0.01" min="0" name="amount_due" value="{{ number_format((float) ($row['debt_raw'] ?? 0), 2, '.', '') }}" required>
                                                    </label>
                                                    <button class="button button--primary button--small" type="submit">Guardar cambios</button>
                                                </form>
                                            @endif
                                        </details>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="form-actions">
            <a class="button button--ghost" href="{{ $backUrl }}">Volver a la cuenta</a>
        </div>
    </section>
</section>
