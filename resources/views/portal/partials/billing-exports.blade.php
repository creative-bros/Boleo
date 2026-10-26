<section class="panel">
    <div class="panel__header"><h3>Descargar reportes de finanzas</h3></div>
    <form class="form-grid" method="GET" action="{{ route('billing.export') }}">
        <label class="field">
            <span>Condominio</span>
            <select class="select-field" name="condominium_profile_id" required>
                @foreach ($condominiumProfiles as $option)
                    <option value="{{ $option->id }}" @selected($selectedCondominiumProfileId === $option->id)>{{ $option->commercial_name ?: 'Condominio #'.$option->id }}</option>
                @endforeach
            </select>
        </label>
        <label class="field">
            <span>Reporte</span>
            <select class="select-field" name="report" required>
                <option value="base">Base completa: desglose por mes, pagado y adeudo</option>
                <option value="debtors">Departamentos con adeudo hasta el mes anterior</option>
                <option value="captured">Pagos capturados en la fecha seleccionada</option>
                <option value="month-payments">Departamentos con pagos recibidos en el mes corriente</option>
                <option value="overdue">Más de dos meses de mantenimiento vencidos</option>
                <option value="boleo-maintenance">Real de Boleo II: adeudo desde enero 2023 de $2,000 o más</option>
                <option value="boleo-tags">Real de Boleo II: con TAG y adeudo desde enero 2024 de $2,000 o más</option>
            </select>
        </label>
        <label class="field">
            <span>Fecha de captura (para el reporte de pagos capturados)</span>
            <input type="date" name="date" value="{{ now('America/Mexico_City')->toDateString() }}" required>
        </label>
        <p class="field field--full">Descarga CSV compatible con Excel. El adeudo cierra al mes anterior. La base incluye el mes corriente y recibos futuros identificados fuera del adeudo vencido. Los reportes de vencidos cierran al mes anterior; los de Real de Boleo II usan una cuota base de $1,000. Los reportes especiales usan periodos mensuales identificados; los saldos históricos sin mes se conservan en la base completa. Los pagos del mes usan la fecha de pago; los capturados usan la fecha de registro en el sistema.</p>
        <div class="form-actions"><button class="button button--primary" type="submit">Descargar reporte CSV</button></div>
    </form>
</section>
