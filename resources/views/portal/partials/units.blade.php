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
                                    @if (! empty($residentSearchResult['details']))
                                        <p>{{ $residentSearchResult['details'] }}</p>
                                    @endif
                                </div>
                            </div>
                            <div class="billing-search-result__stats">
                                <span>
                                    <small>Departamento</small>
                                    <strong>{{ $residentSearchResult['location'] ?: '--' }}</strong>
                                </span>
                                <span>
                                    <small>Correo</small>
                                    <strong>{{ $residentSearchResult['email'] ?: '--' }}</strong>
                                </span>
                                <span>
                                    <small>Origen</small>
                                    <strong>{{ $residentSearchResult['source'] }}</strong>
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
                            @if (! empty($residentReportCommands))
                                <div class="billing-search-result__actions billing-search-result__actions--reports">
                                    @foreach ($residentReportCommands as $command)
                                        <a class="button button--ghost button--small" href="{{ $command['href'] }}">
                                            {{ $command['label'] }}
                                        </a>
                                    @endforeach
                                </div>
                            @endif
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
                                    <small>Base activa</small>
                                    <strong>{{ $condominiumOverview['active_base'] }}</strong>
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
            <p class="section-intro__eyebrow">Captura y operación</p>
            <h3 class="section-intro__title">Gestión de residentes dentro de la plataforma</h3>
        </div>
        <p class="section-intro__note">Captura o revisa los datos del residente, inquilino, accesos y espacios asignados.</p>
    </div>

    <section class="content-grid content-grid--resident-capture">
        @if ($canManage)
            <article class="panel panel--resident-import">
                <div class="panel__header">
                    <h3>Importar Residentes</h3>
                    <span>Excel o CSV</span>
                </div>
                @if ($errors->has('residents_file'))
                    <div class="alert alert--error">{{ $errors->first('residents_file') }}</div>
                @endif
                <form class="form-grid" method="POST" action="{{ route('units.import') }}" enctype="multipart/form-data">
                    @csrf
                    <label class="field field--wide">
                        <span>Archivo de residentes</span>
                        <input type="file" name="residents_file" accept=".xlsx,.xls,.csv,.txt" required>
                    </label>
                    <div class="form-actions">
                        <button class="button button--primary" type="submit">Importar residentes</button>
                    </div>
                </form>
            </article>
        @endif

        <article class="panel panel--resident-form" id="captura-residentes">
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
                <form class="resident-form-grid" method="POST" action="{{ $editingUnit ? route('units.update', $editingUnit) : route('units.store') }}">
                    @csrf
                    @if ($editingUnit)
                        @method('PATCH')
                    @endif

                    <div class="resident-form-section resident-form-section--unit">
                        <div class="resident-form-section__header">
                            <span>Departamento</span>
                        </div>
                        <div class="resident-form-section__fields">
                            <label class="field">
                                <span>Condominio</span>
                                <input type="text" name="condominium" value="{{ old('condominium', $condominiumName) }}" readonly>
                            </label>
                            <label class="field">
                                <span>Torre</span>
                                <input type="text" name="tower" value="{{ old('tower', $editingUnit?->tower) }}" required>
                            </label>
                            <label class="field">
                                <span>Sub Torre</span>
                                <input type="text" name="sub_tower" value="{{ old('sub_tower', $editingUnit?->sub_tower) }}">
                            </label>
                            <label class="field">
                                <span>DEPT</span>
                                <input type="text" name="unit_number" value="{{ old('unit_number', $editingUnit?->unit_number) }}" required>
                            </label>
                        </div>
                    </div>

                    <div class="resident-form-section">
                        <div class="resident-form-section__header">
                            <span>Dueño</span>
                        </div>
                        <div class="resident-form-section__fields">
                            <label class="field">
                                <span>Nombre Dueño</span>
                                <input type="text" name="owner_name" value="{{ old('owner_name', $editingUnit?->owner_name) }}" required>
                            </label>
                            <label class="field">
                                <span>Correo electronico</span>
                                <input type="email" name="owner_email" value="{{ old('owner_email', $editingUnit?->owner_email) }}">
                            </label>
                            <label class="field">
                                <span>Telefono 1</span>
                                <input type="text" name="owner_phone_primary" value="{{ old('owner_phone_primary', $editingUnit?->owner_phone_primary) }}">
                            </label>
                            <label class="field">
                                <span>Telefono 2</span>
                                <input type="text" name="owner_phone_secondary" value="{{ old('owner_phone_secondary', $editingUnit?->owner_phone_secondary) }}">
                            </label>
                        </div>
                    </div>

                    <div class="resident-form-section">
                        <div class="resident-form-section__header">
                            <span>Inquilino</span>
                        </div>
                        <div class="resident-form-section__fields">
                            <label class="field">
                                <span>Nombre inquilino</span>
                                <input type="text" name="tenant_name" value="{{ old('tenant_name', $editingUnit?->tenant_name) }}">
                            </label>
                            <label class="field">
                                <span>Correo electronico inquilino</span>
                                <input type="email" name="tenant_email" value="{{ old('tenant_email', $editingUnit?->tenant_email) }}">
                            </label>
                            <label class="field">
                                <span>Telefono 1 inquilino</span>
                                <input type="text" name="tenant_phone_primary" value="{{ old('tenant_phone_primary', $editingUnit?->tenant_phone_primary) }}">
                            </label>
                            <label class="field">
                                <span>Telefono 2 inquilino</span>
                                <input type="text" name="tenant_phone_secondary" value="{{ old('tenant_phone_secondary', $editingUnit?->tenant_phone_secondary) }}">
                            </label>
                        </div>
                    </div>

                    <div class="resident-form-section">
                        <div class="resident-form-section__header">
                            <span>Accesos y espacios</span>
                        </div>
                        <div class="resident-form-section__fields">
                            <label class="field">
                                <span>Cajon de estacionamenito</span>
                                <input type="text" name="parking_assignment" value="{{ old('parking_assignment', $editingUnit?->parking_assignment) }}">
                            </label>
                            <label class="field">
                                <span>Roof Garden</span>
                                <input type="text" name="roof_garden" value="{{ old('roof_garden', $editingUnit?->roof_garden) }}">
                            </label>
                            <label class="field">
                                <span>Tag Vehiculo</span>
                                <input type="text" name="vehicle_tag" value="{{ old('vehicle_tag', $editingUnit?->vehicle_tag) }}">
                            </label>
                            <label class="field">
                                <span>TAG Peatonal</span>
                                <input type="text" name="pedestrian_tag" value="{{ old('pedestrian_tag', $editingUnit?->pedestrian_tag) }}">
                            </label>
                            <label class="field">
                                <span>Bodega</span>
                                <input type="text" name="storage_assignment" value="{{ old('storage_assignment', $editingUnit?->storage_assignment) }}">
                            </label>
                            <label class="field">
                                <span>Mascotas</span>
                                <input type="text" name="pet" value="{{ old('pet', $editingUnit?->pet) }}">
                            </label>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button class="button button--primary" type="submit">{{ $editingUnit ? 'Guardar cambios' : 'Crear unidad' }}</button>
                    </div>
                </form>
            @else
                <div class="readonly-note">
                    <strong>Modo solo lectura</strong>
                    <p>Tu cuenta puede consultar residentes, inquilinos, accesos y espacios asignados, pero solo un administrador puede crear, editar o eliminar registros.</p>
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
        <p class="section-intro__note">Estas tarjetas concentran la información capturada y la base importada para validar residentes, accesos y espacios asignados.</p>
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
                                @if (! empty($resident['details']))
                                    <p>{{ $resident['details'] }}</p>
                                @endif
                                <span class="badge {{ $statusClass }}">{{ $resident['status'] }}</span>
                            </div>
                            <div class="resident-card__meta">
                                <span class="table-sub">{{ $resident['extras'] }}</span>
                                <div class="resident-card__actions">
                                    <a class="button button--ghost button--small" href="{{ route('billing', $residentRouteParams) }}">Cuenta</a>
                                    @if ($canManage && $resident['unit_id'])
                                        <a class="button button--primary button--small" href="{{ route('units', ['edit' => $resident['unit_id'], 'condominium' => $condominiumQuery, 'q' => request('q')]) }}#captura-residentes">Editar</a>
                                        <form id="unit-destroy-{{ $resident['unit_id'] }}" method="POST" action="{{ route('units.destroy', $resident['unit_id']) }}">
                                            @csrf
                                            @method('DELETE')
                                        </form>
                                        <button
                                            class="button button--danger button--small"
                                            type="button"
                                            data-confirm-submit="unit-destroy-{{ $resident['unit_id'] }}"
                                            data-confirm-title="¿Eliminar este residente?"
                                            data-confirm-text="También se quitará su registro importado vinculado dentro de este condominio."
                                            data-confirm-button-text="Sí, eliminar"
                                        >Eliminar</button>
                                    @elseif ($resident['account_id'])
                                        <a class="button button--primary button--small" href="{{ route('billing.letters.show', $resident['account_id']) }}">Carta</a>
                                        @if ($canManage)
                                            <form id="imported-account-destroy-{{ $resident['account_id'] }}" method="POST" action="{{ route('billing.imported-accounts.delete', $resident['account_id']) }}">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="redirect_to" value="units">
                                            </form>
                                            <button
                                                class="button button--danger button--small"
                                                type="button"
                                                data-confirm-submit="imported-account-destroy-{{ $resident['account_id'] }}"
                                                data-confirm-title="¿Eliminar este residente importado?"
                                                data-confirm-button-text="Sí, eliminar"
                                            >Eliminar</button>
                                        @endif
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
