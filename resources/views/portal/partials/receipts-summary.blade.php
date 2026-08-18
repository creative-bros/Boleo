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
            <h3>{{ $receiptType === 'ordinarias' ? 'Cuotas mensuales pendientes' : 'Cuotas extraordinarias pendientes' }}</h3>
            <span>Saldo: ${{ number_format((float) $total, 2) }}</span>
        </div>

        <div class="table-wrap">
            @if (empty($rows))
                <div class="empty-state">
                    <strong>{{ $receiptType === 'ordinarias' ? 'Sin cuotas ordinarias pendientes' : 'Sin cuotas extraordinarias pendientes' }}</strong>
                    <p>Esta cuenta no tiene {{ $receiptType === 'ordinarias' ? 'cuotas mensuales' : 'cuotas extraordinarias' }} registradas con saldo pendiente.</p>
                </div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Concepto / periodo</th>
                            <th>Importe</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td>{{ $row['concept'] }}</td>
                                <td>${{ number_format((float) $row['amount'], 2) }}</td>
                            </tr>
                        @endforeach
                        <tr>
                            <td><strong>Total</strong></td>
                            <td><strong>${{ number_format((float) $total, 2) }}</strong></td>
                        </tr>
                    </tbody>
                </table>
            @endif
        </div>

        <div class="form-actions">
            <a class="button button--ghost" href="{{ $backUrl }}">Volver a la cuenta</a>
        </div>
    </section>
</section>
