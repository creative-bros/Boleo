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
        @if ($selectedUnitId)
            <input type="hidden" name="unit" value="{{ $selectedUnitId }}">
        @endif
        <div class="form-actions">
            <button class="button button--ghost" type="submit">Buscar</button>
        </div>
    </form>
</section>

<section class="content-grid content-grid--billing">
    <article class="panel">
        <div class="panel__header">
            <h3>Resultados Recientes</h3>
            <span>{{ count($residents) }}</span>
        </div>
        @if (empty($residents))
            <div class="empty-state">
                <strong>No hay cuentas cargadas</strong>
                <p>Los residentes con movimientos de cobranza apareceran aqui cuando se registren.</p>
            </div>
        @else
            <div class="resident-list">
                @foreach ($residents as $resident)
                    <a class="resident-card resident-card--link" href="{{ route('billing', ['unit' => $resident['id'], 'q' => request('q'), 'condominium' => $condominiumQuery]) }}">
                        <div class="avatar">{{ substr($resident['name'], 0, 1) }}</div>
                        <div>
                            <strong>{{ $resident['name'] }}</strong>
                            <p>{{ $resident['unit'] }}</p>
                            <p>{{ $resident['email'] ?: 'Sin correo vinculado' }}</p>
                            <span class="badge {{ $resident['status'] === 'Deudor' ? 'badge--warning' : 'badge--success' }}">{{ $resident['status'] }}</span>
                        </div>
                        <strong>{{ $resident['balance'] }}</strong>
                    </a>
                @endforeach
            </div>
        @endif
    </article>

    <article class="panel">
        @if (blank($account['name']))
            <div class="empty-state empty-state--large">
                <strong>No hay perfil de cobranza seleccionado</strong>
                <p>Cuando exista informacion de una cuenta, aqui veran el detalle de pagos y datos del residente.</p>
            </div>
        @else
            <div class="billing-profile">
                <div class="avatar avatar--large">{{ substr($account['name'], 0, 1) }}</div>
                <div>
                    <h3>{{ $account['name'] }}</h3>
                    <p>{{ $account['location'] }} | {{ $account['role'] }}</p>
                    <p>{{ $account['email'] ?: 'Sin correo vinculado' }}</p>
                </div>
            </div>
            <div class="mini-stats">
                <div class="mini-stat">
                    <span>Periodo</span>
                    <strong>{{ $billingPeriod }}</strong>
                </div>
                <div class="mini-stat">
                    <span>Ultimo pago</span>
                    <strong>{{ $account['last_payment'] }}</strong>
                </div>
                <div class="mini-stat">
                    <span>Cuota mensual</span>
                    <strong>{{ $account['fee'] }}</strong>
                    <span class="table-sub">Se paga cada mes</span>
                </div>
                <div class="mini-stat">
                    <span>Pagado en el periodo</span>
                    <strong>{{ $account['paid'] }}</strong>
                </div>
            </div>
            <div class="readonly-note billing-reminder">
                <strong>Recordatorio de pago</strong>
                <p>La cuota total de esta unidad se paga cada mes. Aqui puedes revisar el monto mensual, lo abonado en el periodo y el saldo pendiente.</p>
            </div>
        @endif
    </article>

    <article class="panel panel--primary compact-panel">
        <h3>Estado de Cuenta</h3>
        <strong>{{ $account['balance'] }}</strong>
        <p>Saldo pendiente del periodo | {{ $account['status'] }}</p>
        <p>Recuerda que la cuota mensual se paga cada mes.</p>
        <a class="button button--light" href="{{ route('billing.pdf', ['unit' => $selectedUnitId]) }}">Generar Estado PDF</a>
        <a class="button button--ghost-light" href="{{ route('billing.report.pdf') }}">Reporte de Cobranza</a>
        <a class="button button--ghost-light" href="{{ route('billing.debtors.pdf') }}">Reporte de Deudores</a>
        <small>{{ $debtorsCount }} unidad(es) con saldo pendiente este mes.</small>
    </article>
</section>

<section class="panel">
    <div class="panel__header">
        <h3>Historial de Transacciones</h3>
        <span class="badge badge--neutral">{{ $canManage ? 'Registro de pagos funcional' : 'Solo lectura + PDF' }}</span>
    </div>
    @if ($canManage)
        <form class="form-grid" method="POST" action="{{ route('payments.store') }}">
            @csrf
            <label class="field">
                <span>Unidad</span>
                <select class="select-field" name="unit_id" required>
                    <option value="">Selecciona una unidad</option>
                    @foreach ($billingUnits as $unit)
                        <option value="{{ $unit->id }}" @selected((string) old('unit_id', $selectedUnitId) === (string) $unit->id)>
                            {{ $unit->tower }} - {{ $unit->unit_number }} | {{ $unit->owner_name }}
                        </option>
                    @endforeach
                </select>
            </label>
            <label class="field">
                <span>Concepto</span>
                <input type="text" name="concept" value="{{ old('concept') }}" required>
            </label>
            <label class="field">
                <span>Monto</span>
                <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" required>
            </label>
            <label class="field">
                <span>Fecha de pago</span>
                <input type="date" name="paid_at" value="{{ old('paid_at', now()->toDateString()) }}" required>
            </label>
            <div class="form-actions">
                <button class="button button--primary" type="submit">Registrar Pago</button>
            </div>
        </form>
    @else
        <div class="readonly-note">
            <strong>Acceso de usuario</strong>
            <p>Puedes revisar movimientos y descargar los PDFs de estado de cuenta, cobranza, deudores y recibos, pero el registro de pagos esta reservado para administradores.</p>
            <p>Recuerda que la cuota mensual se paga cada mes.</p>
        </div>
    @endif
    <div class="table-wrap">
        @if (empty($transactions))
            <div class="empty-state">
                <strong>No hay transacciones registradas</strong>
                <p>Los pagos y cargos capturados apareceran aqui cuando comiencen a usar el modulo.</p>
            </div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Concepto</th>
                        <th>Fecha</th>
                        <th>Monto</th>
                        <th>Estatus</th>
                        <th>Recibo</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($transactions as $transaction)
                        <tr>
                            <td>{{ $transaction['concept'] }}</td>
                            <td>{{ $transaction['date'] }}</td>
                            <td>{{ $transaction['amount'] }}</td>
                            <td><span class="badge badge--success">{{ $transaction['status'] }}</span></td>
                            <td>
                                <a class="button button--ghost button--small" href="{{ route('payments.receipt.pdf', $transaction['id']) }}">
                                    Descargar
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</section>
