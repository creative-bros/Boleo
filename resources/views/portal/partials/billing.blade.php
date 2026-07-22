@php
    $hasBillingSearchContext = filled(request('q')) || filled(request('condominium')) || request()->has('unit') || request()->has('account');
    $hasBillingResidentSearchContext = filled(request('q')) || request()->has('unit') || request()->has('account');
@endphp

<section class="section-stack">
    <div class="section-intro">
        <div>
            <p class="section-intro__eyebrow">Consulta de cobranza</p>
            <h3 class="section-intro__title">Busqueda de cuenta y reportes</h3>
        </div>
        <p class="section-intro__note">Primero localiza el condominio o la unidad, después usa los reportes y finalmente revisa el detalle del residente.</p>
    </div>

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
            <div class="form-actions">
                <button class="button button--ghost" type="submit">Buscar</button>
            </div>
        </form>

        @if ($hasBillingSearchContext)
            @php
                $quickAccountRouteParams = array_filter([
                    'unit' => $selectedUnitId,
                    'account' => $selectedImportedAccount?->id,
                    'q' => request('q'),
                    'condominium' => $condominiumQuery,
                    'receipt_year' => $receiptYear,
                ], fn ($value) => filled($value));
            @endphp
            <div class="billing-search-result">
                @if (blank($account['name']))
                    <div>
                        <span class="billing-search-result__eyebrow">Sin resultado seleccionado</span>
                        <strong>No encontramos información de ese residente o departamento.</strong>
                        <p>Revisa el nombre, departamento o condominio y vuelve a buscar.</p>
                    </div>
                @else
                    <div class="billing-search-result__main">
                        <div class="avatar">{{ substr($account['name'], 0, 1) }}</div>
                        <div>
                            <span class="billing-search-result__eyebrow">Resultado encontrado</span>
                            <strong>{{ $account['name'] }}</strong>
                            <p>{{ $account['location'] ?: 'Sin departamento vinculado' }}{{ $account['email'] ? ' | '.$account['email'] : ' | Sin correo vinculado' }}</p>
                        </div>
                    </div>
                    <div class="billing-search-result__stats">
                        <span>
                            <small>Saldo</small>
                            <strong>{{ $account['balance'] }}</strong>
                        </span>
                        <span>
                            <small>Estatus</small>
                            <strong>{{ $account['status'] }}</strong>
                        </span>
                        <span>
                            <small>Pagado</small>
                            <strong>{{ $account['paid'] }}</strong>
                        </span>
                    </div>
                    <div class="billing-search-result__actions">
                        <a class="button button--primary button--small" href="{{ route('billing', $quickAccountRouteParams) }}#estado-cuenta-residente">Ver cuenta</a>
                        @if ($selectedUnitId)
                            <a class="button button--ghost button--small" href="{{ route('billing', $quickAccountRouteParams) }}#recibos-condomino">Recibos</a>
                        @endif
                        @if ($selectedImportedAccount)
                            <a class="button button--ghost button--small" href="{{ route('billing.letters.show', $selectedImportedAccount) }}">Carta</a>
                        @elseif ($selectedUnitId)
                            <a class="button button--ghost button--small" href="{{ route('billing.letters.unit', array_filter(['unit' => $selectedUnitId, 'month' => request('month')], fn ($value) => filled($value))) }}">Carta</a>
                        @endif
                    </div>
                    @if ($hasBillingResidentSearchContext && ! empty($reportCommands))
                        <div class="billing-search-result__actions billing-search-result__actions--reports">
                            @foreach ($reportCommands as $command)
                                <a class="button button--ghost button--small" href="{{ $command['href'] }}">
                                    {{ $command['label'] }}
                                </a>
                            @endforeach
                        </div>
                    @endif
                @endif
            </div>
        @endif
    </section>

    <section class="content-grid content-grid--settings-bottom">
        <article class="panel compact-panel">
            <h3>Pagos visibles del residente</h3>
            <p>Cuando un residente paga algo, aquí se refleja su movimiento reciente para seguimiento del administrador.</p>
            <p>También se mantiene visible dentro del historial de transacciones y en el recibo PDF.</p>
        </article>
    </section>

    @if ($showBillingConfigurationInFinance ?? false)
        <section class="panel">
            <div class="panel__header">
                <h3>Base histórica y cartas</h3>
                <span>{{ $importedAccountsCount }} cuenta(s) importada(s)</span>
            </div>
            <div class="content-grid content-grid--settings-bottom">
                <form class="form-grid" method="POST" action="{{ route('billing.import-base') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="form-block-title field--full">
                        <span>Importar base de adeudos</span>
                        <small>Sube aquí la base completa. Boleo guardará el archivo aunque no coincidan columnas o formato.</small>
                        <small>Si el archivo se puede leer como Excel, también se convertirá en tabla editable para llevar el control.</small>
                    </div>
                    <label class="field field--full">
                        <span>Archivo de base</span>
                        <input type="file" name="base_file" required>
                    </label>
                    <div class="form-actions">
                        <button class="button button--primary" type="submit">Importar base</button>
                    </div>
                </form>

                <form class="form-grid" method="POST" action="{{ route('billing.letter-templates.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="form-block-title field--full">
                        <span>Plantillas para reportes</span>
                        <small>Usa adeudo para Reporte de deudores y no adeudo para Reporte de no adeudores.</small>
                        <small>Precarga una plantilla Word (.docx) o PDF. Boleo descargará la carta final en PDF con los datos del departamento.</small>
                    </div>
                    <label class="field">
                        <span>Plantilla reporte de deudores (adeudo)</span>
                        <input type="file" name="debt_letter_template" accept="application/pdf,.docx">
                        <small>{{ $letterTemplates['debt_custom'] ? 'Plantilla cargada' : ($letterTemplates['debt'] ? 'Plantilla base incluida' : 'Sin plantilla cargada') }}</small>
                    </label>
                    <label class="field">
                        <span>Plantilla reporte de no adeudores</span>
                        <input type="file" name="no_debt_letter_template" accept="application/pdf,.docx">
                        <small>{{ $letterTemplates['no_debt_custom'] ? 'Plantilla cargada' : ($letterTemplates['no_debt'] ? 'Plantilla base incluida' : 'Sin plantilla cargada') }}</small>
                    </label>
                    <label class="field">
                        <span>Firma para reportes</span>
                        <input type="file" name="report_signature" accept="image/png,image/jpeg">
                        <small>{{ $letterTemplates['signature_custom'] ? 'Firma cargada' : 'Firma base incluida' }}</small>
                    </label>
                    <div class="form-actions">
                        <button class="button button--ghost" type="submit">Guardar plantillas</button>
                    </div>
                </form>
            </div>

            <div class="table-wrap">
                <div class="panel__header panel__header--subtle">
                    <h3>Cartas del condominio</h3>
                    <span>{{ $condominiumLetterStats['total'] }} carta(s) disponible(s)</span>
                </div>
                <div class="billing-search-result__actions billing-search-result__actions--reports">
                    <a class="button button--primary button--small" href="{{ route('billing.letters.bulk', array_filter(['month' => request('month')], fn ($value) => filled($value))) }}">
                        Descargar todas
                    </a>
                    <a class="button button--ghost button--small" href="{{ route('billing.letters.bulk', array_filter(['status' => 'adeudo', 'month' => request('month')], fn ($value) => filled($value))) }}">
                        Solo adeudo ({{ $condominiumLetterStats['debt'] }})
                    </a>
                    <a class="button button--ghost button--small" href="{{ route('billing.letters.bulk', array_filter(['status' => 'no_adeudo', 'month' => request('month')], fn ($value) => filled($value))) }}">
                        Solo no adeudo ({{ $condominiumLetterStats['no_debt'] }})
                    </a>
                </div>
                @if ($condominiumLetterRows->isEmpty())
                    <div class="empty-state">
                        <strong>No hay cartas para generar</strong>
                        <p>Registra departamentos o importa la base de cobranza para habilitar las cartas del condominio.</p>
                    </div>
                @else
                    <table>
                        <thead>
                            <tr>
                                <th>Departamento</th>
                                <th>Residente</th>
                                <th>Saldo</th>
                                <th>Estatus</th>
                                <th>Carta</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($condominiumLetterRows as $letterRow)
                                <tr>
                                    <td>{{ $letterRow['unit'] }}</td>
                                    <td>{{ $letterRow['name'] }}</td>
                                    <td>{{ $letterRow['balance'] }}</td>
                                    <td>{{ $letterRow['status'] }}</td>
                                    <td>
                                        <a class="button button--ghost button--small" href="{{ $letterRow['href'] }}">
                                            Generar carta
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if ($condominiumLetterStats['total'] > $condominiumLetterRows->count())
                        <p class="table-sub">Se muestran los primeros {{ $condominiumLetterRows->count() }} departamentos. La descarga masiva incluye todas las cartas disponibles.</p>
                    @endif
                @endif
            </div>

            <div class="table-wrap">
                <div class="panel__header panel__header--subtle">
                    <h3>Bases importadas</h3>
                    <span>Historial del condominio</span>
                </div>
                @if ($billingBaseImports->isEmpty())
                    <div class="empty-state">
                        <strong>No hay bases cargadas</strong>
                        <p>Cuando subas el Excel, quedará guardado aquí para consulta y descarga.</p>
                    </div>
                @else
                    <table>
                        <thead>
                            <tr>
                                <th>Archivo</th>
                                <th>Fecha</th>
                                <th>Registros</th>
                                <th>Estatus</th>
                                <th>Descarga</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($billingBaseImports as $baseImport)
                                <tr>
                                    <td>{{ $baseImport->original_name }}</td>
                                    <td>{{ optional($baseImport->imported_at)->format('d/m/Y H:i') }}</td>
                                    <td>{{ $baseImport->imported_rows }}</td>
                                    <td>
                                        <span class="badge badge--neutral">{{ ucfirst($baseImport->status) }}</span>
                                        @if ($activeBaseImport?->id === $baseImport->id)
                                            <small class="table-sub">Base activa en pantalla</small>
                                        @endif
                                        @if ($baseImport->notes)
                                            <small class="table-sub">{{ $baseImport->notes }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <a class="button button--ghost button--small" href="{{ route('billing', ['base_import' => $baseImport->id]) }}">
                                            Ver tabla
                                        </a>
                                        @if ($baseImport->stored_path)
                                            <a class="button button--ghost button--small" href="{{ route('billing.import-base.download', $baseImport) }}">
                                                Descargar Excel
                                            </a>
                                        @else
                                            <span class="table-sub">Creada en Boleo</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            <div class="table-wrap table-wrap--excel">
                <div class="panel__header panel__header--subtle">
                    <h3>Excel editable en pantalla</h3>
                    <span>{{ $activeBaseImport?->original_name ?? 'Sin base activa' }} | {{ $importedAccountsGrid->count() }} renglones | {{ count($billingBaseGridHeaders) }} columnas</span>
                </div>
                @if ($importedAccountsGrid->isEmpty())
                    <div class="empty-state">
                        <strong>No hay datos para mostrar</strong>
                        <p>Sube el Excel completo para visualizar la base dentro de Boleo con sus columnas originales.</p>
                    </div>
                @else
                    <div class="excel-scroll">
                        <table class="excel-table">
                            <thead>
                                <tr>
                                    <th>Acciones</th>
                                    @foreach ($billingBaseGridHeaders as $header)
                                        <th>{{ $header }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($importedAccountsGrid as $importedAccount)
                                    @php
                                        $rowFormId = 'billing-excel-row-'.$importedAccount->id;
                                        $deleteFormId = 'billing-excel-delete-'.$importedAccount->id;
                                        $rowPayload = $importedAccount->raw_payload ?? [];
                                    @endphp
                                    <tr>
                                        <td class="excel-table__actions">
                                            <form id="{{ $rowFormId }}" method="POST" action="{{ route('billing.imported-accounts.update', $importedAccount) }}">
                                                @csrf
                                                @method('PUT')
                                            </form>
                                            <form id="{{ $deleteFormId }}" method="POST" action="{{ route('billing.imported-accounts.delete', $importedAccount) }}">
                                                @csrf
                                                @method('DELETE')
                                            </form>
                                            <button class="button button--primary button--small" type="submit" form="{{ $rowFormId }}">
                                                Guardar
                                            </button>
                                            <a class="button button--ghost button--small" href="{{ route('billing.letters.show', $importedAccount) }}">
                                                Carta
                                            </a>
                                            <button class="button button--ghost button--small" type="submit" form="{{ $deleteFormId }}">
                                                Eliminar
                                            </button>
                                        </td>
                                        @foreach ($billingBaseGridHeaders as $header)
                                            @php
                                                $cellValue = $rowPayload[$header] ?? '';
                                                $normalizedHeader = mb_strtoupper($header, 'UTF-8');
                                                $isLongCell = str_contains($normalizedHeader, 'OBSERVACIONES') || mb_strlen((string) $cellValue, 'UTF-8') > 80;
                                            @endphp
                                            <td>
                                                @if ($isLongCell)
                                                    <textarea class="excel-input excel-input--textarea" name="payload[{{ $header }}]" rows="2" form="{{ $rowFormId }}">{{ $cellValue }}</textarea>
                                                @else
                                                    <input class="excel-input" type="text" name="payload[{{ $header }}]" value="{{ $cellValue }}" form="{{ $rowFormId }}">
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="table-sub">Edita directamente las celdas y usa Guardar en el renglón correspondiente. El Excel original completo permanece disponible en “Descargar Excel”.</p>
                @endif
            </div>

            @php
                $editingPayload = old('payload', $editingImportedAccount?->raw_payload ?? []);
            @endphp
            <form class="form-grid" method="POST" action="{{ $editingImportedAccount ? route('billing.imported-accounts.update', $editingImportedAccount) : route('billing.imported-accounts.store') }}">
                @csrf
                @if ($editingImportedAccount)
                    @method('PUT')
                @endif
                <div class="form-block-title field--full">
                    <span>{{ $editingImportedAccount ? 'Editar registro de la base' : 'Crear registro nuevo' }}</span>
                    <small>Captura o modifica la cuenta sin depender del Excel. Los campos completos respetan la estructura de la base importada.</small>
                </div>
                <div class="form-block-title field--full">
                    <span>Agregar adeudo por periodo</span>
                    <small>Captura año, periodo y monto para guardar el importe en la columna del Excel correspondiente.</small>
                </div>
                <label class="field">
                    <span>Año</span>
                    <input type="number" min="2017" max="2100" name="period_year" value="{{ old('period_year') }}" placeholder="2026">
                </label>
                <label class="field">
                    <span>Periodo</span>
                    <select class="select-field" name="period_month">
                        <option value="">Selecciona</option>
                        @foreach (range(1, 12) as $month)
                            <option value="{{ $month }}" @selected((string) old('period_month') === (string) $month)>{{ str_pad((string) $month, 2, '0', STR_PAD_LEFT) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field">
                    <span>Monto</span>
                    <input type="number" step="0.01" min="0" name="period_amount" value="{{ old('period_amount') }}" placeholder="0.00">
                </label>
                <label class="checkbox field--full">
                    <input type="checkbox" name="period_closes_year" value="1" @checked(old('period_closes_year'))>
                    <span>Cierre de año</span>
                </label>
                @foreach ($billingBaseKeyFields as $field)
                    <label class="field {{ str_contains($field, 'OBSERVACIONES') ? 'field--full' : '' }}">
                        <span>{{ $field }}</span>
                        @if (str_contains($field, 'OBSERVACIONES'))
                            <textarea name="payload[{{ $field }}]" rows="3">{{ $editingPayload[$field] ?? '' }}</textarea>
                        @else
                            <input type="{{ $field === 'TOTAL ADEUDO' ? 'number' : 'text' }}" step="0.01" name="payload[{{ $field }}]" value="{{ $editingPayload[$field] ?? '' }}" @required($field === 'DEPT')>
                        @endif
                    </label>
                @endforeach
                <details class="field field--full">
                    <summary>Ver campos completos del Excel</summary>
                    <div class="form-grid form-grid--nested">
                        @foreach ($billingBaseExtraFields as $field)
                            <label class="field">
                                <span>{{ $field }}</span>
                                <input type="text" name="payload[{{ $field }}]" value="{{ $editingPayload[$field] ?? '' }}">
                            </label>
                        @endforeach
                    </div>
                </details>
                <div class="form-actions">
                    @if ($editingImportedAccount)
                        <a class="button button--ghost" href="{{ route('billing') }}">Cancelar edición</a>
                    @endif
                    <button class="button button--primary" type="submit">{{ $editingImportedAccount ? 'Actualizar registro' : 'Crear registro' }}</button>
                </div>
            </form>

            <div class="table-wrap">
                <div class="panel__header panel__header--subtle">
                    <h3>Registro importado en Boleo</h3>
                    <span>Primeros {{ $importedAccountsPreview->count() }} registros</span>
                </div>
                @if ($importedAccountsPreview->isEmpty())
                    <div class="empty-state">
                        <strong>No hay saldos importados</strong>
                        <p>Al cargar la base, aquí verás las unidades con su saldo y estado de cobranza.</p>
                    </div>
                @else
                    <table>
                        <thead>
                            <tr>
                                <th>Unidad</th>
                                <th>Torre</th>
                                <th>Residente</th>
                                <th>Saldo</th>
                                <th>Estado</th>
                                <th>Carta</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($importedAccountsPreview as $importedAccount)
                                <tr>
                                    <td>{{ $importedAccount->unit_number }}</td>
                                    <td>{{ $importedAccount->tower ?: 'Sin torre' }}</td>
                                    <td>{{ $importedAccount->owner_name }}</td>
                                    <td>${{ number_format((float) $importedAccount->total_debt, 2) }}</td>
                                    <td>
                                        <span class="badge {{ $importedAccount->status === 'adeudo' ? 'badge--warning' : 'badge--success' }}">
                                            {{ $importedAccount->status === 'adeudo' ? 'Adeudo' : 'No adeudo' }}
                                        </span>
                                    </td>
                                    <td>
                                        <a class="button button--ghost button--small" href="{{ route('billing.letters.show', $importedAccount) }}">
                                            Generar carta
                                        </a>
                                    </td>
                                    <td>
                                        <a class="button button--ghost button--small" href="{{ route('billing', ['edit_base_account' => $importedAccount->id]) }}">
                                            Editar
                                        </a>
                                        <form method="POST" action="{{ route('billing.imported-accounts.delete', $importedAccount) }}" class="inline-form">
                                            @csrf
                                            @method('DELETE')
                                            <button class="button button--ghost button--small" type="submit">Eliminar</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </section>
    @endif
</section>

<section class="section-stack" id="estado-cuenta-residente">
    <div class="section-intro">
        <div>
            <p class="section-intro__eyebrow">Estado de cuenta</p>
            <h3 class="section-intro__title">Perfil del residente y saldo del periodo</h3>
        </div>
        <p class="section-intro__note">Aquí se concentra la información del residente seleccionado, cuánto debe pagar, cuánto ha pagado y su saldo pendiente.</p>
    </div>

    <section class="content-grid content-grid--billing billing-account-grid">
        <article class="panel billing-results-panel">
            <div class="panel__header">
                <h3>Resultados Recientes</h3>
                <span>{{ count($residents) }}</span>
            </div>
            @if (empty($residents))
                <div class="empty-state">
                    <strong>No hay cuentas cargadas</strong>
                    <p>Los residentes con movimientos de cobranza aparecerán aquí cuando se registren.</p>
                </div>
            @else
                <div class="resident-list">
                    @foreach ($residents as $resident)
                        @php
                            $residentRouteParams = array_filter([
                                'unit' => $resident['unit_id'] ?? null,
                                'account' => $resident['account_id'] ?? null,
                                'q' => request('q'),
                                'condominium' => $condominiumQuery,
                                'receipt_year' => $receiptYear,
                            ], fn ($value) => filled($value));
                        @endphp
                        <div class="resident-card resident-card--link resident-card--actions">
                            <div class="avatar">{{ substr($resident['name'], 0, 1) }}</div>
                            <div>
                                <strong>{{ $resident['name'] }}</strong>
                                <p>{{ $resident['unit'] }}</p>
                                <p>{{ $resident['email'] ?: 'Sin correo vinculado' }}</p>
                                <p>Pagado: {{ $resident['paid'] }}</p>
                                <p>Recibos: {{ $resident['receipt_meta'] }}</p>
                                <span class="badge {{ $resident['status'] === 'Deudor' ? 'badge--warning' : 'badge--success' }}">{{ $resident['status'] }}</span>
                            </div>
                            <div class="resident-card__meta">
                                <strong>{{ $resident['balance'] }}</strong>
                                <span class="table-sub">{{ $resident['last_payment'] }}</span>
                                <span class="table-sub">Pendiente recibos: {{ $resident['receipt_balance'] }}</span>
                                <div class="resident-card__actions">
                                    <a class="button button--ghost button--small" href="{{ route('billing', $residentRouteParams) }}">
                                        Cuenta
                                    </a>
                                    @if ($resident['unit_id'])
                                        <a class="button button--primary button--small" href="{{ route('billing', $residentRouteParams) }}#recibos-condomino">
                                            Recibos
                                        </a>
                                        <a class="button button--ghost button--small" href="{{ route('billing.letters.unit', array_filter(['unit' => $resident['unit_id'], 'account' => $resident['account_id'] ?? null, 'month' => request('month')], fn ($value) => filled($value))) }}">
                                            Carta
                                        </a>
                                    @elseif ($resident['account_id'])
                                        <a class="button button--primary button--small" href="{{ route('billing.letters.show', $resident['account_id']) }}">
                                            Carta
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </article>

        <article class="panel billing-detail-panel">
            @if (blank($account['name']))
                <div class="empty-state empty-state--large">
                    <strong>No hay perfil de cobranza seleccionado</strong>
                    <p>Cuando exista información de una cuenta, aquí verán el detalle de pagos y datos del residente.</p>
                </div>
            @else
                <div class="billing-profile billing-profile--featured">
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
                        <span>Último pago</span>
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
                <div class="billing-reminder billing-inline-note">
                    <strong>Recordatorio de pago</strong>
                    <p>La cuota total de esta unidad se paga cada mes. Aquí puedes revisar el monto mensual, lo abonado en el periodo y el saldo pendiente.</p>
                </div>
                @if ($selectedImportedAccount)
                    <div class="billing-import-summary">
                        <strong>Base histórica importada</strong>
                        <p>Saldo detectado: ${{ number_format((float) $selectedImportedAccount->total_debt, 2) }} | {{ $selectedImportedAccount->status === 'adeudo' ? 'Carta de adeudo' : 'Carta de no adeudo' }}</p>
                        <a class="button button--primary" href="{{ route('billing.letters.show', $selectedImportedAccount) }}">Generar carta</a>
                    </div>
                @endif
            @endif
        </article>

        <article class="panel panel--primary compact-panel billing-actions-panel">
            <div class="billing-actions-panel__summary">
                <span>Estado de Cuenta</span>
                <strong>{{ $account['balance'] }}</strong>
                <p>Saldo pendiente del periodo | {{ $account['status'] }}</p>
            </div>
            <small>{{ $debtorsCount }} unidad(es) con saldo pendiente este mes.</small>
        </article>
    </section>
</section>

<section class="section-stack" id="recibos-condomino">
    <div class="section-intro">
        <div>
            <p class="section-intro__eyebrow">Recibos por condomino</p>
            <h3 class="section-intro__title">{{ $usesImportedStatement ? 'Estado importado del Excel' : 'Pagados, parciales y pendientes' }}</h3>
        </div>
        <p class="section-intro__note">{{ $usesImportedStatement ? 'La tabla viene de la base importada del residente. Exigible es lo que paga de mantenimiento cada mes.' : 'Cada recibo guarda mes, año, cantidad a pagar, abonos y notas. El estatus se calcula con base en lo abonado.' }}</p>
    </div>

    <section class="panel">
        <div class="panel__header">
            <h3>{{ $account['name'] ? 'Recibos de '.$account['name'] : 'Recibos del condomino' }}</h3>
            <span>Pendiente: ${{ number_format((float) $receiptSummary['pending_amount'], 2) }}</span>
        </div>

        @if (blank($account['name']))
            <div class="empty-state">
                <strong>No hay condomino seleccionado</strong>
                <p>Selecciona un dueño desde la lista para ver su estado importado o administrar recibos.</p>
            </div>
        @elseif ($usesImportedStatement)
            <div class="mini-stats mini-stats--five">
                <div class="mini-stat">
                    <span>Total registros</span>
                    <strong>{{ $receiptSummary['total'] }}</strong>
                </div>
                <div class="mini-stat">
                    <span>Pagados</span>
                    <strong>{{ $receiptSummary['paid_count'] }}</strong>
                </div>
                <div class="mini-stat">
                    <span>Parciales</span>
                    <strong>{{ $receiptSummary['partial_count'] }}</strong>
                </div>
                <div class="mini-stat">
                    <span>Pendientes</span>
                    <strong>{{ $receiptSummary['pending_count'] }}</strong>
                </div>
                <div class="mini-stat">
                    <span>Adeudo</span>
                    <strong>${{ number_format((float) $receiptSummary['pending_amount'], 2) }}</strong>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>ESTATUS</th>
                            <th>EXIGIBLE</th>
                            <th>PAGADO</th>
                            <th>ADEUDO</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($excelStatementRows as $row)
                            <tr>
                                <td>{{ $row['name'] }}</td>
                                <td>{{ $row['status'] }}</td>
                                <td>{{ $row['exigible'] }}</td>
                                <td>{{ $row['paid'] }}</td>
                                <td>{{ $row['debt'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @elseif (! $selectedUnitId)
            <div class="empty-state">
                <strong>Cuenta de base histórica sin unidad vinculada</strong>
                <p>La información de cobranza está disponible arriba. Vincula el registro a una unidad para administrar recibos mensuales.</p>
            </div>
        @else
            <div class="mini-stats mini-stats--five">
                <div class="mini-stat">
                    <span>Total recibos</span>
                    <strong>{{ $receiptSummary['total'] }}</strong>
                </div>
                <div class="mini-stat">
                    <span>Pagados</span>
                    <strong>{{ $receiptSummary['paid_count'] }}</strong>
                </div>
                <div class="mini-stat">
                    <span>Parciales</span>
                    <strong>{{ $receiptSummary['partial_count'] }}</strong>
                </div>
                <div class="mini-stat">
                    <span>Pendientes</span>
                    <strong>{{ $receiptSummary['pending_count'] }}</strong>
                </div>
                <div class="mini-stat">
                    <span>Saldo recibos</span>
                    <strong>${{ number_format((float) $receiptSummary['pending_amount'], 2) }}</strong>
                </div>
            </div>

            <div class="billing-receipts-toolbar">
                <form class="form-grid form-grid--inline" method="GET" action="{{ route('billing') }}">
                    <input type="hidden" name="unit" value="{{ $selectedUnitId }}">
                    <input type="hidden" name="q" value="{{ request('q') }}">
                    <input type="hidden" name="condominium" value="{{ $condominiumQuery }}">
                    <label class="field">
                        <span>Año</span>
                        <select class="select-field" name="receipt_year">
                            @foreach ($receiptYears as $year)
                                <option value="{{ $year }}" @selected((string) $receiptYear === (string) $year)>{{ $year }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div class="form-actions">
                        <button class="button button--ghost" type="submit">Ver año</button>
                    </div>
                </form>

                @if ($selectedImportedAccount)
                    <div class="billing-import-summary billing-import-summary--compact">
                        <strong>Cartas del condomino</strong>
                        <p>Disponibles desde la base histórica vinculada a esta unidad.</p>
                        <div class="table-actions">
                            <a class="button button--ghost button--small" href="{{ route('billing.letters.show', ['account' => $selectedImportedAccount, 'template' => 'adeudo']) }}">Carta adeudo</a>
                            <a class="button button--ghost button--small" href="{{ route('billing.letters.show', ['account' => $selectedImportedAccount, 'template' => 'no_adeudo']) }}">Carta no adeudo</a>
                        </div>
                    </div>
                @endif
            </div>

            @if ($canManage)
                <form class="form-grid" method="POST" action="{{ route('billing.receipts.store') }}">
                    @csrf
                    <input type="hidden" name="unit_id" value="{{ $selectedUnitId }}">
                    <div class="form-block-title field--full">
                        <span>Agregar o actualizar recibo</span>
                        <small>Si ya existe un recibo para ese mes y año, Boleo actualizará el monto, abono y notas.</small>
                    </div>
                    <label class="field">
                        <span>Año</span>
                        <input type="number" min="2017" max="2100" name="period_year" value="{{ old('period_year', $receiptYear) }}" required>
                    </label>
                    <label class="field">
                        <span>Mes</span>
                        <select class="select-field" name="period_month" required>
                            @foreach (range(1, 12) as $month)
                                <option value="{{ $month }}" @selected((string) old('period_month', now()->month) === (string) $month)>{{ str_pad((string) $month, 2, '0', STR_PAD_LEFT) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="field">
                        <span>Cantidad a pagar</span>
                        <input type="number" step="0.01" min="0.01" name="amount_due" value="{{ old('amount_due', number_format((float) $receiptDefaultAmount, 2, '.', '')) }}" required>
                    </label>
                    <label class="field">
                        <span>Abonado</span>
                        <input type="number" step="0.01" min="0" name="amount_paid" value="{{ old('amount_paid', '0.00') }}">
                    </label>
                    <label class="field field--full">
                        <span>Notas</span>
                        <textarea name="notes" rows="3" placeholder="Notas internas del recibo">{{ old('notes') }}</textarea>
                    </label>
                    <div class="form-actions">
                        <button class="button button--primary" type="submit">Guardar recibo</button>
                    </div>
                </form>
            @endif

            <div class="table-wrap">
                @if (empty($residentReceipts))
                    <div class="empty-state">
                        <strong>No hay recibos en {{ $receiptYear }}</strong>
                        <p>Agrega el primer recibo mensual para comenzar el control de pagados, parciales y pendientes.</p>
                    </div>
                @else
                    <table>
                        <thead>
                            <tr>
                                <th>Mes y año</th>
                                <th>Cantidad</th>
                                <th>Abonado</th>
                                <th>Pendiente</th>
                                <th>Estatus</th>
                                <th>Notas</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($residentReceipts as $receipt)
                                @php
                                    $receiptFormId = 'resident-receipt-'.$receipt['id'];
                                    $receiptDeleteFormId = 'resident-receipt-delete-'.$receipt['id'];
                                @endphp
                                <tr>
                                    <td>{{ $receipt['period_label'] }}</td>
                                    <td>
                                        @if ($canManage)
                                            <input class="excel-input" type="number" step="0.01" min="0.01" name="amount_due" value="{{ number_format((float) $receipt['amount_due_raw'], 2, '.', '') }}" form="{{ $receiptFormId }}">
                                        @else
                                            {{ $receipt['amount_due'] }}
                                        @endif
                                    </td>
                                    <td>
                                        {{ $receipt['amount_paid'] }}
                                        @if ($canManage && (float) $receipt['amount_paid_raw'] > 0)
                                            <span class="table-sub">Pago aplicado</span>
                                        @endif
                                    </td>
                                    <td>{{ $receipt['pending'] }}</td>
                                    <td><span class="badge {{ $receipt['status_badge'] }}">{{ $receipt['status_label'] }}</span></td>
                                    <td>
                                        @if ($canManage)
                                            <textarea class="excel-input excel-input--textarea" name="notes" rows="2" form="{{ $receiptFormId }}">{{ $receipt['notes'] }}</textarea>
                                        @else
                                            {{ $receipt['notes'] ?: 'Sin notas' }}
                                        @endif
                                    </td>
                                    <td>
                                        @if ($canManage)
                                            <form id="{{ $receiptFormId }}" method="POST" action="{{ route('billing.receipts.update', $receipt['id']) }}">
                                                @csrf
                                                @method('PATCH')
                                            </form>
                                            <form id="{{ $receiptDeleteFormId }}" method="POST" action="{{ route('billing.receipts.delete', $receipt['id']) }}" class="inline-form">
                                                @csrf
                                                @method('DELETE')
                                            </form>
                                            <button class="button button--primary button--small" type="submit" form="{{ $receiptFormId }}">Guardar</button>
                                            <details class="receipt-payment-details">
                                                <summary>Aplicar pago</summary>
                                                <form class="form-grid form-grid--receipt-payment" method="POST" action="{{ route('billing.receipts.apply', $receipt['id']) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <label class="field">
                                                        <span>Fecha de pago</span>
                                                        <input type="date" name="paid_at" value="{{ old('paid_at', now()->toDateString()) }}" required>
                                                    </label>
                                                    <label class="field">
                                                        <span>Método</span>
                                                        <select class="select-field" name="payment_method" required>
                                                            <option value="transferencia" @selected(old('payment_method') === 'transferencia')>Transferencia</option>
                                                            <option value="efectivo" @selected(old('payment_method') === 'efectivo')>Efectivo</option>
                                                        </select>
                                                    </label>
                                                    <label class="field">
                                                        <span>Abono</span>
                                                        <select class="select-field" name="payment_type" required>
                                                            <option value="total" @selected(old('payment_type') === 'total')>Total</option>
                                                            <option value="parcial" @selected(old('payment_type') === 'parcial')>Parcial</option>
                                                        </select>
                                                    </label>
                                                    <label class="field">
                                                        <span>Monto parcial</span>
                                                        <input type="number" step="0.01" min="0.01" max="{{ number_format((float) $receipt['pending_raw'], 2, '.', '') }}" name="partial_amount" value="{{ old('partial_amount') }}" placeholder="{{ number_format((float) $receipt['pending_raw'], 2, '.', '') }}">
                                                    </label>
                                                    <div class="form-actions">
                                                        <button class="button button--primary button--small" type="submit">Aplicar</button>
                                                    </div>
                                                </form>
                                            </details>
                                            @if ((float) $receipt['amount_paid_raw'] > 0)
                                                <form method="POST" action="{{ route('billing.receipts.unapply', $receipt['id']) }}" class="inline-form" onsubmit="return confirm('Desaplicar los pagos de este recibo?');">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button class="button button--ghost button--small" type="submit">Desaplicar</button>
                                                </form>
                                            @endif
                                            <button class="button button--ghost button--small" type="submit" form="{{ $receiptDeleteFormId }}">Eliminar</button>
                                        @endif
                                        <a class="button button--ghost button--small" href="{{ $receipt['pdf_url'] }}">PDF</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @endif
    </section>
</section>

<section class="section-stack">
    <div class="section-intro">
        <div>
            <p class="section-intro__eyebrow">Movimientos</p>
            <h3 class="section-intro__title">Pagos visibles e historial</h3>
        </div>
        <p class="section-intro__note">Usa estas tablas para validar que pago el residente y descargar sus comprobantes o recibos en PDF.</p>
    </div>

    <section class="panel">
        <div class="panel__header">
            <h3>Pagos Reportados por Residentes</h3>
            <span class="badge badge--neutral">Visibles en pantalla</span>
        </div>
        <div class="table-wrap">
            @if (empty($recentResidentPayments))
                <div class="empty-state">
                    <strong>No hay pagos visibles todavia</strong>
                    <p>Cuando un residente pague algo, aquí aparecerá el movimiento reciente.</p>
                </div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Residente</th>
                            <th>Unidad</th>
                            <th>Concepto</th>
                            <th>Fecha</th>
                            <th>Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recentResidentPayments as $payment)
                            <tr>
                                <td>{{ $payment['resident'] }}</td>
                                <td>{{ $payment['unit'] }}</td>
                                <td>{{ $payment['concept'] }}</td>
                                <td>{{ $payment['date'] }}</td>
                                <td>{{ $payment['amount'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
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
                    <span>Aplicar a recibo</span>
                    <select class="select-field" name="resident_receipt_id">
                        <option value="">Sin recibo específico</option>
                        @foreach ($selectedUnitReceipts as $receipt)
                            <option value="{{ $receipt['id'] }}" @selected((string) old('resident_receipt_id') === (string) $receipt['id'])>
                                {{ $receipt['period_label'] }} | {{ $receipt['status_label'] }} | Pendiente {{ $receipt['pending'] }}
                            </option>
                        @endforeach
                    </select>
                    <small>Se muestran los recibos del condomino seleccionado arriba.</small>
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
                    <p>Los pagos y cargos capturados aparecerán aquí cuando comiencen a usar el módulo.</p>
                </div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Concepto</th>
                            <th>Fecha</th>
                            <th>Monto</th>
                            <th>Método</th>
                            <th>Abono</th>
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
                                <td>{{ $transaction['method'] }}</td>
                                <td>{{ $transaction['payment_type'] }}</td>
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
</section>
