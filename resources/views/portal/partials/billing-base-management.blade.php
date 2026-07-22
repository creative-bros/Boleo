<section class="section-stack" id="base-historica-cartas">
    <div class="section-intro">
        <div>
            <p class="section-intro__eyebrow">Base importada y cartas</p>
            <h3 class="section-intro__title">Base histórica, Excel editable y plantillas</h3>
        </div>
        <p class="section-intro__note">Administra la base de adeudos, las plantillas de cartas y el historial de archivos importados desde Configuración.</p>
    </div>

    <section class="panel">
        <div class="panel__header">
            <h3>Base histórica y cartas</h3>
            <span>{{ $importedAccountsCount }} cuenta(s) importada(s)</span>
        </div>

        <div class="content-grid content-grid--settings-bottom">
            <form class="form-grid" method="POST" action="{{ route('settings.import-base') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="redirect_to" value="settings">
                <div class="form-block-title field--full">
                    <span>Importar base de adeudos</span>
                    <small>Sube la base completa. Boleo guardará el archivo aunque no coincidan columnas o formato.</small>
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

            <form class="form-grid" method="POST" action="{{ route('settings.letter-templates.store') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="redirect_to" value="settings">
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
                <a class="button button--primary button--small" href="{{ route('settings.letters.bulk', array_filter(['month' => request('month')], fn ($value) => filled($value))) }}">
                    Descargar todas
                </a>
                <a class="button button--ghost button--small" href="{{ route('settings.letters.bulk', array_filter(['status' => 'adeudo', 'month' => request('month')], fn ($value) => filled($value))) }}">
                    Solo adeudo ({{ $condominiumLetterStats['debt'] }})
                </a>
                <a class="button button--ghost button--small" href="{{ route('settings.letters.bulk', array_filter(['status' => 'no_adeudo', 'month' => request('month')], fn ($value) => filled($value))) }}">
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
                                    <a class="button button--ghost button--small" href="{{ route('settings', ['base_import' => $baseImport->id]) }}#base-historica-cartas">
                                        Usar base
                                    </a>
                                    @if ($baseImport->stored_path)
                                        <a class="button button--ghost button--small" href="{{ route('settings.import-base.download', $baseImport) }}">
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

        @php
            $editingPayload = old('payload', $editingImportedAccount?->raw_payload ?? []);
        @endphp
        <form class="form-grid" method="POST" action="{{ $editingImportedAccount ? route('settings.imported-accounts.update', $editingImportedAccount) : route('settings.imported-accounts.store') }}">
            @csrf
            <input type="hidden" name="redirect_to" value="settings">
            @if ($editingImportedAccount)
                @method('PUT')
            @endif
            <div class="form-block-title field--full">
                <span>{{ $editingImportedAccount ? 'Editar registro de la base' : 'Crear registro nuevo' }}</span>
                <small>Captura o modifica la cuenta sin depender del archivo original. Los campos completos respetan la estructura de la base importada.</small>
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
                <summary>Ver campos completos de la base</summary>
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
                    <a class="button button--ghost" href="{{ route('settings') }}#base-historica-cartas">Cancelar edición</a>
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
                                    <a class="button button--ghost button--small" href="{{ route('settings.letters.show', $importedAccount) }}">
                                        Generar carta
                                    </a>
                                </td>
                                <td>
                                    <a class="button button--ghost button--small" href="{{ route('settings', ['edit_base_account' => $importedAccount->id]) }}#base-historica-cartas">
                                        Editar
                                    </a>
                                    <form method="POST" action="{{ route('settings.imported-accounts.delete', $importedAccount) }}" class="inline-form">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="redirect_to" value="settings">
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
</section>
