@php
    $profileError = fn (string $field) => $errors->settingsProfile->first($field);
    $userError = fn (string $field) => $errors->settingsUsers->first($field);
    $isEditingCondominium = filled($selectedCondominiumProfile?->id);
    $profileData = array_merge($identity, $operations);
    $profileValue = fn (string $field, mixed $fallback = '') => old($field, $isEditingCondominium ? data_get($profileData, $field, $fallback) : $fallback);
    $profileBoolValue = fn (string $field) => old($field, $isEditingCondominium ? (data_get($profileData, $field) ? '1' : '0') : '');
    $userCondominium = $selectedUserCondominium ?? $selectedCondominiumProfile;
@endphp

@if ($canManage)
    @if ($showUserAccess ?? false)
    <section class="section-stack">
        <div class="section-intro">
            <div>
                <p class="section-intro__eyebrow">Gestion de accesos</p>
                <h3 class="section-intro__title">Usuarios del Portal</h3>
            </div>
            <p class="section-intro__note">Este bloque aparece primero para que puedas revisar y editar auxiliares, residentes o administradores antes de completar la información del condominio.</p>
        </div>

        <section class="panel">
            <div class="panel__header">
                <h3>Usuarios del Portal</h3>
                <span class="badge badge--neutral">Gestion de accesos</span>
            </div>

            @if ($selectedUser)
                <div class="user-summary-card">
                    <div class="panel__header panel__header--tight">
                        <h3>Resumen del usuario en edición</h3>
                        <span>{{ $selectedUser->roleLabel() }}</span>
                    </div>
                    <div class="user-summary-grid">
                        <div class="summary-item">
                            <span>Nombre</span>
                            <strong>{{ $selectedUser->name }}</strong>
                        </div>
                        <div class="summary-item">
                            <span>Correo</span>
                            <strong>{{ $selectedUser->email }}</strong>
                        </div>
                        <div class="summary-item">
                            <span>Teléfono</span>
                            <strong>{{ $selectedUser->phone }}</strong>
                        </div>
                        <div class="summary-item">
                            <span>Rol asignado</span>
                            <strong>{{ $selectedUser->roleLabel() }}</strong>
                        </div>
                    </div>

                    <div class="content-grid content-grid--settings-user">
                        <div class="subpanel">
                            <h4>Información vinculada del condominio</h4>
                            <div class="summary-list">
                                <div class="summary-list__row">
                                    <span>Condominio</span>
                                    <strong>{{ $userCondominium?->commercial_name ?: 'Sin configurar' }}</strong>
                                </div>
                                <div class="summary-list__row">
                                    <span>RFC</span>
                                    <strong>{{ $userCondominium?->tax_id ?: 'Sin configurar' }}</strong>
                                </div>
                                <div class="summary-list__row">
                                    <span>Ubicación</span>
                                    <strong>{{ $userCondominium?->address ?: 'Sin configurar' }}</strong>
                                </div>
                                <div class="summary-list__row">
                                    <span>Cuota ordinaria</span>
                                    <strong>${{ number_format((float) ($userCondominium?->ordinary_fee_amount ?? 0), 2) }}</strong>
                                </div>
                                <div class="summary-list__row">
                                    <span>Cuenta de depósito</span>
                                    <strong>{{ $userCondominium?->bank ?: 'Sin configurar' }}{{ $userCondominium?->account_number ? ' | '.$userCondominium->account_number : '' }}</strong>
                                </div>
                                <div class="summary-list__row">
                                    <span>Titular de la cuenta</span>
                                    <strong>{{ $userCondominium?->account_holder ?: 'Sin configurar' }}</strong>
                                </div>
                                <div class="summary-list__row">
                                    <span>CLABE</span>
                                    <strong>{{ $userCondominium?->clabe ?: 'Sin configurar' }}</strong>
                                </div>
                                <div class="summary-list__row">
                                    <span>Tipo de cuota</span>
                                    <strong>{{ $identity['fee_type_label'] ?: 'Sin configurar' }}</strong>
                                </div>
                                <div class="summary-list__row">
                                    <span>Departamentos / Cajones</span>
                                    <strong>{{ $identity['departments_count'] }} departamentos | {{ $identity['parking_spaces_count'] }} cajones</strong>
                                </div>
                                <div class="summary-list__row">
                                    <span>Bodegas / Jaulas de tendido</span>
                                    <strong>{{ $identity['storage_rooms_count'] }} bodegas | {{ $identity['clothesline_cages_count'] }} jaulas</strong>
                                </div>
                                <div class="summary-list__row">
                                    <span>Administrador vinculado</span>
                                    <strong>{{ $identity['admin_name'] ?: 'Sin configurar' }}</strong>
                                    <small>{{ $identity['admin_email'] ?: 'Sin correo' }}{{ $identity['admin_phone'] ? ' | '.$identity['admin_phone'] : '' }}</small>
                                </div>
                            </div>
                        </div>

                        <div class="subpanel">
                            <h4>Infraestructura y unidades vinculadas</h4>
                            <div class="summary-list">
                                @foreach ($infrastructure as $item)
                                    <div class="summary-list__row">
                                        <span>{{ $item['name'] }}</span>
                                        <strong>{{ $item['active'] ? 'Activo' : 'No activo' }}</strong>
                                        <small>{{ $item['meta'] }}</small>
                                    </div>
                                @endforeach
                            </div>

                            <div class="summary-divider"></div>

                            <h4>Unidades vinculadas al usuario</h4>
                            @if ($selectedUserUnits->isEmpty())
                                <div class="empty-state empty-state--compact">
                                    <strong>Sin unidad vinculada</strong>
                                    <p>Este usuario todavía no coincide con una unidad por correo o nombre.</p>
                                </div>
                            @else
                                <div class="summary-list">
                                    @foreach ($selectedUserUnits as $linkedUnit)
                                        <div class="summary-list__row summary-list__row--stack">
                                            <span>{{ $linkedUnit->tower }} - {{ $linkedUnit->unit_number }}</span>
                                            <strong>{{ $linkedUnit->owner_name }}</strong>
                                            <small>{{ $linkedUnit->owner_email }}</small>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            @if ($errors->settingsUsers->any())
                <div class="alert alert--error">{{ $errors->settingsUsers->first() }}</div>
            @endif

            <form class="form-grid" method="POST" action="{{ $editingUser ? route('users.update', $editingUser) : route('users.store') }}" autocomplete="off">
                @csrf
                @if ($editingUser)
                    @method('PATCH')
                @endif

                @php
                    $userFormName = $editingUser ? old('name', $editingUser->name) : old('name', '');
                    $userFormEmail = $editingUser ? old('email', $editingUser->email) : old('email', '');
                    $userFormPhone = $editingUser ? old('phone', $editingUser->phone) : old('phone', '');
                    $userFormRole = $editingUser ? old('role', $editingUser->role) : old('role', '');
                    $userFormCondominium = $editingUser ? old('condominium_profile_id', $editingUser->condominium_profile_id) : old('condominium_profile_id', $selectedCondominiumProfile?->id);
                @endphp

                <label class="field {{ $userError('name') ? 'field--error' : '' }}">
                    <span>Nombre completo</span>
                    <input type="text" name="name" value="{{ $userFormName }}" autocomplete="off" required>
                    @if ($userError('name'))
                        <small class="field-error">{{ $userError('name') }}</small>
                    @endif
                </label>
                <label class="field {{ $userError('email') ? 'field--error' : '' }}">
                    <span>Correo electrónico</span>
                    <input type="email" name="email" value="{{ $userFormEmail }}" autocomplete="off" required>
                    @if ($userError('email'))
                        <small class="field-error">{{ $userError('email') }}</small>
                    @endif
                </label>
                <label class="field {{ $userError('phone') ? 'field--error' : '' }}">
                    <span>Teléfono</span>
                    <input type="text" name="phone" value="{{ $userFormPhone }}" autocomplete="off" required>
                    @if ($userError('phone'))
                        <small class="field-error">{{ $userError('phone') }}</small>
                    @endif
                </label>
                <label class="field {{ $userError('role') ? 'field--error' : '' }}">
                    <span>Rol</span>
                    <select name="role" class="select-field" required>
                        @foreach ($roleOptions as $roleKey => $roleLabel)
                            <option value="{{ $roleKey }}" @selected($userFormRole === $roleKey)>{{ $roleLabel }}</option>
                        @endforeach
                    </select>
                    @if ($userError('role'))
                        <small class="field-error">{{ $userError('role') }}</small>
                    @endif
                </label>
                <label class="field field--full {{ $userError('condominium_profile_id') ? 'field--error' : '' }}">
                    <span>Condominio asignado</span>
                    <select name="condominium_profile_id" class="select-field">
                        <option value="">Sin condominio asignado</option>
                        @foreach ($condominiumProfiles as $condominiumOption)
                            <option value="{{ $condominiumOption->id }}" @selected((string) $userFormCondominium === (string) $condominiumOption->id)>
                                {{ $condominiumOption->commercial_name ?: 'Condominio sin nombre #'.$condominiumOption->id }}
                            </option>
                        @endforeach
                    </select>
                    @if ($userError('condominium_profile_id'))
                        <small class="field-error">{{ $userError('condominium_profile_id') }}</small>
                    @endif
                </label>
                <label class="field {{ $userError('password') ? 'field--error' : '' }}">
                    <span>{{ $editingUser ? 'Nueva contraseña (opcional)' : 'Contraseña' }}</span>
                    <input type="password" name="password" autocomplete="new-password" {{ $editingUser ? '' : 'required' }}>
                    @if ($userError('password'))
                        <small class="field-error">{{ $userError('password') }}</small>
                    @endif
                </label>
                <label class="field {{ $userError('password_confirmation') ? 'field--error' : '' }}">
                    <span>Confirmar contraseña</span>
                    <input type="password" name="password_confirmation" autocomplete="new-password" {{ $editingUser ? '' : 'required' }}>
                    @if ($userError('password_confirmation'))
                        <small class="field-error">{{ $userError('password_confirmation') }}</small>
                    @endif
                </label>
                <div class="form-actions">
                    @if ($editingUser)
                        <a class="button button--ghost" href="{{ route('altas') }}">Cancelar edicion</a>
                    @endif
                    <button class="button button--primary" type="submit">{{ $editingUser ? 'Actualizar cuenta' : 'Crear cuenta' }}</button>
                </div>
            </form>

            <div class="table-wrap">
                @if ($users->isEmpty())
                    <div class="empty-state">
                        <strong>No hay cuentas registradas</strong>
                        <p>Las cuentas del portal aparecerán aquí conforme se den de alta.</p>
                    </div>
                @else
                    <table>
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Contacto</th>
                                <th>Condominio</th>
                                <th>Rol</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($users as $userAccount)
                                <tr>
                                    <td>{{ $userAccount->name }}</td>
                                    <td>
                                        <strong>{{ $userAccount->email }}</strong>
                                        <span class="table-sub">{{ $userAccount->phone }}</span>
                                    </td>
                                    <td>{{ $userAccount->condominiumProfile?->commercial_name ?: 'Sin asignar' }}</td>
                                    <td>
                                        <span class="badge {{ $userAccount->role === 'admin' ? 'badge--warning' : 'badge--neutral' }}">
                                            {{ $userAccount->roleLabel() }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="table-actions">
                                            <a class="button button--ghost" href="{{ route('altas', ['edit_user' => $userAccount->id]) }}">Editar</a>
                                            @if (auth()->id() !== $userAccount->id)
                                                <form method="POST" action="{{ route('users.destroy', $userAccount) }}" onsubmit="return confirm('Eliminar esta cuenta?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="button button--danger" type="submit">Eliminar</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </section>
    </section>
    @endif

    @if (!($showUserAccessOnly ?? false))
    <section class="section-stack">
        <div class="section-intro">
            <div>
                <p class="section-intro__eyebrow">Archivo de condominios</p>
                <h3 class="section-intro__title">Condominios registrados</h3>
            </div>
            <p class="section-intro__note">Busca, selecciona o crea un condominio independiente para que su información no se mezcle con otros registros.</p>
        </div>

        <section class="panel panel--condominium-directory">
            <div class="panel__header">
                <h3>Buscador de condominios</h3>
                <span>{{ $condominiumProfiles->count() }} registrados</span>
            </div>

            <form class="condominium-directory__search" method="GET" action="{{ route('settings') }}">
                <label class="field">
                    <span>Nombre, RFC o dirección</span>
                    <input type="text" name="condominium_q" value="{{ $condominiumQuery }}" placeholder="Buscar condominio">
                </label>
                <div class="form-actions">
                    <button class="button button--primary" type="submit">Buscar</button>
                    <a class="button button--ghost" href="{{ route('settings', ['new_condominium' => 1]) }}">Nuevo condominio</a>
                </div>
            </form>

            <div class="condominium-card-grid">
                @forelse ($condominiumProfiles as $condominium)
                    <article class="condominium-card {{ $selectedCondominiumProfile?->id === $condominium->id ? 'condominium-card--active' : '' }}">
                        <div>
                            <span>{{ $condominium->tax_id ?: 'Sin RFC' }}</span>
                            <strong>{{ $condominium->commercial_name ?: 'Condominio sin nombre #'.$condominium->id }}</strong>
                            <small>{{ $condominium->address ?: 'Ubicación sin capturar' }}</small>
                        </div>
                        <div class="condominium-card__meta">
                            <span>${{ number_format((float) $condominium->ordinary_fee_amount, 2) }}</span>
                            <span>{{ $condominium->departments_count }} departamentos</span>
                        </div>
                        <a class="button button--ghost" href="{{ route('settings', ['condominium_profile_id' => $condominium->id]) }}">
                            {{ $selectedCondominiumProfile?->id === $condominium->id ? 'Seleccionado' : 'Seleccionar' }}
                        </a>
                        <form method="POST" action="{{ route('settings.condominiums.destroy', $condominium) }}" onsubmit="return confirm('Eliminar este condominio? Esta accion tambien quitara sus documentos y minutas.');">
                            @csrf
                            @method('DELETE')
                            <button class="button button--danger" type="submit">Eliminar condominio</button>
                        </form>
                    </article>
                @empty
                    <div class="empty-state empty-state--compact">
                        <strong>No hay condominios registrados</strong>
                        <p>Captura el primero desde Datos generales e infraestructura.</p>
                    </div>
                @endforelse
            </div>
        </section>
    </section>

    @if ($selectedCondominiumProfile)
        <section class="section-stack">
            <div class="section-intro">
                <div>
                    <p class="section-intro__eyebrow">Información guardada</p>
                    <h3 class="section-intro__title">Resumen del condominio seleccionado</h3>
                </div>
                <p class="section-intro__note">Aquí puedes verificar lo que ya quedó guardado para este condominio antes de editar o complementar datos.</p>
            </div>

            <section class="content-grid content-grid--settings-summary">
                <article class="panel">
                    <div class="panel__header">
                        <h3>Datos generales</h3>
                        <span>Identidad</span>
                    </div>
                    <div class="summary-list">
                        <div class="summary-list__row">
                            <span>Condominio</span>
                            <strong>{{ $identity['commercial_name'] ?: 'Sin capturar' }}</strong>
                        </div>
                        <div class="summary-list__row">
                            <span>RFC / Identificación</span>
                            <strong>{{ $identity['tax_id'] ?: 'Sin capturar' }}</strong>
                        </div>
                        <div class="summary-list__row">
                            <span>Ubicación</span>
                            <strong>{{ $identity['address'] ?: 'Sin capturar' }}</strong>
                            @if ($identity['latitude'] && $identity['longitude'])
                                <small>{{ $identity['latitude'] }}, {{ $identity['longitude'] }}</small>
                            @endif
                        </div>
                        <div class="summary-list__row">
                            <span>Cuota ordinaria</span>
                            <strong>${{ number_format((float) $identity['ordinary_fee_amount'], 2) }}</strong>
                            <small>{{ $identity['fee_type_label'] ?: 'Tipo de cuota sin capturar' }}</small>
                        </div>
                        <div class="summary-list__row">
                            <span>Departamentos / cajones</span>
                            <strong>{{ $identity['departments_count'] }} departamentos · {{ $identity['parking_spaces_count'] }} cajones</strong>
                        </div>
                        <div class="summary-list__row">
                            <span>Administrador</span>
                            <strong>{{ $identity['admin_name'] ?: 'Sin capturar' }}</strong>
                            <small>{{ $identity['admin_type_label'] ?: 'Tipo sin capturar' }} · {{ $identity['admin_email'] ?: 'Sin correo' }} · {{ $identity['admin_phone'] ?: 'Sin teléfono' }}</small>
                        </div>
                        <div class="summary-list__row">
                            <span>Administrador auxiliar</span>
                            <strong>{{ $identity['assistant_admin_names'] ?: 'Sin capturar' }}</strong>
                            <small>{{ $identity['assistant_admin_phone'] ?: 'Sin teléfono' }}</small>
                        </div>
                    </div>
                </article>

                <article class="panel">
                    <div class="panel__header">
                        <h3>Infraestructura Técnica</h3>
                        <span>{{ collect($infrastructure)->where('active', true)->count() }} activos</span>
                    </div>
                    <div class="summary-list">
                        @foreach ($infrastructure as $item)
                            <div class="summary-list__row">
                                <span>{{ $item['name'] }}</span>
                                <strong>{{ $item['active'] ? 'Sí' : 'No' }}</strong>
                                <small>{{ $item['meta'] }}</small>
                            </div>
                        @endforeach
                    </div>
                </article>

                <article class="panel">
                    <div class="panel__header">
                        <h3>Horarios y reglamento</h3>
                        <span>Operación</span>
                    </div>
                    <div class="summary-list">
                        <div class="summary-list__row">
                            <span>Horario para mudanza</span>
                            <strong>{{ $operations['moving_hours'] ?: 'Sin capturar' }}</strong>
                        </div>
                        <div class="summary-list__row">
                            <span>Horario de trabajo</span>
                            <strong>{{ $operations['work_hours'] ?: 'Sin capturar' }}</strong>
                        </div>
                        <div class="summary-list__row">
                            <span>Horario para reunión</span>
                            <strong>{{ $operations['meeting_hours'] ?: 'Sin capturar' }}</strong>
                        </div>
                        <div class="summary-list__row">
                            <span>Reglamento</span>
                            <strong>{{ $documentStatus['regulations'] ? 'PDF cargado' : 'Sin archivo' }}</strong>
                        </div>
                        <div class="summary-list__row">
                            <span>Mapa de estacionamiento</span>
                            <strong>{{ $documentStatus['parking_map'] ? 'PDF cargado' : 'Sin archivo' }}</strong>
                        </div>
                        <div class="summary-list__row">
                            <span>Régimen de propiedad</span>
                            <strong>{{ $documentStatus['property_regime'] ? 'PDF cargado' : 'Sin archivo' }}</strong>
                        </div>
                    </div>
                </article>

                <article class="panel">
                    <div class="panel__header">
                        <h3>Personal operativo</h3>
                        <span>Contactos y permisos</span>
                    </div>
                    <div class="summary-list">
                        <div class="summary-list__row">
                            <span>Limpieza</span>
                            <strong>{{ $operations['cleaning_staff_name'] ?: 'Sin capturar' }}</strong>
                            <small>{{ $operations['cleaning_staff_phone'] ?: 'Sin teléfono' }} · {{ $operations['cleaning_staff_contact'] ?: 'Sin contacto' }}</small>
                        </div>
                        <div class="summary-list__row">
                            <span>Consignas de limpieza</span>
                            <strong>{{ $documentStatus['cleaning_instructions'] ? 'PDF cargado' : 'Sin archivo' }}</strong>
                            <small>Permisos: {{ $documentStatus['cleaning_permits'] ? 'PDF cargado' : 'Sin archivo' }}</small>
                        </div>
                        <div class="summary-list__row">
                            <span>Vigilancia 1</span>
                            <strong>{{ $operations['security_staff_name'] ?: 'Sin capturar' }}</strong>
                            <small>{{ $operations['security_staff_phone'] ?: 'Sin teléfono' }} · {{ $operations['security_staff_contact'] ?: 'Sin contacto' }}</small>
                        </div>
                        <div class="summary-list__row">
                            <span>Vigilancia 2</span>
                            <strong>{{ $operations['security_staff_secondary_name'] ?: 'Sin capturar' }}</strong>
                            <small>{{ $operations['security_staff_secondary_phone'] ?: 'Sin teléfono' }} · {{ $operations['security_staff_secondary_contact'] ?: 'Sin contacto' }}</small>
                        </div>
                        <div class="summary-list__row">
                            <span>Consignas de vigilancia</span>
                            <strong>{{ $documentStatus['security_instructions'] ? 'PDF cargado' : 'Sin archivo' }}</strong>
                            <small>Permisos: {{ $documentStatus['security_permits'] ? 'PDF cargado' : 'Sin archivo' }}</small>
                        </div>
                    </div>
                </article>

                <article class="panel">
                    <div class="panel__header">
                        <h3>Cuenta bancaria</h3>
                        <span>Cuotas de mantenimiento</span>
                    </div>
                    <div class="summary-list">
                        <div class="summary-list__row">
                            <span>Institución bancaria</span>
                            <strong>{{ $banking['bank'] ?: 'Sin capturar' }}</strong>
                        </div>
                        <div class="summary-list__row">
                            <span>Titular</span>
                            <strong>{{ $banking['holder'] ?: 'Sin capturar' }}</strong>
                        </div>
                        <div class="summary-list__row">
                            <span>Tipo y número de cuenta</span>
                            <strong>{{ $banking['account_type'] ?: 'Sin tipo' }}</strong>
                            <small>{{ $banking['account'] ?: 'Sin número' }}</small>
                        </div>
                        <div class="summary-list__row">
                            <span>CLABE</span>
                            <strong>{{ $banking['clabe'] ?: 'Sin capturar' }}</strong>
                        </div>
                    </div>
                </article>

                <article class="panel">
                    <div class="panel__header">
                        <h3>Minutas de asamblea</h3>
                        <span>{{ $assemblyMinutes->count() }} registradas</span>
                    </div>
                    @if ($assemblyMinutes->isEmpty())
                        <div class="empty-state empty-state--compact">
                            <strong>Sin minutas registradas</strong>
                            <p>Cuando guardes minutas para este condominio, aparecerán en este resumen y en el archivo inferior.</p>
                        </div>
                    @else
                        <div class="summary-list">
                            @foreach ($assemblyMinutes->take(3) as $minute)
                                <div class="summary-list__row">
                                    <span>{{ optional($minute->assembly_date)->format('d/m/Y') ?: 'Sin fecha' }}</span>
                                    <strong>{{ $minute->title }}</strong>
                                    <small>{{ $minute->document_path ? 'Minuta cargada' : 'Sin minuta' }} · {{ $minute->convocation_path ? 'Convocatoria cargada' : 'Sin convocatoria' }}</small>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </article>
            </section>
        </section>
    @endif

    <section class="section-stack">
        <div class="section-intro">
            <div>
                <p class="section-intro__eyebrow">Identidad del condominio</p>
                <h3 class="section-intro__title">Datos generales e infraestructura</h3>
            </div>
            <p class="section-intro__note">Llena toda la información del condominio y guárdala en un solo paso para mantener el registro completo y ordenado.</p>
        </div>

        @if ($errors->settingsProfile->any())
            <div class="alert alert--error">
                <strong>Revisa estos campos antes de guardar toda la información:</strong>
                <ul class="alert-list">
                    @foreach ($errors->settingsProfile->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form id="settings-master-form" class="settings-master-form" method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data" autocomplete="off">
            @csrf
            @if ($selectedCondominiumProfile)
                <input type="hidden" name="condominium_profile_id" value="{{ $selectedCondominiumProfile->id }}">
            @endif

            <section class="content-grid content-grid--settings">
                <article class="panel panel--infrastructure panel--settings-identity">
                    <div class="panel__header">
                        <h3>Identidad del Condominio</h3>
                        <span>General</span>
                    </div>

                    <div class="form-grid form-grid--settings-profile">
                        <div class="form-block-title field--full">
                            <span>Identificacion del condominio</span>
                            <small>Captura los datos oficiales, la dirección y la base administrativa del inmueble.</small>
                        </div>
                        <label class="field {{ $profileError('commercial_name') ? 'field--error' : '' }}">
                            <span>Condominio</span>
                            <input type="text" name="commercial_name" value="{{ $profileValue('commercial_name') }}" autocomplete="off" required>
                            @if ($profileError('commercial_name'))
                                <small class="field-error">{{ $profileError('commercial_name') }}</small>
                            @endif
                        </label>
                        <label class="field {{ $profileError('tax_id') ? 'field--error' : '' }}">
                            <span>RFC / Identificacion</span>
                            <input type="text" name="tax_id" value="{{ $profileValue('tax_id') }}" autocomplete="off">
                            @if ($profileError('tax_id'))
                                <small class="field-error">{{ $profileError('tax_id') }}</small>
                            @endif
                        </label>
                        <label class="field field--full {{ $profileError('address') ? 'field--error' : '' }}">
                            <span>Ubicación del condominio</span>
                            <input type="text" name="address" value="{{ $profileValue('address') }}" autocomplete="off" data-geo-address @readonly(filled($identity['address']) && filled($identity['latitude']) && filled($identity['longitude']))>
                            @if ($profileError('address'))
                                <small class="field-error">{{ $profileError('address') }}</small>
                            @endif
                        </label>
                        <input type="hidden" name="latitude" value="{{ $profileValue('latitude') }}" data-geo-lat>
                        <input type="hidden" name="longitude" value="{{ $profileValue('longitude') }}" data-geo-lng>
                        <div class="field field--full field--geo-tools">
                            <span>Geolocalización</span>
                            <div class="geo-tools">
                                <button class="button button--ghost" type="button" data-fill-geolocation>Usar mi ubicación</button>
                                <button class="button button--ghost" type="button" data-edit-geolocation @disabled(!filled($identity['address']))>Modificar ubicación</button>
                                <a class="button button--ghost" href="{{ $identity['address'] ? 'https://www.google.com/maps/search/?api=1&query='.urlencode($identity['address']) : '#' }}" target="_blank" rel="noreferrer" data-open-map data-saved-address="{{ $identity['address'] }}" data-saved-lat="{{ $identity['latitude'] }}" data-saved-lng="{{ $identity['longitude'] }}">Abrir mapa</a>
                                <button class="button button--ghost" type="button" data-share-map>Compartir ubicación</button>
                            </div>
                            <small data-geo-status>{{ $identity['address'] ? 'Ubicación actual: '.$identity['address'].' (solo administradores pueden desbloquearla y modificarla)' : 'Usa tu ubicación para capturar la dirección del condominio.' }}</small>
                        </div>

                        <div class="form-block-title field--full">
                            <span>Capacidad y cuota</span>
                            <small>Estos datos alimentan la operación general del condominio y la cobranza base.</small>
                        </div>
                        <label class="field {{ $profileError('ordinary_fee_amount') ? 'field--error' : '' }}">
                            <span>Monto de cuota ordinaria</span>
                            <input type="number" step="0.01" min="0" name="ordinary_fee_amount" value="{{ $profileValue('ordinary_fee_amount') }}" autocomplete="off" required>
                            @if ($profileError('ordinary_fee_amount'))
                                <small class="field-error">{{ $profileError('ordinary_fee_amount') }}</small>
                            @endif
                        </label>
                        <label class="field {{ $profileError('fee_type') ? 'field--error' : '' }}">
                            <span>Tipo de cuota</span>
                            <select name="fee_type" class="select-field" required>
                                <option value="" @selected($profileValue('fee_type') === '') disabled>Selecciona una opción</option>
                                @foreach ($feeTypeOptions as $key => $label)
                                    <option value="{{ $key }}" @selected($profileValue('fee_type') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @if ($profileError('fee_type'))
                                <small class="field-error">{{ $profileError('fee_type') }}</small>
                            @endif
                        </label>
                        <label class="field {{ $profileError('departments_count') ? 'field--error' : '' }}">
                            <span>Número de departamentos</span>
                            <input type="number" min="0" name="departments_count" value="{{ $profileValue('departments_count') }}" autocomplete="off" required>
                            @if ($profileError('departments_count'))
                                <small class="field-error">{{ $profileError('departments_count') }}</small>
                            @endif
                        </label>
                        <label class="field {{ $profileError('parking_spaces_count') ? 'field--error' : '' }}">
                            <span>Número de cajones</span>
                            <input type="number" min="0" name="parking_spaces_count" value="{{ $profileValue('parking_spaces_count') }}" autocomplete="off" required>
                            @if ($profileError('parking_spaces_count'))
                                <small class="field-error">{{ $profileError('parking_spaces_count') }}</small>
                            @endif
                        </label>
                        <label class="field {{ $profileError('storage_rooms_count') ? 'field--error' : '' }}">
                            <span>Número de bodegas</span>
                            <input type="number" min="0" name="storage_rooms_count" value="{{ $profileValue('storage_rooms_count') }}" autocomplete="off" required>
                            @if ($profileError('storage_rooms_count'))
                                <small class="field-error">{{ $profileError('storage_rooms_count') }}</small>
                            @endif
                        </label>
                        <label class="field {{ $profileError('clothesline_cages_count') ? 'field--error' : '' }}">
                            <span>Número de jaulas de tendido</span>
                            <input type="number" min="0" name="clothesline_cages_count" value="{{ $profileValue('clothesline_cages_count') }}" autocomplete="off" required>
                            @if ($profileError('clothesline_cages_count'))
                                <small class="field-error">{{ $profileError('clothesline_cages_count') }}</small>
                            @endif
                        </label>
                        <label class="field {{ $profileError('security_booth') ? 'field--error' : '' }}">
                            <span>Caseta de vigilancia</span>
                            <select name="security_booth" class="select-field" required>
                                <option value="" @selected((string) $profileBoolValue('security_booth') === '') disabled>Selecciona una opción</option>
                                <option value="1" @selected((string) $profileBoolValue('security_booth') === '1')>Sí</option>
                                <option value="0" @selected((string) $profileBoolValue('security_booth') === '0')>No</option>
                            </select>
                            @if ($profileError('security_booth'))
                                <small class="field-error">{{ $profileError('security_booth') }}</small>
                            @endif
                        </label>

                        <div class="form-block-title field--full">
                            <span>Administración responsable</span>
                            <small>Los datos del administrador principal aparecen listos para capturarse automaticamente.</small>
                        </div>
                        <label class="field">
                            <span>Administrador</span>
                            <input type="text" name="admin_name" value="{{ $profileValue('admin_name', $defaultAdministrator['name']) }}" autocomplete="off">
                        </label>
                        <label class="field">
                            <span>Tipo de administrador</span>
                            <select name="admin_type" class="select-field">
                                <option value="" @selected($profileValue('admin_type') === '') disabled>Selecciona una opción</option>
                                @foreach ($adminTypeOptions as $key => $label)
                                    <option value="{{ $key }}" @selected($profileValue('admin_type') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="field field--full">
                            <span>Administrador auxiliar</span>
                            <select name="assistant_admin_names" class="select-field" data-assistant-select>
                                <option value="" @selected($profileValue('assistant_admin_names') === '')>Selecciona una opción</option>
                                @foreach ($assistantAdminOptions as $assistantName => $assistantPhone)
                                    <option value="{{ $assistantName }}" data-phone="{{ $assistantPhone }}" @selected($profileValue('assistant_admin_names') === $assistantName)>{{ $assistantName }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="field">
                            <span>Teléfono del administrador auxiliar</span>
                            <input type="text" name="assistant_admin_phone" value="{{ $profileValue('assistant_admin_phone') }}" autocomplete="off" data-assistant-phone readonly>
                        </label>
                        <label class="field">
                            <span>Correo del administrador</span>
                            <input type="email" name="admin_email" value="{{ $profileValue('admin_email', $defaultAdministrator['email']) }}" autocomplete="off">
                        </label>
                        <label class="field">
                            <span>Teléfono del administrador</span>
                            <input type="text" name="admin_phone" value="{{ $profileValue('admin_phone', $defaultAdministrator['phone']) }}" autocomplete="off">
                        </label>

                    </div>

                    <div class="map-card">
                        <div class="map-card__pin">
                            {{ $identity['address'] ?: 'Ubicación pendiente de configurar' }}
                            @if ($identity['latitude'] && $identity['longitude'])
                                <small>{{ $identity['latitude'] }}, {{ $identity['longitude'] }}</small>
                            @endif
                        </div>
                    </div>

                </article>

                <article class="panel panel--settings-infra">
                    <div class="panel__header">
                        <h3>Infraestructura Tecnica</h3>
                        <span>Activos</span>
                    </div>

                    <div class="form-grid form-grid--infrastructure">
                        <div class="form-block-title field--full">
                            <span>Equipamiento principal</span>
                            <small>Marca lo que existe y, cuando aplique, registra cantidades fisicas.</small>
                        </div>
                        @foreach ([
                            ['label' => 'Elevadores', 'enabled' => 'elevators_enabled', 'count' => 'elevators_count', 'count_label' => 'Número de elevadores'],
                            ['label' => 'Cisternas', 'enabled' => 'cisterns_enabled', 'count' => 'cisterns_count', 'count_label' => 'Número de cisternas'],
                            ['label' => 'Tinacos', 'enabled' => 'water_tanks_enabled', 'count' => 'water_tanks_count', 'count_label' => 'Número de tinacos'],
                            ['label' => 'Hidroneumáticos', 'enabled' => 'hydropneumatics_enabled', 'count' => 'hydropneumatics_count', 'count_label' => 'Número de hidroneumáticos'],
                        ] as $equipment)
                            <label class="field">
                                <span>{{ $equipment['label'] }}</span>
                                <select name="{{ $equipment['enabled'] }}" class="select-field" required>
                                    <option value="" @selected((string) $profileBoolValue($equipment['enabled']) === '') disabled>Selecciona una opción</option>
                                    <option value="1" @selected((string) $profileBoolValue($equipment['enabled']) === '1')>Sí</option>
                                    <option value="0" @selected((string) $profileBoolValue($equipment['enabled']) === '0')>No</option>
                                </select>
                            </label>
                            <label class="field">
                                <span>{{ $equipment['count_label'] }}</span>
                                <input type="number" min="0" name="{{ $equipment['count'] }}" value="{{ $profileValue($equipment['count']) }}" autocomplete="off" required>
                            </label>
                        @endforeach

                        <div class="form-block-title field--full">
                            <span>Amenidades e instalaciones comunes</span>
                            <small>Estas opciones ayudan a reflejar con más precisión las áreas activas del condominio.</small>
                        </div>
                        @foreach ([
                            ['label' => 'Alberca', 'name' => 'pool_enabled'],
                            ['label' => 'Chapoteadero', 'name' => 'wading_pool_enabled'],
                            ['label' => 'Salón de eventos', 'name' => 'event_hall_enabled'],
                            ['label' => 'Roof garden', 'name' => 'roof_garden_enabled'],
                            ['label' => 'Salón de yoga', 'name' => 'yoga_room_enabled'],
                            ['label' => 'Salón de juegos', 'name' => 'game_room_enabled'],
                            ['label' => 'GYM', 'name' => 'gym_enabled'],
                            ['label' => 'Asador', 'name' => 'grill_enabled'],
                        ] as $amenityField)
                            <label class="field">
                                <span>{{ $amenityField['label'] }}</span>
                                <select name="{{ $amenityField['name'] }}" class="select-field">
                                    <option value="" @selected($profileBoolValue($amenityField['name']) === '') disabled>Selecciona una opción</option>
                                    <option value="1" @selected($profileBoolValue($amenityField['name']) === '1')>Sí</option>
                                    <option value="0" @selected($profileBoolValue($amenityField['name']) === '0')>No</option>
                                </select>
                            </label>
                        @endforeach
                    </div>
                </article>
            </section>

            <section class="section-stack">
                <div class="section-intro">
                    <div>
                        <p class="section-intro__eyebrow">Operación del condominio</p>
                        <h3 class="section-intro__title">Horarios, reglamento y personal operativo</h3>
                    </div>
                    <p class="section-intro__note">Aquí se guardan las ventanas operativas del condominio, el reglamento en PDF y los datos de limpieza y vigilancia.</p>
                </div>

                <section class="content-grid content-grid--settings-operations">
                    <article class="panel panel--settings-operations-main">
                        <div class="panel__header">
                            <h3>Horarios y reglamento</h3>
                            <span>Operación</span>
                        </div>

                        <div class="form-grid form-grid--settings-ops">
                            <div class="form-block-title field--full">
                                <span>Ventanas operativas</span>
                                <small>Establece horarios autorizados para mudanzas, trabajos y reuniones.</small>
                            </div>
                            @foreach ([
                                ['title' => 'Horario para mudanza', 'day' => 'moving_hours_day', 'start' => 'moving_hours_start', 'end' => 'moving_hours_end'],
                                ['title' => 'Horario de trabajo', 'day' => 'work_hours_day', 'start' => 'work_hours_start', 'end' => 'work_hours_end'],
                                ['title' => 'Horario para reunión', 'day' => 'meeting_hours_day', 'start' => 'meeting_hours_start', 'end' => 'meeting_hours_end'],
                            ] as $scheduleField)
                                <div class="form-block-title field--full form-block-title--compact">
                                    <span>{{ $scheduleField['title'] }}</span>
                                </div>
                                <label class="field {{ $profileError($scheduleField['day']) ? 'field--error' : '' }}">
                                    <span>Día autorizado</span>
                                    <select name="{{ $scheduleField['day'] }}" class="select-field">
                                        <option value="" @selected(old($scheduleField['day'], $operations[$scheduleField['day']] ?? '') === '')>Selecciona una opción</option>
                                        @foreach ($scheduleDayOptions as $scheduleValue => $scheduleLabel)
                                            <option value="{{ $scheduleValue }}" @selected(old($scheduleField['day'], $operations[$scheduleField['day']] ?? '') === $scheduleValue)>{{ $scheduleLabel }}</option>
                                        @endforeach
                                    </select>
                                    @if ($profileError($scheduleField['day']))
                                        <small class="field-error">{{ $profileError($scheduleField['day']) }}</small>
                                    @endif
                                </label>
                                <label class="field {{ $profileError($scheduleField['start']) ? 'field--error' : '' }}">
                                    <span>Inicio</span>
                                    <select name="{{ $scheduleField['start'] }}" class="select-field">
                                        <option value="" @selected(old($scheduleField['start'], $operations[$scheduleField['start']] ?? '') === '')>Selecciona inicio</option>
                                        @foreach ($timeOptions as $timeOption)
                                            <option value="{{ $timeOption }}" @selected(old($scheduleField['start'], $operations[$scheduleField['start']] ?? '') === $timeOption)>{{ $timeOption }}</option>
                                        @endforeach
                                    </select>
                                    @if ($profileError($scheduleField['start']))
                                        <small class="field-error">{{ $profileError($scheduleField['start']) }}</small>
                                    @endif
                                </label>
                                <label class="field {{ $profileError($scheduleField['end']) ? 'field--error' : '' }}">
                                    <span>Final</span>
                                    <select name="{{ $scheduleField['end'] }}" class="select-field">
                                        <option value="" @selected(old($scheduleField['end'], $operations[$scheduleField['end']] ?? '') === '')>Selecciona final</option>
                                        @foreach ($timeOptions as $timeOption)
                                            <option value="{{ $timeOption }}" @selected(old($scheduleField['end'], $operations[$scheduleField['end']] ?? '') === $timeOption)>{{ $timeOption }}</option>
                                        @endforeach
                                    </select>
                                    @if ($profileError($scheduleField['end']))
                                        <small class="field-error">{{ $profileError($scheduleField['end']) }}</small>
                                    @endif
                                </label>
                            @endforeach
                            <label class="field field--full">
                                <span>Adjuntar reglamento del condominio (PDF)</span>
                                <input type="file" name="regulations_file" accept="application/pdf">
                            </label>
                            @if ($operations['regulations_path'])
                                <div class="field field--full field--file-preview">
                                    <span>Reglamento actual</span>
                                    <a class="button button--ghost" href="{{ route('settings.regulations.document') }}" target="_blank" rel="noreferrer">Ver reglamento cargado</a>
                                </div>
                            @endif
                            <label class="field">
                                <span>Mapa de estacionamiento (PDF)</span>
                                <input type="file" name="parking_map_file" accept="application/pdf">
                            </label>
                            @if ($operations['parking_map_path'])
                                <div class="field field--file-preview">
                                    <span>Mapa actual</span>
                                    <a class="button button--ghost" href="{{ route('settings.documents.show', 'parking-map') }}" target="_blank" rel="noreferrer">Ver mapa cargado</a>
                                </div>
                            @endif
                            <label class="field">
                                <span>Régimen de propiedad y condominio (PDF)</span>
                                <input type="file" name="property_regime_file" accept="application/pdf">
                            </label>
                            @if ($operations['property_regime_path'])
                                <div class="field field--file-preview">
                                    <span>Régimen actual</span>
                                    <a class="button button--ghost" href="{{ route('settings.documents.show', 'property-regime') }}" target="_blank" rel="noreferrer">Ver documento cargado</a>
                                </div>
                            @endif
                        </div>
                    </article>

                    <article class="panel panel--settings-operations-staff">
                        <div class="panel__header">
                            <h3>Personal operativo</h3>
                            <span>Contacto</span>
                        </div>

                        <div class="form-grid form-grid--settings-staff">
                            <div class="form-block-title field--full">
                                <span>Limpieza y vigilancia</span>
                                <small>Guarda responsables, teléfonos y los PDF de consignas para operación diaria.</small>
                            </div>
                            <label class="field">
                                <span>Personal de limpieza</span>
                                <input type="text" name="cleaning_staff_name" value="{{ $profileValue('cleaning_staff_name') }}">
                            </label>
                            <label class="field">
                                <span>Teléfono de limpieza</span>
                                <input type="text" name="cleaning_staff_phone" value="{{ $profileValue('cleaning_staff_phone') }}">
                            </label>
                            <label class="field field--full">
                                <span>Datos de contacto de limpieza</span>
                                <input type="text" name="cleaning_staff_contact" value="{{ $profileValue('cleaning_staff_contact') }}" placeholder="Correo o referencia">
                            </label>
                            <label class="field field--full">
                                <span>Consignas de limpieza (PDF)</span>
                                <input type="file" name="cleaning_instructions_file" accept="application/pdf">
                            </label>
                            @if ($operations['cleaning_instructions_path'])
                                <div class="field field--full field--file-preview">
                                    <span>Consignas actuales de limpieza</span>
                                    <a class="button button--ghost" href="{{ route('settings.documents.show', 'cleaning-instructions') }}" target="_blank" rel="noreferrer">Ver PDF cargado</a>
                                </div>
                            @endif
                            <label class="field field--full">
                                <span>Permisos de limpieza (PDF)</span>
                                <input type="file" name="cleaning_permits_file" accept="application/pdf">
                            </label>
                            @if ($operations['cleaning_permits_path'])
                                <div class="field field--full field--file-preview">
                                    <span>Permisos actuales de limpieza</span>
                                    <a class="button button--ghost" href="{{ route('settings.documents.show', 'cleaning-permits') }}" target="_blank" rel="noreferrer">Ver PDF cargado</a>
                                </div>
                            @endif
                            <label class="field">
                                <span>Personal de vigilancia</span>
                                <input type="text" name="security_staff_name" value="{{ $profileValue('security_staff_name') }}">
                            </label>
                            <label class="field">
                                <span>Teléfono de vigilancia</span>
                                <input type="text" name="security_staff_phone" value="{{ $profileValue('security_staff_phone') }}">
                            </label>
                            <label class="field field--full">
                                <span>Datos de contacto de vigilancia</span>
                                <input type="text" name="security_staff_contact" value="{{ $profileValue('security_staff_contact') }}" placeholder="Turno, correo o referencia">
                            </label>
                            <label class="field field--full">
                                <span>Consignas de vigilancia (PDF)</span>
                                <input type="file" name="security_instructions_file" accept="application/pdf">
                            </label>
                            @if ($operations['security_instructions_path'])
                                <div class="field field--full field--file-preview">
                                    <span>Consignas actuales de vigilancia</span>
                                    <a class="button button--ghost" href="{{ route('settings.documents.show', 'security-instructions') }}" target="_blank" rel="noreferrer">Ver PDF cargado</a>
                                </div>
                            @endif
                            <label class="field field--full">
                                <span>Permisos de vigilancia (PDF)</span>
                                <input type="file" name="security_permits_file" accept="application/pdf">
                            </label>
                            @if ($operations['security_permits_path'])
                                <div class="field field--full field--file-preview">
                                    <span>Permisos actuales de vigilancia</span>
                                    <a class="button button--ghost" href="{{ route('settings.documents.show', 'security-permits') }}" target="_blank" rel="noreferrer">Ver PDF cargado</a>
                                </div>
                            @endif
                        </div>
                    </article>
                </section>
            </section>

            <section class="section-stack">
                <div class="section-intro">
                    <div>
                        <p class="section-intro__eyebrow">Depositos y permisos</p>
                        <h3 class="section-intro__title">Cuenta bancaria y nivel de acceso</h3>
                    </div>
                    <p class="section-intro__note">Completa los datos bancarios y después guarda toda la información del condominio con un solo botón.</p>
                </div>

                <section class="content-grid content-grid--settings-bottom">
                    <article class="panel panel--settings-banking">
                        <div class="panel__header">
                            <h3>Datos de la cuenta para depositar las cuotas de mantenimiento</h3>
                            <span>Finanzas</span>
                        </div>

                        <div class="form-grid form-grid--settings-banking">
                            <div class="form-block-title field--full">
                                <span>Cuenta receptora</span>
                                <small>Estos datos se muestran en reportes, recordatorios, estados de cuenta y tambien pueden exportarse en PDF.</small>
                            </div>
                            <label class="field">
                                <span>Institución bancaria</span>
                                <input type="text" name="bank" value="{{ old('bank', $isEditingCondominium ? $banking['bank'] : '') }}" autocomplete="off">
                            </label>
                            <label class="field">
                                <span>Titular de la cuenta</span>
                                <input type="text" name="account_holder" value="{{ old('account_holder', $isEditingCondominium ? $banking['holder'] : '') }}" autocomplete="off">
                            </label>
                            <label class="field">
                                <span>Tipo de cuenta</span>
                                <input type="text" name="bank_account_type" value="{{ old('bank_account_type', $isEditingCondominium ? $banking['account_type'] : '') }}" autocomplete="off" placeholder="Ej. Cheques, debito o empresarial">
                            </label>
                            <label class="field">
                                <span>Número de cuenta</span>
                                <input type="text" name="account_number" value="{{ old('account_number', $isEditingCondominium ? $banking['account'] : '') }}" autocomplete="off">
                            </label>
                            <label class="field">
                                <span>CLABE</span>
                                <input type="text" name="clabe" value="{{ old('clabe', $isEditingCondominium ? $banking['clabe'] : '') }}" autocomplete="off">
                            </label>
                            <div class="field field--full field--file-preview">
                                <span>Formato bancario</span>
                                <a class="button button--ghost" href="{{ route('settings.banking.word') }}">Descargar formato PDF</a>
                            </div>
                        </div>
                    </article>

                    <article class="panel panel--primary action-panel">
                        <h3>Nivel de Acceso</h3>
                        <p>Este botón guardará todos los campos capturados en datos generales, infraestructura, operación y cuenta bancaria.</p>
                        <div class="role-chip role-chip--light">{{ $currentUser?->roleLabel() ?? 'Administrador' }}</div>
                    </article>
                </section>
            </section>

        </form>
    </section>
    @endif
@endif

@if (!($showUserAccessOnly ?? false))
<section class="section-stack">
    <div class="section-intro">
        <div>
            <p class="section-intro__eyebrow">Archivo y acceso</p>
            <h3 class="section-intro__title">Minutas de asamblea y nivel de acceso</h3>
        </div>
        <p class="section-intro__note">Este bloque mantiene el historial de minutas y el contexto del nivel de permisos del usuario actual.</p>
    </div>

    <section class="content-grid content-grid--settings-bottom">
        <article class="panel panel--settings-minutes">
            <div class="panel__header">
                <h3>Minutas de asamblea</h3>
                <span>Archivo</span>
            </div>
            @if ($canManage)
                @if ($errors->settingsMinutes->any())
                    <div class="alert alert--error">{{ $errors->settingsMinutes->first() }}</div>
                @endif
                <form class="form-grid form-grid--settings-minutes" method="POST" action="{{ route('settings.minutes.store') }}" enctype="multipart/form-data" autocomplete="off">
                    @csrf
                    @if ($selectedCondominiumProfile)
                        <input type="hidden" name="condominium_profile_id" value="{{ $selectedCondominiumProfile->id }}">
                    @endif
                    <div class="form-block-title field--full">
                        <span>Registro de minutas</span>
                        <small>Guarda la fecha, la minuta y la convocatoria en PDF de cada reunión del condominio.</small>
                    </div>
                    <label class="field">
                        <span>Titulo de la minuta</span>
                        <input type="text" name="title" value="{{ old('title', '') }}" autocomplete="off" placeholder="Ej. Asamblea ordinaria de abril">
                    </label>
                    <label class="field">
                        <span>Fecha de la asamblea</span>
                        <input type="date" name="assembly_date" value="{{ old('assembly_date', '') }}" autocomplete="off">
                    </label>
                    <label class="field field--full">
                        <span>Adjuntar minuta</span>
                        <input type="file" name="document_file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp">
                    </label>
                    <label class="field field--full">
                        <span>Adjuntar convocatoria (PDF)</span>
                        <input type="file" name="convocation_file" accept="application/pdf">
                    </label>
                    <div class="form-actions">
                        <button class="button button--primary" type="submit">Guardar minuta</button>
                    </div>
                </form>
            @endif

            <div class="table-wrap table-wrap--compact">
                @if ($assemblyMinutes->isEmpty())
                    <div class="empty-state empty-state--compact">
                        <strong>No hay minutas registradas</strong>
                        <p>Las minutas de asamblea aparecerán aquí conforme se vayan guardando.</p>
                    </div>
                @else
                    <table>
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Titulo</th>
                                <th>Convocatoria</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($assemblyMinutes as $minute)
                                <tr>
                                    <td>{{ optional($minute->assembly_date)->format('d/m/Y') ?: 'Sin fecha' }}</td>
                                    <td>{{ $minute->title }}</td>
                                    <td>{{ $minute->convocation_path ? 'Cargada' : 'Sin archivo' }}</td>
                                    <td>
                                        <div class="table-actions">
                                            @if ($minute->document_path)
                                                <a class="button button--ghost" href="{{ route('settings.minutes.document', $minute) }}" target="_blank" rel="noreferrer">Ver archivo</a>
                                            @endif
                                            @if ($minute->convocation_path)
                                                <a class="button button--ghost" href="{{ route('settings.minutes.convocation', $minute) }}" target="_blank" rel="noreferrer">Ver convocatoria</a>
                                            @endif
                                            @if ($canManage)
                                                <form method="POST" action="{{ route('settings.minutes.destroy', $minute) }}" onsubmit="return confirm('Eliminar esta minuta?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="button button--danger" type="submit">Eliminar</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </article>

        <article class="panel panel--primary action-panel">
            <h3>Nivel de Acceso</h3>
            <p>{{ $canManage ? 'Tu cuenta tiene permisos para crear, leer, actualizar y borrar información del portal.' : 'Tu cuenta tiene permisos de lectura y descarga de PDFs.' }}</p>
            <div class="role-chip role-chip--light">{{ $currentUser?->roleLabel() ?? ($canManage ? 'Administrador' : 'Auxiliar') }}</div>
        </article>
    </section>
</section>

@if ($canManage)
    <div class="settings-save-bar settings-save-bar--bottom">
        <button class="button button--primary settings-save-bar__button" type="submit" form="settings-master-form">Guardar toda la información del condominio</button>
    </div>
@endif
@endif

@if ($canManage)
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const geoButton = document.querySelector('[data-fill-geolocation]');
            const editGeoButton = document.querySelector('[data-edit-geolocation]');
            const openMapButton = document.querySelector('[data-open-map]');
            const shareMapButton = document.querySelector('[data-share-map]');
            const latInput = document.querySelector('[data-geo-lat]');
            const lngInput = document.querySelector('[data-geo-lng]');
            const addressInput = document.querySelector('[data-geo-address]');
            const status = document.querySelector('[data-geo-status]');
            const assistantSelect = document.querySelector('[data-assistant-select]');
            const assistantPhoneInput = document.querySelector('[data-assistant-phone]');

            const lockAddressField = () => {
                if (!addressInput) {
                    return;
                }

                const hasGeolocation = latInput?.value !== '' && lngInput?.value !== '' && addressInput.value !== '';
                addressInput.readOnly = hasGeolocation;

                if (editGeoButton) {
                    editGeoButton.disabled = !hasGeolocation;
                }
            };

            const buildAddress = (payload) => {
                if (!payload) {
                    return '';
                }

                const address = payload.address ?? {};
                const parts = [
                    address.road,
                    address.house_number,
                    address.suburb,
                    address.neighbourhood,
                    address.city || address.town || address.village,
                    address.state,
                    address.postcode,
                    address.country,
                ].filter(Boolean);

                return parts.length > 0 ? parts.join(', ') : (payload.display_name ?? '');
            };

            const updateMapLink = () => {
                if (!openMapButton || !addressInput) {
                    return;
                }

                const savedAddress = openMapButton.dataset.savedAddress?.trim() ?? '';
                const rawQuery = addressInput.value.trim() !== ''
                    ? addressInput.value.trim()
                    : savedAddress;

                openMapButton.href = rawQuery !== ''
                    ? `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(rawQuery)}`
                    : '#';
            };

            const syncAssistantPhone = () => {
                if (!assistantSelect || !assistantPhoneInput) {
                    return;
                }

                const selectedOption = assistantSelect.options[assistantSelect.selectedIndex];
                const phone = selectedOption?.dataset?.phone ?? '';

                if (phone !== '') {
                    assistantPhoneInput.value = phone;
                } else if (assistantSelect.value === '') {
                    assistantPhoneInput.value = '';
                }
            };

            if (assistantSelect && assistantPhoneInput) {
                assistantSelect.addEventListener('change', syncAssistantPhone);
                syncAssistantPhone();
            }

            if (addressInput) {
                addressInput.addEventListener('input', updateMapLink);
                updateMapLink();
                lockAddressField();
            }

            if (openMapButton) {
                openMapButton.addEventListener('click', (event) => {
                    updateMapLink();

                    if (openMapButton.getAttribute('href') === '#') {
                        event.preventDefault();
                        status.textContent = 'Primero captura o escribe la dirección completa del condominio.';
                    }
                });
            }

            if (shareMapButton) {
                shareMapButton.addEventListener('click', async () => {
                    updateMapLink();

                    const mapUrl = openMapButton?.getAttribute('href') ?? '#';
                    if (mapUrl === '#') {
                        status.textContent = 'Primero captura o escribe la dirección completa del condominio.';
                        return;
                    }

                    try {
                        await navigator.clipboard.writeText(mapUrl);
                        status.textContent = 'Link de ubicación copiado. Ya puedes compartirlo.';
                    } catch (error) {
                        window.open(mapUrl, '_blank', 'noopener,noreferrer');
                        status.textContent = 'Se abrió el mapa para que puedas copiar o compartir la ubicación.';
                    }
                });
            }

            if (!geoButton || !latInput || !lngInput || !addressInput || !status) {
                return;
            }

            if (editGeoButton) {
                editGeoButton.addEventListener('click', () => {
                    addressInput.readOnly = false;
                    addressInput.focus();
                    status.textContent = 'Ubicación desbloqueada. Como administrador puedes corregirla manualmente o volver a capturarla.';
                });
            }

            geoButton.addEventListener('click', () => {
                if (!navigator.geolocation) {
                    status.textContent = 'Tu navegador no permite geolocalización.';
                    return;
                }

                status.textContent = 'Obteniendo ubicación...';

                navigator.geolocation.getCurrentPosition(
                    async (position) => {
                        latInput.value = position.coords.latitude.toFixed(7);
                        lngInput.value = position.coords.longitude.toFixed(7);

                        try {
                            const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${encodeURIComponent(latInput.value)}&lon=${encodeURIComponent(lngInput.value)}`);
                            if (!response.ok) {
                                throw new Error('reverse-geocode-failed');
                            }

                            const payload = await response.json();
                            const resolvedAddress = buildAddress(payload);

                            if (resolvedAddress !== '') {
                                addressInput.value = resolvedAddress;
                                openMapButton.dataset.savedAddress = resolvedAddress;
                                openMapButton.dataset.savedLat = latInput.value;
                                openMapButton.dataset.savedLng = lngInput.value;
                                lockAddressField();
                                status.textContent = `Ubicación capturada: ${resolvedAddress}`;
                                updateMapLink();
                                return;
                            }
                        } catch (error) {
                            // Si la geocodificación falla, dejamos una referencia útil en el campo.
                        }

                        addressInput.value = `${latInput.value}, ${lngInput.value}`;
                        openMapButton.dataset.savedAddress = addressInput.value;
                        openMapButton.dataset.savedLat = latInput.value;
                        openMapButton.dataset.savedLng = lngInput.value;
                        lockAddressField();
                        status.textContent = `Ubicación capturada: ${addressInput.value}`;
                        updateMapLink();
                    },
                    () => {
                        status.textContent = 'No fue posible obtener la ubicación. Puedes escribir la dirección manualmente.';
                    }
                );
            });
        });
    </script>
@endif
