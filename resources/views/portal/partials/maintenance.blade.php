<section class="stats-grid stats-grid--four">
    @foreach ($summary as $item)
        <article class="stat-card stat-card--{{ $item['tone'] }}">
            <span>{{ $item['label'] }}</span>
            <strong>{{ $item['value'] }}</strong>
            <small>{{ $item['meta'] }}</small>
        </article>
    @endforeach
</section>

<section class="content-grid content-grid--settings-bottom">
    <article class="panel">
        <div class="panel__header">
            <h3>Comandos para Reportes</h3>
            <span>{{ $expenseMonthLabel }}</span>
        </div>
        <form class="form-grid" method="GET" action="{{ route('maintenance') }}">
            <label class="field">
                <span>Mes a revisar</span>
                <input type="month" name="expense_month" value="{{ $expenseMonth }}">
            </label>
            <label class="field">
                <span>Buscar proveedor</span>
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Proveedor, categoria o contacto">
            </label>
            <div class="form-actions">
                <button class="button button--ghost" type="submit">Actualizar vista</button>
            </div>
        </form>
        <div class="command-grid">
            @foreach ($expenseCommands as $command)
                <a class="button {{ $command['style'] === 'primary' ? 'button--primary' : 'button--ghost' }}" href="{{ $command['href'] }}">
                    {{ $command['label'] }}
                </a>
            @endforeach
        </div>
    </article>

    <article class="panel compact-panel">
        <h3>Caracteristicas del gasto</h3>
        <p>Separa gastos fijos y no fijos para tener reportes mas claros por mes.</p>
        <p>Los gastos fijos incluyen limpieza, servicio y vigilancia.</p>
        <p>Los no fijos contemplan mantenimiento, compra de focos, agua y otros imprevistos.</p>
    </article>
</section>

@if ($canManage)
    <section class="content-grid content-grid--maintenance-capture">
        <article class="panel">
            <div class="panel__header">
                <h3>Registrar Tarea</h3>
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
                <h3>Registrar Gasto</h3>
                <span>Financiero</span>
            </div>
            <form class="form-grid" method="POST" action="{{ route('maintenance.expenses.store') }}" enctype="multipart/form-data">
                @csrf
                <label class="field">
                    <span>Fecha editable</span>
                    <input type="date" name="spent_at" value="{{ old('spent_at', now()->toDateString()) }}" required>
                </label>
                <label class="field">
                    <span>Mes del gasto</span>
                    <input type="month" name="report_month" value="{{ old('report_month', $expenseMonth) }}" required>
                </label>
                <label class="field">
                    <span>Tipo de gasto</span>
                    <select name="expense_group" class="select-field" required>
                        <option value="fixed" @selected(old('expense_group') === 'fixed')>Fijo</option>
                        <option value="variable" @selected(old('expense_group') === 'variable')>No fijo</option>
                    </select>
                </label>
                <label class="field">
                    <span>Categoria sugerida</span>
                    <select name="category" class="select-field" required>
                        <optgroup label="Gastos fijos">
                            @foreach ($fixedExpenseCategories as $category)
                                <option value="{{ $category }}" @selected(old('category') === $category)>{{ $category }}</option>
                            @endforeach
                        </optgroup>
                        <optgroup label="Gastos no fijos">
                            @foreach ($variableExpenseCategories as $category)
                                <option value="{{ $category }}" @selected(old('category') === $category)>{{ $category }}</option>
                            @endforeach
                        </optgroup>
                    </select>
                </label>
                <label class="field">
                    <span>Concepto</span>
                    <input type="text" name="concept" value="{{ old('concept') }}" placeholder="Ej. Recibo de vigilancia, agua, compra de focos" required>
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
                <label class="field">
                    <span>Adjuntar documento</span>
                    <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,.webp">
                </label>
                <label class="field field--full">
                    <span>Observaciones</span>
                    <input type="text" name="observations" value="{{ old('observations') }}" placeholder="Comentarios, referencia o informacion adicional del mes">
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
                    <p>Los reportes reales apareceran aqui cuando se registren.</p>
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
            <h3>Historial de Egresos del Mes</h3>
            <span>{{ $expenseMonthLabel }}</span>
        </div>
        <div class="table-wrap">
            @if (empty($expenses))
                <div class="empty-state">
                    <strong>No hay egresos registrados</strong>
                    <p>Los gastos de mantenimiento apareceran aqui cuando los capturen.</p>
                </div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Mes</th>
                            <th>Tipo</th>
                            <th>Categoria</th>
                            <th>Concepto</th>
                            <th>Proveedor</th>
                            <th>Monto</th>
                            <th>Documento</th>
                            <th>PDF</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($expenses as $expense)
                            <tr>
                                <td>{{ $expense['date'] }}</td>
                                <td>{{ $expense['month'] }}</td>
                                <td>{{ $expense['group'] }}</td>
                                <td>{{ $expense['category'] }}</td>
                                <td>
                                    <strong>{{ $expense['concept'] }}</strong>
                                    <span class="table-sub">{{ $expense['observations'] ?: 'Sin observaciones' }}</span>
                                </td>
                                <td>{{ $expense['provider'] }}</td>
                                <td>{{ $expense['amount'] }}</td>
                                <td>
                                    @if ($expense['has_document'])
                                        <a class="button button--ghost button--small" href="{{ $expense['document_url'] }}">Adjunto</a>
                                    @else
                                        <span class="table-sub">Sin adjunto</span>
                                    @endif
                                </td>
                                <td>
                                    <a class="button button--ghost button--small" href="{{ $expense['receipt_url'] }}">Recibo PDF</a>
                                </td>
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
                <p>Cuando agreguen proveedores de mantenimiento, se mostraran aqui.</p>
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
