<section class="content-grid content-grid--settings">
    <article class="panel panel--infrastructure">
        <div class="panel__header">
            <h3>Identidad del Condominio</h3>
            <span>General</span>
        </div>

        @if ($canManage)
            @if ($errors->settingsProfile->any())
                <div class="alert alert--error">{{ $errors->settingsProfile->first() }}</div>
            @endif
            <form class="form-grid" method="POST" action="{{ route('settings.update') }}">
                @csrf
                <label class="field">
                    <span>Condominio</span>
                    <input type="text" name="commercial_name" value="{{ old('commercial_name', $identity['commercial_name']) }}" required>
                </label>
                <label class="field">
                    <span>RFC / Identificacion</span>
                    <input type="text" name="tax_id" value="{{ old('tax_id', $identity['tax_id']) }}">
                </label>
                <label class="field field--full">
                    <span>Ubicacion del condominio</span>
                    <input type="text" name="address" value="{{ old('address', $identity['address']) }}">
                </label>
                <label class="field">
                    <span>Monto de cuota ordinaria</span>
                    <input type="number" step="0.01" min="0" name="ordinary_fee_amount" value="{{ old('ordinary_fee_amount', $identity['ordinary_fee_amount']) }}" required>
                </label>
                <label class="field">
                    <span>Tipo de cuota</span>
                    <select name="fee_type" class="select-field" required>
                        @foreach ($feeTypeOptions as $key => $label)
                            <option value="{{ $key }}" @selected(old('fee_type', $identity['fee_type']) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field">
                        <span>Número de departamentos</span>
                    <input type="number" min="0" name="departments_count" value="{{ old('departments_count', $identity['departments_count']) }}" required>
                </label>
                <label class="field">
                        <span>Número de cajones</span>
                    <input type="number" min="0" name="parking_spaces_count" value="{{ old('parking_spaces_count', $identity['parking_spaces_count']) }}" required>
                </label>
                <label class="field">
                        <span>Número de bodegas</span>
                    <input type="number" min="0" name="storage_rooms_count" value="{{ old('storage_rooms_count', $identity['storage_rooms_count']) }}" required>
                </label>
                <label class="field">
                        <span>Número de jaulas de tendido</span>
                    <input type="number" min="0" name="clothesline_cages_count" value="{{ old('clothesline_cages_count', $identity['clothesline_cages_count']) }}" required>
                </label>
                <label class="field">
                    <span>Caseta de vigilancia</span>
                    <select name="security_booth" class="select-field" required>
                        <option value="1" @selected(old('security_booth', $identity['security_booth']))>Si</option>
                        <option value="0" @selected(! old('security_booth', $identity['security_booth']))>No</option>
                    </select>
                </label>
                <label class="field">
                    <span>Administrador</span>
                    <input type="text" name="admin_name" value="{{ old('admin_name', $identity['admin_name']) }}">
                </label>
                <label class="field">
                    <span>Correo del administrador</span>
                    <input type="email" name="admin_email" value="{{ old('admin_email', $identity['admin_email']) }}">
                </label>
                <label class="field">
                        <span>Teléfono del administrador</span>
                    <input type="text" name="admin_phone" value="{{ old('admin_phone', $identity['admin_phone']) }}">
                </label>
                <div class="form-actions">
                    <button class="button button--primary" type="submit">Guardar datos del condominio</button>
                </div>
            </form>
        @else
            <div class="form-grid">
                <label class="field">
                    <span>Condominio</span>
                    <input type="text" value="{{ $identity['commercial_name'] }}" readonly>
                </label>
                <label class="field">
                    <span>RFC / Identificacion</span>
                    <input type="text" value="{{ $identity['tax_id'] }}" readonly>
                </label>
                <label class="field field--full">
                    <span>Ubicacion del condominio</span>
                    <input type="text" value="{{ $identity['address'] }}" readonly>
                </label>
            </div>
        @endif

        <div class="map-card">
            <div class="map-card__pin">Ubicacion verificada</div>
        </div>
    </article>

    <article class="panel">
        <div class="panel__header">
                <h3>Infraestructura Técnica</h3>
            <span>Activos</span>
        </div>
        @if ($canManage)
            @if ($errors->settingsInfrastructure->any())
                <div class="alert alert--error">{{ $errors->settingsInfrastructure->first() }}</div>
            @endif
            <form class="form-grid form-grid--infrastructure" method="POST" action="{{ route('settings.infrastructure.update') }}">
                @csrf

                <label class="field">
                    <span>Elevadores</span>
                    <select name="elevators_enabled" class="select-field" required>
                        <option value="1" @selected(old('elevators_enabled', $infrastructure[0]['active']))>Si</option>
                        <option value="0" @selected(! old('elevators_enabled', $infrastructure[0]['active']))>No</option>
                    </select>
                </label>
                <label class="field">
                            <span>Número de elevadores</span>
                    <input type="number" min="0" name="elevators_count" value="{{ old('elevators_count', preg_replace('/\D/', '', $infrastructure[0]['meta'])) }}" required>
                </label>
                <label class="field">
                    <span>Cisternas</span>
                    <select name="cisterns_enabled" class="select-field" required>
                        <option value="1" @selected(old('cisterns_enabled', $infrastructure[1]['active']))>Si</option>
                        <option value="0" @selected(! old('cisterns_enabled', $infrastructure[1]['active']))>No</option>
                    </select>
                </label>
                <label class="field">
                            <span>Número de cisternas</span>
                    <input type="number" min="0" name="cisterns_count" value="{{ old('cisterns_count', preg_replace('/\D/', '', $infrastructure[1]['meta'])) }}" required>
                </label>
                <label class="field">
                    <span>Tinacos</span>
                    <select name="water_tanks_enabled" class="select-field" required>
                        <option value="1" @selected(old('water_tanks_enabled', $infrastructure[2]['active']))>Si</option>
                        <option value="0" @selected(! old('water_tanks_enabled', $infrastructure[2]['active']))>No</option>
                    </select>
                </label>
                <label class="field">
                            <span>Número de tinacos</span>
                    <input type="number" min="0" name="water_tanks_count" value="{{ old('water_tanks_count', preg_replace('/\D/', '', $infrastructure[2]['meta'])) }}" required>
                </label>
                <label class="field">
                    <span>Hidroneumaticos</span>
                    <select name="hydropneumatics_enabled" class="select-field" required>
                        <option value="1" @selected(old('hydropneumatics_enabled', $infrastructure[3]['active']))>Si</option>
                        <option value="0" @selected(! old('hydropneumatics_enabled', $infrastructure[3]['active']))>No</option>
                    </select>
                </label>
                <label class="field">
                            <span>Número de hidroneumáticos</span>
                    <input type="number" min="0" name="hydropneumatics_count" value="{{ old('hydropneumatics_count', preg_replace('/\D/', '', $infrastructure[3]['meta'])) }}" required>
                </label>
                <div class="form-actions">
                    <button class="button button--primary" type="submit">Guardar infraestructura</button>
                </div>
            </form>
        @else
            <div class="infra-list">
                @foreach ($infrastructure as $item)
                    <div class="infra-card">
                        <div>
                            <strong>{{ $item['name'] }}</strong>
                            <p>{{ $item['meta'] }}</p>
                        </div>
                        <div class="toggle {{ $item['active'] ? 'is-on' : '' }}"></div>
                    </div>
                @endforeach
            </div>
        @endif
    </article>
</section>

<section class="content-grid content-grid--settings-bottom">
    <article class="panel">
        <div class="panel__header">
            <h3>Datos de la cuenta para depositar las cuotas de mantenimiento</h3>
            <span>Finanzas</span>
        </div>
        @if ($canManage)
            @if ($errors->settingsBanking->any())
                <div class="alert alert--error">{{ $errors->settingsBanking->first() }}</div>
            @endif
            <form class="form-grid" method="POST" action="{{ route('settings.banking.update') }}">
                @csrf
                <label class="field">
                    <span>Institucion bancaria</span>
                    <input type="text" name="bank" value="{{ old('bank', $banking['bank']) }}">
                </label>
                <label class="field">
                    <span>Titular de la cuenta</span>
                    <input type="text" name="account_holder" value="{{ old('account_holder', $banking['holder']) }}">
                </label>
                <label class="field">
                    <span>Número de cuenta</span>
                    <input type="text" name="account_number" value="{{ old('account_number', $banking['account']) }}">
                </label>
                <label class="field">
                    <span>CLABE</span>
                    <input type="text" name="clabe" value="{{ old('clabe', $banking['clabe']) }}">
                </label>
                <div class="form-actions">
                    <button class="button button--primary" type="submit">Guardar cuenta de deposito</button>
                </div>
            </form>
        @else
            <div class="form-grid">
                <label class="field">
                    <span>Institucion bancaria</span>
                    <input type="text" value="{{ $banking['bank'] }}" readonly>
                </label>
                <label class="field">
                    <span>Titular de la cuenta</span>
                    <input type="text" value="{{ $banking['holder'] }}" readonly>
                </label>
                <label class="field">
                    <span>Número de cuenta</span>
                    <input type="text" value="{{ $banking['account'] }}" readonly>
                </label>
                <label class="field">
                    <span>CLABE Interbancaria</span>
                    <input type="text" value="{{ $banking['clabe'] }}" readonly>
                </label>
            </div>
        @endif
    </article>

    <article class="panel panel--primary action-panel">
        <h3>Nivel de Acceso</h3>
                <p>{{ $canManage ? 'Tu cuenta tiene permisos para crear, leer, actualizar y borrar información del portal.' : 'Tu cuenta tiene permisos de lectura y descarga de PDFs.' }}</p>
        <div class="role-chip role-chip--light">{{ $canManage ? 'Administrador' : 'Usuario' }}</div>
    </article>
</section>

@if ($canManage)
    <section class="panel">
        <div class="panel__header">
            <h3>Usuarios del Portal</h3>
                <span class="badge badge--neutral">Gestión de accesos</span>
        </div>

        @if ($selectedUser)
            <div class="user-summary-card">
                <div class="panel__header panel__header--tight">
                    <h3>Resumen del usuario guardado</h3>
                    <span>{{ $selectedUser->role === 'admin' ? 'Administrador' : 'Usuario' }}</span>
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
                        <span>Telefono</span>
                        <strong>{{ $selectedUser->phone }}</strong>
                    </div>
                    <div class="summary-item">
                        <span>Rol asignado</span>
                        <strong>{{ $selectedUser->role === 'admin' ? 'Administrador' : 'Usuario' }}</strong>
                    </div>
                </div>

                <div class="content-grid content-grid--settings-user">
                    <div class="subpanel">
                        <h4>Informacion vinculada del condominio</h4>
                        <div class="summary-list">
                            <div class="summary-list__row">
                                <span>Condominio</span>
                                <strong>{{ $identity['commercial_name'] ?: 'Sin configurar' }}</strong>
                            </div>
                            <div class="summary-list__row">
                                <span>RFC</span>
                                <strong>{{ $identity['tax_id'] ?: 'Sin configurar' }}</strong>
                            </div>
                            <div class="summary-list__row">
                                <span>Ubicacion</span>
                                <strong>{{ $identity['address'] ?: 'Sin configurar' }}</strong>
                            </div>
                            <div class="summary-list__row">
                                <span>Cuota ordinaria</span>
                                <strong>${{ number_format((float) $identity['ordinary_fee_amount'], 2) }}</strong>
                            </div>
                            <div class="summary-list__row">
                                <span>Cuenta de deposito</span>
                                <strong>{{ $banking['bank'] ?: 'Sin configurar' }}{{ $banking['account'] ? ' | '.$banking['account'] : '' }}</strong>
                            </div>
                        </div>
                    </div>

                    <div class="subpanel">
                        <h4>Unidades vinculadas al usuario</h4>
                        @if ($selectedUserUnits->isEmpty())
                            <div class="empty-state empty-state--compact">
                                <strong>Sin unidad vinculada</strong>
                                <p>Este usuario todavia no coincide con una unidad por correo o nombre.</p>
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

        <form class="form-grid" method="POST" action="{{ $editingUser ? route('users.update', $editingUser) : route('users.store') }}">
            @csrf
            @if ($editingUser)
                @method('PATCH')
            @endif

            <label class="field">
                <span>Nombre completo</span>
                <input type="text" name="name" value="{{ old('name', $editingUser?->name) }}" required>
            </label>
            <label class="field">
                        <span>Correo electrónico</span>
                <input type="email" name="email" value="{{ old('email', $editingUser?->email) }}" required>
            </label>
            <label class="field">
                <span>Telefono</span>
                <input type="text" name="phone" value="{{ old('phone', $editingUser?->phone) }}" required>
            </label>
            <label class="field">
                <span>Rol</span>
                <select name="role" class="select-field" required>
                    @foreach ($roleOptions as $roleKey => $roleLabel)
                        <option value="{{ $roleKey }}" @selected(old('role', $editingUser?->role) === $roleKey)>{{ $roleLabel }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">
                        <span>{{ $editingUser ? 'Nueva contraseña (opcional)' : 'Contraseña' }}</span>
                <input type="password" name="password" {{ $editingUser ? '' : 'required' }}>
            </label>
            <label class="field">
                        <span>Confirmar contraseña</span>
                <input type="password" name="password_confirmation" {{ $editingUser ? '' : 'required' }}>
            </label>
            <div class="form-actions">
                @if ($editingUser)
                    <a class="button button--ghost" href="{{ route('settings') }}">Cancelar edicion</a>
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
                                <td>
                                    <span class="badge {{ $userAccount->role === 'admin' ? 'badge--warning' : 'badge--neutral' }}">
                                        {{ $userAccount->role === 'admin' ? 'Administrador' : 'Usuario' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <a class="button button--ghost" href="{{ route('settings', ['view_user' => $userAccount->id, 'q' => request('q')]) }}">Ver detalle</a>
                                        <a class="button button--ghost" href="{{ route('settings', ['edit_user' => $userAccount->id]) }}">Editar</a>
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
@endif
