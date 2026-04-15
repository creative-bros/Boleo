<section class="split-grid">
    <article class="panel">
        <div class="panel__header">
            <h3>Inventario Total</h3>
            <span>{{ $units->count() }} unidades</span>
        </div>
        <div class="mini-stats">
            @foreach ($inventory as $item)
                <div class="mini-stat">
                    <span>{{ $item['label'] }}</span>
                    <strong>{{ $item['value'] }}</strong>
                </div>
            @endforeach
        </div>
    </article>

    <article class="panel panel--primary">
        <div class="panel__header">
            <h3>Configuracion de Cuota</h3>
            <span>Modelo de cobro</span>
        </div>
        <div class="segmented-control">
            @foreach ($feeModes as $mode)
                @php
                    $modeValue = str_contains(strtolower($mode), 'indiviso') ? 'indiviso' : 'standard';
                    $isActive = $defaultFeeType === $modeValue;
                @endphp
                @if ($canManage)
                    <form method="POST" action="{{ route('units.fee-type') }}" class="segmented-control__form">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="fee_type" value="{{ $modeValue }}">
                        <button class="segmented-control__item {{ $isActive ? 'is-active' : '' }}" type="submit">{{ $mode }}</button>
                    </form>
                @else
                    <button class="segmented-control__item {{ $isActive ? 'is-active' : '' }}" type="button">{{ $mode }}</button>
                @endif
            @endforeach
        </div>
        <p class="panel__muted">Cada unidad puede llevar cuota ordinaria, extraordinaria y rentas de cajones o bodegas.</p>
        <p class="panel__muted">La cuota total se paga cada mes y aqui pueden consultar el monto actualizado.</p>
    </article>
</section>

