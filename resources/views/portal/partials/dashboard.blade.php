<section class="stats-grid stats-grid--three">
    @foreach ($stats as $stat)
        <article class="stat-card stat-card--{{ $stat['tone'] }}">
            <span>{{ $stat['label'] }}</span>
            <strong>{{ $stat['value'] }}</strong>
            <small>{{ $stat['meta'] }}</small>
        </article>
    @endforeach
</section>

<section class="content-grid content-grid--dashboard">
    <article class="panel panel--chart">
        <div class="panel__header">
            <h3>Distribucion Presupuestaria</h3>
            <span>Ultimos 6 meses</span>
        </div>
        @if (empty($panels['budget']))
            <div class="empty-state empty-state--large">
                <strong>Aun no hay datos presupuestarios</strong>
                <p>Los graficos apareceran aqui cuando registren ingresos y egresos reales.</p>
            </div>
        @else
            <div class="bar-chart">
                @foreach ($panels['budget'] as $bar)
                    <div class="bar-chart__item">
                        <div class="bar-chart__bar" style="height: {{ $bar['value'] }}%"></div>
                        <span>{{ $bar['month'] }}</span>
                    </div>
                @endforeach
            </div>
            <div class="chart-summary">
                <div><span>Gastos operativos</span><strong>--</strong></div>
                <div><span>Fondo de reserva</span><strong class="positive">--</strong></div>
            </div>
        @endif
    </article>

    <article class="panel panel--tasks">
        <div class="panel__header">
            <h3>Mantenimiento</h3>
            <span>Sin registros</span>
        </div>
        @if (empty($panels['tasks']))
            <div class="empty-state">
                <strong>No hay tareas cargadas</strong>
                <p>Las incidencias de mantenimiento apareceran aqui cuando se registren.</p>
            </div>
        @else
            <div class="task-list">
                @foreach ($panels['tasks'] as $task)
                    <div class="task-card">
                        <strong>{{ $task['title'] }}</strong>
                        <p>{{ $task['detail'] }}</p>
                        <span class="badge badge--warning">{{ $task['status'] }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </article>
</section>

<section class="panel">
    <div class="panel__header">
        <h3>Ultimos Movimientos</h3>
        <span>Vista rapida</span>
    </div>
    <div class="table-wrap">
        @if (empty($panels['movements']))
            <div class="empty-state">
                <strong>No hay movimientos registrados</strong>
                <p>En esta tabla se reflejaran pagos, gastos y otros movimientos cuando ustedes los capturen.</p>
            </div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Referencia</th>
                        <th>Persona asignada</th>
                        <th>Concepto</th>
                        <th>Fecha</th>
                        <th>Monto</th>
                        <th>Estatus</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($panels['movements'] as $movement)
                        <tr>
                            <td>{{ $movement['reference'] }}</td>
                            <td>
                                <strong>{{ $movement['resident'] }}</strong>
                                <span class="table-sub">{{ $movement['email'] }}</span>
                            </td>
                            <td>{{ $movement['concept'] }}</td>
                            <td>{{ $movement['date'] }}</td>
                            <td>{{ $movement['amount'] }}</td>
                            <td><span class="badge badge--neutral">{{ $movement['status'] }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</section>
