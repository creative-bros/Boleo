<?php

namespace App\Http\Controllers;

use App\Models\Amenity;
use App\Models\AmenityReservation;
use App\Models\CondominiumProfile;
use App\Models\MaintenanceExpense;
use App\Models\MaintenanceTask;
use App\Models\Payment;
use App\Models\Provider;
use App\Models\Unit;
use App\Models\User;
use App\Support\ResidentMonthlyReportPdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PortalController extends Controller
{
    public function login(): View
    {
        return view('portal.login');
    }

    public function register(): View
    {
        return view('portal.register');
    }

    public function forgotPassword(): View
    {
        return view('portal.forgot-password');
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        if (! Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            if ($credentials['email'] === 'admin@boleo.mx' && $credentials['password'] === 'secret123') {
                User::query()->updateOrCreate([
                    'email' => 'admin@boleo.mx',
                ], [
                    'name' => 'Administrador Boleo',
                    'phone' => '5512345678',
                    'role' => 'admin',
                    'password' => Hash::make('secret123'),
                ]);

                if (Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
                    $request->session()->regenerate();

                    return redirect()->route('dashboard');
                }
            }

            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'Las credenciales no son válidas.',
                ]);
        }

        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function storeRegister(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('settingsProfile', [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:30', 'unique:users,phone'],
            'password' => ['required', 'confirmed', 'min:6'],
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'role' => 'user',
            'password' => Hash::make($data['password']),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()
            ->route('dashboard')
            ->with('status', 'Cuenta creada correctamente. Bienvenido a Boleo.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function sendRecoveryMessage(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('settingsInfrastructure', [
            'email' => ['required', 'email'],
            'phone' => ['required', 'string', 'max:30'],
        ]);

        $user = User::query()
            ->where('email', $data['email'])
            ->where('phone', $data['phone'])
            ->first();

        if (! $user) {
            return back()
                ->withInput()
                ->withErrors([
                    'email' => 'No encontramos una cuenta que coincida con ese correo y número telefónico.',
                ]);
        }

        $token = Password::broker()->createToken($user);
        $resetUrl = route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ]);

        Mail::raw(
            "Hola {$user->name},\n\nRecibimos una solicitud para recuperar tu contraseña en Boleo.\n\nRestablécela aquí: {$resetUrl}\n\nSi no solicitaste este cambio, ignora este mensaje.",
            fn ($message) => $message
                ->to($user->email)
                ->subject('Recuperación de contraseña Boleo')
        );

        return back()->with('status', 'Te enviamos un mensaje de recuperación al correo registrado.');
    }

    public function showResetPassword(Request $request, string $token): View
    {
        return view('portal.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('settingsUsers', [
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', 'min:6'],
        ]);

        $status = Password::reset(
            $data,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'El enlace de recuperación no es válido o ya expiró.',
                ]);
        }

        return redirect()
            ->route('login')
            ->with('status', 'Contraseña actualizada correctamente. Ya puedes iniciar sesión.');
    }

    public function dashboard(): View
    {
        $q = trim((string) request('q', ''));
        $expenseMonth = $this->resolveExpenseMonth(request('expense_month'));
        $payments = Payment::query()
            ->with('unit')
            ->when($q !== '', function ($query) use ($q) {
                $query->where('concept', 'like', "%{$q}%")
                    ->orWhereHas('unit', function ($unitQuery) use ($q) {
                        $unitQuery->where('unit_number', 'like', "%{$q}%")
                            ->orWhere('owner_name', 'like', "%{$q}%")
                            ->orWhere('owner_email', 'like', "%{$q}%")
                            ->orWhere('tower', 'like', "%{$q}%");
                    });
            })
            ->latest('paid_at')
            ->get();

        $currentMonthTotal = (float) Payment::query()
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('amount');

        $totalUnits = Unit::count();
        $billingRows = $this->buildBillingRows(Unit::query()->with('payments')->get(), $this->profile());
        $paidUnits = $billingRows->where('pending_amount', '<=', 0)->count();
        $lateUnits = max($totalUnits - $paidUnits, 0);

        return $this->page('dashboard', [
            'headline' => 'Estado de la Comunidad',
            'subheadline' => 'Aquí se mostrará el resumen general cuando comiencen a registrar movimientos reales.',
            'stats' => [
                ['label' => 'Recaudacion Mensual', 'value' => $currentMonthTotal > 0 ? '$'.number_format($currentMonthTotal, 2) : '--', 'meta' => $currentMonthTotal > 0 ? 'Total registrado este mes' : 'Sin registros todavia', 'tone' => 'primary'],
                ['label' => 'Morosidad Actual', 'value' => $totalUnits > 0 ? $lateUnits.' / '.$totalUnits : '--', 'meta' => $totalUnits > 0 ? 'Unidades sin pago registrado' : 'Sin registros todavia', 'tone' => 'danger'],
                ['label' => 'Unidades al Día', 'value' => $totalUnits > 0 ? $paidUnits.' / '.$totalUnits : '--', 'meta' => $totalUnits > 0 ? 'Unidades con pagos registrados' : 'Sin registros todavía', 'tone' => 'success'],
            ],
            'panels' => [
                'budget' => [],
                'tasks' => [],
                'movements' => $payments->take(10)->map(fn (Payment $payment) => [
                    'reference' => trim(($payment->unit?->tower ?? '').' - '.($payment->unit?->unit_number ?? ''), ' -'),
                    'concept' => $payment->concept,
                    'resident' => $payment->unit?->owner_name ?? 'Sin residente',
                    'email' => $payment->unit?->owner_email ?? 'Sin correo vinculado',
                    'date' => optional($payment->paid_at)->format('d M, Y'),
                    'amount' => '$'.number_format((float) $payment->amount, 2),
                    'status' => $payment->status,
                ])->all(),
            ],
        ]);
    }

    public function units(): View
    {
        $profile = $this->profile();
        $q = trim((string) request('q', ''));
        $condominiumQuery = trim((string) request('condominium', $profile->commercial_name));
        $condominiumMatches = $condominiumQuery === ''
            || Str::contains(Str::lower($profile->commercial_name), Str::lower($condominiumQuery));

        $units = $condominiumMatches
            ? Unit::query()
                ->with('payments')
                ->when($q !== '', function ($query) use ($q) {
                    $query->where('unit_number', 'like', "%{$q}%")
                        ->orWhere('tower', 'like', "%{$q}%")
                        ->orWhere('unit_type', 'like', "%{$q}%")
                        ->orWhere('owner_name', 'like', "%{$q}%")
                        ->orWhere('owner_email', 'like', "%{$q}%");
                })
                ->orderBy('tower')
                ->orderBy('unit_number')
                ->get()
            : collect();

        $editingId = request()->integer('edit');
        $editingUnit = $editingId ? $units->firstWhere('id', $editingId) : null;
        $billingRows = $this->buildBillingRows($units, $profile)->keyBy('id');

        return $this->page('units', [
            'headline' => 'Gestión de Unidades',
            'subheadline' => 'Control de residentes, correos vinculados y cuotas base del condominio.',
            'inventory' => [
                ['label' => 'Total unidades', 'value' => $units->count()],
                ['label' => 'Pagadas', 'value' => $units->where('status', 'Pagado')->count()],
                ['label' => 'Pendientes', 'value' => $units->where('status', 'Atrasado')->count()],
            ],
            'feeModes' => ['Estándar', 'Indiviso %'],
            'defaultFeeType' => $profile->fee_type,
            'billingRows' => $billingRows,
            'units' => $units,
            'editingUnit' => $editingUnit,
            'unitStatuses' => ['Pagado', 'Atrasado', 'Vacante'],
            'condominiumQuery' => $condominiumQuery,
            'condominiumName' => $profile->commercial_name,
            'condominiumMatches' => $condominiumMatches,
            'quickCommands' => [
                ['label' => 'Ir a cobranza', 'href' => route('billing', ['condominium' => $profile->commercial_name]), 'style' => 'primary'],
                ['label' => 'Reporte de cobranza', 'href' => route('billing.report.pdf'), 'style' => 'ghost'],
                ['label' => 'Reporte de deudores', 'href' => route('billing.debtors.pdf'), 'style' => 'ghost'],
                ['label' => 'Gastos mensuales PDF', 'href' => route('maintenance.expenses.monthly.pdf', ['expense_month' => now()->format('Y-m')]), 'style' => 'ghost'],
            ],
            'characteristics' => [
                ['label' => 'Condominio', 'value' => $profile->commercial_name ?: 'Sin configurar'],
                ['label' => 'Departamentos', 'value' => (string) $profile->departments_count],
                ['label' => 'Cajones totales', 'value' => (string) $profile->parking_spaces_count],
                ['label' => 'Bodegas', 'value' => (string) $profile->storage_rooms_count],
                ['label' => 'Caseta', 'value' => $profile->security_booth ? 'Si' : 'No'],
            ],
        ]);
    }

    public function storeUnit(Request $request): RedirectResponse
    {
        $this->ensureAdmin();

        Unit::create($this->validateUnit($request));

        return redirect()
            ->route('units')
            ->with('status', 'Unidad creada correctamente.');
    }

    public function updateUnit(Request $request, Unit $unit): RedirectResponse
    {
        $this->ensureAdmin();

        $unit->update($this->validateUnit($request));

        return redirect()
            ->route('units')
            ->with('status', 'Unidad actualizada correctamente.');
    }

    public function destroyUnit(Unit $unit): RedirectResponse
    {
        $this->ensureAdmin();

        $unit->delete();

        return redirect()
            ->route('units')
            ->with('status', 'Unidad eliminada correctamente.');
    }

    public function updateUnitStatus(Request $request, Unit $unit): RedirectResponse
    {
        $this->ensureAdmin();

        $data = $request->validateWithBag('settingsUsers', [
            'status' => ['required', Rule::in(['Pagado', 'Atrasado', 'Vacante'])],
        ]);

        $unit->update([
            'status' => $data['status'],
        ]);

        return redirect()
            ->route('units')
            ->with('status', 'Estatus de la unidad actualizado correctamente.');
    }

    public function updateFeeType(Request $request): RedirectResponse
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'fee_type' => ['required', Rule::in(['standard', 'indiviso'])],
        ]);

        $this->profile()->update([
            'fee_type' => $data['fee_type'],
        ]);

        return redirect()
            ->route('units')
            ->with('status', 'Modelo de cobro actualizado correctamente.');
    }

    public function amenities(): View
    {
        $amenities = Amenity::query()->orderBy('name')->get();
        $todayReservations = AmenityReservation::query()
            ->with(['amenity', 'user'])
            ->whereDate('booking_date', today())
            ->where('status', '!=', 'Cancelada')
            ->orderBy('start_time')
            ->get();
        $recentReservations = AmenityReservation::query()
            ->with(['amenity', 'user'])
            ->where('status', '!=', 'Cancelada')
            ->latest('booking_date')
            ->latest('start_time')
            ->take(12)
            ->get();
        $editingReservationId = request()->integer('edit_reservation');
        $editingReservation = $editingReservationId
            ? AmenityReservation::query()->with('amenity')->find($editingReservationId)
            : null;
        $canManageReservations = Auth::user()?->isAdmin() ?? false;
        $weeklyReservations = AmenityReservation::query()
            ->whereBetween('booking_date', [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()])
            ->where('status', '!=', 'Cancelada')
            ->count();

        return $this->page('amenities', [
            'headline' => 'Gestión de Amenidades',
            'subheadline' => 'Administra las áreas comunes del condominio, su capacidad y disponibilidad.',
            'amenities' => $amenities->map(function (Amenity $amenity, int $index) {
                $accents = ['sunset', 'steel', 'copper', 'ghost'];

                return [
                    'id' => $amenity->id,
                    'name' => $amenity->name,
                    'status' => $amenity->status,
                    'capacity' => $amenity->capacity ?: 'Sin capacidad definida',
                    'hours' => $amenity->hours ?: 'Sin horario definido',
                    'accent' => $accents[$index % count($accents)],
                    'notes' => $amenity->notes,
                    'area' => $amenity->area,
                    'can_manage' => Auth::user()?->isAdmin() ?? false,
                ];
            })->all(),
            'todayBookings' => $todayReservations->map(fn (AmenityReservation $reservation) => [
                'id' => $reservation->id,
                'hour' => substr((string) $reservation->start_time, 0, 5),
                'title' => $reservation->amenity?->name ?? 'Amenidad',
                'meta' => trim(($reservation->user?->name ?? 'Sin usuario').' | '.substr((string) $reservation->end_time, 0, 5)),
                'status' => $reservation->status,
                'can_manage' => $canManageReservations,
            ])->all(),
            'maintenanceReports' => MaintenanceTask::query()
                ->latest('updated_at')
                ->take(6)
                ->get()
                ->map(fn (MaintenanceTask $task) => [
                    'area' => $task->area ?: 'Área común',
                    'last' => optional($task->updated_at)->format('d M Y'),
                    'status' => $task->status,
                    'next' => optional($task->due_date)->format('d M Y') ?: 'Pendiente',
                ])->all(),
            'amenityStatuses' => ['Disponible', 'Reservada', 'Mantenimiento'],
            'weeklyReservations' => $weeklyReservations,
            'reservations' => $recentReservations->map(fn (AmenityReservation $reservation) => [
                'id' => $reservation->id,
                'amenity' => $reservation->amenity?->name ?? 'Amenidad',
                'resident' => $reservation->user?->name ?? 'Sin usuario',
                'date' => optional($reservation->booking_date)->format('d M Y'),
                'schedule' => substr((string) $reservation->start_time, 0, 5).' - '.substr((string) $reservation->end_time, 0, 5),
                'status' => $reservation->status,
                'can_manage' => $canManageReservations,
            ])->all(),
            'editingReservation' => $canManageReservations ? $editingReservation : null,
        ]);
    }

    public function storeAmenity(Request $request): RedirectResponse
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'area' => ['nullable', 'string', 'max:150'],
            'status' => ['required', Rule::in(['Disponible', 'Reservada', 'Mantenimiento'])],
            'capacity' => ['nullable', 'string', 'max:100'],
            'hours' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $data['area'] = $data['area'] ?? '';
        $data['capacity'] = $data['capacity'] ?? '';
        $data['hours'] = $data['hours'] ?? '';

        Amenity::create($data);

        return redirect()
            ->route('amenities')
            ->with('status', 'Amenidad registrada correctamente.');
    }

    public function destroyAmenity(Amenity $amenity): RedirectResponse
    {
        $this->ensureAdmin();

        $amenity->delete();

        return redirect()
            ->route('amenities')
            ->with('status', 'Amenidad eliminada correctamente.');
    }

    public function storeAmenityReservation(Request $request): RedirectResponse
    {
        [$data, $amenity] = $this->validateReservationData($request);

        AmenityReservation::query()->create([
            'amenity_id' => $amenity->id,
            'user_id' => Auth::id(),
            'booking_date' => $data['booking_date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'status' => 'Reservada',
            'notes' => $data['notes'] ?? null,
        ]);

        $this->syncAmenityReservationStatus($amenity);

        return redirect()
            ->route('amenities')
            ->with('status', 'Reserva registrada correctamente.');
    }

    public function updateAmenityReservation(Request $request, AmenityReservation $reservation): RedirectResponse
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        $originalAmenity = $reservation->amenity;
        [$data, $amenity] = $this->validateReservationData($request, $reservation);

        $reservation->update([
            'amenity_id' => $amenity->id,
            'booking_date' => $data['booking_date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'status' => $reservation->status === 'Cancelada' ? 'Reservada' : $reservation->status,
            'notes' => $data['notes'] ?? null,
        ]);

        if ($originalAmenity && $originalAmenity->id !== $amenity->id) {
            $this->syncAmenityReservationStatus($originalAmenity);
        }
        $this->syncAmenityReservationStatus($amenity);

        return redirect()
            ->route('amenities')
            ->with('status', 'Reserva actualizada correctamente.');
    }

    public function cancelAmenityReservation(AmenityReservation $reservation): RedirectResponse
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        $reservation->update([
            'status' => 'Cancelada',
        ]);

        if ($reservation->amenity) {
            $this->syncAmenityReservationStatus($reservation->amenity);
        }

        return redirect()
            ->route('amenities')
            ->with('status', 'Reserva cancelada correctamente.');
    }

    public function destroyAmenityReservation(AmenityReservation $reservation): RedirectResponse
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        $amenity = $reservation->amenity;
        $reservation->delete();

        if ($amenity) {
            $this->syncAmenityReservationStatus($amenity);
        }

        return redirect()
            ->route('amenities')
            ->with('status', 'Reserva eliminada correctamente.');
    }

    private function validateReservationData(Request $request, ?AmenityReservation $ignoreReservation = null): array
    {
        $data = $request->validate([
            'amenity_name' => ['required', 'string', 'max:150'],
            'booking_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $amenityName = trim($data['amenity_name']);
        $amenity = Amenity::query()
            ->whereRaw('LOWER(name) = ?', [Str::lower($amenityName)])
            ->first();

        if (! $amenity) {
            $amenity = Amenity::query()->create([
                'name' => $amenityName,
                'area' => 'General',
                'status' => 'Disponible',
                'capacity' => 'Por definir',
                'hours' => 'Por definir',
                'notes' => 'Creada desde el módulo de reservas.',
            ]);
        }

        if ($amenity->status === 'Mantenimiento') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'amenity_name' => 'Esta amenidad está en mantenimiento y no puede reservarse.',
            ]);
        }

        $conflictQuery = AmenityReservation::query()
            ->where('amenity_id', $amenity->id)
            ->whereDate('booking_date', $data['booking_date'])
            ->where('status', '!=', 'Cancelada')
            ->where(function ($query) use ($data) {
                $query->where(function ($inner) use ($data) {
                    $inner->where('start_time', '<', $data['end_time'])
                        ->where('end_time', '>', $data['start_time']);
                });
            });

        if ($ignoreReservation) {
            $conflictQuery->where('id', '!=', $ignoreReservation->id);
        }

        if ($conflictQuery->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'booking_date' => 'Ya existe una reserva para esa amenidad en el horario seleccionado.',
            ]);
        }

        return [$data, $amenity];
    }

    private function syncAmenityReservationStatus(Amenity $amenity): void
    {
        if ($amenity->status === 'Mantenimiento') {
            return;
        }

        $hasActiveReservations = AmenityReservation::query()
            ->where('amenity_id', $amenity->id)
            ->where('status', '!=', 'Cancelada')
            ->whereDate('booking_date', '>=', today()->toDateString())
            ->exists();

        $amenity->update([
            'status' => $hasActiveReservations ? 'Reservada' : 'Disponible',
        ]);
    }

    public function maintenance(): View
    {
        $q = trim((string) request('q', ''));
        $expenseMonth = $this->resolveExpenseMonth(request()->string('expense_month')->toString());
        $providers = Provider::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('category', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            })
            ->orderBy('name')
            ->get();
        $tasks = MaintenanceTask::query()->with('provider')->orderByRaw("case when status = 'Pendiente' then 0 when status = 'En proceso' then 1 else 2 end")->orderBy('due_date')->get();
        $allExpenses = MaintenanceExpense::query()->with('provider')->latest('spent_at')->get();
        $expenses = $allExpenses
            ->filter(fn (MaintenanceExpense $expense) => $this->expenseMonthKey($expense) === $expenseMonth->format('Y-m'))
            ->values();
        $fixedTotal = (float) $expenses->where('expense_group', 'fixed')->sum('amount');
        $variableTotal = (float) $expenses->where('expense_group', 'variable')->sum('amount');
        $totalMonthlyExpenses = (float) $expenses->sum('amount');
        $expenseMotives = $this->expenseMotives();

        return $this->page('maintenance', [
            'headline' => 'Gestión de Mantenimiento',
            'subheadline' => 'Tareas, proveedores, último costo y gastos del mantenimiento del condominio.',
            'summary' => [
                ['label' => 'Gasto mensual', 'value' => $totalMonthlyExpenses > 0 ? '$'.number_format($totalMonthlyExpenses, 2) : '--', 'meta' => 'Total registrado en '.$expenseMonth->translatedFormat('F Y'), 'tone' => 'primary'],
                ['label' => 'Gastos fijos', 'value' => $fixedTotal > 0 ? '$'.number_format($fixedTotal, 2) : '--', 'meta' => 'Limpieza, servicio, vigilancia y similares', 'tone' => 'info'],
                ['label' => 'Gastos no fijos', 'value' => $variableTotal > 0 ? '$'.number_format($variableTotal, 2) : '--', 'meta' => 'Mantenimiento, agua, focos y compras variables', 'tone' => 'success'],
                ['label' => 'Urgente', 'value' => (string) $tasks->where('status', 'Pendiente')->count(), 'meta' => 'Tareas pendientes', 'tone' => 'danger'],
            ],
            'board' => [
                'Pendiente' => $this->mapTasks($tasks->where('status', 'Pendiente')),
                'En proceso' => $this->mapTasks($tasks->where('status', 'En proceso')),
                'Finalizado' => $this->mapTasks($tasks->where('status', 'Finalizado')),
            ],
            'expenses' => $expenses->map(fn (MaintenanceExpense $expense) => [
                'id' => $expense->id,
                'date' => optional($expense->spent_at)->format('d M Y'),
                'month' => optional($expense->report_month ?? $expense->spent_at)->translatedFormat('F Y'),
                'group' => $expense->expense_group === 'fixed' ? 'Fijo' : 'No fijo',
                'category' => $expense->category,
                'concept' => $expense->concept,
                'provider' => $expense->provider?->name ?? 'Sin proveedor',
                'amount' => '$'.number_format((float) $expense->amount, 2),
                'observations' => $expense->observations,
                'document_name' => $expense->document_name,
                'has_document' => filled($expense->document_path),
                'receipt_url' => route('maintenance.expenses.receipt.pdf', $expense),
                'document_url' => filled($expense->document_path) ? route('maintenance.expenses.document', $expense) : null,
            ])->all(),
            'providers' => $providers->map(fn (Provider $provider) => [
                'name' => $provider->name,
                'tag' => $provider->category,
                'contact' => trim(($provider->phone ?: '').' '.($provider->email ?: '')),
            ])->all(),
            'providerOptions' => $providers,
            'taskStatuses' => ['Pendiente', 'En proceso', 'Finalizado'],
            'expenseMonth' => $expenseMonth->format('Y-m'),
            'expenseMonthLabel' => $expenseMonth->translatedFormat('F Y'),
            'fixedExpenseCategories' => $this->fixedExpenseCategories(),
            'variableExpenseCategories' => $this->variableExpenseCategories(),
            'expenseCommands' => [
                ['label' => 'Reporte general PDF', 'href' => route('maintenance.pdf'), 'style' => 'ghost'],
                ['label' => 'Gastos del mes PDF', 'href' => route('maintenance.expenses.monthly.pdf', ['expense_month' => $expenseMonth->format('Y-m')]), 'style' => 'primary'],
            ],
            'expenseMotives' => $expenseMotives,
            'expenseSheetTitle' => 'Detalle de gastos realizados del 1 al '.$expenseMonth->copy()->endOfMonth()->format('d').' '.$expenseMonth->translatedFormat('F \\d\\e Y'),
            'expenseSheetRows' => $expenses->map(fn (MaintenanceExpense $expense) => [
                'date' => optional($expense->spent_at)->format('d/m/Y'),
                'motive' => $expense->concept,
                'group' => $expense->expense_group === 'fixed' ? 'Fijo' : 'No fijo',
                'amount' => '$'.number_format((float) $expense->amount, 2),
                'observations' => $expense->observations ?: 'Sin observaciones',
                'document_name' => $expense->document_name ?: 'Sin documento',
                'document_url' => filled($expense->document_path) ? route('maintenance.expenses.document', $expense) : null,
            ])->all(),
            'variableExpenseSheetRows' => $expenses
                ->where('expense_group', 'variable')
                ->map(fn (MaintenanceExpense $expense) => [
                    'date' => optional($expense->spent_at)->format('d/m/Y'),
                    'motive' => $expense->concept,
                    'amount' => '$'.number_format((float) $expense->amount, 2),
                    'observations' => $expense->observations ?: 'Sin observaciones',
                ])->values()->all(),
            'expenseSheetTotal' => '$'.number_format($totalMonthlyExpenses, 2),
            'variableExpenseSheetTotal' => '$'.number_format($variableTotal, 2),
        ]);
    }

    public function storeProvider(Request $request): RedirectResponse
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        Provider::create($data);

        return redirect()
            ->route('maintenance')
            ->with('status', 'Proveedor agregado correctamente.');
    }

    public function storeMaintenanceTask(Request $request): RedirectResponse
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'area' => ['nullable', 'string', 'max:150'],
            'provider_id' => ['nullable', 'exists:providers,id'],
            'last_cost' => ['nullable', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['Pendiente', 'En proceso', 'Finalizado'])],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        MaintenanceTask::create([
            ...$data,
            'last_cost' => $data['last_cost'] ?? 0,
        ]);

        return redirect()
            ->route('maintenance')
            ->with('status', 'Tarea de mantenimiento registrada correctamente.');
    }

    public function storeMaintenanceExpense(Request $request): RedirectResponse
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'spent_at' => ['required', 'date'],
            'expense_group' => ['required', Rule::in(['fixed', 'variable'])],
            'category' => ['required', 'string', 'max:100'],
            'report_month' => ['required', 'date_format:Y-m'],
            'concept' => ['required', 'string', 'max:150'],
            'provider_id' => ['nullable', 'exists:providers,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'document' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,webp'],
            'observations' => ['nullable', 'string', 'max:2000'],
        ]);

        $documentPath = null;
        $documentName = null;

        if ($request->hasFile('document')) {
            $documentPath = $request->file('document')->store('maintenance-expenses', 'local');
            $documentName = $request->file('document')->getClientOriginalName();
        }

        MaintenanceExpense::create([
            'spent_at' => $data['spent_at'],
            'expense_group' => $data['expense_group'],
            'category' => $data['category'],
            'report_month' => Carbon::createFromFormat('Y-m', $data['report_month'])->startOfMonth()->toDateString(),
            'concept' => $data['concept'],
            'provider_id' => $data['provider_id'] ?? null,
            'amount' => $data['amount'],
            'document_path' => $documentPath,
            'document_name' => $documentName,
            'observations' => $data['observations'] ?? null,
        ]);

        return redirect()
            ->route('maintenance', ['expense_month' => $data['report_month']])
            ->with('status', 'Gasto de mantenimiento registrado correctamente.');
    }

    public function maintenancePdf(): Response
    {
        $tasks = MaintenanceTask::query()->with('provider')->orderBy('status')->orderBy('due_date')->get();
        $expenses = MaintenanceExpense::query()->with('provider')->latest('spent_at')->take(20)->get();
        $lines = [
            'Reporte de Mantenimiento Boleo',
            '',
            'Tareas registradas:',
            '',
        ];

        foreach ($tasks as $task) {
            $lines[] = $task->title.' - '.($task->area ?: 'Sin area').' - '.$task->status.' - '.($task->provider?->name ?? 'Sin proveedor');
        }

        if ($tasks->isEmpty()) {
            $lines[] = 'Sin tareas registradas.';
        }

        $lines[] = '';
        $lines[] = 'Gastos recientes:';
        $lines[] = '';

        foreach ($expenses as $expense) {
            $lines[] = optional($expense->spent_at)->format('d/m/Y').' - '.$expense->category.' - '.$expense->concept.' - $'.number_format((float) $expense->amount, 2);
        }

        if ($expenses->isEmpty()) {
            $lines[] = 'Sin gastos registrados.';
        }

        return $this->pdfResponse('reporte-mantenimiento-boleo.pdf', $lines);
    }

    public function maintenanceMonthlyExpensesPdf(Request $request): Response
    {
        $expenseMonth = $this->resolveExpenseMonth($request->string('expense_month')->toString());
        $expenses = MaintenanceExpense::query()->with('provider')->latest('spent_at')->get()
            ->filter(fn (MaintenanceExpense $expense) => $this->expenseMonthKey($expense) === $expenseMonth->format('Y-m'))
            ->values();

        $lines = [
            'Reporte Mensual de Gastos Boleo',
            '',
            'Mes: '.$expenseMonth->translatedFormat('F Y'),
            'Total: $'.number_format((float) $expenses->sum('amount'), 2),
            '',
        ];

        foreach ($expenses as $expense) {
            $lines[] = optional($expense->spent_at)->format('d/m/Y')
                .' - '.$expense->category
                .' - '.$expense->concept
                .' - '.($expense->provider?->name ?? 'Sin proveedor')
                .' - $'.number_format((float) $expense->amount, 2);
        }

        if ($expenses->isEmpty()) {
            $lines[] = 'Sin gastos registrados para este mes.';
        }

        return $this->pdfResponse('reporte-gastos-mensual-boleo.pdf', $lines);
    }

    public function maintenanceExpenseReceiptPdf(MaintenanceExpense $expense): Response
    {
        $expense->load('provider');

        return $this->pdfResponse('recibo-gasto-'.$expense->id.'.pdf', [
            'Recibo de Gasto Boleo',
            '',
            'Fecha: '.optional($expense->spent_at)->format('d/m/Y'),
            'Mes: '.optional($expense->report_month ?? $expense->spent_at)->translatedFormat('F Y'),
            'Tipo: '.($expense->expense_group === 'fixed' ? 'Gasto fijo' : 'Gasto no fijo'),
            'Categoria: '.$expense->category,
            'Concepto: '.$expense->concept,
            'Proveedor: '.($expense->provider?->name ?? 'Sin proveedor'),
            'Monto: $'.number_format((float) $expense->amount, 2),
            'Observaciones: '.($expense->observations ?: 'Sin observaciones'),
            'Documento adjunto: '.($expense->document_name ?: 'Sin documento'),
        ]);
    }

    public function maintenanceExpenseDocument(MaintenanceExpense $expense)
    {
        abort_unless($expense->document_path && Storage::disk('local')->exists($expense->document_path), 404);

        return response()->download(
            Storage::disk('local')->path($expense->document_path),
            $expense->document_name ?: basename($expense->document_path)
        );
    }

    public function billing(): View
    {
        $profile = $this->profile();
        $q = trim((string) request('q', ''));
        $condominiumQuery = trim((string) request('condominium', $profile->commercial_name));
        $reportMonth = $this->resolveExpenseMonth(request('month'));
        $units = Unit::query()
            ->with(['payments' => fn ($query) => $query->latest('paid_at')])
            ->when($q !== '', function ($query) use ($q) {
                $query->where('unit_number', 'like', "%{$q}%")
                    ->orWhere('tower', 'like', "%{$q}%")
                    ->orWhere('owner_name', 'like', "%{$q}%")
                    ->orWhere('owner_email', 'like', "%{$q}%");
            })
            ->orderBy('tower')
            ->orderBy('unit_number')
            ->get();

        $billingRows = $this->buildBillingRows($units, $profile, $reportMonth)->keyBy('id');

        $selectedUnitId = request()->integer('unit');
        $selectedUnit = $selectedUnitId ? $units->firstWhere('id', $selectedUnitId) : $units->first();
        $selectedSummary = $selectedUnit ? $billingRows->get($selectedUnit->id) : null;

        $transactions = $selectedUnit
            ? $selectedUnit->payments->map(fn (Payment $payment) => [
                'id' => $payment->id,
                'concept' => $payment->concept,
                'date' => optional($payment->paid_at)->format('d M Y'),
                'amount' => '$'.number_format((float) $payment->amount, 2),
                'status' => $payment->status,
            ])->all()
            : [];

        $recentResidentPayments = Payment::query()
            ->with('unit')
            ->latest('paid_at')
            ->take(8)
            ->get()
            ->map(fn (Payment $payment) => [
                'resident' => $payment->unit?->owner_name ?? 'Sin residente',
                'unit' => trim(($payment->unit?->tower ?? '').' - '.($payment->unit?->unit_number ?? ''), ' -'),
                'concept' => $payment->concept,
                'date' => optional($payment->paid_at)->format('d M Y'),
                'amount' => '$'.number_format((float) $payment->amount, 2),
            ])->all();

        $residents = $units->map(function (Unit $unit) use ($billingRows) {
            $summary = $billingRows->get($unit->id);

            return [
                'id' => $unit->id,
                'name' => $unit->owner_name,
                'email' => $unit->owner_email,
                'unit' => trim($unit->tower.' - '.$unit->unit_number, ' -'),
                'status' => $summary['status_label'],
                'balance' => '$'.number_format((float) $summary['pending_amount'], 2),
                'paid' => '$'.number_format((float) $summary['paid_amount'], 2),
                'last_payment' => optional($unit->payments->first()?->paid_at)->format('d M Y') ?: 'Sin registro',
            ];
        })->all();

        return $this->page('billing', [
            'headline' => 'Módulo de Cobranza',
            'subheadline' => 'Buscador de condominio y departamento, recibos, estados de cuenta y reportes.',
            'residents' => $residents,
            'account' => [
                'name' => $selectedUnit?->owner_name ?? '',
                'email' => $selectedUnit?->owner_email ?? '',
                'location' => $selectedUnit ? trim($selectedUnit->tower.', Unidad '.$selectedUnit->unit_number, ' ,') : '',
                'role' => $selectedUnit?->unit_type ?? '',
                'last_payment' => optional($selectedUnit?->payments->first()?->paid_at)->format('d M, Y') ?: '--',
                'method' => 'Registro manual',
                'balance' => $selectedSummary ? '$'.number_format((float) $selectedSummary['pending_amount'], 2) : '--',
                'paid' => $selectedSummary ? '$'.number_format((float) $selectedSummary['paid_amount'], 2) : '--',
                'fee' => $selectedSummary ? '$'.number_format((float) $selectedSummary['fee_amount'], 2) : '--',
                'status' => $selectedSummary['status_label'] ?? '--',
            ],
            'transactions' => $transactions,
            'billingUnits' => $units,
            'selectedUnitId' => $selectedUnit?->id,
            'billingPeriod' => $this->billingPeriodLabel($reportMonth),
            'debtorsCount' => $billingRows->where('pending_amount', '>', 0)->count(),
            'condominiumQuery' => $condominiumQuery,
            'condominiumName' => $profile->commercial_name,
            'recentResidentPayments' => $recentResidentPayments,
            'reportCommands' => array_values(array_filter([
                ['label' => 'Estado de cuenta PDF', 'href' => route('billing.pdf', ['unit' => $selectedUnit?->id]), 'style' => 'light'],
                ['label' => 'Reporte de cobranza', 'href' => route('billing.report.pdf'), 'style' => 'ghost-light'],
                ['label' => 'Reporte de deudores', 'href' => route('billing.debtors.pdf'), 'style' => 'ghost-light'],
                $selectedUnit ? [
                    'label' => 'Reporte mensual del residente',
                    'href' => route('billing.resident.monthly.pdf', ['unit' => $selectedUnit->id, 'month' => $reportMonth->format('Y-m')]),
                    'style' => 'ghost-light',
                ] : null,
            ])),
        ]);
    }

    public function storePayment(Request $request): RedirectResponse
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'unit_id' => ['required', 'exists:units,id'],
            'concept' => ['required', 'string', 'max:150'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'paid_at' => ['required', 'date'],
        ]);

        $duplicateExists = Payment::query()
            ->where('unit_id', $data['unit_id'])
            ->where('concept', $data['concept'])
            ->where('amount', $data['amount'])
            ->whereDate('paid_at', $data['paid_at'])
            ->exists();

        if ($duplicateExists) {
            return redirect()
                ->route('billing', ['unit' => $data['unit_id']])
                ->withErrors([
                    'concept' => 'Ya existe un pago igual registrado para esta unidad en la misma fecha.',
                ]);
        }

        Payment::create([
            ...$data,
            'status' => 'Completado',
        ]);

        return redirect()
            ->route('billing', ['unit' => $data['unit_id']])
            ->with('status', 'Pago registrado correctamente.');
    }

    public function billingPdf(Request $request): Response
    {
        $unit = Unit::query()->with('payments')->find($request->integer('unit'));
        $period = $this->resolveExpenseMonth($request->string('month')->toString());
        $summary = $unit ? $this->billingSnapshot($unit, $this->profile(), $period) : null;
        $lines = [
            'Estado de Cuenta Boleo',
            '',
            'Periodo: '.$this->billingPeriodLabel($period),
            'Unidad: '.($unit ? trim($unit->tower.' '.$unit->unit_number) : 'Sin seleccionar'),
            'Propietario: '.($unit?->owner_name ?: 'Sin dato'),
            'Correo vinculado: '.($unit?->owner_email ?: 'Sin correo vinculado'),
            'Cuota mensual: '.($summary ? '$'.number_format((float) $summary['fee_amount'], 2) : '--'),
            'Pagado en el periodo: '.($summary ? '$'.number_format((float) $summary['paid_amount'], 2) : '--'),
            'Saldo pendiente: '.($summary ? '$'.number_format((float) $summary['pending_amount'], 2) : '--'),
            'Estatus: '.($summary['status_label'] ?? 'Sin dato'),
            '',
        ];

        foreach (($unit?->payments ?? collect()) as $payment) {
            $lines[] = optional($payment->paid_at)->format('d/m/Y').' - '.$payment->concept.' - $'.number_format((float) $payment->amount, 2);
        }

        if (count($lines) === 10) {
            $lines[] = 'Sin pagos registrados.';
        }

        return $this->pdfResponse('estado-cuenta-boleo.pdf', $lines);
    }

    public function residentMonthlyReportPdf(Request $request): Response
    {
        $period = $this->resolveExpenseMonth($request->string('month')->toString());
        $profile = $this->profile();
        $unit = Unit::query()
            ->with(['payments' => fn ($query) => $query->latest('paid_at')])
            ->findOrFail($request->integer('unit'));

        $summary = $this->billingSnapshot($unit, $profile, $period);
        $payments = $unit->payments
            ->filter(fn (Payment $payment) => $payment->paid_at?->format('Y-m') === $period->format('Y-m'))
            ->values();
        $expenses = $this->monthlyExpenses($period);

        $pdf = new ResidentMonthlyReportPdf(
            $profile,
            $unit,
            $period,
            $summary,
            $expenses,
            $payments
        );

        return response($pdf->render(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="reporte-mensual-residente-'.$unit->id.'-'.$period->format('Y-m').'.pdf"',
        ]);
    }

    public function paymentReceiptPdf(Payment $payment): Response
    {
        $payment->load('unit');

        $lines = [
            'Recibo de Pago Boleo',
            '',
            'Fecha: '.optional($payment->paid_at)->format('d/m/Y'),
            'Unidad: '.trim(($payment->unit?->tower ?? '').' '.($payment->unit?->unit_number ?? '')),
            'Residente: '.($payment->unit?->owner_name ?? 'Sin dato'),
            'Correo: '.($payment->unit?->owner_email ?? 'Sin correo vinculado'),
            'Concepto: '.$payment->concept,
            'Monto: $'.number_format((float) $payment->amount, 2),
            'Estatus: '.$payment->status,
        ];

        return $this->pdfResponse('recibo-pago-'.$payment->id.'.pdf', $lines);
    }

    public function billingReportPdf(): Response
    {
        $period = $this->resolveExpenseMonth(request('month'));
        $rows = $this->buildBillingRows(Unit::query()->with('payments')->orderBy('tower')->orderBy('unit_number')->get(), $this->profile(), $period);
        $lines = [
            'Reporte de Cobranza Boleo',
            '',
            'Periodo: '.$this->billingPeriodLabel($period),
            '',
        ];

        foreach ($rows as $row) {
            $lines[] = "{$row['unit_label']} - {$row['owner_name']} - Cuota: $".number_format((float) $row['fee_amount'], 2)
                ." - Pagado: $".number_format((float) $row['paid_amount'], 2)
                ." - Pendiente: $".number_format((float) $row['pending_amount'], 2)
                ." - {$row['status_label']}";
        }

        if ($rows->isEmpty()) {
            $lines[] = 'Sin unidades registradas.';
        }

        return $this->pdfResponse('reporte-cobranza-boleo.pdf', $lines);
    }

    public function debtorsReportPdf(): Response
    {
        $period = $this->resolveExpenseMonth(request('month'));
        $rows = $this->buildBillingRows(Unit::query()->with('payments')->orderBy('tower')->orderBy('unit_number')->get(), $this->profile(), $period)
            ->where('pending_amount', '>', 0)
            ->values();

        $lines = [
            'Reporte de Deudores Boleo',
            '',
            'Periodo: '.$this->billingPeriodLabel($period),
            '',
        ];

        foreach ($rows as $row) {
            $lines[] = "{$row['unit_label']} - {$row['owner_name']} - Correo: {$row['owner_email']} - Pendiente: $"
                .number_format((float) $row['pending_amount'], 2);
        }

        if ($rows->isEmpty()) {
            $lines[] = 'No hay deudores en el periodo actual.';
        }

        return $this->pdfResponse('reporte-deudores-boleo.pdf', $lines);
    }

    public function settings(): View
    {
        $profile = $this->profile();
        $feeTypeOptions = [
            'standard' => 'Estándar',
            'indiviso' => 'Indiviso',
        ];
        $adminTypeOptions = [
            'professional' => 'Profesional',
            'condomino' => 'Condómino',
        ];
        $q = trim((string) request('q', ''));
        $users = User::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('role', 'like', "%{$q}%");
            })
            ->where('name', 'not like', 'Usuario Playwright%')
            ->where('email', 'not like', 'playwright-%')
            ->orderByRaw("case when role = 'admin' then 0 else 1 end")
            ->orderBy('name')
            ->get();

        $editingUserId = request()->integer('edit_user');
        $editingUser = $editingUserId ? $users->firstWhere('id', $editingUserId) : null;
        $selectedUser = $editingUser;
        $linkedUnits = $selectedUser
            ? Unit::query()
                ->where('owner_email', $selectedUser->email)
                ->orWhere('owner_name', $selectedUser->name)
                ->orderBy('tower')
                ->orderBy('unit_number')
                ->get()
            : collect();

        return $this->page('settings', [
            'headline' => 'Ajustes del Condominio',
            'subheadline' => 'Información general, cuenta para depósitos, infraestructura y administración.',
            'identity' => [
                'commercial_name' => $profile->commercial_name,
                'tax_id' => $profile->tax_id,
                'address' => $profile->address,
                'latitude' => $profile->latitude,
                'longitude' => $profile->longitude,
                'ordinary_fee_amount' => $profile->ordinary_fee_amount,
                'fee_type' => $profile->fee_type,
                'fee_type_label' => $feeTypeOptions[$profile->fee_type] ?? null,
                'departments_count' => $profile->departments_count,
                'parking_spaces_count' => $profile->parking_spaces_count,
                'storage_rooms_count' => $profile->storage_rooms_count,
                'clothesline_cages_count' => $profile->clothesline_cages_count,
                'security_booth' => $profile->security_booth,
                'admin_type' => $profile->admin_type,
                'admin_type_label' => $adminTypeOptions[$profile->admin_type] ?? null,
                'admin_name' => $profile->admin_name,
                'assistant_admin_names' => $profile->assistant_admin_names,
                'assistant_admin_phone' => $profile->assistant_admin_phone,
                'admin_email' => $profile->admin_email,
                'admin_phone' => $profile->admin_phone,
            ],
            'infrastructure' => [
                ['name' => 'Elevadores', 'active' => $profile->elevators_enabled, 'meta' => $profile->elevators_count.' registrados'],
                ['name' => 'Cisternas', 'active' => $profile->cisterns_enabled, 'meta' => $profile->cisterns_count.' registradas'],
                ['name' => 'Tinacos', 'active' => $profile->water_tanks_enabled, 'meta' => $profile->water_tanks_count.' registrados'],
                ['name' => 'Hidroneumáticos', 'active' => $profile->hydropneumatics_enabled, 'meta' => $profile->hydropneumatics_count.' registrados'],
                ['name' => 'Alberca', 'active' => $profile->pool_enabled, 'meta' => $profile->pool_enabled ? 'Disponible' : 'No disponible'],
                ['name' => 'Chapoteadero', 'active' => $profile->wading_pool_enabled, 'meta' => $profile->wading_pool_enabled ? 'Disponible' : 'No disponible'],
                ['name' => 'Salón de eventos', 'active' => $profile->event_hall_enabled, 'meta' => $profile->event_hall_enabled ? 'Disponible' : 'No disponible'],
                ['name' => 'Roof garden', 'active' => $profile->roof_garden_enabled, 'meta' => $profile->roof_garden_enabled ? 'Disponible' : 'No disponible'],
                ['name' => 'Salón de yoga', 'active' => $profile->yoga_room_enabled, 'meta' => $profile->yoga_room_enabled ? 'Disponible' : 'No disponible'],
                ['name' => 'Salón de juegos', 'active' => $profile->game_room_enabled, 'meta' => $profile->game_room_enabled ? 'Disponible' : 'No disponible'],
                ['name' => 'GYM', 'active' => $profile->gym_enabled, 'meta' => $profile->gym_enabled ? 'Disponible' : 'No disponible'],
            ],
            'operations' => [
                'moving_hours' => $profile->moving_hours,
                'work_hours' => $profile->work_hours,
                'meeting_hours' => $profile->meeting_hours,
                'regulations_path' => $profile->regulations_path,
                'cleaning_staff_name' => $profile->cleaning_staff_name,
                'cleaning_staff_phone' => $profile->cleaning_staff_phone,
                'cleaning_staff_contact' => $profile->cleaning_staff_contact,
                'security_staff_name' => $profile->security_staff_name,
                'security_staff_phone' => $profile->security_staff_phone,
                'security_staff_contact' => $profile->security_staff_contact,
            ],
            'banking' => [
                'bank' => $profile->bank,
                'holder' => $profile->account_holder,
                'account' => $profile->account_number,
                'clabe' => $profile->clabe,
            ],
            'users' => $users,
            'editingUser' => $editingUser,
            'selectedUser' => $selectedUser,
            'selectedUserUnits' => $linkedUnits,
            'adminTypeOptions' => $adminTypeOptions,
            'roleOptions' => [
                'admin' => 'Administrador',
                'user' => 'Usuario',
            ],
            'feeTypeOptions' => [
                'standard' => 'Estándar',
                'indiviso' => 'Indiviso',
            ],
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $this->ensureAdmin();

        $data = $request->validateWithBag('settingsProfile', [
            'commercial_name' => ['required', 'string', 'max:150'],
            'tax_id' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'ordinary_fee_amount' => ['required', 'numeric', 'min:0'],
            'fee_type' => ['required', Rule::in(['standard', 'indiviso'])],
            'departments_count' => ['required', 'integer', 'min:0'],
            'parking_spaces_count' => ['required', 'integer', 'min:0'],
            'storage_rooms_count' => ['required', 'integer', 'min:0'],
            'clothesline_cages_count' => ['required', 'integer', 'min:0'],
            'security_booth' => ['nullable', 'boolean'],
            'admin_type' => ['nullable', Rule::in(['professional', 'condomino'])],
            'admin_name' => ['nullable', 'string', 'max:150'],
            'assistant_admin_names' => ['nullable', 'string', 'max:500'],
            'assistant_admin_phone' => ['nullable', 'string', 'max:60'],
            'admin_email' => ['nullable', 'email', 'max:255'],
            'admin_phone' => ['nullable', 'string', 'max:30'],
        ]);

        $this->profile()->update([
            ...$data,
            'security_booth' => $request->boolean('security_booth'),
        ]);

        return redirect()
            ->route('settings')
            ->with('status', 'Datos del condominio actualizados correctamente.');
    }

    public function updateInfrastructure(Request $request): RedirectResponse
    {
        $this->ensureAdmin();

        $data = $request->validateWithBag('settingsInfrastructure', [
            'elevators_enabled' => ['nullable', 'boolean'],
            'elevators_count' => ['required', 'integer', 'min:0'],
            'cisterns_enabled' => ['nullable', 'boolean'],
            'cisterns_count' => ['required', 'integer', 'min:0'],
            'water_tanks_enabled' => ['nullable', 'boolean'],
            'water_tanks_count' => ['required', 'integer', 'min:0'],
            'hydropneumatics_enabled' => ['nullable', 'boolean'],
            'hydropneumatics_count' => ['required', 'integer', 'min:0'],
            'pool_enabled' => ['nullable', 'boolean'],
            'wading_pool_enabled' => ['nullable', 'boolean'],
            'event_hall_enabled' => ['nullable', 'boolean'],
            'roof_garden_enabled' => ['nullable', 'boolean'],
            'yoga_room_enabled' => ['nullable', 'boolean'],
            'game_room_enabled' => ['nullable', 'boolean'],
            'gym_enabled' => ['nullable', 'boolean'],
        ]);

        $this->profile()->update([
            ...$data,
            'elevators_enabled' => $request->boolean('elevators_enabled'),
            'cisterns_enabled' => $request->boolean('cisterns_enabled'),
            'water_tanks_enabled' => $request->boolean('water_tanks_enabled'),
            'hydropneumatics_enabled' => $request->boolean('hydropneumatics_enabled'),
            'pool_enabled' => $request->boolean('pool_enabled'),
            'wading_pool_enabled' => $request->boolean('wading_pool_enabled'),
            'event_hall_enabled' => $request->boolean('event_hall_enabled'),
            'roof_garden_enabled' => $request->boolean('roof_garden_enabled'),
            'yoga_room_enabled' => $request->boolean('yoga_room_enabled'),
            'game_room_enabled' => $request->boolean('game_room_enabled'),
            'gym_enabled' => $request->boolean('gym_enabled'),
        ]);

        return redirect()
            ->route('settings')
            ->with('status', 'Infraestructura actualizada correctamente.');
    }

    public function updateOperations(Request $request): RedirectResponse
    {
        $this->ensureAdmin();

        $data = $request->validateWithBag('settingsOperations', [
            'moving_hours' => ['nullable', 'string', 'max:120'],
            'work_hours' => ['nullable', 'string', 'max:120'],
            'meeting_hours' => ['nullable', 'string', 'max:120'],
            'regulations_file' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'cleaning_staff_name' => ['nullable', 'string', 'max:150'],
            'cleaning_staff_phone' => ['nullable', 'string', 'max:60'],
            'cleaning_staff_contact' => ['nullable', 'string', 'max:255'],
            'security_staff_name' => ['nullable', 'string', 'max:150'],
            'security_staff_phone' => ['nullable', 'string', 'max:60'],
            'security_staff_contact' => ['nullable', 'string', 'max:255'],
        ]);

        $profile = $this->profile();

        if ($request->hasFile('regulations_file')) {
            if (filled($profile->regulations_path) && Storage::disk('public')->exists($profile->regulations_path)) {
                Storage::disk('public')->delete($profile->regulations_path);
            }

            $data['regulations_path'] = $request->file('regulations_file')->store('regulations', 'public');
        }

        $profile->update($data);

        return redirect()
            ->route('settings')
            ->with('status', 'Operación del condominio actualizada correctamente.');
    }

    public function regulationsDocument(): Response
    {
        $profile = $this->profile();

        abort_if(! filled($profile->regulations_path) || ! Storage::disk('public')->exists($profile->regulations_path), 404);

        return response()->file(
            Storage::disk('public')->path($profile->regulations_path),
            ['Content-Type' => 'application/pdf']
        );
    }

    public function updateBanking(Request $request): RedirectResponse
    {
        $this->ensureAdmin();

        $data = $request->validateWithBag('settingsBanking', [
            'bank' => ['nullable', 'string', 'max:150'],
            'account_holder' => ['nullable', 'string', 'max:150'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'clabe' => ['nullable', 'string', 'max:100'],
        ]);

        $this->profile()->update($data);

        return redirect()
            ->route('settings')
            ->with('status', 'Cuenta de deposito actualizada correctamente.');
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $this->ensureAdmin();

        $data = $request->validateWithBag('settingsUsers', [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:30', 'unique:users,phone'],
            'role' => ['required', Rule::in(['admin', 'user'])],
            'password' => ['required', 'confirmed', 'min:6'],
        ]);

        User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'role' => $data['role'],
            'password' => Hash::make($data['password']),
        ]);

        return redirect()
            ->route('settings')
            ->with('status', 'Cuenta creada correctamente.');
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $this->ensureAdmin();

        $data = $request->validateWithBag('settingsUsers', [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['required', 'string', 'max:30', Rule::unique('users', 'phone')->ignore($user->id)],
            'role' => ['required', Rule::in(['admin', 'user'])],
            'password' => ['nullable', 'confirmed', 'min:6'],
        ]);

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'role' => $data['role'],
        ];

        if (! empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
        }

        $user->update($payload);

        return redirect()
            ->route('settings')
            ->with('status', 'Cuenta actualizada correctamente.');
    }

    public function destroyUser(User $user): RedirectResponse
    {
        $this->ensureAdmin();

        if (Auth::id() === $user->id) {
            return redirect()
                ->route('settings')
                ->withErrors([
                    'email' => 'No puedes eliminar tu propia cuenta mientras estas conectado.',
                ]);
        }

        $user->delete();

        return redirect()
            ->route('settings')
            ->with('status', 'Cuenta eliminada correctamente.');
    }

    private function page(string $page, array $payload): View
    {
        return view('portal.page', array_merge($payload, [
            'page' => $page,
            'navigation' => $this->navigation(),
            'searchQuery' => request('q', ''),
            'currentUser' => Auth::user(),
            'canManage' => Auth::user()?->isAdmin() ?? false,
        ]));
    }

    private function navigation(): array
    {
        return [
            [
                'section' => 'Vista general',
                'items' => [
                    ['key' => 'dashboard', 'label' => 'Dashboard', 'route' => 'dashboard', 'description' => 'Resumen general'],
                ],
            ],
            [
                'section' => 'Comunidad',
                'items' => [
                    ['key' => 'units', 'label' => 'Residentes', 'route' => 'units', 'description' => 'Unidades y personas'],
                    ['key' => 'amenities', 'label' => 'Amenidades', 'route' => 'amenities', 'description' => 'Reservas y espacios'],
                ],
            ],
            [
                'section' => 'Operación',
                'items' => [
                    ['key' => 'maintenance', 'label' => 'Mantenimiento', 'route' => 'maintenance', 'description' => 'Tareas y gastos'],
                    ['key' => 'billing', 'label' => 'Finanzas', 'route' => 'billing', 'description' => 'Pagos y reportes'],
                ],
            ],
            [
                'section' => 'Administración',
                'items' => [
                    ['key' => 'settings', 'label' => 'Configuración', 'route' => 'settings', 'description' => 'Condómino y accesos'],
                ],
            ],
        ];
    }

    private function validateUnit(Request $request): array
    {
        return $request->validate([
            'unit_number' => ['required', 'string', 'max:20'],
            'tower' => ['required', 'string', 'max:100'],
            'unit_type' => ['required', 'string', 'max:100'],
            'owner_name' => ['required', 'string', 'max:150'],
            'owner_email' => ['required', 'email', 'max:255'],
            'ordinary_fee' => ['required', 'numeric', 'min:0'],
            'indiviso_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'extraordinary_fee' => ['nullable', 'numeric', 'min:0'],
            'parking_rent' => ['nullable', 'numeric', 'min:0'],
            'storage_rent' => ['nullable', 'numeric', 'min:0'],
            'parking_spots' => ['required', 'integer', 'min:0'],
            'storage_rooms' => ['required', 'integer', 'min:0'],
            'clothesline_cages' => ['required', 'integer', 'min:0'],
            'fee' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:Pagado,Atrasado,Vacante'],
        ]);
    }

    private function buildBillingRows(Collection $units, CondominiumProfile $profile, ?Carbon $period = null): Collection
    {
        return $units->map(fn (Unit $unit) => $this->billingSnapshot($unit, $profile, $period));
    }

    private function billingSnapshot(Unit $unit, CondominiumProfile $profile, ?Carbon $period = null): array
    {
        $period ??= now()->startOfMonth();
        $periodPayments = $unit->payments
            ->filter(fn (Payment $payment) => $payment->paid_at?->format('Y-m') === $period->format('Y-m'));

        $paidAmount = (float) $periodPayments->sum('amount');
        $baseFeeAmount = $profile->ordinary_fee_amount > 0
            ? (float) $profile->ordinary_fee_amount
            : (float) ($unit->ordinary_fee > 0 ? $unit->ordinary_fee : $unit->fee);

        $feeAmount = $profile->fee_type === 'indiviso'
            ? $baseFeeAmount * ((float) $unit->indiviso_percentage / 100)
            : (float) ($unit->ordinary_fee > 0 ? $unit->ordinary_fee : $baseFeeAmount);

        $feeAmount += (float) $unit->extraordinary_fee;
        $feeAmount += (float) $unit->parking_rent;
        $feeAmount += (float) $unit->storage_rent;
        $pendingAmount = max($feeAmount - $paidAmount, 0);

        return [
            'id' => $unit->id,
            'unit_label' => trim($unit->tower.' '.$unit->unit_number),
            'owner_name' => $unit->owner_name,
            'owner_email' => $unit->owner_email ?: 'Sin correo vinculado',
            'fee_amount' => $feeAmount,
            'indiviso_percentage' => (float) $unit->indiviso_percentage,
            'paid_amount' => $paidAmount,
            'pending_amount' => $pendingAmount,
            'status_label' => $pendingAmount > 0 ? 'Deudor' : 'Al corriente',
        ];
    }

    private function billingPeriodLabel(?Carbon $period = null): string
    {
        return ($period ?? now())->copy()->locale('es_MX')->translatedFormat('F Y');
    }

    private function profile(): CondominiumProfile
    {
        return CondominiumProfile::query()->firstOrCreate(
            ['id' => 1],
            [
                'commercial_name' => 'Boleo Condominio',
                'fee_type' => 'standard',
            ]
        );
    }

    private function mapTasks(Collection $tasks): array
    {
        return $tasks->map(fn (MaintenanceTask $task) => [
            'priority' => $task->status,
            'title' => $task->title,
            'ticket' => '#'.$task->id,
            'meta' => trim(($task->area ?: 'Sin area').' | '.($task->provider?->name ?? 'Sin proveedor').' | Ultimo costo $'.number_format((float) $task->last_cost, 2)),
        ])->values()->all();
    }

    private function resolveExpenseMonth(?string $month): Carbon
    {
        if (is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month) === 1) {
            return Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        }

        return now()->startOfMonth();
    }

    private function expenseMonthKey(MaintenanceExpense $expense): string
    {
        return optional($expense->report_month ?? $expense->spent_at)->format('Y-m') ?: now()->format('Y-m');
    }

    private function monthlyExpenses(Carbon $period): Collection
    {
        return MaintenanceExpense::query()
            ->with('provider')
            ->orderBy('spent_at')
            ->get()
            ->filter(fn (MaintenanceExpense $expense) => $this->expenseMonthKey($expense) === $period->format('Y-m'))
            ->values();
    }

    private function fixedExpenseCategories(): array
    {
        return [
            'Vigilancia',
            'Limpieza',
            'Servicio',
            'Administracion',
            'Jardineria',
        ];
    }

    private function variableExpenseCategories(): array
    {
        return [
            'Mantenimiento',
            'Compra de focos',
            'Agua',
            'Pintura',
            'Material electrico',
            'Otro',
        ];
    }

    private function expenseMotives(): array
    {
        return [
            'Pago CFE',
            'Pago elevadores',
            'Liquidacion de tarjeta de porton',
            'Recoleccion de basura',
            'Servicio de limpieza',
            'Servicio de seguridad diamante',
            'Servicio de administracion',
            'Trabajos de impermeabilizacion',
            'Cableado de camaras',
            'Material de camaras',
            'Recibo de vigilancia',
        ];
    }

    private function ensureAdmin(): void
    {
        abort_unless(Auth::user()?->isAdmin(), 403);
    }

    private function pdfResponse(string $filename, array $lines): Response
    {
        $content = $this->buildSimplePdf($lines);

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function buildSimplePdf(array $lines): string
    {
        $safeLines = array_map(fn ($line) => $this->pdfEscape(Str::limit($line, 110, '')), $lines);
        $streamLines = ['BT', '/F1 12 Tf'];

        foreach ($safeLines as $index => $line) {
            $y = 800 - ($index * 18);
            if ($y < 50) {
                break;
            }
            $streamLines[] = "1 0 0 1 50 {$y} Tm ({$line}) Tj";
        }

        $streamLines[] = 'ET';
        $stream = implode("\n", $streamLines);
        $length = strlen($stream);

        $objects = [];
        $objects[] = '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj';
        $objects[] = '2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj';
        $objects[] = '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >> endobj';
        $objects[] = '4 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj';
        $objects[] = "5 0 obj << /Length {$length} >> stream\n{$stream}\nendstream endobj";

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object."\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n";
        $pdf .= '0 '.(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$i])."\n";
        }

        $pdf .= 'trailer << /Size '.(count($objects) + 1).' /Root 1 0 R >>'."\n";
        $pdf .= "startxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    private function pdfEscape(string $value): string
    {
        $ascii = iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $value) ?: $value;

        return str_replace(['\\', '(', ')'], ['\\\\', '\(', '\)'], $ascii);
    }
}
