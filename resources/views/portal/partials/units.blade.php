@php
    $hasResidentSearchContext = filled(request('q')) || filled(request('condominium')) || request()->has('unit') || request()->has('account');
    $residentResultRouteParams = array_filter([
        'unit' => $residentSearchResult['unit_id'] ?? null,
        'account' => $residentSearchResult['account_id'] ?? null,
        'q' => request('q'),
        'condominium' => $condominiumQuery,
    ], fn ($value) => filled($value));
@endphp

<section class="section-stack">
    <div class="section-intro">
        <div>
            <p class="section-intro__eyebrow">Consulta del condominio</p>
            <h3 class="section-intro__title">Busqueda de residentes y condominio</h3>
        </div>
        <p class="section-intro__note">Busca por condominio, residente, unidad o correo para ver la información guardada en plataforma y en la base importada.</p>
    </div>

    <section class="content-grid content-grid--units-top">
        <article class="panel panel--units-search" id="buscador-residentes">
            <div class="panel__header">
                <h3>Buscador de Residentes</h3>
                <span>{{ $condominiumName }}</span>
            </div>
            <form class="form-grid" method="GET" action="{{ route('units') }}">
                <label class="field">
                    <span>Nombre del condominio</span>
                    <input type="text" name="condominium" value="{{ old('condominium', $condominiumQuery) }}" placeholder="Buscar nombre del condominio">
                </label>
                <label class="field">
                    <span>Residente, unidad o torre</span>
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Buscar por nombre, unidad, torre o correo">
                </label>
                <div class="form-actions">
                    <button class="button button--ghost" type="submit">Buscar</button>
                </div>
            </form>

            @if ($hasResidentSearchContext)
                <div class="billing-search-result resident-search-result">
                    @unless ($condominiumMatches)
                        <div>
                            <span class="billing-search-result__eyebrow">Sin condominio</span>
                            <strong>No encontramos un condominio que coincida con esa búsqueda.</strong>
                            <p>Revisa el nombre, dirección o RFC del condominio y vuelve a buscar.</p>
                        </div>
                    @else
                        @if (filled(request('q')) && ! $residentSearchResult)
                            <div>
                                <span class="billing-search-result__eyebrow">Sin residente</span>
                                <strong>No encontramos información de ese residente o departamento.</strong>
                                <p>{{ $condominiumName }} está seleccionado. Ajusta el nombre, correo, torre o unidad para buscar de nuevo.</p>
                            </div>
                        @elseif ($residentSearchResult)
                            <div class="billing-search-result__main">
                                <div class="avatar">{{ substr($residentSearchResult['name'], 0, 1) }}</div>
                                <div>
                                    <span class="billing-search-result__eyebrow">Resultado encontrado</span>
                                    <strong>{{ $residentSearchResult['name'] }}</strong>
                                    <p>{{ $residentSearchResult['location'] ?: 'Sin departamento vinculado' }}{{ $residentSearchResult['email'] ? ' | '.$residentSearchResult['email'] : ' | Sin correo vinculado' }}</p>
                                    <p>{{ $residentSearchResult['source'] }}{{ $residentSearchResult['role'] ? ' | '.$residentSearchResult['role'] : '' }}</p>
                                </div>
                            </div>
                            <div class="billing-search-result__stats">
                                <span>
                                    <small>Saldo</small>
                                    <strong>{{ $residentSearchResult['balance'] }}</strong>
                                </span>
                                <span>
                                    <small>Cuota mensual</small>
                                    <strong>{{ $residentSearchResult['fee'] }}</strong>
                                </span>
                                <span>
                                    <small>Estatus</small>
                                    <strong>{{ $residentSearchResult['status'] }}</strong>
                                </span>
                            </div>
                            <div class="billing-search-result__actions">
                                <a class="button button--primary button--small" href="{{ route('billing', $residentResultRouteParams) }}">Ver cuenta</a>
                                @if ($canManage && $residentSearchResult['unit_id'])
                                    <a class="button button--ghost button--small" href="{{ route('units', ['edit' => $residentSearchResult['unit_id'], 'condominium' => $condominiumQuery, 'q' => request('q')]) }}#captura-residentes">Editar</a>
                                @endif
                                @if ($residentSearchResult['account_id'])
                                    <a class="button button--ghost button--small" href="{{ route('billing.letters.show', $residentSearchResult['account_id']) }}">Carta</a>
                                @endif
                            </div>
                        @else
                            <div class="billing-search-result__main">
                                <div class="avatar">{{ substr($condominiumOverview['name'], 0, 1) }}</div>
                                <div>
                                    <span class="billing-search-result__eyebrow">Condominio encontrado</span>
                                    <strong>{{ $condominiumOverview['name'] }}</strong>
                                    <p>{{ $condominiumOverview['address'] }}</p>
                                    <p>Base activa: {{ $condominiumOverview['active_base'] }}</p>
                                </div>
                            </div>
                            <div class="billing-search-result__stats">
                                <span>
                                    <small>Unidades</small>
                                    <strong>{{ $condominiumOverview['units'] }}</strong>
                                </span>
                                <span>
                                    <small>Base importada</small>
                                    <strong>{{ $condominiumOverview['imported_accounts'] }}</strong>
                                </span>
                                <span>
                                    <small>Cuota base</small>
                                    <strong>{{ $condominiumOverview['fee'] }}</strong>
                                </span>
                            </div>
                            <div class="billing-search-result__actions">
                                <a class="button button--primary button--small" href="#listado-residentes">Ver residentes</a>
                                <a class="button button--ghost button--small" href="{{ route('billing', ['condominium' => $condominiumQuery]) }}">Ir a cobranza</a>
                            </div>
                        @endif
                    @endunless
                </div>
            @endif
        </article>

        <article class="panel panel--units-commands">
            <div class="panel__header">
                <h3>Comandos para Reportes</h3>
                <span>Accesos rápidos</span>
            </div>
            <div class="command-grid">
                @foreach ($quickCommands as $command)
                    <a class="button {{ $command['style'] === 'primary' ? 'button--primary' : 'button--ghost' }}" href="{{ $command['href'] }}">
                        {{ $command['label'] }}
                    </a>
                @endforeach
            </div>
        </article>

        <article class="panel panel--units-characteristics">
            <div class="panel__header">
                <h3>Características del Condominio</h3>
                <span>Resumen operativo</span>
            </div>
            <div class="characteristics-grid">
                @foreach ($characteristics as $item)
                    <div class="characteristic-card">
                        <span class="characteristic-card__label">{{ $item['label'] }}</span>
                        <strong class="characteristic-card__value">{{ $item['value'] }}</strong>
                    </div>
                @endforeach
            </div>
        </article>
    </section>