<section class="content-grid content-grid--settings-bottom">
    <article class="panel">
        <div class="panel__header">
            <h3>{{ $canManage ? ($editingUnit ? 'Editar Unidad' : 'Registrar Nueva Unidad') : 'Informacion de Unidades' }}</h3>
            @if ($canManage && $editingUnit)
                <a class="button button--ghost" href="{{ route('units') }}">Cancelar edicion</a>
            @endif
        </div>

        @if ($errors->any())
            <div class="alert alert--error">{{ $errors->first() }}</div>
        @endif

        @if ($canManage)
            <form class="form-grid" method="POST" action="{{ $editingUnit ? route('units.update', $editingUnit) : route('units.store') }}">
                @csrf
                @if ($editingUnit)
                    @method('PATCH')
                @endif

                <label class="field">
                    <span>Numero de departamento</span>
                    <input type="text" name="unit_number" value="{{ old('unit_number', $editingUnit?->unit_number) }}" required>
                </label>
                <label class="field">
                    <span>Torre / edificio</span>
                    <input type="text" name="tower" value="{{ old('tower', $editingUnit?->tower) }}" required>
                </label>
                <label class="field">
                    <span>Tipo</span>
                    <input type="text" name="unit_type" value="{{ old('unit_type', $editingUnit?->unit_type) }}" required>
                </label>
                <label class="field">
                    <span>Persona asignada</span>
                    <input type="text" name="owner_name" value="{{ old('owner_name', $editingUnit?->owner_name) }}" required>
                </label>
                <label class="field">
                    <span>Correo vinculado</span>
                    <input type="email" name="owner_email" value="{{ old('owner_email', $editingUnit?->owner_email) }}" required>
                </label>
                <label class="field">
                    <span>Cuota ordinaria</span>
                    <input type="number" name="ordinary_fee" step="0.01" min="0" value="{{ old('ordinary_fee', $editingUnit?->ordinary_fee) }}" required>
                </label>
                <label class="field">
                    <span>Indiviso %</span>
                    <input type="number" name="indiviso_percentage" step="0.0001" min="0" max="100" value="{{ old('indiviso_percentage', $editingUnit?->indiviso_percentage) }}" placeholder="0.0000">
                </label>
                <label class="field">
                    <span>Cuota extraordinaria</span>
                    <input type="number" name="extraordinary_fee" step="0.01" min="0" value="{{ old('extraordinary_fee', $editingUnit?->extraordinary_fee) }}">
                </label>
                <label class="field">
                    <span>Renta de cajones</span>
                    <input type="number" name="parking_rent" step="0.01" min="0" value="{{ old('parking_rent', $editingUnit?->parking_rent) }}">
                </label>
                <label class="field">
                    <span>Renta de bodega</span>
                    <input type="number" name="storage_rent" step="0.01" min="0" value="{{ old('storage_rent', $editingUnit?->storage_rent) }}">
                </label>
                <label class="field">
                    <span>Numero de cajones</span>
                    <input type="number" name="parking_spots" min="0" value="{{ old('parking_spots', $editingUnit?->parking_spots) }}" required>
                </label>
                <label class="field">
                    <span>Numero de bodegas</span>
                    <input type="number" name="storage_rooms" min="0" value="{{ old('storage_rooms', $editingUnit?->storage_rooms) }}" required>
                </label>
                <label class="field">
                    <span>Numero de jaulas de tendido</span>
                    <input type="number" name="clothesline_cages" min="0" value="{{ old('clothesline_cages', $editingUnit?->clothesline_cages) }}" required>
                </label>
                <label class="field">
                    <span>Cuota total base</span>
                    <input type="number" name="fee" step="0.01" min="0" value="{{ old('fee', $editingUnit?->fee) }}" required>
                </label>
                <label class="field">
                    <span>Estatus</span>
                    <select name="status" class="select-field" required>
                        @foreach ($unitStatuses as $status)
                            <option value="{{ $status }}" @selected(old('status', $editingUnit?->status) === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                </label>

                <div class="form-actions">
                    <button class="button button--primary" type="submit">{{ $editingUnit ? 'Guardar cambios' : 'Crear unidad' }}</button>
                </div>
            </form>
        @else
            <div class="readonly-note">
                <strong>Modo solo lectura</strong>
                <p>Tu cuenta puede consultar unidades, cuotas y extras vinculados, pero solo un administrador puede crear, editar o eliminar registros.</p>
                <p>Recuerda: el monto total de tu unidad se paga cada mes.</p>
            </div>
        @endif
    </article>

    <article class="panel compact-panel">
        <h3>{{ $canManage ? 'Edicion desde la plataforma' : 'Permisos de consulta' }}</h3>
        <p>{{ $canManage ? 'Aqui puedes capturar cuota ordinaria, extraordinaria y rentas de cajones o bodegas por unidad.' : 'Puedes revisar el inventario y los datos operativos sin riesgo de modificar informacion.' }}</p>
        <p>{{ $canManage ? 'Tambien puedes registrar cuantos cajones, bodegas y jaulas de tendido tiene cada unidad.' : 'Si necesitas cambios, un administrador puede hacerlos desde este mismo modulo.' }}</p>
        <p>El monto total mensual aparece abajo para administrador y usuario.</p>
    </article>
</section>

<section class="panel">
    <div class="panel__header">
        <h3>Listado de Departamentos</h3>
        <div class="panel__actions">
            <span class="badge badge--neutral">{{ $canManage ? 'CRUD activo' : 'Solo lectura' }}</span>
        </div>
    </div>
    <div class="table-wrap">
        @if ($units->isEmpty())
            <div class="empty-state">
                <strong>Aun no hay unidades registradas</strong>
                <p>Cuando agreguen unidades desde este formulario, se mostraran aqui automaticamente.</p>
            </div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Unidad</th>
                        <th>Persona asignada</th>
                        <th>Cuotas</th>
                        <th>Monto total mensual</th>
                        <th>Indiviso</th>
                        <th>Extras</th>
                        <th>Estatus</th>
                        @if ($canManage)
                            <th>Acciones</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($units as $unit)
                        @php($summary = $billingRows->get($unit->id))
                        <tr>
                            <td>
                                <strong>{{ $unit->unit_number }}</strong>
                                <span class="table-sub">{{ $unit->tower }} | {{ $unit->unit_type }}</span>
                            </td>
                            <td>
                                <strong>{{ $unit->owner_name }}</strong>
                                <span class="table-sub">{{ $unit->owner_email }}</span>
                            </td>
                            <td>
                                <strong>Ord. ${{ number_format((float) $unit->ordinary_fee, 2) }}</strong>
                                <span class="table-sub">Ext. ${{ number_format((float) $unit->extraordinary_fee, 2) }}</span>
                            </td>
                            <td>
                                <strong>${{ number_format((float) ($summary['fee_amount'] ?? 0), 2) }}</strong>
                                <span class="table-sub">Se paga cada mes</span>
                            </td>
                            <td>
                                <strong>{{ number_format((float) $unit->indiviso_percentage, 4) }}%</strong>
                                <span class="table-sub">{{ $defaultFeeType === 'indiviso' ? 'Activo en calculo' : 'Solo referencia' }}</span>
                            </td>
                            <td>
                                <strong>{{ $unit->parking_spots }} cajones | {{ $unit->storage_rooms }} bodegas</strong>
                                <span class="table-sub">{{ $unit->clothesline_cages }} jaulas | Rentas ${{ number_format((float) ($unit->parking_rent + $unit->storage_rent), 2) }}</span>
                            </td>
                            <td><span class="badge badge--neutral">{{ $unit->status }}</span></td>
                            @if ($canManage)
                                <td>
                                    <div class="table-actions table-actions--stacked">
                                        <div class="status-actions">
                                            @foreach ($unitStatuses as $status)
                                                @if ($unit->status !== $status)
                                                    <form method="POST" action="{{ route('units.status', $unit) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="status" value="{{ $status }}">
                                                        <button class="button button--ghost button--chip" type="submit">{{ $status }}</button>
                                                    </form>
                                                @endif
                                            @endforeach
                                        </div>
                                        <a class="button button--ghost" href="{{ route('units', ['edit' => $unit->id]) }}">Editar</a>
                                        <form method="POST" action="{{ route('units.destroy', $unit) }}" onsubmit="return confirm('Eliminar esta unidad?');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="button button--danger" type="submit">Eliminar</button>
                                        </form>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</section>
