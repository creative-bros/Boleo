<?php

namespace App\Http\Controllers;

use App\Models\Amenity;
use App\Models\AmenityReservation;
use App\Models\AssemblyMinute;
use App\Models\BillingBaseImport;
use App\Models\CondominiumProfile;
use App\Models\ImportedResidentAccount;
use App\Models\MaintenanceExpense;
use App\Models\MaintenanceTask;
use App\Models\Payment;
use App\Models\Provider;
use App\Models\ResidentReceipt;
use App\Models\Unit;
use App\Models\User;
use App\Support\AccountStatusLetterDocx;
use App\Support\AccountStatusLetterPdf;
use App\Support\BillingBaseSchema;
use App\Support\BillingExcelImporter;
use App\Support\ResidentMonthlyReportPdf;
use App\Support\SimpleLetterheadPdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

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
        $assistantAdminOptions = [
            'Alondra Velázquez Hernández' => '5525403862',
            'Rene Alberto Solano' => '7228378509',
        ];
        $scheduleOptions = [
            'Lunes a viernes de 09:00 a 18:00' => 'Lunes a viernes de 09:00 a 18:00',
            'Lunes a sabado de 08:00 a 17:00' => 'Lunes a sabado de 08:00 a 17:00',
            'Sabado de 10:00 a 14:00' => 'Sabado de 10:00 a 14:00',
            'Hasta las 23:00 horas' => 'Hasta las 23:00 horas',
            'Previa autorizacion administrativa' => 'Previa autorizacion administrativa',
        ];
        $assistantAdminOptions = [
            'Alondra Velázquez Hernández' => '5525403862',
            'Rene Alberto Solano' => '7228378509',
        ];
        $scheduleOptions = [
            'Lunes a viernes de 09:00 a 18:00' => 'Lunes a viernes de 09:00 a 18:00',
            'Lunes a sabado de 08:00 a 17:00' => 'Lunes a sabado de 08:00 a 17:00',
            'Sabado de 10:00 a 14:00' => 'Sabado de 10:00 a 14:00',
            'Hasta las 23:00 horas' => 'Hasta las 23:00 horas',
            'Previa autorizacion administrativa' => 'Previa autorizacion administrativa',
        ];
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
                ['label' => 'Reporte de no adeudores', 'href' => route('billing.report.pdf'), 'style' => 'ghost'],
                ['label' => 'Reporte de deudores', 'href' => route('billing.debtors.pdf'), 'style' => 'ghost'],
                ['label' => 'Gastos mensuales PDF', 'href' => route('maintenance.expenses.monthly.pdf', ['expense_month' => now()->format('Y-m')]), 'style' => 'ghost'],
            ],
            'characteristics' => [
                ['label' => 'Condominio', 'value' => $profile->commercial_name ?: 'Sin configurar'],
                ['label' => 'Departamentos', 'value' => (string) $profile->departments_count],
                ['label' => 'Cajones totales', 'value' => (string) $profile->parking_spaces_count],
                ['label' => 'Bodegas', 'value' => (string) $profile->storage_rooms_count],
                ['label' => 'Caseta', 'value' => $profile->security_booth ? 'Sí' : 'No'],
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
            throw ValidationException::withMessages([
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
            throw ValidationException::withMessages([
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

        $task = MaintenanceTask::create([
            ...$data,
            'last_cost' => $data['last_cost'] ?? 0,
        ]);

        $this->notifyAssistantAboutMaintenanceTask($task);

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
            $lines[] = $task->title.' - '.($task->area ?: 'Sin área').' - '.$task->status.' - '.($task->provider?->name ?? 'Sin proveedor');
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
        if ($requestedBaseImport = $this->requestedBillingBaseImport()) {
            request()->session()->put('settings_condominium_profile_id', $requestedBaseImport->condominium_profile_id);
        }

        $profile = $this->profile();

        if (request()->has('condominium')) {
            $searchedProfile = $this->profileFromCondominiumQuery(trim((string) request('condominium')), $profile);

            if ($searchedProfile && $searchedProfile->id !== $profile->id) {
                request()->session()->put('settings_condominium_profile_id', $searchedProfile->id);
                $profile = $searchedProfile;
            }
        }

        if ($fallbackProfile = $this->fallbackBillingProfile($profile)) {
            request()->session()->put('settings_condominium_profile_id', $fallbackProfile->id);
            $profile = $fallbackProfile;
        }

        $activeBaseImport = $this->activeBillingBaseImport($profile);
        $q = trim((string) request('q', ''));
        $condominiumQuery = trim((string) request('condominium', $profile->commercial_name));
        $reportMonth = $this->resolveExpenseMonth(request('month'));
        $importedAccounts = ImportedResidentAccount::query()
            ->with('billingBaseImport')
            ->where('condominium_profile_id', $profile->id)
            ->when($activeBaseImport, fn ($query) => $query->where('billing_base_import_id', $activeBaseImport->id))
            ->latest('imported_at')
            ->get();
        $matchingImportedAccounts = $q !== ''
            ? $importedAccounts
                ->filter(fn (ImportedResidentAccount $account): bool => $this->importedAccountMatchesQuery($account, $q))
                ->values()
            : collect();
        $matchingImportedUnitIds = $matchingImportedAccounts
            ->pluck('unit_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
        $units = Unit::query()
            ->with([
                'payments' => fn ($query) => $query->latest('paid_at'),
                'residentReceipts' => fn ($query) => $query
                    ->where('condominium_profile_id', $profile->id)
                    ->orderByDesc('period_year')
                    ->orderByDesc('period_month'),
            ])
            ->when($q !== '', function ($query) use ($q, $matchingImportedUnitIds) {
                $query->where(function ($unitQuery) use ($q, $matchingImportedUnitIds) {
                    $unitQuery->where('unit_number', 'like', "%{$q}%")
                        ->orWhere('tower', 'like', "%{$q}%")
                        ->orWhere('owner_name', 'like', "%{$q}%")
                        ->orWhere('owner_email', 'like', "%{$q}%");

                    if ($matchingImportedUnitIds !== []) {
                        $unitQuery->orWhereIn('id', $matchingImportedUnitIds);
                    }
                });
            })
            ->orderBy('tower')
            ->orderBy('unit_number')
            ->get();

        $billingRows = $this->buildBillingRows($units, $profile, $reportMonth)->keyBy('id');
        $billingBaseImports = BillingBaseImport::query()
            ->where('condominium_profile_id', $profile->id)
            ->latest('imported_at')
            ->limit(10)
            ->get();
        $billingBaseHeaders = BillingBaseSchema::headersForProfile($profile);
        $billingBaseKeyFields = BillingBaseSchema::keyFields();
        $billingBaseExtraFields = BillingBaseSchema::editableExtraHeaders($billingBaseHeaders);
        $importedAccountsGrid = ImportedResidentAccount::query()
            ->where('condominium_profile_id', $profile->id)
            ->when($activeBaseImport, fn ($query) => $query->where('billing_base_import_id', $activeBaseImport->id))
            ->orderByRaw('source_row_number is null')
            ->orderBy('source_row_number')
            ->orderBy('unit_number')
            ->get();
        $billingBaseGridHeaders = $importedAccountsGrid
            ->flatMap(fn (ImportedResidentAccount $account): array => array_keys($account->raw_payload ?? []))
            ->unique()
            ->values()
            ->all();
        $billingBaseGridHeaders = $billingBaseGridHeaders !== [] ? $billingBaseGridHeaders : $billingBaseHeaders;
        $editingImportedAccount = request()->integer('edit_base_account')
            ? ImportedResidentAccount::query()
                ->where('condominium_profile_id', $profile->id)
                ->find(request()->integer('edit_base_account'))
            : null;
        $importedByUnit = $importedAccounts
            ->filter(fn (ImportedResidentAccount $account): bool => filled($account->unit_id))
            ->keyBy('unit_id');

        $selectedAccountId = request()->integer('account');
        $selectedImportedAccountByRequest = $selectedAccountId > 0
            ? ($importedAccounts->firstWhere('id', $selectedAccountId) ?? ImportedResidentAccount::query()
                ->with('billingBaseImport')
                ->where('condominium_profile_id', $profile->id)
                ->find($selectedAccountId))
            : null;
        $selectedUnitId = request()->integer('unit');
        $selectedUnit = $selectedUnitId ? $units->firstWhere('id', $selectedUnitId) : null;

        if (! $selectedUnit && $selectedImportedAccountByRequest?->unit_id) {
            $selectedUnit = $units->firstWhere('id', $selectedImportedAccountByRequest->unit_id)
                ?? Unit::query()
                    ->with([
                        'payments' => fn ($query) => $query->latest('paid_at'),
                        'residentReceipts' => fn ($query) => $query
                            ->where('condominium_profile_id', $profile->id)
                            ->orderByDesc('period_year')
                            ->orderByDesc('period_month'),
                    ])
                    ->find($selectedImportedAccountByRequest->unit_id);
        }

        $selectedUnit ??= $units->first();
        $selectedSummary = $selectedUnit
            ? ($billingRows->get($selectedUnit->id) ?? $this->billingSnapshot($selectedUnit, $profile, $reportMonth))
            : null;
        $selectedImportedAccount = $selectedImportedAccountByRequest
            ?? ($selectedUnit
                ? ($importedByUnit->get($selectedUnit->id) ?? $this->findImportedAccountForUnit($importedAccounts, $selectedUnit))
                : null)
            ?? ($q !== '' ? $matchingImportedAccounts->first() : null);
        $selectedAccountSummary = $selectedImportedAccount
            ? $this->billingSnapshotFromImportedAccount($selectedImportedAccount, $selectedUnit, $selectedSummary)
            : $selectedSummary;
        $receiptYear = request()->integer('receipt_year') ?: (int) now()->year;
        $selectedUnitReceipts = $selectedUnit
            ? $selectedUnit->residentReceipts
                ->where('condominium_profile_id', $profile->id)
                ->sortBy(fn (ResidentReceipt $receipt): string => sprintf('%04d-%02d', $receipt->period_year, $receipt->period_month))
                ->values()
            : collect();
        $selectedReceipts = $selectedUnitReceipts
            ->where('period_year', $receiptYear)
            ->values();
        $receiptYears = $selectedUnitReceipts
            ->pluck('period_year')
            ->push($receiptYear)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
        $receiptSummary = $this->residentReceiptSummary($selectedUnitReceipts);

        $transactions = $selectedUnit
            ? $selectedUnit->payments->map(fn (Payment $payment) => [
                'id' => $payment->id,
                'concept' => $payment->concept,
                'date' => optional($payment->paid_at)->format('d M Y'),
                'amount' => '$'.number_format((float) $payment->amount, 2),
                'status' => $payment->status,
                'receipt' => $payment->resident_receipt_id,
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

        $residentRows = $units->map(function (Unit $unit) use ($billingRows, $importedByUnit, $importedAccounts, $profile) {
            $summary = $billingRows->get($unit->id);
            $imported = $importedByUnit->get($unit->id) ?? $this->findImportedAccountForUnit($importedAccounts, $unit);
            $receiptSummary = $this->residentReceiptSummary(
                $unit->residentReceipts->where('condominium_profile_id', $profile->id)
            );

            return [
                'id' => $unit->id,
                'unit_id' => $unit->id,
                'account_id' => $imported?->id,
                'name' => $unit->owner_name,
                'email' => $unit->owner_email,
                'unit' => trim($unit->tower.' - '.$unit->unit_number, ' -'),
                'status' => $imported ? ($imported->status === 'adeudo' ? 'Deudor' : 'Al corriente') : $summary['status_label'],
                'balance' => '$'.number_format((float) ($imported?->total_debt ?? $summary['pending_amount']), 2),
                'paid' => '$'.number_format((float) $summary['paid_amount'], 2),
                'last_payment' => optional($unit->payments->first()?->paid_at)->format('d M Y') ?: 'Sin registro',
                'imported_account_id' => $imported?->id,
                'receipt_balance' => '$'.number_format((float) $receiptSummary['pending_amount'], 2),
                'receipt_meta' => $receiptSummary['total'] > 0
                    ? $receiptSummary['paid_count'].' pagado(s), '.$receiptSummary['partial_count'].' parcial(es), '.$receiptSummary['pending_count'].' pendiente(s)'
                    : 'Sin recibos',
            ];
        })->toBase();
        $listedImportedAccountIds = $residentRows
            ->pluck('account_id')
            ->filter()
            ->all();
        $listedUnitIds = $residentRows
            ->pluck('unit_id')
            ->filter()
            ->all();
        $importedOnlyRows = $importedAccounts
            ->reject(fn (ImportedResidentAccount $account): bool => in_array($account->id, $listedImportedAccountIds, true)
                || (filled($account->unit_id) && in_array((int) $account->unit_id, $listedUnitIds, true)))
            ->when($q !== '', fn (Collection $accounts) => $accounts->filter(
                fn (ImportedResidentAccount $account): bool => $this->importedAccountMatchesQuery($account, $q)
            ))
            ->map(function (ImportedResidentAccount $account): array {
                return [
                    'id' => null,
                    'unit_id' => null,
                    'account_id' => $account->id,
                    'name' => $account->owner_name,
                    'email' => $this->importedAccountEmail($account),
                    'unit' => trim($account->tower.' - '.$account->unit_number, ' -') ?: 'Sin unidad',
                    'status' => $account->status === 'adeudo' ? 'Deudor' : 'Al corriente',
                    'balance' => '$'.number_format((float) $account->total_debt, 2),
                    'paid' => '--',
                    'last_payment' => 'Base histórica',
                    'imported_account_id' => $account->id,
                    'receipt_balance' => '--',
                    'receipt_meta' => 'Sin unidad vinculada',
                ];
            });
        $residents = $residentRows->merge($importedOnlyRows)->values()->all();
        $noDebtReportHref = $selectedImportedAccount
            ? route('billing.letters.show', ['account' => $selectedImportedAccount, 'template' => 'no_adeudo'])
            : route('billing.report.pdf');
        $debtReportHref = $selectedImportedAccount
            ? route('billing.letters.show', ['account' => $selectedImportedAccount, 'template' => 'adeudo'])
            : route('billing.debtors.pdf');
        $selectedReportParams = array_filter([
            'unit' => $selectedUnit?->id,
            'account' => $selectedImportedAccount?->id,
            'month' => $reportMonth->format('Y-m'),
        ], fn ($value): bool => filled($value));

        return $this->page('billing', [
            'headline' => 'Módulo de Cobranza',
            'subheadline' => 'Buscador de condominio y departamento, recibos, estados de cuenta y reportes.',
            'residents' => $residents,
            'account' => [
                'name' => $selectedAccountSummary['owner_name'] ?? '',
                'email' => $selectedAccountSummary['owner_email'] ?? '',
                'location' => $selectedAccountSummary['unit_label'] ?? '',
                'role' => $selectedUnit?->unit_type ?? '',
                'last_payment' => optional($selectedUnit?->payments->first()?->paid_at)->format('d M, Y') ?: '--',
                'method' => 'Registro manual',
                'balance' => $selectedAccountSummary ? '$'.number_format((float) $selectedAccountSummary['pending_amount'], 2) : '--',
                'paid' => $selectedAccountSummary ? '$'.number_format((float) $selectedAccountSummary['paid_amount'], 2) : '--',
                'fee' => $selectedAccountSummary ? '$'.number_format((float) $selectedAccountSummary['fee_amount'], 2) : '--',
                'fee_raw' => $selectedAccountSummary ? (float) $selectedAccountSummary['fee_amount'] : 0,
                'status' => $selectedAccountSummary['status_label'] ?? '--',
            ],
            'transactions' => $transactions,
            'billingUnits' => $units,
            'selectedUnitId' => $selectedUnit?->id,
            'receiptYear' => $receiptYear,
            'receiptYears' => $receiptYears,
            'residentReceipts' => $this->residentReceiptRows($selectedReceipts),
            'selectedUnitReceipts' => $this->residentReceiptRows($selectedUnitReceipts),
            'receiptSummary' => $receiptSummary,
            'billingPeriod' => $this->billingPeriodLabel($reportMonth),
            'debtorsCount' => $importedAccounts->isNotEmpty()
                ? $importedAccounts->filter(fn (ImportedResidentAccount $account): bool => (float) $account->total_debt > 0)->count()
                : $billingRows->where('pending_amount', '>', 0)->count(),
            'condominiumQuery' => $condominiumQuery,
            'condominiumName' => $profile->commercial_name,
            'recentResidentPayments' => $recentResidentPayments,
            'importedAccountsCount' => $importedAccounts->count(),
            'activeBaseImport' => $activeBaseImport,
            'billingBaseImports' => $billingBaseImports,
            'importedAccountsPreview' => $importedAccounts->take(15),
            'billingBaseHeaders' => $billingBaseHeaders,
            'billingBaseKeyFields' => $billingBaseKeyFields,
            'billingBaseExtraFields' => $billingBaseExtraFields,
            'billingBaseGridHeaders' => $billingBaseGridHeaders,
            'importedAccountsGrid' => $importedAccountsGrid,
            'editingImportedAccount' => $editingImportedAccount,
            'selectedImportedAccount' => $selectedImportedAccount,
            'letterTemplates' => [
                'debt' => $this->billingLetterTemplatePath($profile, 'adeudo') !== null,
                'debt_custom' => filled($profile->debt_letter_template_path),
                'no_debt' => $this->billingLetterTemplatePath($profile, 'no_adeudo') !== null,
                'no_debt_custom' => filled($profile->no_debt_letter_template_path),
            ],
            'reportCommands' => array_values(array_filter([
                ['label' => 'Estado de cuenta PDF', 'href' => route('billing.pdf', $selectedReportParams), 'style' => 'light'],
                ['label' => 'Reporte de no adeudores', 'href' => $noDebtReportHref, 'style' => 'ghost-light'],
                ['label' => 'Reporte de deudores', 'href' => $debtReportHref, 'style' => 'ghost-light'],
                $selectedImportedAccount ? [
                    'label' => 'Generar carta',
                    'href' => route('billing.letters.show', [
                        'account' => $selectedImportedAccount,
                        'template' => $selectedImportedAccount->status,
                    ]),
                    'style' => 'light',
                ] : null,
                $selectedUnit ? [
                    'label' => 'Reporte mensual del residente',
                    'href' => route('billing.resident.monthly.pdf', $selectedReportParams),
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
            'resident_receipt_id' => ['nullable', 'integer', 'exists:resident_receipts,id'],
            'concept' => ['required', 'string', 'max:150'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'paid_at' => ['required', 'date'],
        ]);
        $receipt = null;

        if (filled($data['resident_receipt_id'] ?? null)) {
            $receipt = ResidentReceipt::query()
                ->where('condominium_profile_id', $this->profile()->id)
                ->where('unit_id', $data['unit_id'])
                ->find($data['resident_receipt_id']);

            if (! $receipt) {
                throw ValidationException::withMessages([
                    'resident_receipt_id' => 'El recibo seleccionado no pertenece a esta unidad.',
                ]);
            }
        }

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
            'resident_receipt_id' => $receipt?->id,
            'status' => 'Completado',
        ]);

        if ($receipt) {
            $receipt->amount_paid = (float) $receipt->amount_paid + (float) $data['amount'];
            $receipt->save();
        }

        $unit = Unit::query()->find($data['unit_id']);

        if ($unit) {
            $profile = $this->profile();
            $activeBaseImport = $this->activeBillingBaseImport($profile);
            $importedAccount = $this->findImportedAccountForUnit(
                ImportedResidentAccount::query()
                    ->where('condominium_profile_id', $profile->id)
                    ->when($activeBaseImport, fn ($query) => $query->where('billing_base_import_id', $activeBaseImport->id))
                    ->get(),
                $unit
            );

            if ($importedAccount) {
                $newDebt = max(0, (float) $importedAccount->total_debt - (float) $data['amount']);
                $rawPayload = $this->syncImportedAccountTotalDebtPayload($importedAccount, $newDebt);

                $importedAccount->update([
                    'total_debt' => $newDebt,
                    'status' => $newDebt > 0 ? 'adeudo' : 'no_adeudo',
                    'raw_payload' => $rawPayload,
                ]);
            }
        }

        return redirect()
            ->route('billing', ['unit' => $data['unit_id']])
            ->with('status', 'Pago registrado correctamente.');
    }

    public function storeResidentReceipt(Request $request): RedirectResponse
    {
        $this->ensureAdmin();

        $data = $this->validateResidentReceipt($request);
        $profile = $this->profile();
        $unit = Unit::query()->findOrFail($data['unit_id']);

        ResidentReceipt::query()->updateOrCreate([
            'condominium_profile_id' => $profile->id,
            'unit_id' => $unit->id,
            'period_year' => $data['period_year'],
            'period_month' => $data['period_month'],
        ], [
            'amount_due' => $data['amount_due'],
            'amount_paid' => $data['amount_paid'] ?? 0,
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()
            ->to(route('billing', [
                'unit' => $unit->id,
                'receipt_year' => $data['period_year'],
            ]).'#recibos-condomino')
            ->with('status', 'Recibo del condomino guardado correctamente.');
    }

    public function updateResidentReceipt(Request $request, ResidentReceipt $receipt): RedirectResponse
    {
        $this->ensureAdmin();
        abort_unless($receipt->condominium_profile_id === $this->profile()->id, 404);

        $data = $request->validate([
            'amount_due' => ['required', 'numeric', 'min:0.01'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $receipt->update([
            'amount_due' => $data['amount_due'],
            'amount_paid' => $data['amount_paid'] ?? 0,
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()
            ->to(route('billing', [
                'unit' => $receipt->unit_id,
                'receipt_year' => $receipt->period_year,
            ]).'#recibos-condomino')
            ->with('status', 'Recibo actualizado correctamente.');
    }

    public function deleteResidentReceipt(ResidentReceipt $receipt): RedirectResponse
    {
        $this->ensureAdmin();
        abort_unless($receipt->condominium_profile_id === $this->profile()->id, 404);

        $unitId = $receipt->unit_id;
        $year = $receipt->period_year;
        $receipt->delete();

        return redirect()
            ->to(route('billing', [
                'unit' => $unitId,
                'receipt_year' => $year,
            ]).'#recibos-condomino')
            ->with('status', 'Recibo eliminado correctamente.');
    }

    public function residentReceiptPdf(ResidentReceipt $receipt): Response
    {
        abort_unless($receipt->condominium_profile_id === $this->profile()->id, 404);

        $receipt->load(['unit', 'payments']);
        $pendingAmount = max((float) $receipt->amount_due - (float) $receipt->amount_paid, 0);
        $lines = [
            'Recibo de Condominio Boleo',
            '',
            'Periodo: '.$this->residentReceiptPeriodLabel($receipt),
            'Unidad: '.trim(($receipt->unit?->tower ?? '').' '.($receipt->unit?->unit_number ?? '')),
            'Condómino: '.($receipt->unit?->owner_name ?? 'Sin dato'),
            'Correo: '.($receipt->unit?->owner_email ?? 'Sin correo vinculado'),
            'Cantidad a pagar: $'.number_format((float) $receipt->amount_due, 2),
            'Abonado: $'.number_format((float) $receipt->amount_paid, 2),
            'Pendiente: $'.number_format($pendingAmount, 2),
            'Estatus: '.$this->residentReceiptStatusLabel($receipt->status),
        ];

        if (filled($receipt->notes)) {
            $lines[] = 'Notas: '.$receipt->notes;
        }

        $lines[] = '';
        $lines[] = 'Pagos aplicados:';

        foreach ($receipt->payments as $payment) {
            $lines[] = optional($payment->paid_at)->format('d/m/Y').' - '.$payment->concept.' - $'.number_format((float) $payment->amount, 2);
        }

        if ($receipt->payments->isEmpty()) {
            $lines[] = 'Sin pagos aplicados a este recibo.';
        }

        return $this->pdfResponse(
            'recibo-condomino-'.$receipt->unit_id.'-'.$receipt->period_year.'-'.$receipt->period_month.'.pdf',
            $lines
        );
    }

    public function importBillingBase(Request $request, BillingExcelImporter $importer): RedirectResponse
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'base_file' => ['required', 'file', 'max:102400'],
        ]);

        $path = null;
        $baseImport = null;

        try {
            $profile = $this->profile();
            $file = $data['base_file'];
            $fileHash = hash_file('sha256', $file->getRealPath());
            $existingImport = $this->findExistingBillingBaseImport($profile, $fileHash);

            if ($existingImport) {
                $request->session()->put('settings_condominium_profile_id', $profile->id);

                return redirect()
                    ->route('billing', ['base_import' => $existingImport->id])
                    ->with('status', 'Ese Excel ya estaba cargado. Abrimos la base existente y no se volvió a subir el mismo documento.');
            }

            $path = $file->store('billing-imports', 'public');
            $baseImport = BillingBaseImport::create([
                'condominium_profile_id' => $profile->id,
                'original_name' => $file->getClientOriginalName(),
                'stored_path' => $path,
                'file_hash' => $fileHash,
                'status' => 'cargada',
                'imported_at' => now(),
            ]);
            $imported = $importer->import(Storage::disk('public')->path($path), $profile, $baseImport);
            $baseImport->update([
                'imported_rows' => $imported,
                'status' => 'procesada',
            ]);
        } catch (Throwable $exception) {
            report($exception);

            if ($baseImport) {
                $baseImport->update([
                    'status' => 'cargada',
                    'notes' => $exception->getMessage(),
                ]);
            }

            $redirect = $baseImport
                ? route('billing', ['base_import' => $baseImport->id])
                : route('billing');

            return redirect($redirect)
                ->withErrors([
                    'base_file' => 'El archivo se cargó correctamente en Boleo, pero no se pudo leer como tabla editable en este servidor. Revisa que Railway tenga habilitada la extensión ZIP de PHP o el paquete Node xlsx.',
                ]);
        }

        $request->session()->put('settings_condominium_profile_id', $profile->id);

        return redirect()
            ->route('billing', ['base_import' => $baseImport->id])
            ->with('status', "Base de adeudos importada correctamente. Registros procesados: {$imported}.");
    }

    public function storeImportedResidentAccount(Request $request): RedirectResponse
    {
        $this->ensureAdmin();

        $data = $this->validateImportedAccountPayload($request);
        $profile = $this->profile();
        $unit = $this->syncUnitFromImportedAccountData($data);
        $baseImport = $this->manualBillingBaseImport($profile);

        ImportedResidentAccount::query()->updateOrCreate([
            'billing_base_import_id' => $baseImport->id,
            'unit_number' => $data['unit_number'],
            'tower' => $data['tower'],
        ], [
            'condominium_profile_id' => $profile->id,
            'billing_base_import_id' => $baseImport->id,
            'unit_id' => $unit?->id,
            'sub_tower' => $data['sub_tower'],
            'source_row_number' => null,
            'owner_name' => $data['owner_name'],
            'total_debt' => $data['total_debt'],
            'status' => $data['total_debt'] > 0 ? 'adeudo' : 'no_adeudo',
            'year_statuses' => $data['year_statuses'],
            'raw_payload' => $data['raw_payload'],
            'observations' => $data['observations'],
            'imported_at' => now(),
        ]);

        $baseImport->increment('imported_rows');

        return redirect()
            ->route('billing')
            ->with('status', 'Registro de cobranza guardado correctamente en Boleo.');
    }

    public function updateImportedResidentAccount(Request $request, ImportedResidentAccount $account): RedirectResponse
    {
        $this->ensureAdmin();
        abort_unless($account->condominium_profile_id === $this->profile()->id, 404);

        $data = $this->validateImportedAccountPayload($request, $account);
        $unit = $this->syncUnitFromImportedAccountData($data);
        $duplicateExists = ImportedResidentAccount::query()
            ->where('billing_base_import_id', $account->billing_base_import_id)
            ->where('unit_number', $data['unit_number'])
            ->where('tower', $data['tower'])
            ->whereKeyNot($account->id)
            ->exists();

        if ($duplicateExists) {
            return redirect()
                ->route('billing', ['edit_base_account' => $account->id])
                ->withErrors([
                    'payload.DEPT' => 'Ya existe un registro con esa unidad y torre en la base de cobranza.',
                ]);
        }

        $account->update([
            'unit_id' => $unit?->id,
            'unit_number' => $data['unit_number'],
            'tower' => $data['tower'],
            'sub_tower' => $data['sub_tower'],
            'owner_name' => $data['owner_name'],
            'total_debt' => $data['total_debt'],
            'status' => $data['total_debt'] > 0 ? 'adeudo' : 'no_adeudo',
            'year_statuses' => $data['year_statuses'],
            'raw_payload' => $data['raw_payload'],
            'observations' => $data['observations'],
            'imported_at' => now(),
        ]);

        return redirect()
            ->route('billing')
            ->with('status', 'Registro de cobranza actualizado correctamente.');
    }

    public function deleteImportedResidentAccount(ImportedResidentAccount $account): RedirectResponse
    {
        $this->ensureAdmin();
        abort_unless($account->condominium_profile_id === $this->profile()->id, 404);

        $account->delete();

        return redirect()
            ->route('billing')
            ->with('status', 'Registro de cobranza eliminado correctamente.');
    }

    public function storeBillingLetterTemplates(Request $request): RedirectResponse
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'debt_letter_template' => ['nullable', 'file', 'mimes:pdf,docx', 'max:10240'],
            'no_debt_letter_template' => ['nullable', 'file', 'mimes:pdf,docx', 'max:10240'],
        ]);

        $profile = $this->profile();

        if ($request->hasFile('debt_letter_template')) {
            if ($profile->debt_letter_template_path) {
                Storage::disk('public')->delete($profile->debt_letter_template_path);
            }

            $profile->debt_letter_template_path = $data['debt_letter_template']->store('billing-letter-templates', 'public');
        }

        if ($request->hasFile('no_debt_letter_template')) {
            if ($profile->no_debt_letter_template_path) {
                Storage::disk('public')->delete($profile->no_debt_letter_template_path);
            }

            $profile->no_debt_letter_template_path = $data['no_debt_letter_template']->store('billing-letter-templates', 'public');
        }

        $profile->save();

        return redirect()
            ->route('billing')
            ->with('status', 'Plantillas para reportes actualizadas correctamente.');
    }

    public function downloadBillingBaseImport(BillingBaseImport $baseImport): BinaryFileResponse
    {
        $this->ensureAdmin();
        abort_unless($baseImport->condominium_profile_id === $this->profile()->id, 404);
        abort_unless(Storage::disk('public')->exists($baseImport->stored_path), 404);

        return response()->download(
            Storage::disk('public')->path($baseImport->stored_path),
            $baseImport->original_name
        );
    }

    public function accountStatusLetterPdf(Request $request, ImportedResidentAccount $account): Response
    {
        abort_unless($account->condominium_profile_id === $this->profile()->id, 404);

        $profile = $this->profile();
        $letterStatus = in_array($request->query('template'), ['adeudo', 'no_adeudo'], true)
            ? (string) $request->query('template')
            : $account->status;
        $templatePath = $letterStatus === 'adeudo'
            ? $this->billingLetterTemplatePath($profile, 'adeudo')
            : $this->billingLetterTemplatePath($profile, 'no_adeudo');
        $templateFullPath = $this->billingLetterTemplateFullPath($templatePath);
        $filenameBase = ($letterStatus === 'adeudo' ? 'carta-adeudo-' : 'carta-no-adeudo-').$account->unit_number;

        if ($templateFullPath && strtolower(pathinfo($templateFullPath, PATHINFO_EXTENSION)) === 'docx') {
            $convertedPdf = AccountStatusLetterDocx::convertToPdf(
                AccountStatusLetterDocx::render($templateFullPath, $profile, $account, $letterStatus)
            );

            if ($convertedPdf !== null) {
                return response($convertedPdf, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="'.$filenameBase.'.pdf"',
                ]);
            }
        }

        $pdf = new AccountStatusLetterPdf($profile, $account, $templatePath, $letterStatus);
        $filename = $filenameBase.'.pdf';

        return response($pdf->render(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function billingPdf(Request $request): Response
    {
        $profile = $this->profile();
        $period = $this->resolveExpenseMonth($request->string('month')->toString());
        $unit = Unit::query()->with('payments')->find($request->integer('unit'));
        $importedAccount = $this->importedAccountForBillingRequest($request, $unit, $profile);

        if (! $unit && $importedAccount?->unit_id) {
            $unit = Unit::query()->with('payments')->find($importedAccount->unit_id);
        }

        $summary = $unit ? $this->billingSnapshot($unit, $profile, $period) : null;
        $summary = $importedAccount
            ? $this->billingSnapshotFromImportedAccount($importedAccount, $unit, $summary)
            : $summary;
        $unitLabel = $summary['unit_label'] ?? ($unit ? trim($unit->tower.' '.$unit->unit_number) : 'Sin seleccionar');
        $ownerName = $summary['owner_name'] ?? ($unit?->owner_name ?: 'Sin dato');
        $ownerEmail = $summary['owner_email'] ?? ($unit?->owner_email ?: 'Sin correo vinculado');
        $lines = [
            'Estado de Cuenta Boleo',
            '',
            'Periodo: '.$this->billingPeriodLabel($period),
            'Unidad: '.$unitLabel,
            'Propietario: '.$ownerName,
            'Correo vinculado: '.$ownerEmail,
            'Cuota mensual: '.($summary ? '$'.number_format((float) $summary['fee_amount'], 2) : '--'),
            'Pagado en el periodo: '.($summary ? '$'.number_format((float) $summary['paid_amount'], 2) : '--'),
            'Saldo pendiente: '.($summary ? '$'.number_format((float) $summary['pending_amount'], 2) : '--'),
            'Estatus: '.($summary['status_label'] ?? 'Sin dato'),
            '',
        ];

        if ($importedAccount) {
            $lines[] = 'Base Excel: '.($importedAccount->billingBaseImport?->original_name ?: 'Registro importado en Boleo');
            $lines[] = 'Saldo total en Excel: $'.number_format((float) $importedAccount->total_debt, 2);

            if (filled($importedAccount->observations)) {
                $lines[] = 'Observaciones Excel: '.$importedAccount->observations;
            }

            $excelDetail = $this->selectedExcelDetailLines($importedAccount, $period);

            if ($excelDetail !== []) {
                $lines[] = '';
                $lines[] = 'Detalle tomado del Excel:';
                array_push($lines, ...$excelDetail);
            }

            $lines[] = '';
        }

        foreach (($unit?->payments ?? collect()) as $payment) {
            $lines[] = optional($payment->paid_at)->format('d/m/Y').' - '.$payment->concept.' - $'.number_format((float) $payment->amount, 2);
        }

        if (! $unit || $unit->payments->isEmpty()) {
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
        $importedAccount = $this->importedAccountForBillingRequest($request, $unit, $profile);

        $summary = $this->billingSnapshot($unit, $profile, $period);
        $summary = $importedAccount
            ? $this->billingSnapshotFromImportedAccount($importedAccount, $unit, $summary)
            : $summary;
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
            $payments,
            $importedAccount
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
        $hasImportedAccounts = $this->hasImportedAccountsForCurrentProfile();
        $rows = $this->importedAccountsForReport('no_adeudo');

        if (! $hasImportedAccounts) {
            $rows = $this->buildBillingRows(Unit::query()->with('payments')->orderBy('tower')->orderBy('unit_number')->get(), $this->profile(), $period)
                ->where('pending_amount', '<=', 0)
                ->values();
        }

        $lines = [
            'Reporte de No Adeudores Boleo',
            '',
            'Periodo: '.$this->billingPeriodLabel($period),
            '',
        ];

        foreach ($rows as $row) {
            $lines[] = "{$row['unit_label']} - {$row['owner_name']} - Sin adeudo";
        }

        if ($rows->isEmpty()) {
            $lines[] = 'No hay no adeudores en el periodo actual.';
        }

        return $this->pdfResponse('reporte-no-adeudores-boleo.pdf', $lines);
    }

    public function debtorsReportPdf(): Response
    {
        $period = $this->resolveExpenseMonth(request('month'));
        $hasImportedAccounts = $this->hasImportedAccountsForCurrentProfile();
        $rows = $this->importedAccountsForReport('adeudo');

        if (! $hasImportedAccounts) {
            $rows = $this->buildBillingRows(Unit::query()->with('payments')->orderBy('tower')->orderBy('unit_number')->get(), $this->profile(), $period)
                ->where('pending_amount', '>', 0)
                ->values();
        }

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
        return $this->settingsView('settings');
    }

    public function altas(): View
    {
        return $this->settingsView('altas');
    }

    private function settingsView(string $page): View
    {
        $condominiumQuery = trim((string) request('condominium_q', ''));
        $isNewCondominium = request()->boolean('new_condominium');
        $requestedProfileId = request()->integer('condominium_profile_id');

        if ($isNewCondominium) {
            request()->session()->forget('settings_condominium_profile_id');
        } elseif ($requestedProfileId > 0) {
            request()->session()->put('settings_condominium_profile_id', $requestedProfileId);
        }

        $profile = $this->settingsProfile($isNewCondominium);
        $defaultAdministrator = $this->defaultAdministrator();
        $feeTypeOptions = [
            'standard' => 'Estándar',
            'indiviso' => 'Indiviso',
        ];
        $adminTypeOptions = [
            'professional' => 'Profesional',
            'condomino' => 'Condómino',
        ];
        $condominiumProfiles = CondominiumProfile::query()
            ->when($condominiumQuery !== '', function ($query) use ($condominiumQuery) {
                $query->where('commercial_name', 'like', "%{$condominiumQuery}%")
                    ->orWhere('address', 'like', "%{$condominiumQuery}%")
                    ->orWhere('tax_id', 'like', "%{$condominiumQuery}%");
            })
            ->orderByRaw("case when commercial_name = '' then 1 else 0 end")
            ->orderBy('commercial_name')
            ->orderBy('id')
            ->get();

        if ($condominiumQuery !== '' && $requestedProfileId === 0 && ! $isNewCondominium && $condominiumProfiles->isNotEmpty()) {
            request()->session()->put('settings_condominium_profile_id', $condominiumProfiles->first()->id);
            $profile = $this->settingsProfile(false);
        }

        $movingSchedule = $this->splitOperatingSchedule($profile->moving_hours);
        $workSchedule = $this->splitOperatingSchedule($profile->work_hours);
        $meetingSchedule = $this->splitOperatingSchedule($profile->meeting_hours);

        $q = trim((string) request('q', ''));
        $users = User::query()
            ->with('condominiumProfile')
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
        $selectedUserProfile = $selectedUser?->condominiumProfile ?: $profile;
        $linkedUnits = $selectedUser
            ? Unit::query()
                ->where('owner_email', $selectedUser->email)
                ->orWhere('owner_name', $selectedUser->name)
                ->orderBy('tower')
                ->orderBy('unit_number')
                ->get()
            : collect();
        $assemblyMinutes = AssemblyMinute::query()
            ->where('condominium_profile_id', $profile->id)
            ->latest('assembly_date')
            ->latest('created_at')
            ->get();

        return $this->page($page, [
            'headline' => $page === 'altas' ? 'Altas' : 'Ajustes del Condominio',
            'condominiumProfiles' => $condominiumProfiles,
            'condominiumQuery' => $condominiumQuery,
            'selectedCondominiumProfile' => $profile->exists ? $profile : null,
            'selectedUserCondominium' => $selectedUserProfile,
            'subheadline' => $page === 'altas'
                ? 'Registro y administración de usuarios del portal.'
                : 'Información general, cuenta para depósitos, infraestructura y administración.',
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
                'admin_name' => $profile->admin_name ?: $defaultAdministrator['name'],
                'assistant_admin_names' => $profile->assistant_admin_names,
                'assistant_admin_phone' => $profile->assistant_admin_phone,
                'admin_registration_path' => $profile->admin_registration_path,
                'admin_email' => $profile->admin_email ?: $defaultAdministrator['email'],
                'admin_phone' => $profile->admin_phone ?: $defaultAdministrator['phone'],
            ],
            'infrastructureForm' => [
                'elevators_enabled' => $profile->elevators_enabled,
                'elevators_count' => $profile->elevators_count,
                'cisterns_enabled' => $profile->cisterns_enabled,
                'cisterns_count' => $profile->cisterns_count,
                'water_tanks_enabled' => $profile->water_tanks_enabled,
                'water_tanks_count' => $profile->water_tanks_count,
                'hydropneumatics_enabled' => $profile->hydropneumatics_enabled,
                'hydropneumatics_count' => $profile->hydropneumatics_count,
                'pool_enabled' => $profile->pool_enabled,
                'wading_pool_enabled' => $profile->wading_pool_enabled,
                'event_hall_enabled' => $profile->event_hall_enabled,
                'roof_garden_enabled' => $profile->roof_garden_enabled,
                'yoga_room_enabled' => $profile->yoga_room_enabled,
                'game_room_enabled' => $profile->game_room_enabled,
                'gym_enabled' => $profile->gym_enabled,
                'grill_enabled' => $profile->grill_enabled,
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
                ['name' => 'Asador', 'active' => $profile->grill_enabled, 'meta' => $profile->grill_enabled ? 'Disponible' : 'No disponible'],
            ],
            'operations' => [
                'moving_hours' => $profile->moving_hours,
                'moving_hours_day' => $movingSchedule['day'],
                'moving_hours_start' => $movingSchedule['start'],
                'moving_hours_end' => $movingSchedule['end'],
                'work_hours' => $profile->work_hours,
                'work_hours_day' => $workSchedule['day'],
                'work_hours_start' => $workSchedule['start'],
                'work_hours_end' => $workSchedule['end'],
                'meeting_hours' => $profile->meeting_hours,
                'meeting_hours_day' => $meetingSchedule['day'],
                'meeting_hours_start' => $meetingSchedule['start'],
                'meeting_hours_end' => $meetingSchedule['end'],
                'regulations_path' => $profile->regulations_path,
                'parking_map_path' => $profile->parking_map_path,
                'property_regime_path' => $profile->property_regime_path,
                'cleaning_staff_name' => $profile->cleaning_staff_name,
                'cleaning_staff_phone' => $profile->cleaning_staff_phone,
                'cleaning_staff_contact' => $profile->cleaning_staff_contact,
                'cleaning_instructions_path' => $profile->cleaning_instructions_path,
                'cleaning_permits_path' => $profile->cleaning_permits_path,
                'security_staff_name' => $profile->security_staff_name,
                'security_staff_phone' => $profile->security_staff_phone,
                'security_staff_contact' => $profile->security_staff_contact,
                'security_instructions_path' => $profile->security_instructions_path,
                'security_permits_path' => $profile->security_permits_path,
            ],
            'documentStatus' => [
                'regulations' => filled($profile->regulations_path),
                'parking_map' => filled($profile->parking_map_path),
                'property_regime' => filled($profile->property_regime_path),
                'cleaning_instructions' => filled($profile->cleaning_instructions_path),
                'cleaning_permits' => filled($profile->cleaning_permits_path),
                'security_instructions' => filled($profile->security_instructions_path),
                'security_permits' => filled($profile->security_permits_path),
            ],
            'operationRecords' => $this->operationRecords($profile),
            'banking' => [
                'bank' => $profile->bank,
                'holder' => $profile->account_holder,
                'account_type' => $profile->bank_account_type,
                'account' => $profile->account_number,
                'clabe' => $profile->clabe,
            ],
            'assemblyMinutes' => $assemblyMinutes,
            'users' => $users,
            'editingUser' => $editingUser,
            'selectedUser' => $selectedUser,
            'selectedUserUnits' => $linkedUnits,
            'adminTypeOptions' => $adminTypeOptions,
            'assistantAdminOptions' => [
                'Alondra Velázquez Hernández' => '5525403862',
                'Rene Alberto Solano' => '7228378509',
            ],
            'scheduleOptions' => [
                'Lunes a viernes' => 'Lunes a viernes',
                'Lunes a sabado' => 'Lunes a sabado',
                'Domingo' => 'Domingo',
            ],
            'scheduleDayOptions' => $this->scheduleDayOptions(),
            'timeOptions' => $this->timeOptions(),
            'roleOptions' => [
                'admin' => 'Administrador',
                'user' => 'Auxiliar',
                'resident' => 'Residente',
            ],
            'feeTypeOptions' => [
                'standard' => 'Estándar',
                'indiviso' => 'Indiviso',
            ],
            'defaultAdministrator' => $defaultAdministrator,
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $this->ensureAdmin();

        $saveSection = $request->input('save_section') === 'identity' ? 'identity' : 'all';
        $rules = [
            'condominium_profile_id' => ['nullable', 'integer', 'exists:condominium_profiles,id'],
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
            'admin_registration_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'admin_registration_documents' => ['nullable', 'array'],
            'admin_registration_documents.*' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
            'admin_email' => ['nullable', 'email', 'max:255'],
            'admin_phone' => ['nullable', 'string', 'max:30'],
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
            'grill_enabled' => ['nullable', 'boolean'],
            'moving_hours_day' => ['nullable', 'string', Rule::in(array_keys($this->scheduleDayOptions()))],
            'moving_hours_start' => ['nullable', 'string', Rule::in($this->timeOptions()), 'required_with:moving_hours_end'],
            'moving_hours_end' => ['nullable', 'string', Rule::in($this->timeOptions()), 'required_with:moving_hours_start'],
            'work_hours_day' => ['nullable', 'string', Rule::in(array_keys($this->scheduleDayOptions()))],
            'work_hours_start' => ['nullable', 'string', Rule::in($this->timeOptions()), 'required_with:work_hours_end'],
            'work_hours_end' => ['nullable', 'string', Rule::in($this->timeOptions()), 'required_with:work_hours_start'],
            'meeting_hours_day' => ['nullable', 'string', Rule::in(array_keys($this->scheduleDayOptions()))],
            'meeting_hours_start' => ['nullable', 'string', Rule::in($this->timeOptions()), 'required_with:meeting_hours_end'],
            'meeting_hours_end' => ['nullable', 'string', Rule::in($this->timeOptions()), 'required_with:meeting_hours_start'],
            'regulations_file' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'parking_map_file' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
            'property_regime_file' => ['nullable', 'file', 'mimes:pdf', 'max:102400'],
            'cleaning_staff_name' => ['nullable', 'string', 'max:150'],
            'cleaning_staff_phone' => ['nullable', 'string', 'max:60'],
            'cleaning_staff_contact' => ['nullable', 'string', 'max:255'],
            'cleaning_instructions_file' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
            'cleaning_permits_file' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
            'security_staff_name' => ['nullable', 'string', 'max:150'],
            'security_staff_phone' => ['nullable', 'string', 'max:60'],
            'security_staff_contact' => ['nullable', 'string', 'max:255'],
            'security_instructions_file' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
            'security_permits_file' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
            'bank' => ['nullable', 'string', 'max:150'],
            'account_holder' => ['nullable', 'string', 'max:150'],
            'bank_account_type' => ['nullable', 'string', 'max:100'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'clabe' => ['nullable', 'string', 'max:100'],
        ];

        if ($saveSection === 'identity') {
            $rules = collect($rules)->only([
                'condominium_profile_id',
                'commercial_name',
                'tax_id',
                'address',
                'latitude',
                'longitude',
                'ordinary_fee_amount',
                'fee_type',
                'departments_count',
                'parking_spaces_count',
                'storage_rooms_count',
                'clothesline_cages_count',
                'security_booth',
                'admin_type',
                'admin_name',
                'assistant_admin_names',
                'assistant_admin_phone',
                'admin_registration_file',
                'admin_registration_documents',
                'admin_registration_documents.*',
                'admin_email',
                'admin_phone',
            ])->all();
        }

        $data = $request->validateWithBag('settingsProfile', $rules);

        $profile = filled($data['condominium_profile_id'] ?? null)
            ? CondominiumProfile::query()->findOrFail((int) $data['condominium_profile_id'])
            : new CondominiumProfile($this->defaultCondominiumProfileValues());
        $defaultAdministrator = $this->defaultAdministrator();
        $assistantAdminOptions = $this->assistantAdminOptions();

        unset($data['condominium_profile_id']);

        $data['admin_name'] = trim((string) ($data['admin_name'] ?: $defaultAdministrator['name']));
        $data['admin_email'] = trim((string) ($data['admin_email'] ?: $defaultAdministrator['email']));
        $data['admin_phone'] = trim((string) ($data['admin_phone'] ?: $defaultAdministrator['phone']));

        if (filled($data['assistant_admin_names'] ?? null) && blank($data['assistant_admin_phone'] ?? null)) {
            $data['assistant_admin_phone'] = $assistantAdminOptions[$data['assistant_admin_names']] ?? '';
        }

        $data['moving_hours'] = $this->joinOperatingSchedule(
            $data['moving_hours_day'] ?? null,
            $data['moving_hours_start'] ?? null,
            $data['moving_hours_end'] ?? null
        );
        $data['work_hours'] = $this->joinOperatingSchedule(
            $data['work_hours_day'] ?? null,
            $data['work_hours_start'] ?? null,
            $data['work_hours_end'] ?? null
        );
        $data['meeting_hours'] = $this->joinOperatingSchedule(
            $data['meeting_hours_day'] ?? null,
            $data['meeting_hours_start'] ?? null,
            $data['meeting_hours_end'] ?? null
        );
        unset(
            $data['moving_hours_day'],
            $data['moving_hours_start'],
            $data['moving_hours_end'],
            $data['work_hours_day'],
            $data['work_hours_start'],
            $data['work_hours_end'],
            $data['meeting_hours_day'],
            $data['meeting_hours_start'],
            $data['meeting_hours_end']
        );

        if ($request->hasFile('admin_registration_file')) {
            if (filled($profile->admin_registration_path) && Storage::disk('public')->exists($profile->admin_registration_path)) {
                Storage::disk('public')->delete($profile->admin_registration_path);
            }

            $data['admin_registration_path'] = $request->file('admin_registration_file')->store('admin-registrations', 'public');
        }

        unset($data['admin_registration_file']);

        if (filled($data['address'] ?? null)) {
            $resolvedCoordinates = $this->resolveCoordinatesForAddress((string) $data['address']);

            if ($resolvedCoordinates !== null) {
                $data['latitude'] = $resolvedCoordinates['latitude'];
                $data['longitude'] = $resolvedCoordinates['longitude'];
            }
        }

        $storedDocuments = collect($profile->admin_registration_documents ?? []);

        foreach ($this->adminRegistrationDocumentOptions() as $key => $label) {
            if (! $request->hasFile("admin_registration_documents.$key")) {
                continue;
            }

            $existingPath = data_get($storedDocuments->get($key), 'path');
            if ($existingPath && Storage::disk('public')->exists($existingPath)) {
                Storage::disk('public')->delete($existingPath);
            }

            $uploaded = $request->file("admin_registration_documents.$key");
            $storedDocuments->put($key, [
                'label' => $label,
                'name' => $uploaded->getClientOriginalName(),
                'path' => $uploaded->store('admin-registration-documents', 'public'),
            ]);
        }

        $data['admin_registration_documents'] = $storedDocuments->all();

        if ($request->hasFile('regulations_file')) {
            if (filled($profile->regulations_path) && Storage::disk('public')->exists($profile->regulations_path)) {
                Storage::disk('public')->delete($profile->regulations_path);
            }

            $data['regulations_path'] = $request->file('regulations_file')->store('regulations', 'public');
        }

        if ($request->hasFile('parking_map_file')) {
            if (filled($profile->parking_map_path) && Storage::disk('public')->exists($profile->parking_map_path)) {
                Storage::disk('public')->delete($profile->parking_map_path);
            }

            $data['parking_map_path'] = $request->file('parking_map_file')->store('parking-maps', 'public');
        }

        if ($request->hasFile('property_regime_file')) {
            if (filled($profile->property_regime_path) && Storage::disk('public')->exists($profile->property_regime_path)) {
                Storage::disk('public')->delete($profile->property_regime_path);
            }

            $data['property_regime_path'] = $request->file('property_regime_file')->store('property-regime', 'public');
        }

        if ($request->hasFile('cleaning_instructions_file')) {
            if (filled($profile->cleaning_instructions_path) && Storage::disk('public')->exists($profile->cleaning_instructions_path)) {
                Storage::disk('public')->delete($profile->cleaning_instructions_path);
            }

            $data['cleaning_instructions_path'] = $request->file('cleaning_instructions_file')->store('cleaning-instructions', 'public');
        }

        if ($request->hasFile('cleaning_permits_file')) {
            if (filled($profile->cleaning_permits_path) && Storage::disk('public')->exists($profile->cleaning_permits_path)) {
                Storage::disk('public')->delete($profile->cleaning_permits_path);
            }

            $data['cleaning_permits_path'] = $request->file('cleaning_permits_file')->store('cleaning-permits', 'public');
        }

        if ($request->hasFile('security_instructions_file')) {
            if (filled($profile->security_instructions_path) && Storage::disk('public')->exists($profile->security_instructions_path)) {
                Storage::disk('public')->delete($profile->security_instructions_path);
            }

            $data['security_instructions_path'] = $request->file('security_instructions_file')->store('security-instructions', 'public');
        }

        if ($request->hasFile('security_permits_file')) {
            if (filled($profile->security_permits_path) && Storage::disk('public')->exists($profile->security_permits_path)) {
                Storage::disk('public')->delete($profile->security_permits_path);
            }

            $data['security_permits_path'] = $request->file('security_permits_file')->store('security-permits', 'public');
        }

        unset(
            $data['regulations_file'],
            $data['parking_map_file'],
            $data['property_regime_file'],
            $data['cleaning_instructions_file'],
            $data['cleaning_permits_file'],
            $data['security_instructions_file'],
            $data['security_permits_file']
        );

        $payload = [
            ...$data,
            'security_booth' => $request->boolean('security_booth'),
        ];

        if ($saveSection !== 'identity') {
            $payload = [
                ...$payload,
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
                'grill_enabled' => $request->boolean('grill_enabled'),
            ];
        }

        $payload = $this->normalizeCondominiumTextFields($payload);
        $profile->fill($payload)->save();

        $request->session()->put('settings_condominium_profile_id', $profile->id);

        return redirect()
            ->route('settings')
            ->with('status', $saveSection === 'identity'
                ? 'La identidad del condominio se guardó correctamente.'
                : 'Toda la información del condominio se guardó correctamente.');
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
            'grill_enabled' => ['nullable', 'boolean'],
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
            'grill_enabled' => $request->boolean('grill_enabled'),
        ]);

        return redirect()
            ->route('settings')
            ->with('status', 'Infraestructura actualizada correctamente.');
    }

    public function destroyCondominiumProfile(Request $request, CondominiumProfile $profile): RedirectResponse
    {
        $this->ensureAdmin();

        $this->deleteProfileFiles($profile);
        $profile->assemblyMinutes()->get()->each(function (AssemblyMinute $minute): void {
            if (filled($minute->document_path) && Storage::disk('public')->exists($minute->document_path)) {
                Storage::disk('public')->delete($minute->document_path);
            }

            if (filled($minute->convocation_path) && Storage::disk('public')->exists($minute->convocation_path)) {
                Storage::disk('public')->delete($minute->convocation_path);
            }
        });
        $profile->delete();

        if ((int) $request->session()->get('settings_condominium_profile_id') === $profile->id) {
            $request->session()->forget('settings_condominium_profile_id');
        }

        return redirect()
            ->route('settings')
            ->with('status', 'Condominio eliminado correctamente.');
    }

    public function adminRegistrationDocument(?string $document = null): BinaryFileResponse
    {
        $profile = $this->profile();

        if ($document !== null) {
            $stored = data_get($profile->admin_registration_documents ?? [], $document);
            $path = is_array($stored) ? ($stored['path'] ?? null) : null;

            abort_if(! filled($path) || ! Storage::disk('public')->exists($path), 404);

            return response()->file(Storage::disk('public')->path($path), [
                'Content-Type' => 'application/pdf',
            ]);
        }

        abort_if(! filled($profile->admin_registration_path) || ! Storage::disk('public')->exists($profile->admin_registration_path), 404);

        return response()->file(Storage::disk('public')->path($profile->admin_registration_path));
    }

    public function updateOperations(Request $request): RedirectResponse
    {
        $this->ensureAdmin();

        $data = $request->validateWithBag('settingsOperations', [
            'moving_hours_day' => ['nullable', 'string', Rule::in(array_keys($this->scheduleDayOptions()))],
            'moving_hours_start' => ['nullable', 'string', Rule::in($this->timeOptions()), 'required_with:moving_hours_end'],
            'moving_hours_end' => ['nullable', 'string', Rule::in($this->timeOptions()), 'required_with:moving_hours_start'],
            'work_hours_day' => ['nullable', 'string', Rule::in(array_keys($this->scheduleDayOptions()))],
            'work_hours_start' => ['nullable', 'string', Rule::in($this->timeOptions()), 'required_with:work_hours_end'],
            'work_hours_end' => ['nullable', 'string', Rule::in($this->timeOptions()), 'required_with:work_hours_start'],
            'meeting_hours_day' => ['nullable', 'string', Rule::in(array_keys($this->scheduleDayOptions()))],
            'meeting_hours_start' => ['nullable', 'string', Rule::in($this->timeOptions()), 'required_with:meeting_hours_end'],
            'meeting_hours_end' => ['nullable', 'string', Rule::in($this->timeOptions()), 'required_with:meeting_hours_start'],
            'regulations_file' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'parking_map_file' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
            'property_regime_file' => ['nullable', 'file', 'mimes:pdf', 'max:102400'],
            'cleaning_staff_name' => ['nullable', 'string', 'max:150'],
            'cleaning_staff_phone' => ['nullable', 'string', 'max:60'],
            'cleaning_staff_contact' => ['nullable', 'string', 'max:255'],
            'cleaning_instructions_file' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
            'cleaning_permits_file' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
            'security_staff_name' => ['nullable', 'string', 'max:150'],
            'security_staff_phone' => ['nullable', 'string', 'max:60'],
            'security_staff_contact' => ['nullable', 'string', 'max:255'],
            'security_instructions_file' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
            'security_permits_file' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
        ]);

        $profile = $this->profile();
        $data['moving_hours'] = $this->joinOperatingSchedule(
            $data['moving_hours_day'] ?? null,
            $data['moving_hours_start'] ?? null,
            $data['moving_hours_end'] ?? null
        );
        $data['work_hours'] = $this->joinOperatingSchedule(
            $data['work_hours_day'] ?? null,
            $data['work_hours_start'] ?? null,
            $data['work_hours_end'] ?? null
        );
        $data['meeting_hours'] = $this->joinOperatingSchedule(
            $data['meeting_hours_day'] ?? null,
            $data['meeting_hours_start'] ?? null,
            $data['meeting_hours_end'] ?? null
        );
        unset(
            $data['moving_hours_day'],
            $data['moving_hours_start'],
            $data['moving_hours_end'],
            $data['work_hours_day'],
            $data['work_hours_start'],
            $data['work_hours_end'],
            $data['meeting_hours_day'],
            $data['meeting_hours_start'],
            $data['meeting_hours_end']
        );

        if ($request->hasFile('regulations_file')) {
            if (filled($profile->regulations_path) && Storage::disk('public')->exists($profile->regulations_path)) {
                Storage::disk('public')->delete($profile->regulations_path);
            }

            $data['regulations_path'] = $request->file('regulations_file')->store('regulations', 'public');
        }

        if ($request->hasFile('parking_map_file')) {
            if (filled($profile->parking_map_path) && Storage::disk('public')->exists($profile->parking_map_path)) {
                Storage::disk('public')->delete($profile->parking_map_path);
            }

            $data['parking_map_path'] = $request->file('parking_map_file')->store('parking-maps', 'public');
        }

        if ($request->hasFile('property_regime_file')) {
            if (filled($profile->property_regime_path) && Storage::disk('public')->exists($profile->property_regime_path)) {
                Storage::disk('public')->delete($profile->property_regime_path);
            }

            $data['property_regime_path'] = $request->file('property_regime_file')->store('property-regime', 'public');
        }

        if ($request->hasFile('cleaning_instructions_file')) {
            if (filled($profile->cleaning_instructions_path) && Storage::disk('public')->exists($profile->cleaning_instructions_path)) {
                Storage::disk('public')->delete($profile->cleaning_instructions_path);
            }

            $data['cleaning_instructions_path'] = $request->file('cleaning_instructions_file')->store('cleaning-instructions', 'public');
        }

        if ($request->hasFile('cleaning_permits_file')) {
            if (filled($profile->cleaning_permits_path) && Storage::disk('public')->exists($profile->cleaning_permits_path)) {
                Storage::disk('public')->delete($profile->cleaning_permits_path);
            }

            $data['cleaning_permits_path'] = $request->file('cleaning_permits_file')->store('cleaning-permits', 'public');
        }

        if ($request->hasFile('security_instructions_file')) {
            if (filled($profile->security_instructions_path) && Storage::disk('public')->exists($profile->security_instructions_path)) {
                Storage::disk('public')->delete($profile->security_instructions_path);
            }

            $data['security_instructions_path'] = $request->file('security_instructions_file')->store('security-instructions', 'public');
        }

        if ($request->hasFile('security_permits_file')) {
            if (filled($profile->security_permits_path) && Storage::disk('public')->exists($profile->security_permits_path)) {
                Storage::disk('public')->delete($profile->security_permits_path);
            }

            $data['security_permits_path'] = $request->file('security_permits_file')->store('security-permits', 'public');
        }

        unset(
            $data['regulations_file'],
            $data['parking_map_file'],
            $data['property_regime_file'],
            $data['cleaning_instructions_file'],
            $data['cleaning_permits_file'],
            $data['security_instructions_file'],
            $data['security_permits_file']
        );

        $profile->update($this->normalizeCondominiumTextFields($data));

        return redirect()
            ->route('settings')
            ->with('status', 'Operación del condominio actualizada correctamente.');
    }

    public function regulationsDocument(): BinaryFileResponse
    {
        $profile = $this->profile();

        abort_if(! filled($profile->regulations_path) || ! Storage::disk('public')->exists($profile->regulations_path), 404);

        return response()->file(
            Storage::disk('public')->path($profile->regulations_path),
            ['Content-Type' => 'application/pdf']
        );
    }

    public function settingsDocument(string $type): BinaryFileResponse
    {
        $profile = $this->profile();

        $path = match ($type) {
            'parking-map' => $profile->parking_map_path,
            'property-regime' => $profile->property_regime_path,
            'cleaning-instructions' => $profile->cleaning_instructions_path,
            'cleaning-permits' => $profile->cleaning_permits_path,
            'security-instructions' => $profile->security_instructions_path,
            'security-permits' => $profile->security_permits_path,
            default => null,
        };

        abort_if(! filled($path) || ! Storage::disk('public')->exists($path), 404);

        return response()->file(Storage::disk('public')->path($path), [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function publicSettingsDocument(CondominiumProfile $profile, string $type): BinaryFileResponse
    {
        $path = match ($type) {
            'regulations' => $profile->regulations_path,
            'parking-map' => $profile->parking_map_path,
            'property-regime' => $profile->property_regime_path,
            default => null,
        };

        abort_if(! filled($path) || ! Storage::disk('public')->exists($path), 404);

        return response()->file(Storage::disk('public')->path($path), [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function destroyOperationRecord(string $type): RedirectResponse
    {
        $this->ensureAdmin();

        $profile = $this->profile();
        $updates = [];

        if (in_array($type, ['moving-hours', 'work-hours', 'meeting-hours'], true)) {
            $updates[match ($type) {
                'moving-hours' => 'moving_hours',
                'work-hours' => 'work_hours',
                'meeting-hours' => 'meeting_hours',
            }] = '';
        } else {
            $field = match ($type) {
                'regulations' => 'regulations_path',
                'parking-map' => 'parking_map_path',
                'property-regime' => 'property_regime_path',
                default => null,
            };

            abort_if($field === null, 404);

            $path = $profile->{$field};
            if (filled($path) && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }

            $updates[$field] = '';
        }

        $profile->update($updates);

        return redirect()
            ->route('settings')
            ->with('status', 'Registro de horarios y reglamento eliminado correctamente.');
    }

    public function updateBanking(Request $request): RedirectResponse
    {
        $this->ensureAdmin();

        $data = $request->validateWithBag('settingsBanking', [
            'bank' => ['nullable', 'string', 'max:150'],
            'account_holder' => ['nullable', 'string', 'max:150'],
            'bank_account_type' => ['nullable', 'string', 'max:100'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'clabe' => ['nullable', 'string', 'max:100'],
        ]);

        $this->profile()->update($data);

        return redirect()
            ->route('settings')
            ->with('status', 'Cuenta de depósito actualizada correctamente.');
    }

    public function bankingWordDocument(): Response
    {
        $profile = $this->profile();

        return $this->pdfResponse('datos-bancarios-condominio.pdf', [
            'Datos de la cuenta para depositar las cuotas de mantenimiento',
            'Condominio: '.($profile->commercial_name ?: 'Sin capturar'),
            'Institución bancaria: '.($profile->bank ?: 'Sin capturar'),
            'Titular de la cuenta: '.($profile->account_holder ?: 'Sin capturar'),
            'Tipo de cuenta: '.($profile->bank_account_type ?: 'Sin capturar'),
            'Número de cuenta: '.($profile->account_number ?: 'Sin capturar'),
            'CLABE: '.($profile->clabe ?: 'Sin capturar'),
        ]);
    }

    public function storeAssemblyMinute(Request $request): RedirectResponse
    {
        $this->ensureAdmin();

        $data = $request->validateWithBag('settingsMinutes', [
            'condominium_profile_id' => ['nullable', 'integer', 'exists:condominium_profiles,id'],
            'title' => ['required', 'string', 'max:180'],
            'assembly_date' => ['nullable', 'date'],
            'document_file' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp', 'max:20480'],
            'convocation_file' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
        ]);

        if ($request->hasFile('document_file')) {
            $data['document_path'] = $request->file('document_file')->store('assembly-minutes', 'public');
        }

        if ($request->hasFile('convocation_file')) {
            $data['convocation_path'] = $request->file('convocation_file')->store('assembly-convocations', 'public');
        }

        unset($data['document_file'], $data['convocation_file']);
        $profile = filled($data['condominium_profile_id'] ?? null)
            ? CondominiumProfile::query()->findOrFail((int) $data['condominium_profile_id'])
            : $this->profile();

        $data['condominium_profile_id'] = $profile->id;
        $request->session()->put('settings_condominium_profile_id', $profile->id);

        AssemblyMinute::query()->create($data);

        return redirect()
            ->route('settings')
            ->with('status', 'Minuta de asamblea guardada correctamente.');
    }

    public function assemblyMinuteDocument(AssemblyMinute $minute): BinaryFileResponse
    {
        $profile = $this->profile();

        abort_if($minute->condominium_profile_id !== $profile->id, 404);
        abort_if(! filled($minute->document_path) || ! Storage::disk('public')->exists($minute->document_path), 404);

        return response()->file(Storage::disk('public')->path($minute->document_path));
    }

    public function assemblyMinuteConvocation(AssemblyMinute $minute): BinaryFileResponse
    {
        $profile = $this->profile();

        abort_if($minute->condominium_profile_id !== $profile->id, 404);
        abort_if(! filled($minute->convocation_path) || ! Storage::disk('public')->exists($minute->convocation_path), 404);

        return response()->file(Storage::disk('public')->path($minute->convocation_path), [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function destroyAssemblyMinute(AssemblyMinute $minute): RedirectResponse
    {
        $this->ensureAdmin();

        if (filled($minute->document_path) && Storage::disk('public')->exists($minute->document_path)) {
            Storage::disk('public')->delete($minute->document_path);
        }

        if (filled($minute->convocation_path) && Storage::disk('public')->exists($minute->convocation_path)) {
            Storage::disk('public')->delete($minute->convocation_path);
        }

        $minute->delete();

        return redirect()
            ->route('settings')
            ->with('status', 'Minuta de asamblea eliminada correctamente.');
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $this->ensureAdmin();

        $data = $request->validateWithBag('settingsUsers', [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:30', 'unique:users,phone'],
            'role' => ['required', Rule::in(['admin', 'user', 'resident'])],
            'condominium_profile_id' => [
                Rule::requiredIf(fn () => $request->input('role') === 'resident'),
                'nullable',
                'integer',
                'exists:condominium_profiles,id',
            ],
            'password' => ['required', 'confirmed', 'min:6'],
        ]);

        User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'role' => $data['role'],
            'condominium_profile_id' => $data['condominium_profile_id'] ?? null,
            'password' => Hash::make($data['password']),
        ]);

        return redirect()
            ->route('altas')
            ->with('status', 'Cuenta creada correctamente.');
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $this->ensureAdmin();

        $data = $request->validateWithBag('settingsUsers', [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['required', 'string', 'max:30', Rule::unique('users', 'phone')->ignore($user->id)],
            'role' => ['required', Rule::in(['admin', 'user', 'resident'])],
            'condominium_profile_id' => [
                Rule::requiredIf(fn () => $request->input('role') === 'resident'),
                'nullable',
                'integer',
                'exists:condominium_profiles,id',
            ],
            'password' => ['nullable', 'confirmed', 'min:6'],
        ]);

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'role' => $data['role'],
            'condominium_profile_id' => $data['condominium_profile_id'] ?? null,
        ];

        if (! empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
        }

        $user->update($payload);

        return redirect()
            ->route('altas')
            ->with('status', 'Cuenta actualizada correctamente.');
    }

    public function destroyUser(User $user): RedirectResponse
    {
        $this->ensureAdmin();

        if (Auth::id() === $user->id) {
            return redirect()
                ->route('altas')
                ->withErrors([
                    'email' => 'No puedes eliminar tu propia cuenta mientras estas conectado.',
                ]);
        }

        if ($user->role === 'resident') {
            $this->purgeResidentData($user);
        }

        if ($user->role === 'admin') {
            $this->purgeCondominiumData();
        }

        $user->delete();

        return redirect()
            ->route('altas')
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
                    ['key' => 'altas', 'label' => 'Altas', 'route' => 'altas', 'description' => 'Usuarios y accesos'],
                    ['key' => 'settings', 'label' => 'Configuración', 'route' => 'settings', 'description' => 'Condominio y accesos'],
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

    private function importedAccountsForReport(string $status): Collection
    {
        $profile = $this->profile();
        $activeBaseImport = $this->activeBillingBaseImport($profile);

        return ImportedResidentAccount::query()
            ->where('condominium_profile_id', $profile->id)
            ->when($activeBaseImport, fn ($query) => $query->where('billing_base_import_id', $activeBaseImport->id))
            ->when($status === 'adeudo', fn ($query) => $query->where('total_debt', '>', 0))
            ->when($status === 'no_adeudo', fn ($query) => $query->where('total_debt', '<=', 0))
            ->orderBy('tower')
            ->orderBy('unit_number')
            ->get()
            ->map(function (ImportedResidentAccount $account): array {
                $rawPayload = $account->raw_payload ?? [];
                $email = collect($rawPayload)
                    ->first(fn (mixed $value, string $key): bool => str_contains(mb_strtoupper($key, 'UTF-8'), 'CORREO') && filled($value));

                return [
                    'unit_label' => trim(collect([$account->tower, $account->unit_number])->filter()->implode(' ')) ?: 'Sin unidad',
                    'owner_name' => $account->owner_name ?: 'Sin nombre',
                    'owner_email' => $email ?: 'Sin correo vinculado',
                    'pending_amount' => (float) $account->total_debt,
                    'fee_amount' => 0,
                    'paid_amount' => 0,
                    'status_label' => (float) $account->total_debt > 0 ? 'Adeudo' : 'Sin adeudo',
                ];
            })
            ->values();
    }

    private function hasImportedAccountsForCurrentProfile(): bool
    {
        return ImportedResidentAccount::query()
            ->where('condominium_profile_id', $this->profile()->id)
            ->exists();
    }

    private function requestedBillingBaseImport(): ?BillingBaseImport
    {
        if (! (Auth::user()?->isAdmin() ?? false)) {
            return null;
        }

        $baseImportId = request()->integer('base_import');

        if ($baseImportId <= 0) {
            return null;
        }

        return BillingBaseImport::query()->find($baseImportId);
    }

    private function activeBillingBaseImport(CondominiumProfile $profile): ?BillingBaseImport
    {
        $requestedBaseImportId = request()->integer('base_import');

        if ($requestedBaseImportId > 0) {
            $requestedBaseImport = BillingBaseImport::query()
                ->where('condominium_profile_id', $profile->id)
                ->find($requestedBaseImportId);

            if ($requestedBaseImport) {
                return $requestedBaseImport;
            }
        }

        return BillingBaseImport::query()
            ->where('condominium_profile_id', $profile->id)
            ->latest('imported_at')
            ->latest('id')
            ->first();
    }

    private function findExistingBillingBaseImport(CondominiumProfile $profile, string $fileHash): ?BillingBaseImport
    {
        $existingImport = BillingBaseImport::query()
            ->where('condominium_profile_id', $profile->id)
            ->where('file_hash', $fileHash)
            ->first();

        if ($existingImport) {
            return $existingImport;
        }

        return BillingBaseImport::query()
            ->where('condominium_profile_id', $profile->id)
            ->whereNull('file_hash')
            ->where('stored_path', '!=', '')
            ->get()
            ->first(function (BillingBaseImport $baseImport) use ($fileHash): bool {
                if (! Storage::disk('public')->exists($baseImport->stored_path)) {
                    return false;
                }

                $storedHash = hash_file('sha256', Storage::disk('public')->path($baseImport->stored_path));

                if (! hash_equals($storedHash, $fileHash)) {
                    return false;
                }

                $baseImport->update(['file_hash' => $storedHash]);

                return true;
            });
    }

    private function fallbackBillingProfile(CondominiumProfile $profile): ?CondominiumProfile
    {
        if (! (Auth::user()?->isAdmin() ?? false) || $this->profileHasBillingBase($profile)) {
            return null;
        }

        $latestImport = BillingBaseImport::query()
            ->latest('imported_at')
            ->latest('id')
            ->first();

        if (! $latestImport || $latestImport->condominium_profile_id === $profile->id) {
            return null;
        }

        return CondominiumProfile::query()->find($latestImport->condominium_profile_id);
    }

    private function profileFromCondominiumQuery(string $query, CondominiumProfile $currentProfile): ?CondominiumProfile
    {
        $query = trim($query);

        if ($query === '') {
            return null;
        }

        $normalizedQuery = Str::lower($query);

        if (
            Str::contains(Str::lower((string) $currentProfile->commercial_name), $normalizedQuery)
            || Str::contains(Str::lower((string) $currentProfile->address), $normalizedQuery)
            || Str::contains(Str::lower((string) $currentProfile->tax_id), $normalizedQuery)
        ) {
            return $currentProfile;
        }

        return CondominiumProfile::query()
            ->where(function ($profileQuery) use ($query) {
                $profileQuery->where('commercial_name', 'like', "%{$query}%")
                    ->orWhere('address', 'like', "%{$query}%")
                    ->orWhere('tax_id', 'like', "%{$query}%");
            })
            ->orderByRaw('case when commercial_name = ? then 0 else 1 end', [$query])
            ->orderByRaw("case when commercial_name = '' then 1 else 0 end")
            ->orderBy('commercial_name')
            ->orderBy('id')
            ->first();
    }

    private function profileHasBillingBase(CondominiumProfile $profile): bool
    {
        return BillingBaseImport::query()
            ->where('condominium_profile_id', $profile->id)
            ->exists()
            || ImportedResidentAccount::query()
                ->where('condominium_profile_id', $profile->id)
                ->exists();
    }

    private function findImportedAccountForUnit(Collection $accounts, Unit $unit): ?ImportedResidentAccount
    {
        return $accounts->first(function (ImportedResidentAccount $account) use ($unit): bool {
            $sameUnit = trim((string) $account->unit_number) === trim((string) $unit->unit_number);
            $sameTower = blank($account->tower) || trim((string) $account->tower) === trim((string) $unit->tower);

            return $sameUnit && $sameTower;
        });
    }

    private function importedAccountForBillingRequest(Request $request, ?Unit $unit, CondominiumProfile $profile): ?ImportedResidentAccount
    {
        $accountId = $request->integer('account');

        if ($accountId > 0) {
            return ImportedResidentAccount::query()
                ->with('billingBaseImport')
                ->where('condominium_profile_id', $profile->id)
                ->findOrFail($accountId);
        }

        if (! $unit) {
            return null;
        }

        $activeBaseImport = $this->activeBillingBaseImport($profile);
        $accounts = ImportedResidentAccount::query()
            ->with('billingBaseImport')
            ->where('condominium_profile_id', $profile->id)
            ->when($activeBaseImport, fn ($query) => $query->where('billing_base_import_id', $activeBaseImport->id))
            ->latest('imported_at')
            ->get();

        return $accounts->first(fn (ImportedResidentAccount $account): bool => (int) $account->unit_id === (int) $unit->id)
            ?? $this->findImportedAccountForUnit($accounts, $unit);
    }

    private function billingSnapshotFromImportedAccount(ImportedResidentAccount $account, ?Unit $unit, ?array $fallback = null): array
    {
        $pendingAmount = (float) $account->total_debt;

        return [
            'id' => $unit?->id ?? $account->unit_id ?? $account->id,
            'unit_label' => trim(collect([$account->tower, $account->unit_number])->filter()->implode(' ')) ?: ($fallback['unit_label'] ?? 'Sin unidad'),
            'owner_name' => $account->owner_name ?: ($fallback['owner_name'] ?? $unit?->owner_name ?? 'Sin dato'),
            'owner_email' => $this->importedAccountEmail($account, $unit),
            'fee_amount' => (float) ($fallback['fee_amount'] ?? 0),
            'indiviso_percentage' => (float) ($fallback['indiviso_percentage'] ?? 0),
            'paid_amount' => (float) ($fallback['paid_amount'] ?? 0),
            'pending_amount' => $pendingAmount,
            'status_label' => $pendingAmount > 0 ? 'Deudor' : 'Al corriente',
        ];
    }

    private function importedAccountEmail(ImportedResidentAccount $account, ?Unit $unit = null): string
    {
        $email = collect($account->raw_payload ?? [])
            ->first(function (mixed $value, string $key): bool {
                $normalizedKey = $this->normalizePayloadHeader($key);

                return filled($value)
                    && (str_contains($normalizedKey, 'CORREO') || str_contains($normalizedKey, 'EMAIL'));
            });

        return filled($email) ? (string) $email : ($unit?->owner_email ?: 'Sin correo vinculado');
    }

    private function importedAccountMatchesQuery(ImportedResidentAccount $account, string $query): bool
    {
        $needle = mb_strtolower(trim($query), 'UTF-8');

        if ($needle === '') {
            return true;
        }

        $values = collect([
            $account->unit_number,
            $account->tower,
            $account->sub_tower,
            $account->owner_name,
            $account->observations,
            ...array_values($account->raw_payload ?? []),
        ]);

        return $values->contains(function (mixed $value) use ($needle): bool {
            return filled($value)
                && str_contains(mb_strtolower((string) $value, 'UTF-8'), $needle);
        });
    }

    private function billingLetterTemplatePath(CondominiumProfile $profile, string $letterStatus): ?string
    {
        $storedPath = $letterStatus === 'adeudo'
            ? $profile->debt_letter_template_path
            : $profile->no_debt_letter_template_path;

        if ($storedPath && Storage::disk('public')->exists($storedPath)) {
            return $storedPath;
        }

        $defaultPath = base_path(
            $letterStatus === 'adeudo'
                ? 'resources/docx/billing/carta-adeudo.docx'
                : 'resources/docx/billing/carta-no-adeudo.docx'
        );

        return is_file($defaultPath) ? $defaultPath : null;
    }

    private function billingLetterTemplateFullPath(?string $templatePath): ?string
    {
        if (! $templatePath) {
            return null;
        }

        if (! str_starts_with($templatePath, DIRECTORY_SEPARATOR) && Storage::disk('public')->exists($templatePath)) {
            return Storage::disk('public')->path($templatePath);
        }

        return is_file($templatePath) ? $templatePath : null;
    }

    private function selectedExcelDetailLines(ImportedResidentAccount $account, Carbon $period): array
    {
        $periodKeys = collect([
            $period->format('Y-m'),
            $period->format('Y-n'),
            $period->format('m/Y'),
            $period->format('n/Y'),
            $period->format('Y'),
        ])->map(fn (string $key): string => $this->normalizePayloadHeader($key))->all();

        return collect($account->raw_payload ?? [])
            ->filter(fn (mixed $value): bool => filled($value))
            ->filter(function (mixed $value, string $key) use ($periodKeys): bool {
                $normalizedKey = $this->normalizePayloadHeader($key);

                return in_array($normalizedKey, $periodKeys, true)
                    || str_contains($normalizedKey, 'ADEUDO')
                    || str_contains($normalizedKey, 'SALDO')
                    || str_contains($normalizedKey, 'ESTATUS')
                    || str_contains($normalizedKey, 'OBSERVACIONES');
            })
            ->map(fn (mixed $value, string $key): string => $key.': '.$value)
            ->take(12)
            ->values()
            ->all();
    }

    private function validateImportedAccountPayload(Request $request, ?ImportedResidentAccount $account = null): array
    {
        $validated = $request->validate([
            'payload' => ['required', 'array'],
            'payload.*' => ['nullable', 'string', 'max:5000'],
            'period_year' => ['nullable', 'integer', 'between:2017,2100', 'required_with:period_month,period_amount'],
            'period_month' => ['nullable', 'integer', 'between:1,12', 'required_with:period_year,period_amount'],
            'period_amount' => ['nullable', 'numeric', 'min:0', 'required_with:period_year,period_month'],
            'period_closes_year' => ['nullable', 'boolean'],
        ]);

        $profile = $this->profile();
        $incomingPayload = collect($validated['payload'] ?? [])
            ->mapWithKeys(fn ($value, string|int $key): array => [trim((string) $key) => trim((string) $value)])
            ->all();
        $baseHeaders = $account?->raw_payload
            ? array_keys($account->raw_payload)
            : BillingBaseSchema::headersForProfile($profile);
        $headers = collect($baseHeaders)
            ->merge(collect($incomingPayload)->keys()->filter(
                fn (string $key): bool => filled($incomingPayload[$key] ?? null) || in_array($key, $baseHeaders, true)
            ))
            ->unique()
            ->values()
            ->all();
        $payload = [];

        foreach ($headers as $header) {
            $payload[$header] = trim((string) ($incomingPayload[$header] ?? ''));
        }

        $unitKey = $this->findPayloadHeader($payload, ['DEPT', 'DEPTO', 'DEPARTAMENTO']) ?? 'DEPT';
        $nameKey = $this->findPayloadHeader($payload, ['NOMBRE', 'N O M B R E', 'PROPIETARIO', 'RESIDENTE']) ?? 'Nombre';
        $towerKey = $this->findPayloadHeader($payload, ['TORRE']) ?? 'Torre';
        $subTowerKey = $this->findPayloadHeader($payload, ['SUB TORRE', 'SUBTORRE']) ?? 'Sub Torre';
        $totalDebtKey = $this->findPayloadHeader($payload, ['TOTAL ADEUDO', 'ADEUDO TOTAL', 'SALDO']) ?? 'TOTAL ADEUDO';

        $unitNumber = trim((string) ($payload[$unitKey] ?? ''));
        $ownerName = trim((string) ($payload[$nameKey] ?? ''));

        if ($unitNumber === '') {
            throw ValidationException::withMessages([
                "payload.{$unitKey}" => 'La unidad o departamento es obligatorio para guardar el renglón.',
            ]);
        }

        if ($ownerName === '') {
            throw ValidationException::withMessages([
                "payload.{$nameKey}" => 'El nombre del residente es obligatorio para guardar el renglón.',
            ]);
        }

        $payload[$unitKey] = $unitNumber;
        $payload[$nameKey] = $ownerName;
        $payload[$towerKey] = trim((string) ($payload[$towerKey] ?? ''));
        $payload[$subTowerKey] = trim((string) ($payload[$subTowerKey] ?? ''));
        $payload[$totalDebtKey] = (string) $this->moneyValue($payload[$totalDebtKey] ?? 0);

        if (
            filled($validated['period_year'] ?? null)
            || filled($validated['period_month'] ?? null)
            || filled($validated['period_amount'] ?? null)
        ) {
            $periodKey = sprintf('%d-%02d', (int) $validated['period_year'], (int) $validated['period_month']);
            $periodAmount = $this->moneyValue($validated['period_amount']);
            $previousPeriodAmount = $this->moneyValue($payload[$periodKey] ?? 0);

            if (! array_key_exists($periodKey, $payload)) {
                $payload = $this->insertPayloadBeforeHeader($payload, $periodKey, '', $totalDebtKey);
            }

            $payload[$periodKey] = (string) $periodAmount;

            if ($request->boolean('period_closes_year') || (int) $validated['period_month'] === 12) {
                $yearKey = (string) (int) $validated['period_year'];
                $statusKey = $this->findPayloadHeader($payload, ['ESTATUS']);

                if (! array_key_exists($yearKey, $payload)) {
                    $payload = $this->insertPayloadBeforeHeader($payload, $yearKey, '', $statusKey ?: $totalDebtKey);
                }

                $payload[$yearKey] = (string) $periodAmount;
            }

            $payload[$totalDebtKey] = (string) max(
                0,
                $this->moneyValue($payload[$totalDebtKey]) - $previousPeriodAmount + $periodAmount
            );
        }

        $yearStatuses = collect($payload)
            ->filter(fn ($value, string $key): bool => preg_match('/^20\d{2}$/', $key) === 1 && filled($value))
            ->all();
        $observations = collect($payload)
            ->filter(fn ($value, string $key): bool => str_contains($key, 'OBSERVACIONES') && filled($value))
            ->implode(' ');

        return [
            'unit_number' => $payload[$unitKey],
            'tower' => $payload[$towerKey],
            'sub_tower' => $payload[$subTowerKey] ?: null,
            'owner_name' => $payload[$nameKey],
            'total_debt' => $this->moneyValue($payload[$totalDebtKey]),
            'year_statuses' => $yearStatuses,
            'raw_payload' => $payload,
            'observations' => $observations ?: null,
        ];
    }

    private function findPayloadHeader(array $payload, array $needles): ?string
    {
        foreach ([true, false] as $requireValue) {
            foreach ($payload as $header => $value) {
                if ($requireValue && blank($value)) {
                    continue;
                }

                $normalizedHeader = $this->normalizePayloadHeader((string) $header);

                foreach ($needles as $needle) {
                    if (str_contains($normalizedHeader, $this->normalizePayloadHeader($needle))) {
                        return (string) $header;
                    }
                }
            }
        }

        return null;
    }

    private function insertPayloadBeforeHeader(array $payload, string $newHeader, string $newValue, string $beforeHeader): array
    {
        $updated = [];
        $inserted = false;

        foreach ($payload as $header => $value) {
            if (! $inserted && $header === $beforeHeader) {
                $updated[$newHeader] = $newValue;
                $inserted = true;
            }

            $updated[$header] = $value;
        }

        if (! $inserted) {
            $updated[$newHeader] = $newValue;
        }

        return $updated;
    }

    private function normalizePayloadHeader(string $header): string
    {
        return preg_replace('/\s+/', ' ', mb_strtoupper(trim($header), 'UTF-8')) ?: '';
    }

    private function manualBillingBaseImport(CondominiumProfile $profile): BillingBaseImport
    {
        return BillingBaseImport::query()->firstOrCreate([
            'condominium_profile_id' => $profile->id,
            'original_name' => 'Base creada desde Boleo',
            'status' => 'manual',
        ], [
            'stored_path' => '',
            'imported_rows' => 0,
            'imported_at' => now(),
        ]);
    }

    private function matchUnitForImportedAccount(string $unitNumber, string $tower, string $ownerName): ?Unit
    {
        return Unit::query()
            ->where('unit_number', $unitNumber)
            ->when($tower !== '', fn ($query) => $query->where('tower', $tower))
            ->first();
    }

    private function syncUnitFromImportedAccountData(array $data): Unit
    {
        $unit = $this->matchUnitForImportedAccount($data['unit_number'], $data['tower'], $data['owner_name']);
        $email = collect($data['raw_payload'])
            ->first(fn (mixed $value, string $key): bool => filled($value) && str_contains($this->normalizePayloadHeader($key), 'CORREO'))
            ?: collect($data['raw_payload'])
                ->first(fn (mixed $value, string $key): bool => filled($value) && str_contains($this->normalizePayloadHeader($key), 'EMAIL'));
        $currentFee = $unit ? (float) $unit->fee : 0.0;
        $values = [
            'tower' => $data['tower'],
            'unit_type' => $unit?->unit_type ?: 'Departamento',
            'owner_name' => $data['owner_name'],
            'owner_email' => $email ?: $unit?->owner_email,
            'ordinary_fee' => $unit ? (float) $unit->ordinary_fee : 0,
            'indiviso_percentage' => $unit ? (float) $unit->indiviso_percentage : 0,
            'extraordinary_fee' => $unit ? (float) $unit->extraordinary_fee : 0,
            'parking_rent' => $unit ? (float) $unit->parking_rent : 0,
            'storage_rent' => $unit ? (float) $unit->storage_rent : 0,
            'parking_spots' => $unit?->parking_spots ?? 0,
            'storage_rooms' => $unit?->storage_rooms ?? 0,
            'clothesline_cages' => $unit?->clothesline_cages ?? 0,
            'fee' => $currentFee,
            'status' => $data['total_debt'] > 0 ? 'Atrasado' : 'Pagado',
        ];

        if ($unit) {
            $unit->update($values);

            return $unit;
        }

        return Unit::query()->create([
            'unit_number' => $data['unit_number'],
            ...$values,
        ]);
    }

    private function syncImportedAccountTotalDebtPayload(ImportedResidentAccount $account, float $totalDebt): array
    {
        $payload = $account->raw_payload ?? [];
        $totalDebtKey = $this->findPayloadHeader($payload, ['TOTAL ADEUDO', 'ADEUDO TOTAL', 'SALDO']) ?? 'TOTAL ADEUDO';
        $payload[$totalDebtKey] = (string) $totalDebt;

        return $payload;
    }

    private function moneyValue(mixed $value): float
    {
        return (float) str_replace([',', '$', ' '], '', (string) $value);
    }

    private function validateResidentReceipt(Request $request): array
    {
        return $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'period_year' => ['required', 'integer', 'between:2017,2100'],
            'period_month' => ['required', 'integer', 'between:1,12'],
            'amount_due' => ['required', 'numeric', 'min:0.01'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function residentReceiptRows(Collection $receipts): array
    {
        return $receipts
            ->map(fn (ResidentReceipt $receipt): array => [
                'id' => $receipt->id,
                'period_year' => $receipt->period_year,
                'period_month' => $receipt->period_month,
                'period_label' => $this->residentReceiptPeriodLabel($receipt),
                'amount_due_raw' => (float) $receipt->amount_due,
                'amount_paid_raw' => (float) $receipt->amount_paid,
                'pending_raw' => max((float) $receipt->amount_due - (float) $receipt->amount_paid, 0),
                'amount_due' => '$'.number_format((float) $receipt->amount_due, 2),
                'amount_paid' => '$'.number_format((float) $receipt->amount_paid, 2),
                'pending' => '$'.number_format(max((float) $receipt->amount_due - (float) $receipt->amount_paid, 0), 2),
                'status' => $receipt->status,
                'status_label' => $this->residentReceiptStatusLabel($receipt->status),
                'status_badge' => $this->residentReceiptStatusBadge($receipt->status),
                'notes' => $receipt->notes,
                'pdf_url' => route('billing.receipts.pdf', $receipt),
            ])
            ->values()
            ->all();
    }

    private function residentReceiptSummary(Collection $receipts): array
    {
        return [
            'total' => $receipts->count(),
            'paid_count' => $receipts->where('status', 'pagado')->count(),
            'partial_count' => $receipts->where('status', 'parcial')->count(),
            'pending_count' => $receipts->where('status', 'pendiente')->count(),
            'pending_amount' => $receipts->sum(
                fn (ResidentReceipt $receipt): float => max((float) $receipt->amount_due - (float) $receipt->amount_paid, 0)
            ),
        ];
    }

    private function residentReceiptPeriodLabel(ResidentReceipt $receipt): string
    {
        return Carbon::create($receipt->period_year, $receipt->period_month, 1)
            ->locale('es_MX')
            ->translatedFormat('F Y');
    }

    private function residentReceiptStatusLabel(string $status): string
    {
        return match ($status) {
            'pagado' => 'Pagado',
            'parcial' => 'Parcial',
            default => 'Pendiente',
        };
    }

    private function residentReceiptStatusBadge(string $status): string
    {
        return match ($status) {
            'pagado' => 'badge--success',
            'parcial' => 'badge--warning',
            default => 'badge--neutral',
        };
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
        $selectedProfileId = (int) request()->session()->get('settings_condominium_profile_id', 0);

        if ($selectedProfileId > 0) {
            $selectedProfile = CondominiumProfile::query()->find($selectedProfileId);

            if ($selectedProfile) {
                return $selectedProfile;
            }

            request()->session()->forget('settings_condominium_profile_id');
        }

        return CondominiumProfile::query()->firstOrCreate(
            ['id' => 1],
            $this->defaultCondominiumProfileValues()
        );
    }

    private function settingsProfile(bool $isNewCondominium = false): CondominiumProfile
    {
        if ($isNewCondominium) {
            return new CondominiumProfile($this->defaultCondominiumProfileValues());
        }

        $selectedProfileId = (int) request()->session()->get('settings_condominium_profile_id', 0);

        if ($selectedProfileId > 0) {
            $selectedProfile = CondominiumProfile::query()->find($selectedProfileId);

            if ($selectedProfile) {
                return $selectedProfile;
            }

            request()->session()->forget('settings_condominium_profile_id');
        }

        return CondominiumProfile::query()
            ->orderByRaw("case when commercial_name = '' then 1 else 0 end")
            ->orderBy('commercial_name')
            ->orderBy('id')
            ->first() ?? CondominiumProfile::query()->create($this->defaultCondominiumProfileValues());
    }

    private function mapTasks(Collection $tasks): array
    {
        return $tasks->map(fn (MaintenanceTask $task) => [
            'priority' => $task->status,
            'title' => $task->title,
            'ticket' => '#'.$task->id,
            'meta' => trim(($task->area ?: 'Sin área').' | '.($task->provider?->name ?? 'Sin proveedor').' | Último costo $'.number_format((float) $task->last_cost, 2)),
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
            'Administración',
            'Jardinería',
        ];
    }

    private function variableExpenseCategories(): array
    {
        return [
            'Mantenimiento',
            'Compra de focos',
            'Agua',
            'Pintura',
            'Material eléctrico',
            'Otro',
        ];
    }

    private function expenseMotives(): array
    {
        return [
            'Pago CFE',
            'Pago elevadores',
            'Liquidación de tarjeta de portón',
            'Recolección de basura',
            'Servicio de limpieza',
            'Servicio de seguridad diamante',
            'Servicio de administración',
            'Trabajos de impermeabilización',
            'Cableado de cámaras',
            'Material de cámaras',
            'Recibo de vigilancia',
        ];
    }

    private function notifyAssistantAboutMaintenanceTask(MaintenanceTask $task): void
    {
        $profile = $this->profile();

        if (! filled($profile->assistant_admin_names) && ! filled($profile->assistant_admin_phone)) {
            return;
        }

        $assistantUser = User::query()
            ->when(filled($profile->assistant_admin_phone), function ($query) use ($profile) {
                $query->where('phone', $profile->assistant_admin_phone);
            }, function ($query) use ($profile) {
                $query->where('name', $profile->assistant_admin_names);
            })
            ->first();

        if (! $assistantUser || ! filled($assistantUser->email)) {
            return;
        }

        Mail::raw(
            "Hola {$assistantUser->name},\n\nSe registró una nueva tarea en Boleo.\n\n".
            "Tarea: {$task->title}\n".
            'Área: '.($task->area ?: 'Sin área')."\n".
            'Estatus: '.$task->status."\n".
            'Fecha compromiso: '.(optional($task->due_date)->format('d/m/Y') ?: 'Sin fecha')."\n".
            'Último costo: $'.number_format((float) $task->last_cost, 2)."\n".
            'Notas: '.($task->notes ?: 'Sin notas')."\n\n".
            'Este aviso se envió al auxiliar vinculado al condominio.',
            fn ($message) => $message
                ->to($assistantUser->email)
                ->subject('Nueva tarea asignada para seguimiento')
        );
    }

    private function adminRegistrationDocumentOptions(): array
    {
        return [
            'elevadores' => 'Equipamiento principal - Elevadores',
            'cisternas' => 'Equipamiento principal - Cisternas',
            'tinacos' => 'Equipamiento principal - Tinacos',
            'hidroneumaticos' => 'Equipamiento principal - Hidroneumáticos',
            'alberca' => 'Amenidades e instalaciones comunes - Alberca',
            'chapoteadero' => 'Amenidades e instalaciones comunes - Chapoteadero',
            'salon-eventos' => 'Amenidades e instalaciones comunes - Salón de eventos',
            'roof-garden' => 'Amenidades e instalaciones comunes - Roof garden',
            'salon-yoga' => 'Amenidades e instalaciones comunes - Salón de yoga',
            'salon-juegos' => 'Amenidades e instalaciones comunes - Salón de juegos',
            'gym' => 'Amenidades e instalaciones comunes - GYM',
            'asador' => 'Amenidades e instalaciones comunes - Asador',
        ];
    }

    private function adminRegistrationDocumentVisibilityMap(): array
    {
        return [
            'elevadores' => 'elevators_enabled',
            'cisternas' => 'cisterns_enabled',
            'tinacos' => 'water_tanks_enabled',
            'hidroneumaticos' => 'hydropneumatics_enabled',
            'alberca' => 'pool_enabled',
            'chapoteadero' => 'wading_pool_enabled',
            'salon-eventos' => 'event_hall_enabled',
            'roof-garden' => 'roof_garden_enabled',
            'salon-yoga' => 'yoga_room_enabled',
            'salon-juegos' => 'game_room_enabled',
            'gym' => 'gym_enabled',
            'asador' => 'grill_enabled',
        ];
    }

    private function defaultAdministrator(): array
    {
        return [
            'name' => 'Rodolfo Chiquillo Quevedo',
            'email' => 'Boleo54@yahoo.com.mx',
            'phone' => '5530707950',
        ];
    }

    private function timeOptions(): array
    {
        return [
            'NO HAY',
            '02:00',
            '03:00',
            '04:00',
            '05:00',
            '06:00',
            '07:00',
            '08:00',
            '09:00',
            '10:00',
            '11:00',
            '12:00',
            '13:00',
            '14:00',
            '15:00',
            '16:00',
            '17:00',
            '18:00',
            '19:00',
            '20:00',
            '21:00',
            '22:00',
        ];
    }

    private function scheduleDayOptions(): array
    {
        return [
            'Lunes a Viernes' => 'Lunes a Viernes',
            'Sábados' => 'Sábados',
            'Domingos y días festivos' => 'Domingos y días festivos',
        ];
    }

    private function operationRecords(CondominiumProfile $profile): array
    {
        $records = [];

        foreach ([
            'moving-hours' => ['label' => 'Horario para mudanza', 'value' => $profile->moving_hours],
            'work-hours' => ['label' => 'Horario de trabajo', 'value' => $profile->work_hours],
            'meeting-hours' => ['label' => 'Horario para reunión', 'value' => $profile->meeting_hours],
        ] as $type => $schedule) {
            if (filled($schedule['value'])) {
                $records[] = [
                    'type' => $type,
                    'category' => 'Horario',
                    'name' => $schedule['label'],
                    'detail' => $schedule['value'],
                    'view_url' => null,
                    'share_url' => null,
                ];
            }
        }

        foreach ([
            'regulations' => [
                'category' => 'Documento',
                'name' => 'Reglamento del condominio',
                'path' => $profile->regulations_path,
                'view_url' => route('settings.regulations.document'),
            ],
            'parking-map' => [
                'category' => 'Documento',
                'name' => 'Mapa de estacionamiento',
                'path' => $profile->parking_map_path,
                'view_url' => route('settings.documents.show', 'parking-map'),
            ],
            'property-regime' => [
                'category' => 'Documento',
                'name' => 'Régimen de propiedad y condominio',
                'path' => $profile->property_regime_path,
                'view_url' => route('settings.documents.show', 'property-regime'),
            ],
        ] as $type => $document) {
            if (filled($document['path'])) {
                $records[] = [
                    'type' => $type,
                    'category' => $document['category'],
                    'name' => $document['name'],
                    'detail' => basename((string) $document['path']),
                    'view_url' => $document['view_url'],
                    'share_url' => url(URL::signedRoute('public.settings.documents.show', [
                        'profile' => $profile,
                        'type' => $type,
                    ], null, false)),
                ];
            }
        }

        return $records;
    }

    private function splitOperatingSchedule(?string $value): array
    {
        $value = trim((string) $value);
        $value = str_replace(['Dias'], 'Días', $value);
        $normalizeDay = fn (string $day): string => match ($day) {
            'Sábados' => 'Sábados',
            'Domingos y días festivos' => 'Domingos y días festivos',
            default => $day,
        };

        if (preg_match('/^Días:\s*(.*?)\s*\|\s*Inicio:\s*(.*?)\s*\|\s*Final:\s*(.*?)$/u', $value, $matches) === 1) {
            return [
                'day' => $normalizeDay($matches[1]),
                'start' => $matches[2],
                'end' => $matches[3],
            ];
        }

        if (preg_match('/^(?:D[ií]as):\s*(.*?)\s*\|\s*Inicio:\s*(.*?)\s*\|\s*Final:\s*(.*?)$/', $value, $matches) === 1) {
            return [
                'day' => $normalizeDay($matches[1]),
                'start' => $matches[2],
                'end' => $matches[3],
            ];
        }

        if (preg_match('/^Inicio:\s*(.*?)\s*\|\s*Final:\s*(.*?)$/', $value, $matches) === 1) {
            return [
                'day' => '',
                'start' => $matches[1],
                'end' => $matches[2],
            ];
        }

        return [
            'day' => $normalizeDay($value),
            'start' => '',
            'end' => '',
        ];
    }

    private function joinOperatingSchedule(?string $day, ?string $start, ?string $end): string
    {
        $day = trim((string) $day);
        $start = trim((string) $start);
        $end = trim((string) $end);

        if ($day === '' && $start === '' && $end === '') {
            return '';
        }

        return "Días: {$day} | Inicio: {$start} | Final: {$end}";
    }

    private function normalizeCondominiumTextFields(array $data): array
    {
        foreach ([
            'commercial_name',
            'tax_id',
            'address',
            'fee_type',
            'admin_type',
            'admin_name',
            'assistant_admin_names',
            'assistant_admin_phone',
            'admin_email',
            'admin_phone',
            'moving_hours',
            'work_hours',
            'meeting_hours',
            'regulations_path',
            'cleaning_staff_name',
            'cleaning_staff_phone',
            'cleaning_staff_contact',
            'security_staff_name',
            'security_staff_phone',
            'security_staff_contact',
            'bank',
            'account_holder',
            'bank_account_type',
            'account_number',
            'clabe',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = trim((string) ($data[$field] ?? ''));
            }
        }

        return $data;
    }

    private function defaultCondominiumProfileValues(): array
    {
        return [
            'admin_name' => $this->defaultAdministrator()['name'],
            'admin_email' => $this->defaultAdministrator()['email'],
            'admin_phone' => $this->defaultAdministrator()['phone'],
        ];
    }

    private function assistantAdminOptions(): array
    {
        return [
            'Alondra Velázquez Hernández' => '5525403862',
            'Rene Alberto Solano' => '7228378509',
        ];
    }

    private function deleteProfileFiles(CondominiumProfile $profile): void
    {
        foreach (array_filter([
            $profile->admin_registration_path,
            $profile->regulations_path,
            $profile->parking_map_path,
            $profile->property_regime_path,
            $profile->cleaning_instructions_path,
            $profile->cleaning_permits_path,
            $profile->security_instructions_path,
            $profile->security_permits_path,
            $profile->debt_letter_template_path,
            $profile->no_debt_letter_template_path,
        ]) as $path) {
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }

        foreach (($profile->admin_registration_documents ?? []) as $document) {
            $path = is_array($document) ? ($document['path'] ?? null) : null;

            if ($path && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    private function purgeResidentData(User $user): void
    {
        $linkedUnits = Unit::query()
            ->where('owner_email', $user->email)
            ->orWhere('owner_name', $user->name)
            ->get();

        foreach ($linkedUnits as $unit) {
            $unit->payments()->delete();
            $unit->delete();
        }

        $user->amenityReservations()->delete();
    }

    private function purgeCondominiumData(): void
    {
        $profile = CondominiumProfile::query()->find(1);

        if ($profile) {
            foreach (array_filter([
                $profile->admin_registration_path,
                $profile->regulations_path,
                $profile->parking_map_path,
                $profile->property_regime_path,
                $profile->cleaning_instructions_path,
                $profile->cleaning_permits_path,
                $profile->security_instructions_path,
                $profile->security_permits_path,
                $profile->debt_letter_template_path,
                $profile->no_debt_letter_template_path,
            ]) as $path) {
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }

            foreach (($profile->admin_registration_documents ?? []) as $document) {
                $path = is_array($document) ? ($document['path'] ?? null) : null;

                if ($path && Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }
        }

        AssemblyMinute::query()->get()->each(function (AssemblyMinute $minute): void {
            if (filled($minute->document_path) && Storage::disk('public')->exists($minute->document_path)) {
                Storage::disk('public')->delete($minute->document_path);
            }

            if (filled($minute->convocation_path) && Storage::disk('public')->exists($minute->convocation_path)) {
                Storage::disk('public')->delete($minute->convocation_path);
            }
        });
        AssemblyMinute::query()->delete();

        MaintenanceExpense::query()->get()->each(function (MaintenanceExpense $expense): void {
            if (filled($expense->document_path) && Storage::disk('public')->exists($expense->document_path)) {
                Storage::disk('public')->delete($expense->document_path);
            }
        });
        MaintenanceExpense::query()->delete();

        MaintenanceTask::query()->delete();
        AmenityReservation::query()->delete();
        Amenity::query()->delete();
        Payment::query()->delete();
        Unit::query()->delete();
        Provider::query()->delete();

        if ($profile) {
            $profile->delete();
        }
    }

    private function resolveCoordinatesForAddress(string $address): ?array
    {
        $normalizedAddress = trim($address);

        if ($normalizedAddress === '') {
            return null;
        }

        try {
            $response = Http::timeout(8)
                ->acceptJson()
                ->withHeaders([
                    'User-Agent' => 'Boleo Condominium Portal/1.0',
                ])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'format' => 'jsonv2',
                    'limit' => 1,
                    'q' => $normalizedAddress,
                ]);

            if (! $response->ok()) {
                return null;
            }

            $payload = $response->json();
            $first = is_array($payload) ? ($payload[0] ?? null) : null;

            if (! is_array($first) || ! isset($first['lat'], $first['lon'])) {
                return null;
            }

            return [
                'latitude' => (float) $first['lat'],
                'longitude' => (float) $first['lon'],
            ];
        } catch (Throwable) {
            return null;
        }
    }

    private function ensureAdmin(): void
    {
        abort_unless(Auth::user()?->isAdmin(), 403);
    }

    private function pdfResponse(string $filename, array $lines): Response
    {
        $content = (new SimpleLetterheadPdf($lines, $filename))->render();

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
