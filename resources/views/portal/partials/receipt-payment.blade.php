<section class="section-stack">
    <div class="section-intro">
        <div>
            <p class="section-intro__eyebrow">Recibo {{ $receiptPeriodLabel }}</p>
            <h3 class="section-intro__title">{{ $receipt->unit?->owner_name ?? 'Condomino sin nombre' }}</h3>
        </div>
        <p class="section-intro__note">{{ trim(($receipt->unit?->tower ?? '').' - '.($receipt->unit?->unit_number ?? ''), ' -') ?: 'Sin unidad' }}</p>
    </div>

    <section class="content-grid content-grid--settings-bottom">
        <article class="panel">
            <div class="panel__header">
                <h3>Datos del pago</h3>
                <span>Pendiente: ${{ number_format((float) $pendingAmount, 2) }}</span>
            </div>

            <form class="form-grid form-grid--receipt-payment" method="POST" action="{{ route('billing.receipts.apply', $receipt) }}" data-receipt-payment-form>
                @csrf
                @method('PATCH')

                <label class="field">
                    <span>Cantidad a pagar</span>
                    <input type="number" step="0.01" min="0.01" name="amount_due" value="{{ old('amount_due', number_format((float) $receipt->amount_due, 2, '.', '')) }}" required>
                </label>
                <label class="field">
                    <span>Fecha de pago</span>
                    <input type="date" name="paid_at" value="{{ old('paid_at', now()->toDateString()) }}" required>
                </label>
                <label class="field">
                    <span>Método</span>
                    <select class="select-field" name="payment_method" required>
                        <option value="transferencia" @selected(old('payment_method') === 'transferencia')>Transferencia</option>
                        <option value="efectivo" @selected(old('payment_method') === 'efectivo')>Efectivo</option>
                    </select>
                </label>
                <label class="field">
                    <span>Abono</span>
                    <select class="select-field" name="payment_type" data-payment-type required>
                        <option value="total" @selected(old('payment_type') !== 'parcial')>Total</option>
                        <option value="parcial" @selected(old('payment_type') === 'parcial')>Parcial</option>
                    </select>
                </label>
                <label class="field" data-partial-amount-field hidden>
                    <span>Monto parcial</span>
                    <input type="number" step="0.01" min="0.01" max="{{ number_format((float) $pendingAmount, 2, '.', '') }}" name="partial_amount" value="{{ old('partial_amount') }}">
                </label>
                <label class="field field--full">
                    <span>Notas</span>
                    <textarea name="notes" rows="3">{{ old('notes', $receipt->notes) }}</textarea>
                </label>
                <div class="form-actions">
                    <a class="button button--ghost" href="{{ $backUrl }}">Cancelar</a>
                    <button class="button button--primary" type="submit">Aplicar pago</button>
                </div>
            </form>
        </article>

        <article class="panel compact-panel">
            <h3>Resumen</h3>
            <div class="summary-list">
                <div class="summary-list__row">
                    <span>Cantidad</span>
                    <strong>${{ number_format((float) $receipt->amount_due, 2) }}</strong>
                </div>
                <div class="summary-list__row">
                    <span>Abonado</span>
                    <strong>${{ number_format((float) $receipt->amount_paid, 2) }}</strong>
                </div>
                <div class="summary-list__row">
                    <span>Pendiente</span>
                    <strong>${{ number_format((float) $pendingAmount, 2) }}</strong>
                </div>
                <div class="summary-list__row">
                    <span>Estatus</span>
                    <strong>{{ ucfirst($receipt->status) }}</strong>
                </div>
            </div>

            @if ($receipt->payments->isNotEmpty())
                <form method="POST" action="{{ route('billing.receipts.unapply', $receipt) }}" class="form-actions">
                    @csrf
                    @method('PATCH')
                    <button class="button button--ghost" type="submit">Desaplicar</button>
                </form>
            @endif
        </article>
    </section>
</section>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const typeSelect = document.querySelector('[data-payment-type]');
        const partialField = document.querySelector('[data-partial-amount-field]');

        if (!typeSelect || !partialField) {
            return;
        }

        const syncPartialField = () => {
            const isPartial = typeSelect.value === 'parcial';
            partialField.hidden = !isPartial;
            partialField.querySelector('input').required = isPartial;
        };

        typeSelect.addEventListener('change', syncPartialField);
        syncPartialField();
    });
</script>