</section>

<section class="section-stack">
    <div class="section-intro">
        <div>
            <p class="section-intro__eyebrow">Cobro y panorama general</p>
            <h3 class="section-intro__title">Configuración de cuota e inventario</h3>
        </div>
        <p class="section-intro__note">Este bloque resume cómo se cobra y cuántas unidades están registradas o pendientes dentro del condominio.</p>
    </div>

    <section class="content-grid content-grid--settings-bottom">
        <article class="panel panel--primary">
            <div class="panel__header">
                <h3>Configuración de Cuota</h3>
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
            <p class="panel__muted">La cuota total se paga cada mes y aquí pueden consultar el monto actualizado.</p>
        </article>

        <article class="panel" id="captura-residentes">
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
    </section>
</section>

<section class="section-stack">
    <div class="section-intro">
        <div>
            <p class="section-intro__eyebrow">Captura y operación</p>
            <h3 class="section-intro__title">Gestión de unidades dentro de la plataforma</h3>
        </div>
        <p class="section-intro__note">Captura o revisa unidades con los datos mínimos necesarios para mantener residentes, cuotas y extras ordenados.</p>
    </div>

    <section class="content-grid content-grid--settings-bottom">
        <article class="panel">
            <div class="panel__header">
                <h3>{{ $canManage ? ($editingUnit ? 'Editar Unidad' : 'Registrar Nueva Unidad') : 'Información de Unidades' }}</h3>
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
                        <span>Número de departamento</span>
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
                        <span>Número de cajones</span>
                        <input type="number" name="parking_spots" min="0" value="{{ old('parking_spots', $editingUnit?->parking_spots) }}" required>
                    </label>
                    <label class="field">
                        <span>Número de bodegas</span>
                        <input type="number" name="storage_rooms" min="0" value="{{ old('storage_rooms', $editingUnit?->storage_rooms) }}" required>
                    </label>
                    <label class="field">
                        <span>Número de jaulas de tendido</span>
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

    </section>
