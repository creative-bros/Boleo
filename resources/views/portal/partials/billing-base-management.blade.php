<section class="section-stack" id="base-historica-cartas">
    <div class="section-intro">
        <div>
            <p class="section-intro__eyebrow">Base importada y cartas</p>
            <h3 class="section-intro__title">Base histórica y plantillas</h3>
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
                    <small>Si el archivo se puede leer como Excel, Boleo usará sus datos para saldos, cartas y consultas de cobranza.</small>
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
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </section>
</section>
