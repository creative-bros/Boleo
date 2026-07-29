<section class="section-stack">
    <div class="section-intro">
        <div>
            <p class="section-intro__eyebrow">Concepto importado</p>
            <h3 class="section-intro__title">{{ $paymentConcept }}</h3>
        </div>
        <p class="section-intro__note">{{ $unitLabel }}</p>
    </div>

    <section class="content-grid content-grid--settings-bottom">
        <article class="panel">
            <div class="panel__header">
                <h3>Datos del pago</h3>
                <span>Pendiente: ${{ number_format((float) $pendingAmount, 2) }}</span>
            </div>

            <form class="form-grid form-grid--receipt-payment" method="POST" action="{{ route('billing.imported-payments.apply') }}" data-receipt-payment-form>
                @csrf
                @method('PATCH')
                <input type="hidden" name="unit" value="{{ $unit->id }}">
                <input type="hidden" name="account" value="{{ $importedAccount?->id }}">
                <input type="hidden" name="key" value="{{ $payloadKey }}">
                <input type="hidden" name="concept" value="{{ $paymentConcept }}">
                <input type="hidden" name="receipt_year" value="{{ $receiptYear }}">
                <input type="hidden" name="condominium_profile_id" value="{{ $selectedCondominiumProfileId }}">

                <label class="field">
                    <span>Cantidad a pagar</span>
                    <input type="number" step="0.01" min="0.01" name="amount_due" value="{{ old('amount_due', number_format((float) $pendingAmount, 2, '.', '')) }}" required readonly>
                </label>
                <label class="field">
                    <span>Fecha de pago</span>
                    <input type="date" name="payment_date" value="{{ old('payment_date', now()->toDateString()) }}" required>
                </label>
                <label class="field">
                    <span>Fecha de aplicación del pago</span>
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
                    <span>Concepto</span>
                    <strong>{{ $paymentConcept }}</strong>
                </div>
                <div class="summary-list__row">
                    <span>Pendiente</span>
                    <strong>${{ number_format((float) $pendingAmount, 2) }}</strong>
                </div>
                <div class="summary-list__row">
                    <span>Residente</span>
                    <strong>{{ $unit->owner_name ?: 'Sin residente' }}</strong>
                </div>
            </div>
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
