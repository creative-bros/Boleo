@if ($canManage)
    <section class="panel">
        <div class="panel__header">
            <h3>Registrar Amenidad</h3>
        <span>Áreas comunes</span>
        </div>
        <form class="form-grid" method="POST" action="{{ route('amenities.store') }}">
            @csrf
            <label class="field">
                <span>Nombre</span>
                <input type="text" name="name" value="{{ old('name') }}" required>
            </label>
            <label class="field">
                <span>Area</span>
                <input type="text" name="area" value="{{ old('area') }}" placeholder="Piscina, gimnasio, salon social">
            </label>
            <label class="field">
                <span>Estatus</span>
                <select class="select-field" name="status" required>
                    @foreach ($amenityStatuses as $amenityStatus)
                        <option value="{{ $amenityStatus }}">{{ $amenityStatus }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">
                <span>Capacidad</span>
                <input type="text" name="capacity" value="{{ old('capacity') }}" placeholder="40 personas">
            </label>
            <label class="field">
                <span>Horario</span>
                <input type="text" name="hours" value="{{ old('hours') }}" placeholder="06:00 - 22:00">
            </label>
            <label class="field field--full">
                <span>Notas</span>
                <input type="text" name="notes" value="{{ old('notes') }}" placeholder="Observaciones de uso, reglas o mantenimiento">
            </label>
            <div class="form-actions">
                <button class="button button--primary" type="submit">Guardar amenidad</button>
            </div>
        </form>
    </section>
@endif

<section class="content-grid content-grid--settings-bottom">
    <article class="panel">
        <div class="panel__header">
            <h3>Reservar Amenidad</h3>
            <span>{{ $canManage ? 'Reservas habilitadas' : 'Reservas personales' }}</span>
        </div>
        <form class="form-grid" method="POST" action="{{ $editingReservation ? route('amenities.reservations.update', $editingReservation) : route('amenities.reservations.store') }}">
            @csrf
            @if ($editingReservation)
                @method('PATCH')
            @endif
            <label class="field">
                <span>Amenidad</span>
                <input type="text" name="amenity_name" value="{{ old('amenity_name', $editingReservation?->amenity?->name) }}" placeholder="Escribe la amenidad" required>
            </label>
            <label class="field">
                <span>Fecha</span>
                <input type="date" name="booking_date" value="{{ old('booking_date', $editingReservation?->booking_date?->toDateString() ?? now()->toDateString()) }}" required>
            </label>
            <label class="field">
                <span>Hora de inicio</span>
                <input type="time" name="start_time" value="{{ old('start_time', $editingReservation?->start_time ?? '09:00') }}" required>
            </label>
            <label class="field">
                <span>Hora de termino</span>
                <input type="time" name="end_time" value="{{ old('end_time', $editingReservation?->end_time ?? '10:00') }}" required>
            </label>
            <label class="field field--full">
                <span>Notas</span>
                <input type="text" name="notes" value="{{ old('notes', $editingReservation?->notes) }}" placeholder="Evento, invitados o indicaciones especiales">
            </label>
            <div class="form-actions">
                @if ($editingReservation)
                    <a class="button button--ghost" href="{{ route('amenities') }}">Cancelar edicion</a>
                @endif
                <button class="button button--primary" type="submit">{{ $editingReservation ? 'Guardar cambios' : 'Registrar reserva' }}</button>
            </div>
        </form>
    </article>

    <article class="panel panel--primary compact-panel">
        <h3>Uso Semanal Promedio</h3>
        <strong>{{ $weeklyReservations > 0 ? $weeklyReservations : '--' }}</strong>
        <p>{{ $weeklyReservations > 0 ? 'Reservas registradas esta semana' : 'Sin reservas registradas esta semana' }}</p>
    </article>
</section>

<section class="content-grid content-grid--amenities">
    @if (empty($amenities))
        <div class="panel">
            <div class="empty-state empty-state--large">
                <strong>Aun no hay amenidades registradas</strong>
                    <p>Cuando configuren áreas comunes, se mostrarán aquí con su disponibilidad y capacidad.</p>
            </div>
        </div>
    @else
        <div class="amenity-grid">
            @foreach ($amenities as $amenity)
                <article class="amenity-card amenity-card--{{ $amenity['accent'] }}">
                    @if ($amenity['can_manage'])
                        <div class="amenity-card__actions">
                            <form method="POST" action="{{ route('amenities.destroy', $amenity['id']) }}" onsubmit="return confirm('Eliminar esta amenidad?');">
                                @csrf
                                @method('DELETE')
                                <button class="button button--danger button--chip" type="submit">Eliminar</button>
                            </form>
                        </div>
                    @endif
                    <span class="badge badge--neutral">{{ $amenity['status'] }}</span>
                    <h3>{{ $amenity['name'] }}</h3>
                    @if (! empty($amenity['area']))
                        <small>{{ $amenity['area'] }}</small>
                    @endif
                    <p>{{ $amenity['capacity'] }}</p>
                    <small>{{ $amenity['hours'] }}</small>
                    @if (! empty($amenity['notes']))
                        <small>{{ $amenity['notes'] }}</small>
                    @endif
                </article>
            @endforeach
        </div>
    @endif

    <div class="stack">
        <article class="panel">
            <div class="panel__header">
                <h3>Reservas de Hoy</h3>
                <span>{{ empty($todayBookings) ? 'Sin reservas' : count($todayBookings).' activas' }}</span>
            </div>
            @if (empty($todayBookings))
                <div class="empty-state">
                    <strong>No hay reservas registradas</strong>
                    <p>Las reservas del día aparecerán aquí cuando se capturen en la plataforma.</p>
                </div>
            @else
                <div class="booking-list">
                    @foreach ($todayBookings as $booking)
                        <div class="booking-item">
                            <strong>{{ $booking['hour'] }}</strong>
                            <div>
                                <p>{{ $booking['title'] }}</p>
                                <span>{{ $booking['meta'] }}</span>
                                <span class="badge badge--neutral">{{ $booking['status'] }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </article>

        <article class="panel">
            <div class="panel__header">
                <h3>Resumen</h3>
                    <span>{{ count($amenities) }} áreas</span>
            </div>
            <div class="readonly-note">
                <strong>Control de amenidades</strong>
                <p>Ahora puedes escribir la amenidad, registrar la reserva y administrarla desde la tabla de abajo.</p>
            </div>
        </article>
    </div>
</section>

<section class="panel">
    <div class="panel__header">
        <h3>Reservas registradas</h3>
        <span class="badge badge--neutral">{{ count($reservations) }} movimientos</span>
    </div>
    <div class="table-wrap">
        @if (empty($reservations))
            <div class="empty-state">
                <strong>No hay reservas registradas</strong>
                    <p>Cuando capturen una reserva, aquí podrán cancelarla o borrarla.</p>
            </div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Amenidad</th>
                        <th>Residente</th>
                        <th>Fecha</th>
                        <th>Horario</th>
                        <th>Estatus</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($reservations as $reservation)
                        <tr>
                            <td>{{ $reservation['amenity'] }}</td>
                            <td>{{ $reservation['resident'] }}</td>
                            <td>{{ $reservation['date'] }}</td>
                            <td>{{ $reservation['schedule'] }}</td>
                            <td><span class="badge {{ $reservation['status'] === 'Cancelada' ? 'badge--warning' : 'badge--success' }}">{{ $reservation['status'] }}</span></td>
                            <td>
                                @if ($reservation['can_manage'])
                                    <div class="table-actions">
                                        @if ($reservation['status'] !== 'Cancelada')
                                            <a class="button button--ghost" href="{{ route('amenities', ['edit_reservation' => $reservation['id']]) }}">Editar</a>
                                            <form method="POST" action="{{ route('amenities.reservations.cancel', $reservation['id']) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button class="button button--ghost" type="submit">Cancelar</button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('amenities.reservations.destroy', $reservation['id']) }}" onsubmit="return confirm('Borrar esta reserva?');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="button button--danger" type="submit">Borrar</button>
                                        </form>
                                    </div>
                                @else
                                    <span class="table-sub">Sin permisos</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</section>

<section class="panel">
    <div class="panel__header">
        <h3>Reportes de Mantenimiento</h3>
        <a class="button button--ghost" href="{{ route('maintenance') }}">Ver mantenimiento</a>
    </div>
    <div class="table-wrap">
        @if (empty($maintenanceReports))
            <div class="empty-state">
                <strong>No hay reportes de mantenimiento</strong>
                    <p>Cuando agreguen revisiones o incidencias de amenidades, se mostrarán en esta sección.</p>
            </div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Area</th>
                            <th>Última revisión</th>
                        <th>Estado</th>
                        <th>Proximo servicio</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($maintenanceReports as $report)
                        <tr>
                            <td>{{ $report['area'] }}</td>
                            <td>{{ $report['last'] }}</td>
                            <td><span class="badge badge--neutral">{{ $report['status'] }}</span></td>
                            <td>{{ $report['next'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</section>
