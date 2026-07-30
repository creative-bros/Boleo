@php
    use App\Models\QuoteRequest;

    $statusLabels = [
        QuoteRequest::STATUS_RECEIVED => 'Pendiente',
        QuoteRequest::STATUS_ACCEPTED => 'Aceptada',
        QuoteRequest::STATUS_DENIED => 'Negada',
    ];

    $statusBadges = [
        QuoteRequest::STATUS_RECEIVED => 'badge--warning',
        QuoteRequest::STATUS_ACCEPTED => 'badge--success',
        QuoteRequest::STATUS_DENIED => 'badge--danger',
    ];

    $priorityLabels = [
        'low' => 'Baja',
        'normal' => 'Normal',
        'high' => 'Alta',
        'urgent' => 'Urgente',
    ];
@endphp

<section class="section-stack">
    <div class="section-intro">
        <div>
            <p class="section-intro__eyebrow">Bandeja de entrada</p>
            <h3 class="section-intro__title">Seguimiento de solicitudes recibidas</h3>
        </div>
        <p class="section-intro__note">Cada solicitud conserva su folio, referencia externa y decisión administrativa para evitar duplicidad con otros sistemas.</p>
    </div>

    <section class="stats-grid stats-grid--four">
        @foreach ($summary as $item)
            <article class="stat-card stat-card--{{ $item['tone'] }}">
                <span>{{ $item['label'] }}</span>
                <strong>{{ $item['value'] }}</strong>
                <small>{{ $item['meta'] }}</small>
            </article>
        @endforeach
    </section>
</section>

