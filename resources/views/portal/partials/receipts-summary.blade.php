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
            <h3>{{ $receiptType === 'ordinarias' ? 'Cuotas mensuales' : 'Cuotas extraordinarias pendientes' }}</h3>
            <span>Saldo pendiente: ${{ number_format((float) $total, 2) }}</span>
        </div>

        <div class="table-wrap">
            @if (empty($rows))
                <div class="empty-state">
                    <strong>{{ $receiptType === 'ordinarias' ? 'Sin cuotas ordinarias registradas' : 'Sin cuotas extraordinarias pendientes' }}</strong>
                    <p>Esta cuenta no tiene {{ $receiptType === 'ordinarias' ? 'cuotas mensuales registradas' : 'cuotas extraordinarias con saldo pendiente' }}.</p>
                </div>
            @elseif ($receiptType === 'ordinarias')
                <table>
                    <thead>
                        <tr>
                            <th>Periodo</th>
                            <th>Estatus</th>
                            <th>Exigible</th>
                            <th>Pagado</th>
                            <th>Adeudo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td>{{ $row['name'] }}</td>
                                <td>{{ mb_strtoupper($row['status'] ?? '', 'UTF-8') }}</td>
                                <td>{{ $row['exigible'] }}</td>
                                <td>{{ $row['paid'] }}</td>
                                <td>{{ $row['debt'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
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
