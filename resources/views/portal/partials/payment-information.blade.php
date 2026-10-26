<details class="receipt-edit">
    <summary class="button button--ghost button--small">Ver información del recibo</summary>
    <p>{{ $paymentNotes ?: 'Sin comentarios' }}</p>
    @forelse ($paymentInformation as $paymentInfo)
        <p>
            Pago #{{ $paymentInfo['id'] }} · ${{ number_format((float) $paymentInfo['amount'], 2) }}<br>
            Fecha de pago: {{ $paymentInfo['payment_date'] ?: 'Sin registro' }}<br>
            Aplicación: {{ $paymentInfo['applied_at'] ?: 'Sin registro' }}<br>
            Método: {{ $paymentInfo['method'] ?: 'Sin registro' }} · {{ $paymentInfo['status'] }}
        </p>
        <a class="button button--ghost button--small" href="{{ route('payments.receipt.pdf', $paymentInfo['id']) }}">Descargar comprobante</a>
    @empty
        <p>Sin pagos capturados en el sistema. Los pagos históricos importados se muestran en la columna Pagado.</p>
    @endforelse
</details>