<section class="section-stack">
    <div class="section-intro">
        <div>
            <p class="section-intro__eyebrow">Consulta</p>
            <h3 class="section-intro__title">Filtrar solicitudes</h3>
        </div>
        <p class="section-intro__note">Busca por folio, contacto, condominio, servicio o referencia del sistema externo.</p>
    </div>

    <section class="panel">
        <form class="form-grid form-grid--quote-filters" method="GET" action="{{ route('quote-requests') }}">
            <label class="field">
                <span>Estatus</span>
                <select name="status" class="select-field">
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected($statusFilter === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">
                <span>Búsqueda</span>
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Folio, contacto, servicio o referencia">
            </label>
            <div class="form-actions quote-filter-actions">
                <a class="button button--ghost" href="{{ route('quote-requests') }}">Limpiar</a>
                <button class="button button--primary" type="submit">Aplicar filtros</button>
            </div>
        </form>
    </section>
</section>

<section class="section-stack">
    <div class="section-intro">
        <div>
            <p class="section-intro__eyebrow">Solicitudes</p>
            <h3 class="section-intro__title">Detalle de cotizaciones</h3>
        </div>
        <p class="section-intro__note">{{ $quoteRequests->count() }} resultado(s) en la vista actual.</p>
    </div>

    @if ($quoteRequests->isEmpty())
        <section class="panel">
            <div class="empty-state empty-state--large">
                <strong>No hay solicitudes para mostrar</strong>
                <p>Cuando el endpoint reciba solicitudes de cotización, aparecerán aquí para revisión.</p>
            </div>
        </section>
    @else
        <section class="quote-request-list">
            @foreach ($quoteRequests as $quoteRequest)
                @php
                    $statusLabel = $statusLabels[$quoteRequest->status] ?? $quoteRequest->status;
                    $statusBadge = $statusBadges[$quoteRequest->status] ?? 'badge--neutral';
                    $priorityLabel = $priorityLabels[$quoteRequest->priority] ?? ucfirst((string) $quoteRequest->priority);
                    $folio = $quoteRequest->quote_number ?: '#'.$quoteRequest->id;
                    $sourceLabel = trim(($quoteRequest->source_system ?: 'Sistema externo').' / '.($quoteRequest->external_reference ?: 'Sin referencia'), ' /');
                @endphp

                <article class="quote-request-card">
                    <div class="quote-request-card__header">
                        <div>
                            <p class="section-intro__eyebrow">{{ $folio }}</p>
                            <h3>{{ $quoteRequest->service_type }}</h3>
                            <span>{{ $quoteRequest->condominium_name ?: 'Sin condominio especificado' }}</span>
                        </div>
                        <div class="quote-request-card__actions">
                            <span class="badge {{ $statusBadge }}">{{ $statusLabel }}</span>
                            @if ($canManage)
                                <form id="quote-delete-{{ $quoteRequest->id }}" method="POST" action="{{ route('quote-requests.destroy', $quoteRequest) }}" class="inline-form">
                                    @csrf
                                    @method('DELETE')
                                </form>
                                <button
                                    class="button button--danger button--small"
                                    type="button"
                                    data-confirm-submit="quote-delete-{{ $quoteRequest->id }}"
                                    data-confirm-title="¿Borrar esta solicitud?"
                                    data-confirm-text="Se eliminará la solicitud de cotización y ya no aparecerá en la bandeja."
                                    data-confirm-button-text="Sí, borrar"
                                >Borrar</button>
                            @endif
                        </div>
                    </div>

                    <div class="quote-request-meta">
                        <div>
                            <span>Contacto</span>
                            <strong>{{ $quoteRequest->contact_name }}</strong>
                            <small>{{ $quoteRequest->contact_phone ?: 'Sin teléfono' }}</small>
                            <small>{{ $quoteRequest->contact_email ?: 'Sin correo' }}</small>
                        </div>
                        <div>
                            <span>Origen</span>
                            <strong>{{ $sourceLabel }}</strong>
                            <small>Recibida {{ optional($quoteRequest->created_at)->format('d/m/Y H:i') }}</small>
                        </div>
                        <div>
                            <span>Fecha y presupuesto</span>
                            <strong>{{ $quoteRequest->desired_date ? $quoteRequest->desired_date->format('d/m/Y') : 'Sin fecha solicitada' }}</strong>
                            <small>{{ $quoteRequest->budget_amount !== null ? '$'.number_format((float) $quoteRequest->budget_amount, 2) : 'Sin presupuesto' }}</small>
                        </div>
                        <div>
                            <span>Prioridad</span>
                            <strong>{{ $priorityLabel }}</strong>
                            <small>{{ $quoteRequest->origin_ip ? 'IP '.$quoteRequest->origin_ip : 'Sin IP registrada' }}</small>
                        </div>
                    </div>

                    <div class="quote-request-description">
                        <span>Descripción</span>
                        <p>{{ $quoteRequest->description }}</p>
                    </div>

                    @if ($quoteRequest->decided_at)
                        <div class="quote-decision-note">
                            <span>Decisión registrada</span>
                            <p>
                                {{ $statusLabel }} por {{ $quoteRequest->decidedBy?->name ?? 'usuario no disponible' }}
                                el {{ $quoteRequest->decided_at->format('d/m/Y H:i') }}.
                            </p>
                            @if ($quoteRequest->decision_notes)
                                <p>{{ $quoteRequest->decision_notes }}</p>
                            @endif
                        </div>
                    @endif

                    @if ($canManage)
                        <div class="quote-decision-grid">
                            @if ($quoteRequest->status !== QuoteRequest::STATUS_ACCEPTED)
                                <form id="quote-accept-{{ $quoteRequest->id }}" class="quote-decision-form" method="POST" action="{{ route('quote-requests.accept', $quoteRequest) }}">
                                    @csrf
                                    @method('PATCH')
                                    <label class="field">
                                        <span>Notas al aceptar</span>
                                        <textarea name="decision_notes" rows="2" maxlength="1000" placeholder="Opcional"></textarea>
                                    </label>
                                    <button
                                        class="button button--success"
                                        type="submit"
                                        data-confirm-submit="quote-accept-{{ $quoteRequest->id }}"
                                        data-confirm-title="Aceptar solicitud"
                                        data-confirm-text="La solicitud quedará marcada como aceptada."
                                        data-confirm-button-text="Sí, aceptar"
                                    >
                                        Aceptar solicitud
                                    </button>
                                </form>
                            @endif

                            @if ($quoteRequest->status !== QuoteRequest::STATUS_DENIED)
                                <form id="quote-deny-{{ $quoteRequest->id }}" class="quote-decision-form" method="POST" action="{{ route('quote-requests.deny', $quoteRequest) }}">
                                    @csrf
                                    @method('PATCH')
                                    <label class="field">
                                        <span>Motivo al negar</span>
                                        <textarea name="decision_notes" rows="2" maxlength="1000" placeholder="Opcional"></textarea>
                                    </label>
                                    <button
                                        class="button button--danger"
                                        type="submit"
                                        data-confirm-submit="quote-deny-{{ $quoteRequest->id }}"
                                        data-confirm-title="Negar solicitud"
                                        data-confirm-text="La solicitud quedará marcada como negada."
                                        data-confirm-button-text="Sí, negar"
                                    >
                                        Negar solicitud
                                    </button>
                                </form>
                            @endif
                        </div>
                    @endif
                </article>
            @endforeach
        </section>
    @endif
</section>