</section>

<section class="section-stack">
    <div class="section-intro">
        <div>
            <p class="section-intro__eyebrow">Resultado final</p>
            <h3 class="section-intro__title">Listado general de departamentos</h3>
        </div>
        <p class="section-intro__note">Estas tarjetas concentran la información capturada y la base importada para validar de un vistazo cuotas, saldos, extras y acciones.</p>
    </div>

    <section class="panel" id="listado-residentes">
        <div class="panel__header">
            <h3>Listado de Departamentos</h3>
            <div class="panel__actions">
                <span class="badge badge--neutral">{{ $canManage ? 'CRUD activo' : 'Solo lectura' }}</span>
            </div>
        </div>
        <div class="units-resident-list">
            @if ($residentDirectory->isEmpty())
                <div class="empty-state">
                    <strong>Aún no hay residentes registrados</strong>
                    <p>Cuando exista una unidad o una base importada para este condominio, se mostrará aquí automáticamente.</p>
                </div>
            @else
                <div class="resident-list resident-list--units">
                    @foreach ($residentDirectory as $resident)
                        @php
                            $residentRouteParams = array_filter([
                                'unit' => $resident['unit_id'] ?? null,
                                'account' => $resident['account_id'] ?? null,
                                'q' => request('q'),
                                'condominium' => $condominiumQuery,
                            ], fn ($value) => filled($value));
                            $statusClass = $resident['status'] === 'Deudor' ? 'badge--warning' : 'badge--success';
                        @endphp
                        <div class="resident-card resident-card--link resident-card--actions units-resident-card">
                            <div class="avatar">{{ substr($resident['name'], 0, 1) }}</div>
                            <div>
                                <strong>{{ $resident['name'] }}</strong>
                                <p>{{ $resident['unit'] }} | {{ $resident['type'] }}</p>
                                <p>{{ $resident['email'] ?: 'Sin correo vinculado' }}</p>
                                <p>{{ $resident['source'] }}</p>
                                <span class="badge {{ $statusClass }}">{{ $resident['status'] }}</span>
                            </div>
                            <div class="resident-card__meta">
                                <strong>{{ $resident['balance'] }}</strong>
                                <span class="table-sub">Cuota mensual: {{ $resident['fee'] }}</span>
                                <span class="table-sub">Se paga cada mes</span>
                                <span class="table-sub">Pagado: {{ $resident['paid'] }}</span>
                                <span class="table-sub">{{ $resident['extras'] }}</span>
                                <span class="table-sub">Recibos: {{ $resident['receipt_meta'] }}</span>
                                <div class="resident-card__actions">
                                    <a class="button button--ghost button--small" href="{{ route('billing', $residentRouteParams) }}">Cuenta</a>
                                    @if ($canManage && $resident['unit_id'])
                                        <a class="button button--primary button--small" href="{{ route('units', ['edit' => $resident['unit_id'], 'condominium' => $condominiumQuery, 'q' => request('q')]) }}#captura-residentes">Editar</a>
                                    @elseif ($resident['account_id'])
                                        <a class="button button--primary button--small" href="{{ route('billing.letters.show', $resident['account_id']) }}">Carta</a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
</section>
