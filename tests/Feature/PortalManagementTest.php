<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Unit;
use App\Models\User;
use App\Models\CondominiumProfile;
use App\Models\MaintenanceExpense;
use App\Models\MaintenanceTask;
use App\Models\Amenity;
use App\Models\AmenityReservation;
use App\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PortalManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_is_available(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Boleo');
    }

    public function test_admin_can_log_in(): void
    {
        User::factory()->create([
            'email' => 'admin@boleo.mx',
            'phone' => '5512345678',
            'role' => 'admin',
            'password' => 'secret123',
        ]);

        $response = $this->post('/acceder', [
            'email' => 'admin@boleo.mx',
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    public function test_default_admin_credentials_can_restore_and_log_in(): void
    {
        User::factory()->create([
            'email' => 'admin@boleo.mx',
            'phone' => '5512345678',
            'role' => 'admin',
            'password' => 'otraClave123',
        ]);

        $response = $this->post('/acceder', [
            'email' => 'admin@boleo.mx',
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        $this->assertTrue(Hash::check('secret123', User::query()->where('email', 'admin@boleo.mx')->firstOrFail()->password));
    }

    public function test_guest_can_create_account_with_user_role(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Laura Nieto',
            'email' => 'laura@boleo.mx',
            'phone' => '5588877766',
            'password' => 'claveSegura123',
            'password_confirmation' => 'claveSegura123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'laura@boleo.mx',
            'phone' => '5588877766',
            'role' => 'user',
        ]);
    }

    public function test_recovery_request_requires_matching_email_and_phone(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@boleo.mx',
            'phone' => '5512345678',
            'role' => 'admin',
        ]);

        $response = $this->post(route('password.email'), [
            'email' => $user->email,
            'phone' => $user->phone,
        ]);

        $response->assertSessionHas('status');
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => $user->email,
        ]);
    }

    public function test_user_can_reset_password_from_recovery_link(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@boleo.mx',
            'phone' => '5512345678',
            'role' => 'admin',
        ]);

        $token = Password::broker()->createToken($user);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'nuevaClave123',
            'password_confirmation' => 'nuevaClave123',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('nuevaClave123', $user->fresh()->password));
    }

    public function test_admin_can_manage_units_and_link_email_to_assigned_person(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('units.store'), [
                'unit_number' => '305',
                'tower' => 'Torre C',
                'unit_type' => 'Loft',
                'owner_name' => 'Maria Costa',
                'owner_email' => 'maria@boleo.mx',
                'ordinary_fee' => '3550.50',
                'extraordinary_fee' => '250.00',
                'parking_rent' => '450.00',
                'storage_rent' => '150.00',
                'parking_spots' => '1',
                'storage_rooms' => '1',
                'clothesline_cages' => '1',
                'fee' => '3550.50',
                'status' => 'Pagado',
            ])
            ->assertRedirect(route('units'));

        $unit = Unit::query()->where('unit_number', '305')->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('units.update', $unit), [
                'unit_number' => '305',
                'tower' => 'Torre C',
                'unit_type' => 'Loft Plus',
                'owner_name' => 'Maria Costa',
                'owner_email' => 'maria.costa@boleo.mx',
                'ordinary_fee' => '3650.00',
                'extraordinary_fee' => '300.00',
                'parking_rent' => '500.00',
                'storage_rent' => '200.00',
                'parking_spots' => '2',
                'storage_rooms' => '1',
                'clothesline_cages' => '1',
                'fee' => '3650.00',
                'status' => 'Atrasado',
            ])
            ->assertRedirect(route('units'));

        $this->assertDatabaseHas('units', [
            'id' => $unit->id,
            'unit_type' => 'Loft Plus',
            'owner_email' => 'maria.costa@boleo.mx',
            'ordinary_fee' => 3650,
            'status' => 'Atrasado',
        ]);

        $this->actingAs($admin)
            ->delete(route('units.destroy', $unit))
            ->assertRedirect(route('units'));

        $this->assertDatabaseMissing('units', [
            'id' => $unit->id,
        ]);
    }

    public function test_units_page_shows_monthly_total_and_payment_reminder(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        CondominiumProfile::query()->create([
            'id' => 1,
            'commercial_name' => 'Boleo Condominio',
            'ordinary_fee_amount' => 4000,
            'fee_type' => 'indiviso',
        ]);

        Unit::query()->create([
            'unit_number' => '220',
            'tower' => 'Torre B',
            'unit_type' => 'Departamento',
            'owner_name' => 'Mara Gil',
            'owner_email' => 'mara@boleo.mx',
            'ordinary_fee' => 0,
            'indiviso_percentage' => 25,
            'extraordinary_fee' => 100,
            'parking_rent' => 50,
            'storage_rent' => 0,
            'parking_spots' => 1,
            'storage_rooms' => 0,
            'clothesline_cages' => 0,
            'fee' => 0,
            'status' => 'Atrasado',
        ]);

        $this->actingAs($user)
            ->get(route('units'))
            ->assertOk()
            ->assertSee('$1,150.00', false)
            ->assertSee('Se paga cada mes')
            ->assertSee('Recuerda: el monto total de tu unidad se paga cada mes.');
    }

    public function test_units_search_shows_condominium_search_reports_and_characteristics_sections(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        CondominiumProfile::query()->create([
            'id' => 1,
            'commercial_name' => 'Boleo Condominio Centro',
            'departments_count' => 32,
            'parking_spaces_count' => 40,
            'storage_rooms_count' => 12,
            'security_booth' => true,
        ]);

        Unit::query()->create([
            'unit_number' => '120',
            'tower' => 'Torre A',
            'unit_type' => 'Departamento',
            'owner_name' => 'Jose Rivera',
            'owner_email' => 'jose@boleo.mx',
            'ordinary_fee' => 2000,
            'extraordinary_fee' => 0,
            'parking_rent' => 0,
            'storage_rent' => 0,
            'parking_spots' => 1,
            'storage_rooms' => 0,
            'clothesline_cages' => 0,
            'fee' => 2000,
            'status' => 'Pagado',
        ]);

        $this->actingAs($admin)
            ->get(route('units', ['condominium' => 'Boleo Condominio Centro', 'q' => 'Jose']))
            ->assertOk()
            ->assertSee('Buscador del Condominio')
            ->assertSee('Submenu de Residentes')
            ->assertSee('Comandos para Reportes')
            ->assertSee('Características del Condominio', false)
            ->assertSee('Jose Rivera');
    }

    public function test_billing_page_shows_monthly_payment_reminder_for_user(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $unit = Unit::query()->create([
            'unit_number' => '310',
            'tower' => 'Torre C',
            'unit_type' => 'Departamento',
            'owner_name' => 'Elena Ponce',
            'owner_email' => 'elena@boleo.mx',
            'ordinary_fee' => 2500,
            'indiviso_percentage' => 0,
            'extraordinary_fee' => 0,
            'parking_rent' => 0,
            'storage_rent' => 0,
            'parking_spots' => 1,
            'storage_rooms' => 0,
            'clothesline_cages' => 0,
            'fee' => 2500,
            'status' => 'Atrasado',
        ]);

        $this->actingAs($user)
            ->get(route('billing', ['unit' => $unit->id]))
            ->assertOk()
            ->assertSee('$2,500.00', false)
            ->assertSee('La cuota total de esta unidad se paga cada mes.')
            ->assertSee('Recuerda que la cuota mensual se paga cada mes.');
    }

    public function test_admin_can_quickly_update_unit_payment_status_from_listing(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $unit = Unit::query()->create([
            'unit_number' => '410',
            'tower' => 'Torre E',
            'unit_type' => 'Departamento',
            'owner_name' => 'Luis Campos',
            'owner_email' => 'luis@boleo.mx',
            'ordinary_fee' => 2100,
            'extraordinary_fee' => 0,
            'parking_rent' => 0,
            'storage_rent' => 0,
            'parking_spots' => 1,
            'storage_rooms' => 0,
            'clothesline_cages' => 0,
            'fee' => 2100,
            'status' => 'Atrasado',
        ]);

        $this->actingAs($admin)
            ->patch(route('units.status', $unit), [
                'status' => 'Pagado',
            ])
            ->assertRedirect(route('units'));

        $this->assertDatabaseHas('units', [
            'id' => $unit->id,
            'status' => 'Pagado',
        ]);
    }

    public function test_admin_can_update_fee_type_from_units_card(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->patch(route('units.fee-type'), [
                'fee_type' => 'indiviso',
            ])
            ->assertRedirect(route('units'));

        $this->assertDatabaseHas('condominium_profiles', [
            'id' => 1,
            'fee_type' => 'indiviso',
        ]);
    }

    public function test_indiviso_fee_type_uses_unit_percentage_for_billing(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        CondominiumProfile::query()->create([
            'id' => 1,
            'commercial_name' => 'Boleo Condominio',
            'ordinary_fee_amount' => 4000,
            'fee_type' => 'indiviso',
        ]);

        $unit = Unit::query()->create([
            'unit_number' => '502',
            'tower' => 'Torre F',
            'unit_type' => 'Departamento',
            'owner_name' => 'Laura Campos',
            'owner_email' => 'laura.campos@boleo.mx',
            'ordinary_fee' => 0,
            'indiviso_percentage' => 25,
            'extraordinary_fee' => 100,
            'parking_rent' => 50,
            'storage_rent' => 0,
            'parking_spots' => 1,
            'storage_rooms' => 0,
            'clothesline_cages' => 0,
            'fee' => 0,
            'status' => 'Atrasado',
        ]);

        $response = $this->actingAs($admin)->get(route('billing', ['unit' => $unit->id]));

        $response->assertOk();
        $response->assertSee('$1,150.00', false);
        $response->assertSee('Deudor');
    }

    public function test_admin_can_create_update_and_delete_portal_users(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'name' => 'Carlos Medina',
                'email' => 'carlos@boleo.mx',
                'phone' => '5510101010',
                'role' => 'user',
                'password' => 'claveSegura123',
                'password_confirmation' => 'claveSegura123',
            ])
            ->assertRedirect(route('settings'));

        $managedUser = User::query()->where('email', 'carlos@boleo.mx')->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('users.update', $managedUser), [
                'name' => 'Carlos Medina Admin',
                'email' => 'carlos@boleo.mx',
                'phone' => '5510101010',
                'role' => 'admin',
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertRedirect(route('settings'));

        $this->assertDatabaseHas('users', [
            'id' => $managedUser->id,
            'name' => 'Carlos Medina Admin',
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->delete(route('users.destroy', $managedUser))
            ->assertRedirect(route('settings'));

        $this->assertDatabaseMissing('users', [
            'id' => $managedUser->id,
        ]);
    }

    public function test_admin_only_sees_saved_user_summary_when_editing_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $managedUser = User::factory()->create([
            'name' => 'Sandra Mena',
            'email' => 'sandra@boleo.mx',
            'phone' => '5511002200',
            'role' => 'user',
        ]);

        CondominiumProfile::query()->create([
            'id' => 1,
            'commercial_name' => 'Boleo Condominio Centro',
            'tax_id' => 'RFC-123',
            'address' => 'Av. Central 100',
            'ordinary_fee_amount' => 2500,
            'bank' => 'Banamex',
            'account_number' => '1234567890',
        ]);

        Unit::query()->create([
            'unit_number' => '204',
            'tower' => 'Torre B',
            'unit_type' => 'Departamento',
            'owner_name' => 'Sandra Mena',
            'owner_email' => 'sandra@boleo.mx',
            'ordinary_fee' => 2500,
            'extraordinary_fee' => 0,
            'parking_rent' => 0,
            'storage_rent' => 0,
            'parking_spots' => 1,
            'storage_rooms' => 0,
            'clothesline_cages' => 0,
            'fee' => 2500,
            'status' => 'Pagado',
        ]);

        $this->actingAs($admin)
            ->get(route('settings', ['q' => 'Sandra']))
            ->assertOk()
            ->assertDontSee('Resumen del usuario en edicion')
            ->assertSee('Sandra Mena')
            ->assertSee('Editar');

        $this->actingAs($admin)
            ->get(route('settings', ['edit_user' => $managedUser->id]))
            ->assertOk()
            ->assertSee('Resumen del usuario en edicion')
            ->assertSee('Sandra Mena')
            ->assertSee('Boleo Condominio Centro')
            ->assertSee('Torre B - 204');
    }

    public function test_user_role_is_limited_to_reading_and_pdf_downloads(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $unit = Unit::query()->create([
            'unit_number' => '401',
            'tower' => 'Torre D',
            'unit_type' => 'Loft',
            'owner_name' => 'Carmen Diaz',
            'owner_email' => 'carmen@boleo.mx',
            'ordinary_fee' => 2500,
            'extraordinary_fee' => 0,
            'parking_rent' => 0,
            'storage_rent' => 0,
            'parking_spots' => 0,
            'storage_rooms' => 0,
            'clothesline_cages' => 0,
            'fee' => 2500,
            'status' => 'Pagado',
        ]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->actingAs($user)->get(route('units'))->assertOk();
        $this->actingAs($user)->get(route('billing.pdf', ['unit' => $unit->id]))->assertOk();
        $this->actingAs($user)->get(route('billing.report.pdf'))->assertOk();
        $this->actingAs($user)->get(route('billing.debtors.pdf'))->assertOk();
        $this->actingAs($user)->get(route('maintenance.pdf'))->assertOk();

        $this->actingAs($user)
            ->post(route('units.store'), [
                'unit_number' => '999',
                'tower' => 'Torre Z',
                'unit_type' => 'Suite',
                'owner_name' => 'Bloqueado',
                'owner_email' => 'bloqueado@boleo.mx',
                'ordinary_fee' => '1000.00',
                'extraordinary_fee' => '0',
                'parking_rent' => '0',
                'storage_rent' => '0',
                'parking_spots' => '0',
                'storage_rooms' => '0',
                'clothesline_cages' => '0',
                'fee' => '1000.00',
                'status' => 'Pagado',
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('payments.store'), [
                'unit_id' => $unit->id,
                'concept' => 'Pago restringido',
                'amount' => '100.00',
                'paid_at' => now()->toDateString(),
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('providers.store'), [
                'name' => 'Proveedor restringido',
                'category' => 'Prueba',
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('users.store'), [
                'name' => 'Intento invalido',
                'email' => 'invalido@boleo.mx',
                'phone' => '5599999999',
                'role' => 'admin',
                'password' => 'claveSegura123',
                'password_confirmation' => 'claveSegura123',
            ])
            ->assertForbidden();
    }

    public function test_billing_reports_show_pending_balance_and_debtors(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $currentUnit = Unit::query()->create([
            'unit_number' => '101',
            'tower' => 'Torre A',
            'unit_type' => 'Departamento',
            'owner_name' => 'Lucia Prado',
            'owner_email' => 'lucia@boleo.mx',
            'ordinary_fee' => 2500,
            'extraordinary_fee' => 0,
            'parking_rent' => 0,
            'storage_rent' => 0,
            'parking_spots' => 0,
            'storage_rooms' => 0,
            'clothesline_cages' => 0,
            'fee' => 2500,
            'status' => 'Pagado',
        ]);
        $debtorUnit = Unit::query()->create([
            'unit_number' => '102',
            'tower' => 'Torre A',
            'unit_type' => 'Departamento',
            'owner_name' => 'Mario Soto',
            'owner_email' => 'mario@boleo.mx',
            'ordinary_fee' => 2500,
            'extraordinary_fee' => 0,
            'parking_rent' => 0,
            'storage_rent' => 0,
            'parking_spots' => 0,
            'storage_rooms' => 0,
            'clothesline_cages' => 0,
            'fee' => 2500,
            'status' => 'Atrasado',
        ]);

        Payment::query()->create([
            'unit_id' => $currentUnit->id,
            'concept' => 'Cuota mantenimiento',
            'amount' => 2500,
            'status' => 'Completado',
            'paid_at' => now(),
        ]);
        Payment::query()->create([
            'unit_id' => $debtorUnit->id,
            'concept' => 'Abono parcial',
            'amount' => 1000,
            'status' => 'Completado',
            'paid_at' => now(),
        ]);
        Payment::query()->create([
            'unit_id' => $debtorUnit->id,
            'concept' => 'Pago historico',
            'amount' => 2500,
            'status' => 'Completado',
            'paid_at' => now()->subMonth(),
        ]);

        $billingResponse = $this->actingAs($admin)->get(route('billing', ['unit' => $debtorUnit->id]));

        $billingResponse->assertOk();
        $billingResponse->assertSee('Deudor');
        $billingResponse->assertSee('$1,500.00', false);

        $reportResponse = $this->actingAs($admin)->get(route('billing.report.pdf'));
        $reportResponse->assertOk();
        $reportResponse->assertHeader('content-type', 'application/pdf');

        $debtorsResponse = $this->actingAs($admin)->get(route('billing.debtors.pdf'));
        $debtorsResponse->assertOk();
        $debtorsResponse->assertHeader('content-type', 'application/pdf');
    }

    public function test_admin_can_download_monthly_resident_report_pdf(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        CondominiumProfile::query()->create([
            'id' => 1,
            'commercial_name' => 'Boleo Condominio Centro',
            'admin_name' => 'Rodolfo Quevedo',
            'bank' => 'Banamex',
            'account_holder' => 'Administración Boleo',
            'account_number' => '02180702205744219',
            'clabe' => '02180702205744219',
            'ordinary_fee_amount' => 2500,
        ]);

        $unit = Unit::query()->create([
            'unit_number' => '402',
            'tower' => 'Torre A',
            'unit_type' => 'Departamento',
            'owner_name' => 'Alejandra Soto',
            'owner_email' => 'alejandra@boleo.mx',
            'ordinary_fee' => 2500,
            'extraordinary_fee' => 200,
            'parking_rent' => 150,
            'storage_rent' => 0,
            'parking_spots' => 1,
            'storage_rooms' => 0,
            'clothesline_cages' => 0,
            'fee' => 2500,
            'status' => 'Pagado',
        ]);

        Payment::query()->create([
            'unit_id' => $unit->id,
            'concept' => 'Cuota mantenimiento abril',
            'amount' => 1200,
            'status' => 'Completado',
            'paid_at' => now()->startOfMonth()->addDays(3),
        ]);

        MaintenanceExpense::query()->create([
            'spent_at' => now()->startOfMonth()->addDays(2),
            'expense_group' => 'fixed',
            'category' => 'Vigilancia',
            'report_month' => now()->startOfMonth()->toDateString(),
            'concept' => 'Servicio de vigilancia mensual',
            'amount' => 14500,
            'observations' => 'Turno nocturno y diurno',
        ]);

        $response = $this->actingAs($admin)->get(route('billing.resident.monthly.pdf', [
            'unit' => $unit->id,
            'month' => now()->format('Y-m'),
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_duplicate_payment_for_same_unit_amount_concept_and_date_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $unit = Unit::query()->create([
            'unit_number' => '501',
            'tower' => 'Torre F',
            'unit_type' => 'Loft',
            'owner_name' => 'Paola Ruiz',
            'owner_email' => 'paola@boleo.mx',
            'ordinary_fee' => 1800,
            'extraordinary_fee' => 0,
            'parking_rent' => 0,
            'storage_rent' => 0,
            'parking_spots' => 0,
            'storage_rooms' => 0,
            'clothesline_cages' => 0,
            'fee' => 1800,
            'status' => 'Pagado',
        ]);

        $payload = [
            'unit_id' => $unit->id,
            'concept' => 'Cuota abril',
            'amount' => '1800.00',
            'paid_at' => now()->toDateString(),
        ];

        $this->actingAs($admin)
            ->post(route('payments.store'), $payload)
            ->assertRedirect(route('billing', ['unit' => $unit->id]));

        $this->actingAs($admin)
            ->from(route('billing', ['unit' => $unit->id]))
            ->post(route('payments.store'), $payload)
            ->assertRedirect(route('billing', ['unit' => $unit->id]));

        $this->assertCount(1, Payment::query()->where('unit_id', $unit->id)->get());
    }

    public function test_admin_can_update_condominium_profile_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('settings.update'), [
                'commercial_name' => 'Boleo Residencial Norte',
                'tax_id' => 'RFC-801214-770',
                'address' => 'Av. Principal 100, CDMX',
                'ordinary_fee_amount' => '3200.00',
                'fee_type' => 'standard',
                'departments_count' => '48',
                'parking_spaces_count' => '60',
                'storage_rooms_count' => '18',
                'clothesline_cages_count' => '24',
                'security_booth' => '1',
                'admin_name' => 'Ana Ortega',
                'admin_email' => 'ana@boleo.mx',
                'admin_phone' => '5512340000',
                'elevators_enabled' => '1',
                'elevators_count' => '2',
                'cisterns_enabled' => '1',
                'cisterns_count' => '1',
                'water_tanks_enabled' => '1',
                'water_tanks_count' => '3',
                'hydropneumatics_enabled' => '1',
                'hydropneumatics_count' => '2',
                'bank' => 'Banco Nacional',
                'account_holder' => 'Administracion Boleo AC',
                'account_number' => '1234567890',
                'clabe' => '002010123456789012',
            ])
            ->assertRedirect(route('settings'));

        $this->assertDatabaseHas('condominium_profiles', [
            'id' => 1,
            'commercial_name' => 'Boleo Residencial Norte',
            'departments_count' => 48,
            'security_booth' => true,
        ]);
    }

    public function test_admin_can_update_infrastructure_settings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('settings.infrastructure.update'), [
                'elevators_enabled' => '1',
                'elevators_count' => '2',
                'cisterns_enabled' => '1',
                'cisterns_count' => '1',
                'water_tanks_enabled' => '0',
                'water_tanks_count' => '0',
                'hydropneumatics_enabled' => '1',
                'hydropneumatics_count' => '3',
            ])
            ->assertRedirect(route('settings'));

        $this->assertDatabaseHas('condominium_profiles', [
            'id' => 1,
            'elevators_enabled' => true,
            'elevators_count' => 2,
            'cisterns_enabled' => true,
            'cisterns_count' => 1,
            'water_tanks_enabled' => false,
            'water_tanks_count' => 0,
            'hydropneumatics_enabled' => true,
            'hydropneumatics_count' => 3,
        ]);
    }

    public function test_admin_can_register_amenities_tasks_and_expenses(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $provider = Provider::query()->create([
            'name' => 'Servicios Hidraulicos MX',
            'category' => 'Plomeria',
            'phone' => '5511111111',
            'email' => 'contacto@hidraulicos.mx',
        ]);

        $this->actingAs($admin)
            ->post(route('amenities.store'), [
                'name' => 'Piscina Olimpica',
                'area' => 'Amenidades',
                'status' => 'Disponible',
                'capacity' => '40 personas',
                'hours' => '06:00 - 22:00',
                'notes' => 'Uso exclusivo de residentes',
            ])
            ->assertRedirect(route('amenities'));

        $this->actingAs($admin)
            ->post(route('maintenance.tasks.store'), [
                'title' => 'Revision de bomba principal',
                'area' => 'Piscina',
                'provider_id' => $provider->id,
                'last_cost' => '1250.00',
                'due_date' => now()->toDateString(),
                'status' => 'Pendiente',
                'notes' => 'Inspeccion mensual',
            ])
            ->assertRedirect(route('maintenance'));

        $this->actingAs($admin)
            ->post(route('maintenance.expenses.store'), [
                'spent_at' => now()->toDateString(),
                'expense_group' => 'fixed',
                'category' => 'Vigilancia',
                'report_month' => now()->format('Y-m'),
                'concept' => 'Cambio de valvula',
                'provider_id' => $provider->id,
                'amount' => '890.00',
                'observations' => 'Recibo del mes',
            ])
            ->assertRedirect(route('maintenance', ['expense_month' => now()->format('Y-m')]));

        $this->assertDatabaseHas('amenities', ['name' => 'Piscina Olimpica']);
        $this->assertDatabaseHas('maintenance_tasks', ['title' => 'Revision de bomba principal']);
        $this->assertDatabaseHas('maintenance_expenses', [
            'concept' => 'Cambio de valvula',
            'expense_group' => 'fixed',
            'category' => 'Vigilancia',
        ]);
    }

    public function test_maintenance_page_renders_successfully(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('maintenance'))
            ->assertOk()
            ->assertSee('Gestión de Mantenimiento');
    }

    public function test_admin_can_register_monthly_expense_with_document_and_download_reports(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => 'admin']);
        $provider = Provider::query()->create([
            'name' => 'Seguridad Central',
            'category' => 'Vigilancia',
            'phone' => '5511112211',
            'email' => 'seguridad@boleo.mx',
        ]);

        $this->actingAs($admin)
            ->post(route('maintenance.expenses.store'), [
                'spent_at' => now()->toDateString(),
                'expense_group' => 'fixed',
                'category' => 'Vigilancia',
                'report_month' => now()->format('Y-m'),
                'concept' => 'Recibo de vigilancia',
                'provider_id' => $provider->id,
                'amount' => '3200.00',
                'observations' => 'Cobro mensual de vigilancia',
                'document' => UploadedFile::fake()->create('vigilancia.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('maintenance', ['expense_month' => now()->format('Y-m')]));

        $expense = MaintenanceExpense::query()->firstOrFail();

        Storage::disk('local')->assertExists($expense->document_path);

        $this->actingAs($admin)
            ->get(route('maintenance.expenses.monthly.pdf', ['expense_month' => now()->format('Y-m')]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($admin)
            ->get(route('maintenance.expenses.receipt.pdf', $expense))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($admin)
            ->get(route('maintenance.expenses.document', $expense))
            ->assertOk();
    }

    public function test_billing_page_shows_recent_resident_payment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $unit = Unit::query()->create([
            'unit_number' => '601',
            'tower' => 'Torre G',
            'unit_type' => 'Departamento',
            'owner_name' => 'Rosa Mejia',
            'owner_email' => 'rosa@boleo.mx',
            'ordinary_fee' => 1900,
            'extraordinary_fee' => 0,
            'parking_rent' => 0,
            'storage_rent' => 0,
            'parking_spots' => 1,
            'storage_rooms' => 0,
            'clothesline_cages' => 0,
            'fee' => 1900,
            'status' => 'Pagado',
        ]);

        Payment::query()->create([
            'unit_id' => $unit->id,
            'concept' => 'Pago residente abril',
            'amount' => 1900,
            'status' => 'Completado',
            'paid_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('billing', ['unit' => $unit->id]))
            ->assertOk()
            ->assertSee('Pagos Reportados por Residentes')
            ->assertSee('Pago residente abril')
            ->assertSee('Rosa Mejia');
    }

    public function test_admin_can_register_amenity_with_optional_fields_empty(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('amenities.store'), [
                'name' => 'Piscina Central',
                'area' => 'Amenidades',
                'status' => 'Disponible',
                'capacity' => '',
                'hours' => '',
                'notes' => '',
            ])
            ->assertRedirect(route('amenities'));

        $this->assertDatabaseHas('amenities', [
            'name' => 'Piscina Central',
            'capacity' => '',
            'hours' => '',
        ]);
    }

    public function test_admin_can_delete_amenity_cards_from_amenities_view(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $amenity = Amenity::query()->create([
            'name' => 'Piscina Familiar',
            'area' => 'Amenidades',
            'status' => 'Disponible',
            'capacity' => '20 personas',
            'hours' => '08:00 - 20:00',
        ]);

        $this->actingAs($admin)
            ->delete(route('amenities.destroy', $amenity))
            ->assertRedirect(route('amenities'));

        $this->assertDatabaseMissing('amenities', [
            'id' => $amenity->id,
        ]);
    }

    public function test_authenticated_user_can_create_amenity_reservation_without_schedule_conflict(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)
            ->post(route('amenities.reservations.store'), [
                'amenity_name' => 'Salon de Eventos',
                'booking_date' => now()->toDateString(),
                'start_time' => '10:00',
                'end_time' => '12:00',
                'notes' => 'Cumpleanos',
            ])
            ->assertRedirect(route('amenities'));

        $amenity = Amenity::query()->where('name', 'Salon de Eventos')->firstOrFail();

        $this->assertDatabaseHas('amenity_reservations', [
            'amenity_id' => $amenity->id,
            'user_id' => $user->id,
            'status' => 'Reservada',
        ]);
        $this->assertSame('Reservada', $amenity->fresh()->status);
    }

    public function test_amenity_reservation_rejects_conflicting_schedule(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $amenity = Amenity::query()->create([
            'name' => 'Terraza',
            'area' => 'Social',
            'status' => 'Disponible',
            'capacity' => '25 personas',
            'hours' => '09:00 - 21:00',
        ]);

        AmenityReservation::query()->create([
            'amenity_id' => $amenity->id,
            'user_id' => $user->id,
            'booking_date' => now()->toDateString(),
            'start_time' => '14:00',
            'end_time' => '16:00',
            'status' => 'Reservada',
        ]);

        $this->actingAs($user)
            ->from(route('amenities'))
            ->post(route('amenities.reservations.store'), [
                'amenity_name' => 'Terraza',
                'booking_date' => now()->toDateString(),
                'start_time' => '15:00',
                'end_time' => '17:00',
                'notes' => 'Choque de horario',
            ])
            ->assertRedirect(route('amenities'));

        $this->assertCount(1, AmenityReservation::query()->where('amenity_id', $amenity->id)->get());
    }

    public function test_admin_can_cancel_and_delete_amenity_reservation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);
        $amenity = Amenity::query()->create([
            'name' => 'Cancha de Tenis',
            'area' => 'Deportiva',
            'status' => 'Disponible',
            'capacity' => '8 personas',
            'hours' => '07:00 - 21:00',
        ]);

        $reservation = AmenityReservation::query()->create([
            'amenity_id' => $amenity->id,
            'user_id' => $user->id,
            'booking_date' => now()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '09:00',
            'status' => 'Reservada',
        ]);

        $this->actingAs($admin)
            ->patch(route('amenities.reservations.cancel', $reservation))
            ->assertRedirect(route('amenities'));

        $this->assertDatabaseHas('amenity_reservations', [
            'id' => $reservation->id,
            'status' => 'Cancelada',
        ]);
        $this->assertSame('Disponible', $amenity->fresh()->status);

        $this->actingAs($admin)
            ->get(route('amenities'))
            ->assertSee('0 movimientos')
            ->assertDontSee('08:00 - 09:00');

        $this->actingAs($admin)
            ->delete(route('amenities.reservations.destroy', $reservation))
            ->assertRedirect(route('amenities'));

        $this->assertDatabaseMissing('amenity_reservations', [
            'id' => $reservation->id,
        ]);
        $this->assertSame('Disponible', $amenity->fresh()->status);
    }

    public function test_admin_can_edit_amenity_reservation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);
        $amenity = Amenity::query()->create([
            'name' => 'Salon Ingles',
            'area' => 'Social',
            'status' => 'Disponible',
            'capacity' => '20 personas',
            'hours' => '09:00 - 20:00',
        ]);

        $reservation = AmenityReservation::query()->create([
            'amenity_id' => $amenity->id,
            'user_id' => $user->id,
            'booking_date' => now()->toDateString(),
            'start_time' => '11:00',
            'end_time' => '12:00',
            'status' => 'Reservada',
            'notes' => 'Original',
        ]);

        $this->actingAs($admin)
            ->patch(route('amenities.reservations.update', $reservation), [
                'amenity_name' => 'Salon Ingles',
                'booking_date' => now()->addDay()->toDateString(),
                'start_time' => '12:00',
                'end_time' => '13:30',
                'notes' => 'Actualizada',
            ])
            ->assertRedirect(route('amenities'));

        $this->assertDatabaseHas('amenity_reservations', [
            'id' => $reservation->id,
            'start_time' => '12:00',
            'end_time' => '13:30',
            'notes' => 'Actualizada',
        ]);
        $this->assertSame(now()->addDay()->toDateString(), $reservation->fresh()->booking_date?->toDateString());
    }

    public function test_user_cannot_edit_cancel_or_delete_amenity_reservation(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $amenity = Amenity::query()->create([
            'name' => 'Terraza Norte',
            'area' => 'Social',
            'status' => 'Disponible',
            'capacity' => '15 personas',
            'hours' => '10:00 - 22:00',
        ]);

        $reservation = AmenityReservation::query()->create([
            'amenity_id' => $amenity->id,
            'user_id' => $user->id,
            'booking_date' => now()->toDateString(),
            'start_time' => '16:00',
            'end_time' => '18:00',
            'status' => 'Reservada',
        ]);

        $this->actingAs($user)
            ->patch(route('amenities.reservations.update', $reservation), [
                'amenity_name' => 'Terraza Norte',
                'booking_date' => now()->addDay()->toDateString(),
                'start_time' => '17:00',
                'end_time' => '18:30',
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->patch(route('amenities.reservations.cancel', $reservation))
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('amenities.reservations.destroy', $reservation))
            ->assertForbidden();
    }

    public function test_user_cannot_delete_amenity_cards(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $amenity = Amenity::query()->create([
            'name' => 'Ludoteca',
            'area' => 'Social',
            'status' => 'Disponible',
            'capacity' => '15 personas',
            'hours' => '10:00 - 19:00',
        ]);

        $this->actingAs($user)
            ->delete(route('amenities.destroy', $amenity))
            ->assertForbidden();

        $this->assertDatabaseHas('amenities', [
            'id' => $amenity->id,
        ]);
    }

    public function test_users_can_download_individual_payment_receipts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);
        $unit = Unit::query()->create([
            'unit_number' => '210',
            'tower' => 'Torre B',
            'unit_type' => 'Departamento',
            'owner_name' => 'Sofia Mena',
            'owner_email' => 'sofia@boleo.mx',
            'ordinary_fee' => 2200,
            'extraordinary_fee' => 0,
            'parking_rent' => 0,
            'storage_rent' => 0,
            'parking_spots' => 1,
            'storage_rooms' => 0,
            'clothesline_cages' => 0,
            'fee' => 2200,
            'status' => 'Pagado',
        ]);

        $payment = Payment::query()->create([
            'unit_id' => $unit->id,
            'concept' => 'Cuota abril',
            'amount' => 2200,
            'status' => 'Completado',
            'paid_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('payments.receipt.pdf', $payment))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($user)
            ->get(route('payments.receipt.pdf', $payment))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
