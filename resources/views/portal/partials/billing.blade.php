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
            @if ($selectedUnitId)
                <input type="hidden" name="unit" value="{{ $selectedUnitId }}">
            @endif
            <div class="form-actions">
                <button class="button button--ghost" type="submit">Buscar</button>
            </div>
        </form>
    </section>

    <section class="content-grid content-grid--settings-bottom">
        <article class="panel">
            <div class="panel__header">
                <h3>Comandos de Reporte</h3>
                <span>PDF y consulta</span>
            </div>
            <div class="command-grid">
                @foreach ($reportCommands as $command)
                    <a class="button button--ghost" href="{{ $command['href'] }}">
                        {{ $command['label'] }}
                    </a>
                @endforeach
            </div>
        </article>

        <article class="panel compact-panel">
            <h3>Pagos visibles del residente</h3>
            <p>Cuando un residente paga algo, aquí se refleja su movimiento reciente para seguimiento del administrador.</p>
            <p>También se mantiene visible dentro del historial de transacciones y en el recibo PDF.</p>
        </article>
    </section>

    @if ($canManage)
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
                        <small>Precarga una plantilla PDF o Word (.docx). Al generar el reporte, Boleo lo entrega en PDF.</small>
                    </div>
                    <label class="field">
                        <span>Plantilla reporte de deudores (adeudo)</span>
                        <input type="file" name="debt_letter_template" accept="application/pdf,.docx">
                        <small>{{ $letterTemplates['debt'] ? 'Plantilla cargada' : 'Sin plantilla cargada' }}</small>
                    </label>
                    <label class="field">
                        <span>Plantilla reporte de no adeudores</span>
                        <input type="file" name="no_debt_letter_template" accept="application/pdf,.docx">
                        <small>{{ $letterTemplates['no_debt'] ? 'Plantilla cargada' : 'Sin plantilla cargada' }}</small>
                    </label>
                    <div class="form-actions">
                        <button class="button button--ghost" type="submit">Guardar plantillas</button>
                    </div>
                </form>
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
                                    <td><span class="badge badge--neutral">{{ ucfirst($baseImport->status) }}</span></td>
                                    <td>
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
                    <h3>Vista tipo Excel</h3>
                    <span>{{ $importedAccountsGrid->count() }} renglones visibles | {{ count($billingBaseGridHeaders) }} columnas</span>
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
                                    <tr>
                                        <td class="excel-table__actions">
                                            <a class="button button--ghost button--small" href="{{ route('billing', ['edit_base_account' => $importedAccount->id]) }}">
                                                Editar
                                            </a>
                                            <a class="button button--ghost button--small" href="{{ route('billing.letters.show', $importedAccount) }}">
                                                Carta
                                            </a>
                                        </td>
                                        @foreach ($billingBaseGridHeaders as $header)
                                            <td>{{ data_get($importedAccount->raw_payload ?? [], $header, '') }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="table-sub">Se muestran los primeros 60 renglones para mantener rápida la pantalla. El Excel original completo permanece disponible en “Descargar Excel”.</p>
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
                @foreach ($billingBaseKeyFields as $field)
                    <label class="field {{ str_contains($field, 'OBSERVACIONES') ? 'field--full' : '' }}">
                        <span>{{ $field }}</span>
                        @if (str_contains($field, 'OBSERVACIONES'))
                            <textarea name="payload[{{ $field }}]" rows="3">{{ $editingPayload[$field] ?? '' }}</textarea>
                        @else
                            <input type="{{ $field === 'TOTAL ADEUDO' ? 'number' : 'text' }}" step="0.01" name="payload[{{ $field }}]" value="{{ $editingPayload[$field] ?? '' }}" @required(in_array($field, ['DEPT', 'Nombre'], true))>
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

<section class="section-stack">
    <div class="section-intro">
        <div>
            <p class="section-intro__eyebrow">Estado de cuenta</p>
            <h3 class="section-intro__title">Perfil del residente y saldo del periodo</h3>
        </div>
        <p class="section-intro__note">Aquí se concentra la información del residente seleccionado, cuánto debe pagar, cuánto ha pagado y su saldo pendiente.</p>
    </div>

    <section class="content-grid content-grid--billing">
        <article class="panel">
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
                        <a class="resident-card resident-card--link" href="{{ route('billing', ['unit' => $resident['id'], 'q' => request('q'), 'condominium' => $condominiumQuery]) }}">
                            <div class="avatar">{{ substr($resident['name'], 0, 1) }}</div>
                            <div>
                                <strong>{{ $resident['name'] }}</strong>
                                <p>{{ $resident['unit'] }}</p>
                                <p>{{ $resident['email'] ?: 'Sin correo vinculado' }}</p>
                                <p>Pagado: {{ $resident['paid'] }}</p>
                                <span class="badge {{ $resident['status'] === 'Deudor' ? 'badge--warning' : 'badge--success' }}">{{ $resident['status'] }}</span>
                            </div>
                            <div class="resident-card__meta">
                                <strong>{{ $resident['balance'] }}</strong>
                                <span class="table-sub">{{ $resident['last_payment'] }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </article>

        <article class="panel">
            @if (blank($account['name']))
                <div class="empty-state empty-state--large">
                    <strong>No hay perfil de cobranza seleccionado</strong>
                    <p>Cuando exista información de una cuenta, aquí verán el detalle de pagos y datos del residente.</p>
                </div>
            @else
                <div class="billing-profile">
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
                <div class="readonly-note billing-reminder">
                    <strong>Recordatorio de pago</strong>
                    <p>La cuota total de esta unidad se paga cada mes. Aquí puedes revisar el monto mensual, lo abonado en el periodo y el saldo pendiente.</p>
                </div>
                @if ($selectedImportedAccount)
                    <div class="readonly-note">
                        <strong>Base histórica importada</strong>
                        <p>Saldo detectado: ${{ number_format((float) $selectedImportedAccount->total_debt, 2) }} | {{ $selectedImportedAccount->status === 'adeudo' ? 'Carta de adeudo' : 'Carta de no adeudo' }}</p>
                        <a class="button button--primary" href="{{ route('billing.letters.show', $selectedImportedAccount) }}">Generar carta</a>
                    </div>
                @endif
            @endif
        </article>

        <article class="panel panel--primary compact-panel">
            <h3>Estado de Cuenta</h3>
            <strong>{{ $account['balance'] }}</strong>
            <p>Saldo pendiente del periodo | {{ $account['status'] }}</p>
            <p>Recuerda que la cuota mensual se paga cada mes.</p>
            @foreach ($reportCommands as $command)
                <a class="button {{ $command['style'] === 'light' ? 'button--light' : 'button--ghost-light' }}" href="{{ $command['href'] }}">{{ $command['label'] }}</a>
            @endforeach
            <small>{{ $debtorsCount }} unidad(es) con saldo pendiente este mes.</small>
        </article>
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
