<section class="section-stack">
    <div class="section-intro">
        <div>
            <p class="section-intro__eyebrow">Identidad del condominio</p>
            <h3 class="section-intro__title">Datos generales e infraestructura</h3>
        </div>
        <p class="section-intro__note">Separa claramente la informacion general del condominio y la infraestructura tecnica para guardar cada bloque sin mezclar campos.</p>
    </div>

    <section class="content-grid content-grid--settings">
        <article class="panel panel--infrastructure panel--settings-identity">
            <div class="panel__header">
                <h3>Identidad del Condominio</h3>
                <span>General</span>
            </div>

            @if ($canManage)
                @if ($errors->settingsProfile->any())
                    <div class="alert alert--error">{{ $errors->settingsProfile->first() }}</div>
                @endif
                <form class="form-grid form-grid--settings-profile" method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data" autocomplete="off">
                    @csrf
                    <div class="form-block-title field--full">
                        <span>Identificación del condominio</span>
                        <small>Captura los datos oficiales, ubicación y capacidad general del inmueble.</small>
                    </div>
                    <label class="field">
                        <span>Condominio</span>
                        <input type="text" name="commercial_name" value="{{ old('commercial_name', '') }}" autocomplete="off" required>
                    </label>
                    <label class="field">
                        <span>RFC / Identificacion</span>
                        <input type="text" name="tax_id" value="{{ old('tax_id', '') }}" autocomplete="off">
                    </label>
                    <label class="field field--full">
                        <span>Ubicacion del condominio</span>
                        <input type="text" name="address" value="{{ old('address', '') }}" autocomplete="off" data-geo-address>
                    </label>
                    <input type="hidden" name="latitude" value="{{ old('latitude', '') }}" data-geo-lat>
                    <input type="hidden" name="longitude" value="{{ old('longitude', '') }}" data-geo-lng>
                    <div class="field field--full field--geo-tools">
                        <span>Geolocalizacion</span>
                        <div class="geo-tools">
                            <button class="button button--ghost" type="button" data-fill-geolocation>Usar mi ubicacion</button>
                            <a class="button button--ghost" href="{{ $identity['address'] ? 'https://www.google.com/maps/search/?api=1&query='.urlencode($identity['address']) : '#' }}" target="_blank" rel="noreferrer">Abrir mapa</a>
                        </div>
                        <small data-geo-status>Tambien puedes escribir la direccion y abrirla en mapa.</small>
                    </div>
                    <div class="form-block-title field--full">
                        <span>Capacidad y cuota</span>
                        <small>Estos datos alimentan la operación general del condominio y la cobranza base.</small>
                    </div>
                    <label class="field">
                        <span>Monto de cuota ordinaria</span>
                        <input type="number" step="0.01" min="0" name="ordinary_fee_amount" value="{{ old('ordinary_fee_amount', '') }}" autocomplete="off" required>
                    </label>
                    <label class="field">
                        <span>Tipo de cuota</span>
                        <select name="fee_type" class="select-field" required>
                            <option value="" @selected(old('fee_type', '') === '') disabled>Selecciona una opcion</option>
                            @foreach ($feeTypeOptions as $key => $label)
                                <option value="{{ $key }}" @selected(old('fee_type', '') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="field">
                        <span>Numero de departamentos</span>
                        <input type="number" min="0" name="departments_count" value="{{ old('departments_count', '') }}" autocomplete="off" required>
                    </label>
                    <label class="field">
                        <span>Numero de cajones</span>
                        <input type="number" min="0" name="parking_spaces_count" value="{{ old('parking_spaces_count', '') }}" autocomplete="off" required>
                    </label>
                    <label class="field">
                        <span>Numero de bodegas</span>
                        <input type="number" min="0" name="storage_rooms_count" value="{{ old('storage_rooms_count', '') }}" autocomplete="off" required>
                    </label>
                    <label class="field">
                        <span>Numero de jaulas de tendido</span>
                        <input type="number" min="0" name="clothesline_cages_count" value="{{ old('clothesline_cages_count', '') }}" autocomplete="off" required>
                    </label>
                    <label class="field">
                        <span>Caseta de vigilancia</span>
                        <select name="security_booth" class="select-field" required>
                            <option value="" @selected(old('security_booth', '') === '') disabled>Selecciona una opcion</option>
                            <option value="1" @selected(old('security_booth', '') === '1')>Si</option>
                            <option value="0" @selected(old('security_booth', '') === '0')>No</option>
                        </select>
                    </label>
                    <div class="form-block-title field--full">
                        <span>Administración responsable</span>
                        <small>Define quién administra, sus auxiliares y la documentación oficial de respaldo.</small>
                    </div>
                    <label class="field">
                        <span>Administrador</span>
                        <input type="text" name="admin_name" value="{{ old('admin_name', '') }}" autocomplete="off">
                    </label>
                    <label class="field">
                        <span>Tipo de administrador</span>
                        <select name="admin_type" class="select-field">
                            <option value="" @selected(old('admin_type', '') === '') disabled>Selecciona una opcion</option>
                            @foreach ($adminTypeOptions as $key => $label)
                                <option value="{{ $key }}" @selected(old('admin_type', '') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="field field--full">
                        <span>Administrador auxiliar</span>
                        <input type="text" name="assistant_admin_names" value="{{ old('assistant_admin_names', '') }}" autocomplete="off" placeholder="Ej. Alondra Velazquez Hernandez, Rene Alberto Solano">
                    </label>
                    <label class="field">
                        <span>Telefono del administrador auxiliar</span>
                        <input type="text" name="assistant_admin_phone" value="{{ old('assistant_admin_phone', '') }}" autocomplete="off">
                    </label>
                    <label class="field">
                        <span>Correo del administrador</span>
                        <input type="email" name="admin_email" value="{{ old('admin_email', '') }}" autocomplete="off">
                    </label>
                    <label class="field">
                        <span>Telefono del administrador</span>
                        <input type="text" name="admin_phone" value="{{ old('admin_phone', '') }}" autocomplete="off">
                    </label>
                    <label class="field field--full">
                        <span>Registro del administrador del condominio</span>
                        <input type="file" name="admin_registration_file" accept=".pdf,.jpg,.jpeg,.png,.webp">
                    </label>
                    @if ($identity['admin_registration_path'])
                        <div class="field field--full field--file-preview">
                            <span>Registro actual</span>
                            <a class="button button--ghost" href="{{ route('settings.admin-registration.document') }}" target="_blank" rel="noreferrer">Ver registro cargado</a>
                        </div>
                    @endif
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
                <div class="map-card__pin">
                    {{ $identity['address'] ?: 'Ubicacion pendiente de configurar' }}
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
            @if ($canManage)
                @if ($errors->settingsInfrastructure->any())
                    <div class="alert alert--error">{{ $errors->settingsInfrastructure->first() }}</div>
                @endif
                <form class="form-grid form-grid--infrastructure" method="POST" action="{{ route('settings.infrastructure.update') }}" autocomplete="off">
                    @csrf

                    <div class="form-block-title field--full">
                        <span>Equipamiento principal</span>
                        <small>Marca lo que existe y, cuando aplique, registra cantidades físicas.</small>
                    </div>
                    <label class="field">
                        <span>Elevadores</span>
                        <select name="elevators_enabled" class="select-field" required>
                            <option value="" @selected(old('elevators_enabled', '') === '') disabled>Selecciona una opcion</option>
                            <option value="1" @selected(old('elevators_enabled', '') === '1')>Si</option>
                            <option value="0" @selected(old('elevators_enabled', '') === '0')>No</option>
                        </select>
                    </label>
                    <label class="field">
                        <span>Numero de elevadores</span>
                        <input type="number" min="0" name="elevators_count" value="{{ old('elevators_count', '') }}" autocomplete="off" required>
                    </label>
                    <label class="field">
                        <span>Cisternas</span>
                        <select name="cisterns_enabled" class="select-field" required>
                            <option value="" @selected(old('cisterns_enabled', '') === '') disabled>Selecciona una opcion</option>
                            <option value="1" @selected(old('cisterns_enabled', '') === '1')>Si</option>
                            <option value="0" @selected(old('cisterns_enabled', '') === '0')>No</option>
                        </select>
                    </label>
                    <label class="field">
                        <span>Numero de cisternas</span>
                        <input type="number" min="0" name="cisterns_count" value="{{ old('cisterns_count', '') }}" autocomplete="off" required>
                    </label>
                    <label class="field">
                        <span>Tinacos</span>
                        <select name="water_tanks_enabled" class="select-field" required>
                            <option value="" @selected(old('water_tanks_enabled', '') === '') disabled>Selecciona una opcion</option>
                            <option value="1" @selected(old('water_tanks_enabled', '') === '1')>Si</option>
                            <option value="0" @selected(old('water_tanks_enabled', '') === '0')>No</option>
                        </select>
                    </label>
                    <label class="field">
                        <span>Numero de tinacos</span>
                        <input type="number" min="0" name="water_tanks_count" value="{{ old('water_tanks_count', '') }}" autocomplete="off" required>
                    </label>
                    <label class="field">
                        <span>Hidroneumaticos</span>
                        <select name="hydropneumatics_enabled" class="select-field" required>
                            <option value="" @selected(old('hydropneumatics_enabled', '') === '') disabled>Selecciona una opcion</option>
                            <option value="1" @selected(old('hydropneumatics_enabled', '') === '1')>Si</option>
                            <option value="0" @selected(old('hydropneumatics_enabled', '') === '0')>No</option>
                        </select>
                    </label>
                    <label class="field">
                        <span>Numero de hidroneumaticos</span>
                        <input type="number" min="0" name="hydropneumatics_count" value="{{ old('hydropneumatics_count', '') }}" autocomplete="off" required>
                    </label>
                    <div class="form-block-title field--full">
                        <span>Amenidades e instalaciones comunes</span>
                        <small>Estas opciones ayudan a reflejar con más precisión las áreas activas del condominio.</small>
                    </div>
                    <label class="field">
                        <span>Alberca</span>
                        <select name="pool_enabled" class="select-field">
                            <option value="" @selected(old('pool_enabled', '') === '') disabled>Selecciona una opcion</option>
                            <option value="1" @selected(old('pool_enabled', '') === '1')>Si</option>
                            <option value="0" @selected(old('pool_enabled', '') === '0')>No</option>
                        </select>
                    </label>
                    <label class="field">
                        <span>Chapoteadero</span>
                        <select name="wading_pool_enabled" class="select-field">
                            <option value="" @selected(old('wading_pool_enabled', '') === '') disabled>Selecciona una opcion</option>
                            <option value="1" @selected(old('wading_pool_enabled', '') === '1')>Si</option>
                            <option value="0" @selected(old('wading_pool_enabled', '') === '0')>No</option>
                        </select>
                    </label>
                    <label class="field">
                        <span>Salon de eventos</span>
                        <select name="event_hall_enabled" class="select-field">
                            <option value="" @selected(old('event_hall_enabled', '') === '') disabled>Selecciona una opcion</option>
                            <option value="1" @selected(old('event_hall_enabled', '') === '1')>Si</option>
                            <option value="0" @selected(old('event_hall_enabled', '') === '0')>No</option>
                        </select>
                    </label>
                    <label class="field">
                        <span>Roof garden</span>
                        <select name="roof_garden_enabled" class="select-field">
                            <option value="" @selected(old('roof_garden_enabled', '') === '') disabled>Selecciona una opcion</option>
                            <option value="1" @selected(old('roof_garden_enabled', '') === '1')>Si</option>
                            <option value="0" @selected(old('roof_garden_enabled', '') === '0')>No</option>
                        </select>
                    </label>
                    <label class="field">
                        <span>Salon de yoga</span>
                        <select name="yoga_room_enabled" class="select-field">
                            <option value="" @selected(old('yoga_room_enabled', '') === '') disabled>Selecciona una opcion</option>
                            <option value="1" @selected(old('yoga_room_enabled', '') === '1')>Si</option>
                            <option value="0" @selected(old('yoga_room_enabled', '') === '0')>No</option>
                        </select>
                    </label>
                    <label class="field">
                        <span>Salon de juegos</span>
                        <select name="game_room_enabled" class="select-field">
                            <option value="" @selected(old('game_room_enabled', '') === '') disabled>Selecciona una opcion</option>
                            <option value="1" @selected(old('game_room_enabled', '') === '1')>Si</option>
                            <option value="0" @selected(old('game_room_enabled', '') === '0')>No</option>
                        </select>
                    </label>
                    <label class="field">
                        <span>GYM</span>
                        <select name="gym_enabled" class="select-field">
                            <option value="" @selected(old('gym_enabled', '') === '') disabled>Selecciona una opcion</option>
                            <option value="1" @selected(old('gym_enabled', '') === '1')>Si</option>
                            <option value="0" @selected(old('gym_enabled', '') === '0')>No</option>
                        </select>
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
</section>

<section class="section-stack">
    <div class="section-intro">
        <div>
            <p class="section-intro__eyebrow">Operacion del condominio</p>
            <h3 class="section-intro__title">Horarios, reglamento y personal operativo</h3>
        </div>
        <p class="section-intro__note">Aqui se guardan las ventanas operativas del condominio, el reglamento en PDF y los datos de limpieza y vigilancia.</p>
    </div>

    <section class="content-grid content-grid--settings-operations">
        <article class="panel panel--settings-operations-main">
            <div class="panel__header">
                <h3>Horarios y reglamento</h3>
                <span>Operacion</span>
            </div>
            @if ($canManage)
                @if ($errors->settingsOperations->any())
                    <div class="alert alert--error">{{ $errors->settingsOperations->first() }}</div>
                @endif
                <form class="form-grid form-grid--settings-ops" method="POST" action="{{ route('settings.operations.update') }}" enctype="multipart/form-data" autocomplete="off">
                    @csrf
                    <div class="form-block-title field--full">
                        <span>Ventanas operativas</span>
                        <small>Establece horarios autorizados para mudanzas, trabajos y reuniones.</small>
                    </div>
                    <label class="field">
                        <span>Horario para mudanza</span>
                        <input type="text" name="moving_hours" value="{{ old('moving_hours', '') }}" placeholder="Ej. Lunes a viernes de 09:00 a 18:00">
                    </label>
                    <label class="field">
                        <span>Horario para realizar trabajos</span>
                        <input type="text" name="work_hours" value="{{ old('work_hours', '') }}" placeholder="Ej. Sabado de 10:00 a 14:00">
                    </label>
                    <label class="field field--full">
                        <span>Horario para reuniones</span>
                        <input type="text" name="meeting_hours" value="{{ old('meeting_hours', '') }}" placeholder="Ej. Hasta las 23:00 hrs">
                    </label>
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
                    <div class="form-actions">
                        <button class="button button--primary" type="submit">Guardar operación</button>
                    </div>
                </form>
            @else
                <div class="summary-list">
                    <div class="summary-list__row">
                        <span>Horario para mudanza</span>
                        <strong>{{ $operations['moving_hours'] ?: 'Sin configurar' }}</strong>
                    </div>
                    <div class="summary-list__row">
                        <span>Horario para trabajos</span>
                        <strong>{{ $operations['work_hours'] ?: 'Sin configurar' }}</strong>
                    </div>
                    <div class="summary-list__row">
                        <span>Horario para reuniones</span>
                        <strong>{{ $operations['meeting_hours'] ?: 'Sin configurar' }}</strong>
                    </div>
                    <div class="summary-list__row">
                        <span>Reglamento</span>
                        <strong>{{ $operations['regulations_path'] ? 'PDF cargado' : 'Sin reglamento' }}</strong>
                    </div>
                </div>
            @endif
        </article>

        <article class="panel panel--settings-operations-staff">
            <div class="panel__header">
                <h3>Personal operativo</h3>
                <span>Contacto</span>
            </div>
            @if ($canManage)
                <form class="form-grid form-grid--settings-staff" method="POST" action="{{ route('settings.operations.update') }}" enctype="multipart/form-data" autocomplete="off">
                    @csrf
                    <div class="form-block-title field--full">
                        <span>Limpieza y vigilancia</span>
                        <small>Guarda aquí responsables, teléfonos y referencias de contacto para operación diaria.</small>
                    </div>
                    <label class="field">
                        <span>Personal de limpieza</span>
                        <input type="text" name="cleaning_staff_name" value="{{ old('cleaning_staff_name', '') }}">
                    </label>
                    <label class="field">
                        <span>Telefono de limpieza</span>
                        <input type="text" name="cleaning_staff_phone" value="{{ old('cleaning_staff_phone', '') }}">
                    </label>
                    <label class="field field--full">
                        <span>Datos de contacto de limpieza</span>
                        <input type="text" name="cleaning_staff_contact" value="{{ old('cleaning_staff_contact', '') }}" placeholder="Empresa, correo o referencia">
                    </label>
                    <label class="field">
                        <span>Personal de vigilancia</span>
                        <input type="text" name="security_staff_name" value="{{ old('security_staff_name', '') }}">
                    </label>
                    <label class="field">
                        <span>Telefono de vigilancia</span>
                        <input type="text" name="security_staff_phone" value="{{ old('security_staff_phone', '') }}">
                    </label>
                    <label class="field field--full">
                        <span>Datos de contacto de vigilancia</span>
                        <input type="text" name="security_staff_contact" value="{{ old('security_staff_contact', '') }}" placeholder="Empresa, correo o referencia">
                    </label>
                    <div class="form-actions">
                        <button class="button button--primary" type="submit">Guardar personal</button>
                    </div>
                </form>
            @else
                <div class="summary-list">
                    <div class="summary-list__row">
                        <span>Limpieza</span>
                        <strong>{{ $operations['cleaning_staff_name'] ?: 'Sin configurar' }}</strong>
                        <small>{{ $operations['cleaning_staff_phone'] ?: 'Sin teléfono' }}{{ $operations['cleaning_staff_contact'] ? ' | '.$operations['cleaning_staff_contact'] : '' }}</small>
                    </div>
                    <div class="summary-list__row">
                        <span>Vigilancia</span>
                        <strong>{{ $operations['security_staff_name'] ?: 'Sin configurar' }}</strong>
                        <small>{{ $operations['security_staff_phone'] ?: 'Sin teléfono' }}{{ $operations['security_staff_contact'] ? ' | '.$operations['security_staff_contact'] : '' }}</small>
                    </div>
                </div>
            @endif
        </article>
    </section>
</section>

<section class="section-stack">
    <div class="section-intro">
        <div>
            <p class="section-intro__eyebrow">Depositos y permisos</p>
            <h3 class="section-intro__title">Cuenta bancaria y nivel de acceso</h3>
        </div>
        <p class="section-intro__note">Este bloque reune la cuenta de deposito para cuotas de mantenimiento y el nivel de acceso del usuario actual.</p>
    </div>

    <section class="content-grid content-grid--settings-bottom">
        <article class="panel panel--settings-banking">
            <div class="panel__header">
                <h3>Datos de la cuenta para depositar las cuotas de mantenimiento</h3>
                <span>Finanzas</span>
            </div>
            @if ($canManage)
                @if ($errors->settingsBanking->any())
                    <div class="alert alert--error">{{ $errors->settingsBanking->first() }}</div>
                @endif
                <form class="form-grid form-grid--settings-banking" method="POST" action="{{ route('settings.banking.update') }}" autocomplete="off">
                    @csrf
                    <div class="form-block-title field--full">
                        <span>Cuenta receptora</span>
                        <small>Estos datos se muestran en reportes, recordatorios y estados de cuenta.</small>
                    </div>
                    <label class="field">
                        <span>Institucion bancaria</span>
                        <input type="text" name="bank" value="{{ old('bank', '') }}" autocomplete="off">
                    </label>
                    <label class="field">
                        <span>Titular de la cuenta</span>
                        <input type="text" name="account_holder" value="{{ old('account_holder', '') }}" autocomplete="off">
                    </label>
                    <label class="field">
                        <span>Numero de cuenta</span>
                        <input type="text" name="account_number" value="{{ old('account_number', '') }}" autocomplete="off">
                    </label>
                    <label class="field">
                        <span>CLABE</span>
                        <input type="text" name="clabe" value="{{ old('clabe', '') }}" autocomplete="off">
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
                        <span>Numero de cuenta</span>
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
            <p>{{ $canManage ? 'Tu cuenta tiene permisos para crear, leer, actualizar y borrar informacion del portal.' : 'Tu cuenta tiene permisos de lectura y descarga de PDFs.' }}</p>
            <div class="role-chip role-chip--light">{{ $canManage ? 'Administrador' : 'Usuario' }}</div>
        </article>
    </section>
</section>

@if ($canManage)
    <section class="section-stack">
        <div class="section-intro">
            <div>
                <p class="section-intro__eyebrow">Gestion de accesos</p>
                <h3 class="section-intro__title">Usuarios del portal y edicion puntual</h3>
            </div>
            <p class="section-intro__note">El formulario queda limpio para crear una cuenta nueva. Los datos solo aparecen cuando entras en modo edicion.</p>
        </div>

        <section class="panel">
            <div class="panel__header">
                <h3>Usuarios del Portal</h3>
                <span class="badge badge--neutral">Gestion de accesos</span>
            </div>

            @if ($selectedUser)
                <div class="user-summary-card">
                    <div class="panel__header panel__header--tight">
                        <h3>Resumen del usuario en edicion</h3>
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
                                <div class="summary-list__row">
                                    <span>Titular de la cuenta</span>
                                    <strong>{{ $banking['holder'] ?: 'Sin configurar' }}</strong>
                                </div>
                                <div class="summary-list__row">
                                    <span>CLABE</span>
                                    <strong>{{ $banking['clabe'] ?: 'Sin configurar' }}</strong>
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
                @endphp

                <label class="field">
                    <span>Nombre completo</span>
                    <input type="text" name="name" value="{{ $userFormName }}" autocomplete="off" required>
                </label>
                <label class="field">
                    <span>Correo electronico</span>
                    <input type="email" name="email" value="{{ $userFormEmail }}" autocomplete="off" required>
                </label>
                <label class="field">
                    <span>Telefono</span>
                    <input type="text" name="phone" value="{{ $userFormPhone }}" autocomplete="off" required>
                </label>
                <label class="field">
                    <span>Rol</span>
                    <select name="role" class="select-field" required>
                        @foreach ($roleOptions as $roleKey => $roleLabel)
                            <option value="{{ $roleKey }}" @selected($userFormRole === $roleKey)>{{ $roleLabel }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field">
                    <span>{{ $editingUser ? 'Nueva contrasena (opcional)' : 'Contrasena' }}</span>
                    <input type="password" name="password" autocomplete="new-password" {{ $editingUser ? '' : 'required' }}>
                </label>
                <label class="field">
                    <span>Confirmar contrasena</span>
                    <input type="password" name="password_confirmation" autocomplete="new-password" {{ $editingUser ? '' : 'required' }}>
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
                        <p>Las cuentas del portal apareceran aqui conforme se den de alta.</p>
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
    </section>
@endif

@if ($canManage)
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const geoButton = document.querySelector('[data-fill-geolocation]');
            const latInput = document.querySelector('[data-geo-lat]');
            const lngInput = document.querySelector('[data-geo-lng]');
            const addressInput = document.querySelector('[data-geo-address]');
            const status = document.querySelector('[data-geo-status]');

            if (!geoButton || !latInput || !lngInput || !addressInput || !status) {
                return;
            }

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

            geoButton.addEventListener('click', () => {
                if (!navigator.geolocation) {
                    status.textContent = 'Tu navegador no permite geolocalizacion.';
                    return;
                }

                status.textContent = 'Obteniendo ubicacion...';

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
                                status.textContent = `Ubicacion capturada: ${resolvedAddress}`;
                                return;
                            }
                        } catch (error) {
                            // Si la geocodificacion falla, dejamos una referencia util en el campo.
                        }

                        addressInput.value = `${latInput.value}, ${lngInput.value}`;
                        status.textContent = `Ubicacion capturada: ${addressInput.value}`;
                    },
                    () => {
                        status.textContent = 'No fue posible obtener la ubicacion. Puedes escribir la direccion manualmente.';
                    }
                );
            });
        });
    </script>
@endif
