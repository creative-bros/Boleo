<section class="stats-grid stats-grid--three">
    @foreach ($summary as $item)
        <article class="stat-card stat-card--{{ $item['tone'] }}">
            <span>{{ $item['label'] }}</span>
            <strong>{{ $item['value'] }}</strong>
            <small>{{ $item['meta'] }}</small>
        </article>
    @endforeach
</section>

@if ($canManage)
    <section class="content-grid content-grid--settings-bottom">
        <article class="panel">
            <div class="panel__header">
                <h3>Tareas</h3>
                <span>Operativo</span>
            </div>
            <form class="form-grid" method="POST" action="{{ route('maintenance.tasks.store') }}">
                @csrf
                <label class="field">
                    <span>Tarea</span>
                    <input type="text" name="title" value="{{ old('title') }}" required>
                </label>
                <label class="field">
                    <span>Area</span>
                    <input type="text" name="area" value="{{ old('area') }}">
                </label>
                <label class="field">
                    <span>Proveedor</span>
                    <select name="provider_id" class="select-field">
                        <option value="">Sin proveedor</option>
                        @foreach ($providerOptions as $provider)
                            <option value="{{ $provider->id }}">{{ $provider->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field">
                    <span>Ultimo costo</span>
                    <input type="number" name="last_cost" step="0.01" min="0" value="{{ old('last_cost') }}">
                </label>
                <label class="field">
                    <span>Fecha compromiso</span>
                    <input type="date" name="due_date" value="{{ old('due_date') }}">
                </label>
                <label class="field">
                    <span>Estatus</span>
                    <select name="status" class="select-field" required>
                        @foreach ($taskStatuses as $taskStatus)
                            <option value="{{ $taskStatus }}">{{ $taskStatus }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field field--full">
                    <span>Notas</span>
                    <input type="text" name="notes" value="{{ old('notes') }}">
                </label>
                <div class="form-actions">
                    <button class="button button--primary" type="submit">Registrar tarea</button>
                </div>
            </form>
        </article>

        <article class="panel">
            <div class="panel__header">
                <h3>Gastos</h3>
                <span>Financiero</span>
            </div>
            <form class="form-grid" method="POST" action="{{ route('maintenance.expenses.store') }}">
                @csrf
                <label class="field">
                    <span>Fecha</span>
                    <input type="date" name="spent_at" value="{{ old('spent_at', now()->toDateString()) }}" required>
                </label>
                <label class="field">
                    <span>Concepto</span>
                    <input type="text" name="concept" value="{{ old('concept') }}" required>
                </label>
                <label class="field">
                    <span>Proveedor</span>
                    <select name="provider_id" class="select-field">
                        <option value="">Sin proveedor</option>
                        @foreach ($providerOptions as $provider)
                            <option value="{{ $provider->id }}">{{ $provider->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field">
                    <span>Monto</span>
                    <input type="number" name="amount" step="0.01" min="0.01" value="{{ old('amount') }}" required>
                </label>
                <div class="form-actions">
                    <button class="button button--primary" type="submit">Registrar gasto</button>
                </div>
            </form>
        </article>
    </section>
@endif

<section class="board-grid">
    @foreach ($board as $column => $tickets)
        <article class="panel">
            <div class="panel__header">
                <h3>{{ $column }}</h3>
                <span>Tablero</span>
            </div>
            @if (empty($tickets))
                <div class="empty-state">
                    <strong>Sin tickets en esta columna</strong>
                    <p>Los reportes reales aparecerán aquí cuando se registren.</p>
                </div>
            @else
                <div class="ticket-list">
                    @foreach ($tickets as $ticket)
                        <div class="ticket-card">
                            <span class="badge badge--neutral">{{ $ticket['priority'] }}</span>
                            <strong>{{ $ticket['title'] }}</strong>
                            <p>Ticket {{ $ticket['ticket'] }}</p>
                            <small>{{ $ticket['meta'] }}</small>
                        </div>
                    @endforeach
                </div>
            @endif
        </article>
    @endforeach
</section>

<section class="content-grid content-grid--maintenance">
    <article class="panel">
        <div class="panel__header">
            <h3>Historial de Egresos</h3>
            <a class="button button--ghost" href="{{ route('maintenance.pdf') }}">Descargar PDF</a>
        </div>
        <div class="table-wrap">
            @if (empty($expenses))
                <div class="empty-state">
                    <strong>No hay egresos registrados</strong>
                    <p>Los gastos de mantenimiento aparecerán aquí cuando los capturen.</p>
                </div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Concepto</th>
                            <th>Proveedor</th>
                            <th>Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($expenses as $expense)
                            <tr>
                                <td>{{ $expense['date'] }}</td>
                                <td>{{ $expense['concept'] }}</td>
                                <td>{{ $expense['provider'] }}</td>
                                <td>{{ $expense['amount'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </article>

    <article class="panel">
        <div class="panel__header">
            <h3>Directorio de Proveedores</h3>
            <span class="badge badge--neutral">{{ $canManage ? 'Anadir funcional' : 'Solo lectura + PDF' }}</span>
        </div>
        @if ($canManage)
            <form class="form-grid" method="POST" action="{{ route('providers.store') }}">
                @csrf
                <label class="field">
                    <span>Nombre del proveedor</span>
                    <input type="text" name="name" value="{{ old('name') }}" required>
                </label>
                <label class="field">
                    <span>Categoria</span>
                    <input type="text" name="category" value="{{ old('category') }}" required>
                </label>
                <label class="field">
                    <span>Telefono</span>
                    <input type="text" name="phone" value="{{ old('phone') }}">
                </label>
                <label class="field">
                    <span>Correo</span>
                    <input type="email" name="email" value="{{ old('email') }}">
                </label>
                <div class="form-actions">
                    <button class="button button--primary" type="submit">Anadir proveedor</button>
                </div>
            </form>
        @else
            <div class="readonly-note">
                <strong>Acceso de usuario</strong>
                <p>Puedes consultar el directorio y descargar el PDF, pero solo un administrador puede agregar proveedores.</p>
            </div>
        @endif
        @if (empty($providers))
            <div class="empty-state">
                <strong>No hay proveedores registrados</strong>
                    <p>Cuando agreguen proveedores de mantenimiento, se mostrarán aquí.</p>
            </div>
        @else
            <div class="provider-grid">
                @foreach ($providers as $provider)
                    <div class="provider-card">
                        <div class="provider-card__icon">{{ substr($provider['name'], 0, 1) }}</div>
                        <strong>{{ $provider['name'] }}</strong>
                        <span class="badge badge--success">{{ $provider['tag'] }}</span>
                        @if (! empty($provider['contact']))
                            <span class="provider-contact">{{ $provider['contact'] }}</span>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </article>
</section>
