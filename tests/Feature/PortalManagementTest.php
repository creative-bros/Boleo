<?php

namespace Tests\Feature;

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
use App\Support\DocxTemplateText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use ZipArchive;

class PortalManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_is_available(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Boleo');
        $response->assertSee('Portal de Administración');
        $response->assertSee('Correo electrónico');
        $response->assertSee('name="email"', false);
        $response->assertSee('placeholder="Ingresa tu correo"', false);
        $response->assertSee('value=""', false);
        $response->assertDontSee('name="remember" value="1" checked', false);
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

    public function test_admin_can_open_settings_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('settings'))
            ->assertOk()
            ->assertSee('Ajustes del Condominio')
            ->assertSee('Horarios y reglamento')
            ->assertSee('NO HAY')
            ->assertSee('02:00')
            ->assertSee('Cerrar sesión');
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
            ->assertSee('Buscador de Residentes')
            ->assertSee('Resultado encontrado')
            ->assertSee('Comandos para Reportes')
            ->assertSee('Características del Condominio', false)
            ->assertSee('Unidad registrada')
            ->assertSee('Jose Rivera');
    }

    public function test_units_search_switches_condominium_and_shows_imported_resident_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $currentProfile = CondominiumProfile::query()->create([
            'id' => 1,
            'commercial_name' => 'Boleo Condominio Centro',
        ]);
        $targetProfile = CondominiumProfile::query()->create([
            'id' => 2,
            'commercial_name' => 'Boleo Torre Norte',
            'address' => 'Av. Norte 500',
        ]);

        $account = ImportedResidentAccount::query()->create([
            'condominium_profile_id' => $targetProfile->id,
            'unit_number' => '501',
            'tower' => 'Torre N',
            'owner_name' => 'Mariana Lopez',
            'total_debt' => 4500,
            'status' => 'adeudo',
            'raw_payload' => [
                'Correo' => 'mariana@boleo.mx',
            ],
            'imported_at' => now(),
        ]);

        $this->actingAs($admin)
            ->withSession(['settings_condominium_profile_id' => $currentProfile->id])
            ->get(route('units', ['condominium' => 'Boleo Torre Norte', 'q' => 'Mariana']))
            ->assertOk()
            ->assertSee('Resultado encontrado')
            ->assertSee('Boleo Torre Norte')
            ->assertSee('Mariana Lopez')
            ->assertSee('mariana@boleo.mx')
            ->assertSee('Base histórica importada')
            ->assertSee('$4,500.00')
            ->assertSee(e(route('billing.letters.show', $account)), false);
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
            ->assertRedirect(route('altas'));

        $this->actingAs($admin)
            ->get(route('altas'))
            ->assertOk()
            ->assertSee('name="name" value=""', false)
            ->assertSee('name="email" value=""', false)
            ->assertSee('name="phone" value=""', false)
            ->assertSee('>Crear cuenta<', false);

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
            ->assertRedirect(route('altas'));

        $this->assertDatabaseHas('users', [
            'id' => $managedUser->id,
            'name' => 'Carlos Medina Admin',
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->delete(route('users.destroy', $managedUser))
            ->assertRedirect(route('altas'));

        $this->assertDatabaseMissing('users', [
            'id' => $managedUser->id,
        ]);
    }

    public function test_resident_user_requires_condominium_assignment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->from(route('altas'))
            ->post(route('users.store'), [
                'name' => 'Residente Prueba',
                'email' => 'residente.prueba@boleo.mx',
                'phone' => '5599001122',
                'role' => 'resident',
                'condominium_profile_id' => '',
                'password' => 'claveSegura123',
                'password_confirmation' => 'claveSegura123',
            ])
            ->assertRedirect(route('altas'))
            ->assertSessionHasErrors('condominium_profile_id', null, 'settingsUsers');

        $this->assertDatabaseMissing('users', [
            'email' => 'residente.prueba@boleo.mx',
        ]);
    }

    public function test_deleting_resident_removes_linked_information(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $resident = User::factory()->create([
            'name' => 'Elena Prado',
            'email' => 'elena.prado@boleo.mx',
            'phone' => '5512223344',
            'role' => 'resident',
        ]);

        $unit = Unit::query()->create([
            'unit_number' => '302',
            'tower' => 'Torre C',
            'unit_type' => 'Departamento',
            'owner_name' => 'Elena Prado',
            'owner_email' => 'elena.prado@boleo.mx',
            'ordinary_fee' => 2400,
            'extraordinary_fee' => 0,
            'parking_rent' => 0,
            'storage_rent' => 0,
            'parking_spots' => 1,
            'storage_rooms' => 0,
            'clothesline_cages' => 0,
            'fee' => 2400,
            'status' => 'Atrasado',
        ]);

        Payment::query()->create([
            'unit_id' => $unit->id,
            'concept' => 'Pago mayo',
            'amount' => 1200,
            'status' => 'Aplicado',
            'paid_at' => now()->toDateString(),
        ]);

        Amenity::query()->create([
            'name' => 'Salon social',
            'area' => 'Comunidad',
            'status' => 'Disponible',
            'capacity' => '40 personas',
            'hours' => '08:00-20:00',
            'notes' => '',
        ]);

        $reservation = AmenityReservation::query()->create([
            'amenity_id' => Amenity::query()->value('id'),
            'user_id' => $resident->id,
            'booking_date' => now()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'Activa',
            'notes' => 'Reserva residente',
        ]);

        $this->actingAs($admin)
            ->delete(route('users.destroy', $resident))
            ->assertRedirect(route('altas'));

        $this->assertDatabaseMissing('users', ['id' => $resident->id]);
        $this->assertDatabaseMissing('units', ['id' => $unit->id]);
        $this->assertSame(0, Payment::query()->where('unit_id', $unit->id)->count());
        $this->assertDatabaseMissing('amenity_reservations', ['id' => $reservation->id]);
    }

    public function test_deleting_admin_removes_condominium_information(): void
    {
        Storage::fake('public');

        $actingAdmin = User::factory()->create(['role' => 'admin']);
        $managedAdmin = User::factory()->create([
            'name' => 'Admin Condominio',
            'email' => 'admin.condo@boleo.mx',
            'phone' => '5599998888',
            'role' => 'admin',
        ]);

        $profile = CondominiumProfile::query()->create([
            'id' => 1,
            'commercial_name' => 'Condominio Prueba',
            'admin_name' => 'Admin Condominio',
            'admin_email' => 'admin.condo@boleo.mx',
            'admin_phone' => '5599998888',
            'regulations_path' => 'regulations/test.pdf',
            'admin_registration_documents' => [
                'gym' => [
                    'label' => 'Amenidades e instalaciones comunes - GYM',
                    'name' => 'gym.pdf',
                    'path' => 'admin-registration-documents/gym.pdf',
                ],
            ],
        ]);

        Storage::disk('public')->put('regulations/test.pdf', 'pdf');
        Storage::disk('public')->put('admin-registration-documents/gym.pdf', 'pdf');

        AssemblyMinute::query()->create([
            'condominium_profile_id' => $profile->id,
            'title' => 'Minuta de prueba',
            'assembly_date' => now()->toDateString(),
            'duration' => '2 horas',
            'document_path' => 'assembly-minutes/minuta.pdf',
        ]);
        Storage::disk('public')->put('assembly-minutes/minuta.pdf', 'pdf');

        Provider::query()->create([
            'name' => 'Proveedor Uno',
            'category' => 'Limpieza',
            'phone' => '5510101010',
            'email' => 'proveedor@boleo.mx',
        ]);

        $unit = Unit::query()->create([
            'unit_number' => '101',
            'tower' => 'Torre A',
            'unit_type' => 'Departamento',
            'owner_name' => 'Residente Uno',
            'owner_email' => 'residente@boleo.mx',
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

        Payment::query()->create([
            'unit_id' => $unit->id,
            'concept' => 'Pago inicial',
            'amount' => 2000,
            'status' => 'Aplicado',
            'paid_at' => now()->toDateString(),
        ]);

        Amenity::query()->create([
            'name' => 'Alberca',
            'area' => 'Amenidad',
            'status' => 'Disponible',
            'capacity' => '30',
            'hours' => '08:00-20:00',
            'notes' => '',
        ]);

        MaintenanceTask::query()->create([
            'title' => 'Revision general',
            'area' => 'Lobby',
            'provider_id' => Provider::query()->value('id'),
            'last_cost' => 1500,
            'due_date' => now()->toDateString(),
            'status' => 'Pendiente',
            'notes' => '',
        ]);

        MaintenanceExpense::query()->create([
            'spent_at' => now()->toDateString(),
            'expense_group' => 'fijo',
            'category' => 'Limpieza',
            'report_month' => now()->startOfMonth()->toDateString(),
            'concept' => 'Pago vigilancia',
            'provider_id' => Provider::query()->value('id'),
            'amount' => 1800,
            'document_path' => 'maintenance-expenses/vigilancia.pdf',
            'document_name' => 'vigilancia.pdf',
            'observations' => '',
        ]);
        Storage::disk('public')->put('maintenance-expenses/vigilancia.pdf', 'pdf');

        $this->actingAs($actingAdmin)
            ->delete(route('users.destroy', $managedAdmin))
            ->assertRedirect(route('altas'));

        $this->assertDatabaseMissing('users', ['id' => $managedAdmin->id]);
        $this->assertDatabaseMissing('condominium_profiles', ['id' => 1]);
        $this->assertDatabaseCount('assembly_minutes', 0);
        $this->assertDatabaseCount('providers', 0);
        $this->assertDatabaseCount('units', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('amenities', 0);
        $this->assertDatabaseCount('maintenance_tasks', 0);
        $this->assertDatabaseCount('maintenance_expenses', 0);
        Storage::disk('public')->assertMissing('regulations/test.pdf');
        Storage::disk('public')->assertMissing('admin-registration-documents/gym.pdf');
        Storage::disk('public')->assertMissing('assembly-minutes/minuta.pdf');
        Storage::disk('public')->assertMissing('maintenance-expenses/vigilancia.pdf');
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
            ->get(route('altas', ['q' => 'Sandra']))
            ->assertOk()
            ->assertDontSee('Resumen del auxiliar en edicion')
            ->assertSee('Sandra Mena')
            ->assertSee('Editar');

        $this->actingAs($admin)
            ->get(route('altas', ['edit_user' => $managedUser->id]))
            ->assertOk()
            ->assertSee('Resumen del usuario en edición')
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

    public function test_resident_role_is_limited_to_reading_and_pdf_downloads(): void
    {
        $resident = User::factory()->create(['role' => 'resident']);
        $unit = Unit::query()->create([
            'unit_number' => '402',
            'tower' => 'Torre E',
            'owner_name' => 'Andrea Rios',
            'owner_email' => $resident->email,
            'unit_type' => 'Departamento',
            'fee' => 1800,
            'ordinary_fee' => 1800,
            'status' => 'Atrasado',
        ]);

        $payment = Payment::query()->create([
            'unit_id' => $unit->id,
            'concept' => 'Cuota mayo',
            'amount' => 1800,
            'paid_at' => now()->toDateString(),
        ]);

        $this->actingAs($resident)
            ->get(route('billing'))
            ->assertOk();

        $this->actingAs($resident)
            ->get(route('billing.pdf', ['unit' => $unit->id]))
            ->assertOk();

        $this->actingAs($resident)
            ->get(route('payments.receipt.pdf', $payment))
            ->assertOk();

        $this->actingAs($resident)
            ->post(route('units.store'), [
                'unit_number' => '999',
                'tower' => 'X',
                'owner_name' => 'No permitido',
                'owner_email' => 'x@example.com',
                'ordinary_fee' => 1000,
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

    public function test_admin_can_import_excel_base_as_editable_billing_accounts(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'admin']);
        CondominiumProfile::query()->create([
            'id' => 1,
            'commercial_name' => 'Boleo Condominio Import',
        ]);

        $path = tempnam(sys_get_temp_dir(), 'boleo-base-').'.csv';
        file_put_contents($path, implode(PHP_EOL, [
            'Base de cobranza',
            'DEPT,Nombre,Correo,TOTAL ADEUDO',
            '101,Ana Deudora,ana@boleo.mx,1500',
            '102,Luis Corriente,luis@boleo.mx,0',
        ]));

        $file = new UploadedFile(
            $path,
            'base-cobranza.csv',
            'text/csv',
            null,
            true
        );

        $response = $this->actingAs($admin)
            ->post(route('billing.import-base'), ['base_file' => $file]);

        $baseImport = BillingBaseImport::query()->firstOrFail();

        $response
            ->assertRedirect(route('billing', ['base_import' => $baseImport->id]))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('imported_resident_accounts', [
            'unit_number' => '101',
            'owner_name' => 'Ana Deudora',
            'total_debt' => 1500,
            'status' => 'adeudo',
        ]);
        $this->assertDatabaseHas('imported_resident_accounts', [
            'unit_number' => '102',
            'owner_name' => 'Luis Corriente',
            'total_debt' => 0,
            'status' => 'no_adeudo',
        ]);
        $this->assertDatabaseHas('units', [
            'unit_number' => '101',
            'owner_name' => 'Ana Deudora',
            'owner_email' => 'ana@boleo.mx',
            'status' => 'Atrasado',
        ]);
        $this->assertDatabaseHas('units', [
            'unit_number' => '102',
            'owner_name' => 'Luis Corriente',
            'owner_email' => 'luis@boleo.mx',
            'status' => 'Pagado',
        ]);

        $this->assertSame(2, ImportedResidentAccount::query()->count());
        $this->assertSame(2, Unit::query()->count());
        @unlink($path);
    }

    public function test_admin_can_import_xlsx_base_as_editable_billing_accounts(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'admin']);
        CondominiumProfile::query()->create([
            'id' => 1,
            'commercial_name' => 'Boleo Condominio XLSX',
        ]);

        $path = tempnam(sys_get_temp_dir(), 'boleo-base-').'.xlsx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Base" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>Base de cobranza</t></is></c></row><row r="2"><c r="A2" t="inlineStr"><is><t>DEPT</t></is></c><c r="B2" t="inlineStr"><is><t>Nombre</t></is></c><c r="C2" t="inlineStr"><is><t>Correo</t></is></c><c r="D2" t="inlineStr"><is><t>TOTAL ADEUDO</t></is></c></row><row r="3"><c r="A3" t="inlineStr"><is><t>201</t></is></c><c r="B3" t="inlineStr"><is><t>Rosa Excel</t></is></c><c r="C3" t="inlineStr"><is><t>rosa@boleo.mx</t></is></c><c r="D3"><v>3200</v></c></row><row r="4"><c r="A4" t="inlineStr"><is><t>202</t></is></c><c r="B4" t="inlineStr"><is><t>Mateo Excel</t></is></c><c r="C4" t="inlineStr"><is><t>mateo@boleo.mx</t></is></c><c r="D4"><v>0</v></c></row></sheetData></worksheet>');
        $zip->close();

        $file = new UploadedFile(
            $path,
            'base-cobranza.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->actingAs($admin)
            ->post(route('billing.import-base'), ['base_file' => $file]);

        $baseImport = BillingBaseImport::query()->firstOrFail();

        $response
            ->assertRedirect(route('billing', ['base_import' => $baseImport->id]))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('imported_resident_accounts', [
            'unit_number' => '201',
            'owner_name' => 'Rosa Excel',
            'total_debt' => 3200,
            'status' => 'adeudo',
        ]);
        $this->assertDatabaseHas('imported_resident_accounts', [
            'unit_number' => '202',
            'owner_name' => 'Mateo Excel',
            'total_debt' => 0,
            'status' => 'no_adeudo',
        ]);

        @unlink($path);
    }

    public function test_duplicate_billing_base_file_is_not_uploaded_twice(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'admin']);
        CondominiumProfile::query()->create([
            'id' => 1,
            'commercial_name' => 'Boleo Duplicados',
        ]);
        $contents = implode(PHP_EOL, [
            'DEPT,Nombre,TOTAL ADEUDO',
            '101,Ana Duplicada,1500',
            '102,Luis Duplicado,0',
        ]);
        $firstPath = tempnam(sys_get_temp_dir(), 'boleo-duplicate-').'.csv';
        $secondPath = tempnam(sys_get_temp_dir(), 'boleo-duplicate-').'.csv';
        file_put_contents($firstPath, $contents);
        file_put_contents($secondPath, $contents);

        $this->actingAs($admin)
            ->post(route('billing.import-base'), [
                'base_file' => new UploadedFile($firstPath, 'base-duplicada.csv', 'text/csv', null, true),
            ])
            ->assertSessionHas('status');

        $baseImport = BillingBaseImport::query()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('billing.import-base'), [
                'base_file' => new UploadedFile($secondPath, 'base-duplicada.csv', 'text/csv', null, true),
            ])
            ->assertRedirect(route('billing', ['base_import' => $baseImport->id]))
            ->assertSessionHas('status');

        $this->assertSame(1, BillingBaseImport::query()->count());
        $this->assertSame(2, ImportedResidentAccount::query()->count());

        @unlink($firstPath);
        @unlink($secondPath);
    }

    public function test_existing_billing_base_without_hash_is_detected_as_duplicate(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'admin']);
        CondominiumProfile::query()->create([
            'id' => 1,
            'commercial_name' => 'Boleo Duplicado Migrado',
        ]);
        $contents = implode(PHP_EOL, [
            'DEPT,Nombre,TOTAL ADEUDO',
            '101,Ana Existente,1500',
        ]);
        Storage::disk('public')->put('billing-imports/existente.csv', $contents);
        $baseImport = BillingBaseImport::query()->create([
            'condominium_profile_id' => 1,
            'original_name' => 'existente.csv',
            'stored_path' => 'billing-imports/existente.csv',
            'imported_rows' => 1,
            'status' => 'procesada',
            'imported_at' => now(),
        ]);
        $path = tempnam(sys_get_temp_dir(), 'boleo-existing-').'.csv';
        file_put_contents($path, $contents);

        $this->actingAs($admin)
            ->post(route('billing.import-base'), [
                'base_file' => new UploadedFile($path, 'existente.csv', 'text/csv', null, true),
            ])
            ->assertRedirect(route('billing', ['base_import' => $baseImport->id]))
            ->assertSessionHas('status');

        $this->assertSame(1, BillingBaseImport::query()->count());
        $this->assertNotNull($baseImport->fresh()->file_hash);

        @unlink($path);
    }

    public function test_new_billing_base_with_same_units_preserves_previous_excel_rows(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'admin']);
        CondominiumProfile::query()->create([
            'id' => 1,
            'commercial_name' => 'Boleo Historico',
        ]);
        $firstPath = tempnam(sys_get_temp_dir(), 'boleo-history-').'.csv';
        $secondPath = tempnam(sys_get_temp_dir(), 'boleo-history-').'.csv';
        file_put_contents($firstPath, implode(PHP_EOL, [
            'DEPT,Nombre,TOTAL ADEUDO',
            '101,Ana Original,1500',
        ]));
        file_put_contents($secondPath, implode(PHP_EOL, [
            'DEPT,Nombre,TOTAL ADEUDO',
            '101,Ana Nueva,2200',
        ]));

        $this->actingAs($admin)
            ->post(route('billing.import-base'), [
                'base_file' => new UploadedFile($firstPath, 'base-original.csv', 'text/csv', null, true),
            ])
            ->assertSessionHas('status');
        $firstImport = BillingBaseImport::query()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('billing.import-base'), [
                'base_file' => new UploadedFile($secondPath, 'base-nueva.csv', 'text/csv', null, true),
            ])
            ->assertSessionHas('status');
        $secondImport = BillingBaseImport::query()->latest('id')->firstOrFail();

        $this->assertSame(2, BillingBaseImport::query()->count());
        $this->assertDatabaseHas('imported_resident_accounts', [
            'billing_base_import_id' => $firstImport->id,
            'unit_number' => '101',
            'owner_name' => 'Ana Original',
            'total_debt' => 1500,
        ]);
        $this->assertDatabaseHas('imported_resident_accounts', [
            'billing_base_import_id' => $secondImport->id,
            'unit_number' => '101',
            'owner_name' => 'Ana Nueva',
            'total_debt' => 2200,
        ]);

        @unlink($firstPath);
        @unlink($secondPath);
    }

    public function test_billing_screen_falls_back_to_latest_imported_base_when_current_profile_is_empty(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        CondominiumProfile::query()->create([
            'id' => 1,
            'commercial_name' => 'Perfil vacio',
        ]);
        CondominiumProfile::query()->create([
            'id' => 2,
            'commercial_name' => 'Perfil con base',
        ]);
        $baseImport = BillingBaseImport::query()->create([
            'condominium_profile_id' => 2,
            'original_name' => 'base-visible.xlsx',
            'stored_path' => 'billing-imports/base-visible.xlsx',
            'imported_rows' => 1,
            'status' => 'procesada',
            'imported_at' => now(),
        ]);
        ImportedResidentAccount::query()->create([
            'condominium_profile_id' => 2,
            'billing_base_import_id' => $baseImport->id,
            'unit_number' => '301',
            'owner_name' => 'Cuenta Visible',
            'total_debt' => 900,
            'status' => 'adeudo',
            'raw_payload' => [
                'DEPT' => '301',
                'NOMBRE' => 'Cuenta Visible',
                'TOTAL ADEUDO' => '900',
            ],
            'imported_at' => now(),
        ]);

        $this->actingAs($admin)
            ->withSession(['settings_condominium_profile_id' => 1])
            ->get(route('billing'))
            ->assertOk()
            ->assertSee('base-visible.xlsx')
            ->assertSee('Cuenta Visible');
    }

    public function test_admin_can_edit_imported_excel_account_from_billing_screen(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        CondominiumProfile::query()->create([
            'id' => 1,
            'commercial_name' => 'Boleo Condominio Edit Excel',
        ]);
        $account = ImportedResidentAccount::query()->create([
            'condominium_profile_id' => 1,
            'unit_number' => '101',
            'owner_name' => 'Ana Deudora',
            'total_debt' => 1500,
            'status' => 'adeudo',
            'raw_payload' => [
                'DEPT' => '101',
                'NOMBRE' => 'Ana Deudora',
                'CORREO' => 'ana@boleo.mx',
                'TOTAL ADEUDO' => '1500',
            ],
            'imported_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('billing'))
            ->assertOk()
            ->assertSee('Excel editable en pantalla')
            ->assertSee('name="payload[NOMBRE]"', false)
            ->assertSee('form="billing-excel-row-'.$account->id.'"', false);

        $this->actingAs($admin)
            ->put(route('billing.imported-accounts.update', $account), [
                'payload' => [
                    'DEPT' => '101',
                    'NOMBRE' => 'Ana Actualizada',
                    'CORREO' => 'ana.nueva@boleo.mx',
                    'TOTAL ADEUDO' => '250',
                ],
            ])
            ->assertRedirect(route('billing'))
            ->assertSessionHas('status');

        $account->refresh();

        $this->assertSame('Ana Actualizada', $account->owner_name);
        $this->assertSame('250.00', $account->total_debt);
        $this->assertSame('adeudo', $account->status);
        $this->assertSame('ana.nueva@boleo.mx', $account->raw_payload['CORREO']);
        $this->assertDatabaseHas('units', [
            'unit_number' => '101',
            'owner_name' => 'Ana Actualizada',
            'owner_email' => 'ana.nueva@boleo.mx',
            'status' => 'Atrasado',
        ]);
    }

    public function test_admin_can_add_imported_account_amount_by_year_and_period(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        CondominiumProfile::query()->create([
            'id' => 1,
            'commercial_name' => 'Boleo Periodos',
        ]);

        $this->actingAs($admin)
            ->post(route('billing.imported-accounts.store'), [
                'period_year' => '2026',
                'period_month' => '7',
                'period_amount' => '850',
                'payload' => [
                    'DEPT' => '207',
                    'Torre' => 'B',
                    'Nombre' => 'Residente Periodo',
                    'TOTAL ADEUDO' => '0',
                ],
            ])
            ->assertRedirect(route('billing'))
            ->assertSessionHas('status');

        $account = ImportedResidentAccount::query()->where('unit_number', '207')->firstOrFail();

        $this->assertSame('850.00', $account->total_debt);
        $this->assertSame('adeudo', $account->status);
        $this->assertSame('850', $account->raw_payload['2026-07']);
        $this->assertSame('850', $account->raw_payload['TOTAL ADEUDO']);
        $this->assertDatabaseHas('units', [
            'unit_number' => '207',
            'tower' => 'B',
            'owner_name' => 'Residente Periodo',
            'status' => 'Atrasado',
        ]);
    }

    public function test_admin_can_fill_annual_closing_field_from_period_capture(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        CondominiumProfile::query()->create([
            'id' => 1,
            'commercial_name' => 'Boleo Cierre Anual',
        ]);

        $this->actingAs($admin)
            ->post(route('billing.imported-accounts.store'), [
                'period_year' => '2025',
                'period_month' => '12',
                'period_amount' => '1200',
                'payload' => [
                    'DEPT' => '309',
                    'Torre' => 'C',
                    'Nombre' => 'Residente Cierre',
                    'TOTAL ADEUDO' => '0',
                ],
            ])
            ->assertRedirect(route('billing'))
            ->assertSessionHas('status');

        $account = ImportedResidentAccount::query()->where('unit_number', '309')->firstOrFail();

        $this->assertSame('1200.00', $account->total_debt);
        $this->assertSame('1200', $account->raw_payload['2025-12']);
        $this->assertSame('1200', $account->raw_payload['2025']);
        $this->assertSame('1200', $account->year_statuses['2025']);
    }

    public function test_billing_report_buttons_use_selected_imported_account_letter_when_status_matches(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        CondominiumProfile::query()->create([
            'id' => 1,
            'commercial_name' => 'Boleo Cartas',
        ]);
        $paidUnit = Unit::query()->create([
            'unit_number' => '128',
            'tower' => 'A',
            'unit_type' => 'Departamento',
            'owner_name' => 'Residente Sin Adeudo',
            'owner_email' => 'sinadeudo@boleo.mx',
            'ordinary_fee' => 0,
            'extraordinary_fee' => 0,
            'parking_rent' => 0,
            'storage_rent' => 0,
            'parking_spots' => 0,
            'storage_rooms' => 0,
            'clothesline_cages' => 0,
            'fee' => 0,
            'status' => 'Pagado',
        ]);
        $debtorUnit = Unit::query()->create([
            'unit_number' => '2',
            'tower' => 'A',
            'unit_type' => 'Departamento',
            'owner_name' => 'Residente Deudor',
            'owner_email' => 'deudor@boleo.mx',
            'ordinary_fee' => 0,
            'extraordinary_fee' => 0,
            'parking_rent' => 0,
            'storage_rent' => 0,
            'parking_spots' => 0,
            'storage_rooms' => 0,
            'clothesline_cages' => 0,
            'fee' => 0,
            'status' => 'Atrasado',
        ]);
        $paidAccount = ImportedResidentAccount::query()->create([
            'condominium_profile_id' => 1,
            'unit_id' => $paidUnit->id,
            'unit_number' => '128',
            'tower' => 'A',
            'owner_name' => 'Residente Sin Adeudo',
            'total_debt' => 0,
            'status' => 'no_adeudo',
            'raw_payload' => ['DEPT' => '128', 'NOMBRE' => 'Residente Sin Adeudo', 'TOTAL ADEUDO' => '0'],
            'imported_at' => now(),
        ]);
        $debtorAccount = ImportedResidentAccount::query()->create([
            'condominium_profile_id' => 1,
            'unit_id' => $debtorUnit->id,
            'unit_number' => '2',
            'tower' => 'A',
            'owner_name' => 'Residente Deudor',
            'total_debt' => 1200,
            'status' => 'adeudo',
            'raw_payload' => ['DEPT' => '2', 'NOMBRE' => 'Residente Deudor', 'TOTAL ADEUDO' => '1200'],
            'imported_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('billing', ['unit' => $paidUnit->id]))
            ->assertOk()
            ->assertSee(e(route('billing.pdf', [
                'unit' => $paidUnit->id,
                'account' => $paidAccount->id,
                'month' => now()->format('Y-m'),
            ])), false)
            ->assertSee(e(route('billing.resident.monthly.pdf', [
                'unit' => $paidUnit->id,
                'account' => $paidAccount->id,
                'month' => now()->format('Y-m'),
            ])), false)
            ->assertSee(route('billing.letters.show', ['account' => $paidAccount, 'template' => 'no_adeudo']), false)
            ->assertSee(route('billing.letters.show', ['account' => $paidAccount, 'template' => 'adeudo']), false)
            ->assertDontSee(route('billing.report.pdf'), false);

        $this->actingAs($admin)
            ->get(route('billing', ['unit' => $debtorUnit->id]))
            ->assertOk()
            ->assertSee(e(route('billing.pdf', [
                'unit' => $debtorUnit->id,
                'account' => $debtorAccount->id,
                'month' => now()->format('Y-m'),
            ])), false)
            ->assertSee(e(route('billing.resident.monthly.pdf', [
                'unit' => $debtorUnit->id,
                'account' => $debtorAccount->id,
                'month' => now()->format('Y-m'),
            ])), false)
            ->assertSee(route('billing.letters.show', ['account' => $debtorAccount, 'template' => 'no_adeudo']), false)
            ->assertSee(route('billing.letters.show', ['account' => $debtorAccount, 'template' => 'adeudo']), false)
            ->assertDontSee(route('billing.debtors.pdf'), false);

        $this->actingAs($admin)
            ->get(route('billing.letters.show', ['account' => $debtorAccount, 'template' => 'no_adeudo']))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename="carta-no-adeudo-2.pdf"');

        $this->actingAs($admin)
            ->get(route('billing.letters.show', ['account' => $paidAccount, 'template' => 'adeudo']))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename="carta-adeudo-128.pdf"');
    }

    public function test_account_letter_downloads_pdf_and_fills_docx_template_source(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'admin']);
        $templatePath = 'billing-letter-templates/carta-no-adeudo.docx';
        $this->createDocxTemplate(Storage::disk('public')->path($templatePath), [
            'Ciudad de México 13 de JUNIO del 2026.',
            'CARTA DE NO ADEUDO.',
            'Hago constar que el departamento 128 del condominio Real de Boleo II no cuenta con ningún adeudo hasta JUNIO del 2026.',
        ]);
        CondominiumProfile::query()->create([
            'id' => 1,
            'commercial_name' => 'Boleo Prueba',
            'no_debt_letter_template_path' => $templatePath,
        ]);
        $account = ImportedResidentAccount::query()->create([
            'condominium_profile_id' => 1,
            'unit_number' => '122',
            'tower' => 'A',
            'owner_name' => 'Jose Enrique Diaz Rosales',
            'total_debt' => 0,
            'status' => 'no_adeudo',
            'raw_payload' => ['DEPT' => '122', 'NOMBRE' => 'Jose Enrique Diaz Rosales', 'TOTAL ADEUDO' => '0'],
            'imported_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->get(route('billing.letters.show', ['account' => $account, 'template' => 'no_adeudo']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'attachment; filename="carta-no-adeudo-122.pdf"');

        $this->assertStringStartsWith('%PDF', $response->getContent());

        $generatedPath = tempnam(sys_get_temp_dir(), 'boleo-generated-');
        file_put_contents($generatedPath, AccountStatusLetterDocx::render(
            Storage::disk('public')->path($templatePath),
            CondominiumProfile::query()->findOrFail(1),
            $account,
            'no_adeudo'
        ));
        $zip = new ZipArchive;
        $zip->open($generatedPath);
        $documentXml = $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($generatedPath);

        $this->assertStringContainsString('departamento 122', $documentXml);
        $this->assertStringContainsString('Jose Enrique Diaz Rosales', $documentXml);
        $this->assertStringContainsString('Boleo Prueba', $documentXml);
        $this->assertStringContainsString('CARTA DE NO ADEUDO.', $documentXml);
    }

    public function test_account_letter_uses_bundled_templates_when_profile_has_no_uploads(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        CondominiumProfile::query()->create([
            'id' => 1,
            'commercial_name' => 'Boleo II',
        ]);
        $account = ImportedResidentAccount::query()->create([
            'condominium_profile_id' => 1,
            'unit_number' => '128',
            'tower' => 'A',
            'owner_name' => 'Jessica Fabiola Martinez',
            'total_debt' => 0,
            'status' => 'no_adeudo',
            'raw_payload' => ['DEPT' => '128', 'NOMBRE' => 'Jessica Fabiola Martinez', 'TOTAL ADEUDO' => '0'],
            'imported_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->get(route('billing.letters.show', ['account' => $account, 'template' => 'no_adeudo']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'attachment; filename="carta-no-adeudo-128.pdf"');

        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_bundled_account_letter_templates_have_distinct_debt_and_no_debt_text(): void
    {
        $profile = CondominiumProfile::query()->create([
            'id' => 1,
            'commercial_name' => 'Boleo II',
        ]);
        $account = ImportedResidentAccount::query()->create([
            'condominium_profile_id' => $profile->id,
            'unit_number' => '128',
            'tower' => 'A',
            'owner_name' => 'Jessica Fabiola Martinez',
            'total_debt' => 1200,
            'status' => 'adeudo',
            'raw_payload' => ['DEPT' => '128', 'NOMBRE' => 'Jessica Fabiola Martinez', 'TOTAL ADEUDO' => '1200'],
            'imported_at' => now(),
        ]);

        $debtText = DocxTemplateText::render(
            base_path('resources/docx/billing/carta-adeudo.docx'),
            $profile,
            $account,
            'adeudo'
        );
        $noDebtText = DocxTemplateText::render(
            base_path('resources/docx/billing/carta-no-adeudo.docx'),
            $profile,
            $account,
            'no_adeudo'
        );

        $this->assertStringContainsString('adeudo en las cuotas de mantenimiento', $debtText);
        $this->assertStringContainsString('Saldo registrado en Boleo: $1,200.00', $debtText);
        $this->assertStringContainsString('no cuenta con ningún adeudo', $noDebtText);
        $this->assertStringNotContainsString('Saldo registrado en Boleo', $noDebtText);
        $this->assertNotSame($debtText, $noDebtText);
    }

    public function test_billing_search_shows_imported_account_without_linked_unit(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $profile = CondominiumProfile::query()->create([
            'id' => 1,
            'commercial_name' => 'Boleo II',
        ]);
        $baseImport = BillingBaseImport::query()->create([
            'condominium_profile_id' => $profile->id,
            'original_name' => 'Base historica.xlsx',
            'stored_path' => '',
            'status' => 'manual',
            'imported_at' => now(),
        ]);
        $account = ImportedResidentAccount::query()->create([
            'condominium_profile_id' => $profile->id,
            'billing_base_import_id' => $baseImport->id,
            'unit_number' => '128',
            'tower' => 'A',
            'owner_name' => 'Jessica Fabiola Martinez',
            'total_debt' => 0,
            'status' => 'no_adeudo',
            'raw_payload' => [
                'DEPT' => '128',
                'NOMBRE' => 'Jessica Fabiola Martinez',
                'CORREO' => 'jessica@example.com',
                'TOTAL ADEUDO' => '0',
            ],
            'imported_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('billing', ['q' => 'Jessica']))
            ->assertOk()
            ->assertSee('Resultado encontrado')
            ->assertSee('Jessica Fabiola Martinez')
            ->assertSee('A - 128')
            ->assertSee('jessica@example.com')
            ->assertSee('Ver cuenta')
            ->assertSee('Cuenta de base histórica sin unidad vinculada')
            ->assertSee(e(route('billing', [
                'account' => $account->id,
                'q' => 'Jessica',
                'condominium' => 'Boleo II',
                'receipt_year' => now()->year,
            ])), false)
            ->assertSee(route('billing.letters.show', $account), false);
    }

    public function test_units_search_can_resolve_condominium_from_imported_resident_when_profile_query_does_not_match(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $currentProfile = CondominiumProfile::query()->create([
            'id' => 1,
            'commercial_name' => 'Perfil Actual',
        ]);
        $targetProfile = CondominiumProfile::query()->create([
            'id' => 2,
            'commercial_name' => 'Residencial Norte',
            'address' => 'Av. Norte 500',
        ]);

        ImportedResidentAccount::query()->create([
            'condominium_profile_id' => $targetProfile->id,
            'unit_number' => '501',
            'tower' => 'Torre N',
            'owner_name' => 'Rosaura Avila Rivero',
            'total_debt' => 2750,
            'status' => 'adeudo',
            'raw_payload' => [
                'Correo' => 'rosaura@boleo.mx',
            ],
            'imported_at' => now(),
        ]);

        $this->actingAs($admin)
            ->withSession(['settings_condominium_profile_id' => $currentProfile->id])
            ->get(route('units', ['condominium' => 'Boleo II', 'q' => 'Rosaura Avila Rivero']))
            ->assertOk()
            ->assertDontSee('Sin condominio')
            ->assertSee('Residencial Norte')
            ->assertSee('Rosaura Avila Rivero')
            ->assertSee('rosaura@boleo.mx')
            ->assertSee('Resultado encontrado');
    }

    public function test_billing_search_switches_condominium_before_showing_imported_account_detail(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $currentProfile = CondominiumProfile::query()->create([
            'id' => 1,
            'commercial_name' => 'Perfil Actual',
        ]);
        $targetProfile = CondominiumProfile::query()->create([
            'id' => 2,
            'commercial_name' => 'Boleo Torre Norte',
        ]);
        BillingBaseImport::query()->create([
            'condominium_profile_id' => $currentProfile->id,
            'original_name' => 'Base actual.xlsx',
            'stored_path' => '',
            'status' => 'manual',
            'imported_at' => now()->subDay(),
        ]);
        $baseImport = BillingBaseImport::query()->create([
            'condominium_profile_id' => $targetProfile->id,
            'original_name' => 'Base norte.xlsx',
            'stored_path' => '',
            'status' => 'manual',
            'imported_at' => now(),
        ]);
        $account = ImportedResidentAccount::query()->create([
            'condominium_profile_id' => $targetProfile->id,
            'billing_base_import_id' => $baseImport->id,
            'unit_number' => '904',
            'tower' => 'N',
            'owner_name' => 'Laura Norte',
            'total_debt' => 850,
            'status' => 'adeudo',
            'raw_payload' => [
                'DEPT' => '904',
                'NOMBRE' => 'Laura Norte',
                'CORREO' => 'laura.norte@example.com',
                'TOTAL ADEUDO' => '850',
            ],
            'imported_at' => now(),
        ]);

        $this->actingAs($admin)
            ->withSession(['settings_condominium_profile_id' => $currentProfile->id])
            ->get(route('billing', [
                'condominium' => 'Boleo Torre Norte',
                'q' => 'Laura',
            ]))
            ->assertOk()
            ->assertSee('Boleo Torre Norte')
            ->assertSee('Resultado encontrado')
            ->assertSee('Laura Norte')
            ->assertSee('laura.norte@example.com')
            ->assertSee('$850.00')
            ->assertSee('Ver cuenta')
            ->assertSee('Base histórica importada')
            ->assertSee(e(route('billing', [
                'account' => $account->id,
                'q' => 'Laura',
                'condominium' => 'Boleo Torre Norte',
                'receipt_year' => now()->year,
            ])), false);
    }

    public function test_debt_letter_docx_table_uses_excel_breakdown_and_current_system_debt(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'admin']);
        $templatePath = 'billing-letter-templates/carta-adeudo.docx';
        $this->createDocxTemplate(Storage::disk('public')->path($templatePath), [
            'Ciudad de México 13 de JULIO del 2026.',
            'CARTA DE ESTATUS DE CUOTAS.',
            'Departamento: 2',
            'Por medio de la presente, le informo por año su adeudo en las cuotas de mantenimiento del Condominio Real de Boleo II.',
            'En caso de tener alguna duda, comentario y/o aclaración, tiene hasta el 31 de julio del presente año para indicárselo al administrador.',
        ]);
        CondominiumProfile::query()->create([
            'id' => 1,
            'commercial_name' => 'Boleo Prueba',
            'debt_letter_template_path' => $templatePath,
        ]);
        $account = ImportedResidentAccount::query()->create([
            'condominium_profile_id' => 1,
            'unit_number' => '122',
            'tower' => 'A',
            'owner_name' => 'Jose Enrique Diaz Rosales',
            'total_debt' => 300,
            'status' => 'adeudo',
            'raw_payload' => [
                'DEPT' => '122',
                'NOMBRE' => 'Jose Enrique Diaz Rosales',
                '2026-07' => '500',
                'TOTAL ADEUDO' => '300',
            ],
            'imported_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->get(route('billing.letters.show', ['account' => $account, 'template' => 'adeudo']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'attachment; filename="carta-adeudo-122.pdf"');

        $this->assertStringStartsWith('%PDF', $response->getContent());

        $generatedPath = tempnam(sys_get_temp_dir(), 'boleo-debt-generated-');
        file_put_contents($generatedPath, AccountStatusLetterDocx::render(
            Storage::disk('public')->path($templatePath),
            CondominiumProfile::query()->findOrFail(1),
            $account,
            'adeudo'
        ));
        $zip = new ZipArchive;
        $zip->open($generatedPath);
        $documentXml = $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($generatedPath);

        $this->assertStringContainsString('<w:tbl>', $documentXml);
        $this->assertStringContainsString('Cuotas 2026', $documentXml);
        $this->assertStringContainsString('$500.00', $documentXml);
        $this->assertStringContainsString('Ajuste por pagos o movimientos registrados en sistema', $documentXml);
        $this->assertStringContainsString('-$200.00', $documentXml);
        $this->assertStringContainsString('TOTAL ADEUDO ACTUAL', $documentXml);
        $this->assertStringContainsString('$300.00', $documentXml);
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

    public function test_admin_can_download_monthly_resident_report_without_expenses(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        CondominiumProfile::query()->create([
            'id' => 1,
            'commercial_name' => 'Boleo Sin Gastos',
            'ordinary_fee_amount' => 2500,
        ]);
        $unit = Unit::query()->create([
            'unit_number' => '120',
            'tower' => 'A',
            'unit_type' => 'Departamento',
            'owner_name' => 'Residente Prueba',
            'owner_email' => 'residente@boleo.mx',
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

        $response = $this->actingAs($admin)->get(route('billing.resident.monthly.pdf', [
            'unit' => $unit->id,
            'month' => '2026-07',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_registered_payment_updates_imported_account_total_for_letters(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        CondominiumProfile::query()->create([
            'id' => 1,
            'commercial_name' => 'Boleo Pagos',
        ]);
        $unit = Unit::query()->create([
            'unit_number' => '122',
            'tower' => 'A',
            'unit_type' => 'Departamento',
            'owner_name' => 'Jose Enrique Diaz Rosales',
            'owner_email' => 'jose@boleo.mx',
            'ordinary_fee' => 0,
            'extraordinary_fee' => 0,
            'parking_rent' => 0,
            'storage_rent' => 0,
            'parking_spots' => 0,
            'storage_rooms' => 0,
            'clothesline_cages' => 0,
            'fee' => 0,
            'status' => 'Atrasado',
        ]);
        $account = ImportedResidentAccount::query()->create([
            'condominium_profile_id' => 1,
            'unit_id' => $unit->id,
            'unit_number' => '122',
            'tower' => 'A',
            'owner_name' => 'Jose Enrique Diaz Rosales',
            'total_debt' => 500,
            'status' => 'adeudo',
            'raw_payload' => [
                'DEPT' => '122',
                'NOMBRE' => 'Jose Enrique Diaz Rosales',
                '2026-07' => '500',
                'TOTAL ADEUDO' => '500',
            ],
            'imported_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('payments.store'), [
                'unit_id' => $unit->id,
                'concept' => 'Abono adeudo',
                'amount' => '200',
                'paid_at' => '2026-07-13',
            ])
            ->assertRedirect(route('billing', ['unit' => $unit->id]));

        $account->refresh();

        $this->assertSame('300.00', $account->total_debt);
        $this->assertSame('adeudo', $account->status);
        $this->assertSame('300', $account->raw_payload['TOTAL ADEUDO']);
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

    public function test_admin_can_create_condomino_receipt_and_see_it_from_billing(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        CondominiumProfile::query()->create([
            'id' => 1,
            'commercial_name' => 'Boleo Recibos',
            'ordinary_fee_amount' => 2400,
        ]);
        $unit = Unit::query()->create([
            'unit_number' => '701',
            'tower' => 'Torre H',
            'unit_type' => 'Departamento',
            'owner_name' => 'Mariana Lopez',
            'owner_email' => 'mariana@boleo.mx',
            'ordinary_fee' => 2400,
            'extraordinary_fee' => 0,
            'parking_rent' => 0,
            'storage_rent' => 0,
            'parking_spots' => 1,
            'storage_rooms' => 0,
            'clothesline_cages' => 0,
            'fee' => 2400,
            'status' => 'Atrasado',
        ]);
        $account = ImportedResidentAccount::query()->create([
            'condominium_profile_id' => 1,
            'unit_id' => $unit->id,
            'unit_number' => '701',
            'tower' => 'Torre H',
            'owner_name' => 'Mariana Lopez',
            'total_debt' => 1200,
            'status' => 'adeudo',
            'raw_payload' => [
                'DEPT' => '701',
                'NOMBRE' => 'Mariana Lopez',
                'TOTAL ADEUDO' => '1200',
            ],
            'imported_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('billing.receipts.store'), [
                'unit_id' => $unit->id,
                'period_year' => 2026,
                'period_month' => 7,
                'amount_due' => '2400.00',
                'amount_paid' => '1200.00',
                'notes' => 'Abono recibido en administracion',
            ])
            ->assertRedirect(route('billing', [
                'unit' => $unit->id,
                'receipt_year' => 2026,
            ]).'#recibos-condomino');

        $receipt = ResidentReceipt::query()->where('unit_id', $unit->id)->firstOrFail();

        $this->assertSame('parcial', $receipt->status);
        $this->assertSame('1200.00', $receipt->amount_paid);

        $this->actingAs($admin)
            ->get(route('billing', [
                'unit' => $unit->id,
                'receipt_year' => 2026,
            ]))
            ->assertOk()
            ->assertSee('Recibos por condomino')
            ->assertSee('Mariana Lopez')
            ->assertSee('Parcial')
            ->assertSee('Abono recibido en administracion')
            ->assertSee('Carta adeudo')
            ->assertSee(route('billing.letters.show', ['account' => $account, 'template' => 'no_adeudo']), false);
    }

    public function test_payment_can_be_applied_to_condomino_receipt_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        CondominiumProfile::query()->create([
            'id' => 1,
            'commercial_name' => 'Boleo Abonos',
        ]);
        $unit = Unit::query()->create([
            'unit_number' => '702',
            'tower' => 'Torre H',
            'unit_type' => 'Departamento',
            'owner_name' => 'Daniela Vera',
            'owner_email' => 'daniela@boleo.mx',
            'ordinary_fee' => 2000,
            'extraordinary_fee' => 0,
            'parking_rent' => 0,
            'storage_rent' => 0,
            'parking_spots' => 1,
            'storage_rooms' => 0,
            'clothesline_cages' => 0,
            'fee' => 2000,
            'status' => 'Atrasado',
        ]);
        $receipt = ResidentReceipt::query()->create([
            'condominium_profile_id' => 1,
            'unit_id' => $unit->id,
            'period_year' => 2026,
            'period_month' => 8,
            'amount_due' => 2000,
            'amount_paid' => 0,
            'notes' => 'Cuota mensual',
        ]);

        $this->actingAs($admin)
            ->post(route('payments.store'), [
                'unit_id' => $unit->id,
                'resident_receipt_id' => $receipt->id,
                'concept' => 'Abono agosto',
                'amount' => '500.00',
                'paid_at' => '2026-08-05',
            ])
            ->assertRedirect(route('billing', ['unit' => $unit->id]));

        $receipt->refresh();
        $this->assertSame('parcial', $receipt->status);
        $this->assertSame('500.00', $receipt->amount_paid);

        $this->actingAs($admin)
            ->post(route('payments.store'), [
                'unit_id' => $unit->id,
                'resident_receipt_id' => $receipt->id,
                'concept' => 'Liquidacion agosto',
                'amount' => '1500.00',
                'paid_at' => '2026-08-12',
            ])
            ->assertRedirect(route('billing', ['unit' => $unit->id]));

        $receipt->refresh();
        $this->assertSame('pagado', $receipt->status);
        $this->assertSame('2000.00', $receipt->amount_paid);
        $this->assertCount(2, Payment::query()->where('resident_receipt_id', $receipt->id)->get());
    }

    public function test_users_can_download_condomino_receipt_pdf(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        CondominiumProfile::query()->create([
            'id' => 1,
            'commercial_name' => 'Boleo PDF Recibos',
        ]);
        $unit = Unit::query()->create([
            'unit_number' => '703',
            'tower' => 'Torre H',
            'unit_type' => 'Departamento',
            'owner_name' => 'Pablo Medina',
            'owner_email' => 'pablo@boleo.mx',
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
        $receipt = ResidentReceipt::query()->create([
            'condominium_profile_id' => 1,
            'unit_id' => $unit->id,
            'period_year' => 2026,
            'period_month' => 9,
            'amount_due' => 2100,
            'amount_paid' => 0,
            'notes' => 'Pendiente de transferencia',
        ]);

        $this->actingAs($user)
            ->get(route('billing.receipts.pdf', $receipt))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_admin_can_update_condominium_profile_data(): void
    {
        Storage::fake('public');
        Http::fake([
            'https://nominatim.openstreetmap.org/search*' => Http::response([
                [
                    'lat' => '19.4346087',
                    'lon' => '-99.1739252',
                ],
            ], 200),
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('settings.update'), [
                'commercial_name' => 'Boleo Residencial Norte',
                'tax_id' => 'RFC-801214-770',
                'address' => 'Calle 6 de octubre numero 36, Col. San Bartolo Atepehuacan, Alcaldia Gustavo A. Madero, 07730, CDMX',
                'ordinary_fee_amount' => '3200.00',
                'fee_type' => 'standard',
                'departments_count' => '48',
                'parking_spaces_count' => '60',
                'storage_rooms_count' => '18',
                'clothesline_cages_count' => '24',
                'security_booth' => '1',
                'admin_type' => 'professional',
                'admin_name' => 'Ana Ortega',
                'assistant_admin_names' => 'Alondra Velázquez Hernández',
                'assistant_admin_phone' => '5525403862',
                'elevators_enabled' => '1',
                'elevators_count' => '2',
                'cisterns_enabled' => '1',
                'cisterns_count' => '1',
                'water_tanks_enabled' => '1',
                'water_tanks_count' => '3',
                'hydropneumatics_enabled' => '0',
                'hydropneumatics_count' => '0',
                'pool_enabled' => '1',
                'wading_pool_enabled' => '0',
                'event_hall_enabled' => '1',
                'roof_garden_enabled' => '1',
                'yoga_room_enabled' => '0',
                'game_room_enabled' => '1',
                'gym_enabled' => '1',
                'grill_enabled' => '1',
                'moving_hours_day' => 'Lunes a Viernes',
                'moving_hours_start' => '02:00',
                'moving_hours_end' => 'NO HAY',
                'work_hours_day' => 'Lunes a Viernes',
                'work_hours_start' => '08:00',
                'work_hours_end' => '17:00',
                'meeting_hours_day' => 'Sábados',
                'meeting_hours_start' => '10:00',
                'meeting_hours_end' => '13:00',
                'cleaning_staff_name' => 'Limpieza Norte',
                'cleaning_staff_phone' => '5511002200',
                'cleaning_staff_contact' => 'Turno matutino',
                'security_staff_name' => 'Seguridad Uno',
                'security_staff_phone' => '5522334455',
                'security_staff_contact' => 'Turno nocturno',
                'bank' => 'Banco Boleo',
                'account_holder' => 'Condominio Norte',
                'bank_account_type' => 'Cheques',
                'account_number' => '1234567890',
                'clabe' => '012345678901234567',
                'admin_email' => 'ana@boleo.mx',
                'admin_phone' => '5512340000',
            ])
            ->assertRedirect(route('settings'));

        $this->assertDatabaseHas('condominium_profiles', [
            'id' => 1,
            'commercial_name' => 'Boleo Residencial Norte',
            'departments_count' => 48,
            'security_booth' => true,
            'admin_type' => 'professional',
            'assistant_admin_phone' => '5525403862',
            'latitude' => '19.4346087',
            'longitude' => '-99.1739252',
        ]);

        $profile = CondominiumProfile::query()->findOrFail(1);
        $this->assertSame('Días: Lunes a Viernes | Inicio: 02:00 | Final: NO HAY', $profile->moving_hours);
        $this->assertSame('Días: Lunes a Viernes | Inicio: 08:00 | Final: 17:00', $profile->work_hours);
        $this->assertSame('Días: Sábados | Inicio: 10:00 | Final: 13:00', $profile->meeting_hours);
    }

    public function test_admin_can_save_condominium_profile_with_optional_fields_blank(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('settings.update'), [
                'commercial_name' => 'Condominio sin opcionales',
                'tax_id' => '',
                'address' => '',
                'ordinary_fee_amount' => '0',
                'fee_type' => 'standard',
                'departments_count' => '0',
                'parking_spaces_count' => '0',
                'storage_rooms_count' => '0',
                'clothesline_cages_count' => '0',
                'security_booth' => '0',
                'admin_type' => '',
                'admin_name' => '',
                'assistant_admin_names' => '',
                'assistant_admin_phone' => '',
                'admin_email' => '',
                'admin_phone' => '',
                'elevators_enabled' => '0',
                'elevators_count' => '0',
                'cisterns_enabled' => '0',
                'cisterns_count' => '0',
                'water_tanks_enabled' => '0',
                'water_tanks_count' => '0',
                'hydropneumatics_enabled' => '0',
                'hydropneumatics_count' => '0',
                'pool_enabled' => '0',
                'wading_pool_enabled' => '0',
                'event_hall_enabled' => '0',
                'roof_garden_enabled' => '0',
                'yoga_room_enabled' => '0',
                'game_room_enabled' => '0',
                'gym_enabled' => '0',
                'grill_enabled' => '0',
                'moving_hours_day' => '',
                'moving_hours_start' => '',
                'moving_hours_end' => '',
                'work_hours_day' => '',
                'work_hours_start' => '',
                'work_hours_end' => '',
                'meeting_hours_day' => '',
                'meeting_hours_start' => '',
                'meeting_hours_end' => '',
                'cleaning_staff_name' => '',
                'cleaning_staff_phone' => '',
                'cleaning_staff_contact' => '',
                'security_staff_name' => '',
                'security_staff_phone' => '',
                'security_staff_contact' => '',
                'bank' => '',
                'account_holder' => '',
                'bank_account_type' => '',
                'account_number' => '',
                'clabe' => '',
            ])
            ->assertRedirect(route('settings'));

        $profile = CondominiumProfile::query()
            ->where('commercial_name', 'Condominio sin opcionales')
            ->firstOrFail();

        $this->assertSame('', $profile->tax_id);
        $this->assertSame('', $profile->moving_hours);
        $this->assertSame('', $profile->work_hours);
        $this->assertSame('', $profile->meeting_hours);
        $this->assertSame('', $profile->cleaning_staff_name);
        $this->assertSame('', $profile->bank);
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
                'pool_enabled' => '1',
                'wading_pool_enabled' => '1',
                'event_hall_enabled' => '1',
                'roof_garden_enabled' => '0',
                'yoga_room_enabled' => '1',
                'game_room_enabled' => '1',
                'gym_enabled' => '1',
                'grill_enabled' => '1',
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
            'pool_enabled' => true,
            'wading_pool_enabled' => true,
            'event_hall_enabled' => true,
            'roof_garden_enabled' => false,
            'yoga_room_enabled' => true,
            'game_room_enabled' => true,
            'gym_enabled' => true,
            'grill_enabled' => true,
        ]);
    }

    public function test_selected_condominium_shows_saved_summary_sections(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        Storage::disk('public')->put('regulations/lago.pdf', 'PDF reglamento');
        Storage::disk('public')->put('parking-maps/lago.pdf', 'PDF mapa');
        Storage::disk('public')->put('property-regime/lago.pdf', 'PDF regimen');

        $profile = CondominiumProfile::query()->create([
            'commercial_name' => 'Condominio Lago Azul',
            'tax_id' => 'CLA-123',
            'address' => 'Calle Lago 100',
            'ordinary_fee_amount' => 1500,
            'fee_type' => 'standard',
            'departments_count' => 24,
            'parking_spaces_count' => 12,
            'storage_rooms_count' => 4,
            'clothesline_cages_count' => 2,
            'security_booth' => true,
            'elevators_enabled' => true,
            'elevators_count' => 2,
            'cisterns_enabled' => true,
            'cisterns_count' => 1,
            'water_tanks_enabled' => false,
            'water_tanks_count' => 0,
            'hydropneumatics_enabled' => false,
            'hydropneumatics_count' => 0,
            'moving_hours' => 'Días: Lunes a Viernes | Inicio: 02:00 | Final: NO HAY',
            'work_hours' => 'Días: Sábados | Inicio: 08:00 | Final: 17:00',
            'meeting_hours' => 'Días: Domingos y días festivos | Inicio: NO HAY | Final: NO HAY',
            'regulations_path' => 'regulations/lago.pdf',
            'parking_map_path' => 'parking-maps/lago.pdf',
            'property_regime_path' => 'property-regime/lago.pdf',
            'cleaning_staff_name' => 'Limpieza Lago',
            'cleaning_staff_phone' => '5511223344',
            'cleaning_staff_contact' => 'limpieza@lago.mx',
            'security_staff_name' => 'Vigilancia Lago',
            'security_staff_phone' => '5599887766',
            'security_staff_contact' => 'vigilancia@lago.mx',
            'bank' => 'Banco Lago',
            'account_holder' => 'Condominio Lago Azul',
            'bank_account_type' => 'Cheques',
            'account_number' => '1234567890',
            'clabe' => '012345678901234567',
        ]);

        AssemblyMinute::query()->create([
            'condominium_profile_id' => $profile->id,
            'title' => 'Asamblea Lago Azul',
            'assembly_date' => '2026-05-10',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('settings', ['condominium_profile_id' => $profile->id]))
            ->assertOk()
            ->assertDontSee('Resumen del condominio seleccionado')
            ->assertSee('value="Condominio Lago Azul"', false)
            ->assertSee('value="CLA-123"', false)
            ->assertSee('value="Calle Lago 100"', false)
            ->assertSee('value="Limpieza Lago"', false)
            ->assertSee('value="Vigilancia Lago"', false)
            ->assertSee('value="5599887766"', false)
            ->assertSee('value="vigilancia@lago.mx"', false)
            ->assertDontSee('Vigilancia 2')
            ->assertSee('value="Banco Lago"', false)
            ->assertSee('value="1234567890"', false)
            ->assertSee('name="moving_hours_start"', false)
            ->assertSee('name="moving_hours_end"', false)
            ->assertSee('02:00')
            ->assertSee('NO HAY')
            ->assertSee('Infraestructura Técnica')
            ->assertSee('Horario para mudanza')
            ->assertSee('Reglamento actual')
            ->assertSee('Compartir ubicación')
            ->assertSee('Horario para mudanza')
            ->assertSee('Horario de trabajo')
            ->assertSee('Horario para reunión')
            ->assertSee('Mapa de estacionamiento')
            ->assertSee('Régimen de propiedad y condominio')
            ->assertSee('Personal operativo')
            ->assertSee('Cuenta bancaria')
            ->assertSee('Asamblea Lago Azul');

        $html = $response->getContent();

        $this->assertStringContainsString('name="elevators_count" value="2"', $html);
        $this->assertStringContainsString('name="cisterns_count" value="1"', $html);
        $this->assertStringContainsString('name="water_tanks_count" value="0"', $html);
        $this->assertStringContainsString('name="hydropneumatics_count" value="0"', $html);
        $this->assertMatchesRegularExpression('/<select name="elevators_enabled"[^>]*>.*?<option value="1" selected>/s', $html);
        $this->assertMatchesRegularExpression('/<select name="cisterns_enabled"[^>]*>.*?<option value="1" selected>/s', $html);
        $this->assertMatchesRegularExpression('/<select name="water_tanks_enabled"[^>]*>.*?<option value="0" selected>/s', $html);
        $this->assertMatchesRegularExpression('/<select name="hydropneumatics_enabled"[^>]*>.*?<option value="0" selected>/s', $html);
        $this->assertMatchesRegularExpression('/<select name="moving_hours_start"[^>]*>.*?<option value="02:00" selected>/s', $html);
        $this->assertMatchesRegularExpression('/<select name="moving_hours_end"[^>]*>.*?<option value="NO HAY" selected>/s', $html);
        $this->assertMatchesRegularExpression('/<select name="work_hours_start"[^>]*>.*?<option value="08:00" selected>/s', $html);
        $this->assertMatchesRegularExpression('/<select name="work_hours_end"[^>]*>.*?<option value="17:00" selected>/s', $html);
        $this->assertMatchesRegularExpression('/<select name="meeting_hours_start"[^>]*>.*?<option value="NO HAY" selected>/s', $html);
        $this->assertMatchesRegularExpression('/<select name="meeting_hours_end"[^>]*>.*?<option value="NO HAY" selected>/s', $html);

        $this->get(URL::signedRoute('public.settings.documents.show', [
            'profile' => $profile,
            'type' => 'regulations',
        ], null, false))->assertOk();
    }

    public function test_admin_can_update_operations_settings_with_regulations_pdf(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('settings.operations.update'), [
                'moving_hours_day' => 'Lunes a Viernes',
                'moving_hours_start' => '07:00',
                'moving_hours_end' => '12:00',
                'work_hours_day' => 'Lunes a Viernes',
                'work_hours_start' => '08:00',
                'work_hours_end' => '17:00',
                'meeting_hours_day' => 'Domingos y días festivos',
                'meeting_hours_start' => '11:00',
                'meeting_hours_end' => '14:00',
                'regulations_file' => UploadedFile::fake()->create('reglamento.pdf', 120, 'application/pdf'),
                'parking_map_file' => UploadedFile::fake()->create('estacionamiento.pdf', 200, 'application/pdf'),
                'property_regime_file' => UploadedFile::fake()->create('regimen.pdf', 5000, 'application/pdf'),
                'cleaning_staff_name' => 'Equipo de limpieza Norte',
                'cleaning_staff_phone' => '5511223344',
                'cleaning_staff_contact' => 'limpieza@boleo.mx',
                'cleaning_instructions_file' => UploadedFile::fake()->create('consignas-limpieza.pdf', 150, 'application/pdf'),
                'security_staff_name' => 'Seguridad Diamante',
                'security_staff_phone' => '5511998877',
                'security_staff_contact' => 'supervisor de turno',
                'security_instructions_file' => UploadedFile::fake()->create('consignas-vigilancia.pdf', 150, 'application/pdf'),
            ])
            ->assertRedirect(route('settings'));

        $profile = CondominiumProfile::query()->findOrFail(1);

        $this->assertSame('Días: Lunes a Viernes | Inicio: 07:00 | Final: 12:00', $profile->moving_hours);
        $this->assertSame('Días: Lunes a Viernes | Inicio: 08:00 | Final: 17:00', $profile->work_hours);
        $this->assertSame('Días: Domingos y días festivos | Inicio: 11:00 | Final: 14:00', $profile->meeting_hours);
        $this->assertSame('Equipo de limpieza Norte', $profile->cleaning_staff_name);
        $this->assertSame('Seguridad Diamante', $profile->security_staff_name);
        $this->assertNotSame('', $profile->regulations_path);
        $this->assertNotSame('', $profile->parking_map_path);
        $this->assertNotSame('', $profile->property_regime_path);
        $this->assertNotSame('', $profile->cleaning_instructions_path);
        $this->assertNotSame('', $profile->security_instructions_path);
        Storage::disk('public')->assertExists($profile->regulations_path);
        Storage::disk('public')->assertExists($profile->parking_map_path);
        Storage::disk('public')->assertExists($profile->property_regime_path);
        Storage::disk('public')->assertExists($profile->cleaning_instructions_path);
        Storage::disk('public')->assertExists($profile->security_instructions_path);
    }

    public function test_admin_can_update_banking_settings_and_download_pdf_format(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('settings.banking.update'), [
                'bank' => 'BBVA',
                'account_holder' => 'Boleo Condominio AC',
                'bank_account_type' => 'Empresarial',
                'account_number' => '123456789012',
                'clabe' => '012345678901234567',
            ])
            ->assertRedirect(route('settings'));

        $this->assertDatabaseHas('condominium_profiles', [
            'id' => 1,
            'bank' => 'BBVA',
            'account_holder' => 'Boleo Condominio AC',
            'bank_account_type' => 'Empresarial',
        ]);

        $response = $this->actingAs($admin)->get(route('settings.banking.word'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $response->assertHeader('content-disposition', 'attachment; filename="datos-bancarios-condominio.pdf"');
    }

    public function test_admin_can_store_and_delete_assembly_minutes(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('settings.minutes.store'), [
                'title' => 'Asamblea ordinaria abril',
                'assembly_date' => '2026-04-29',
                'document_file' => UploadedFile::fake()->create('minuta-abril.pdf', 180, 'application/pdf'),
                'convocation_file' => UploadedFile::fake()->create('convocatoria-abril.pdf', 180, 'application/pdf'),
            ])
            ->assertRedirect(route('settings'));

        $minute = AssemblyMinute::query()->firstOrFail();

        $this->assertSame('Asamblea ordinaria abril', $minute->title);
        Storage::disk('public')->assertExists($minute->document_path);
        Storage::disk('public')->assertExists($minute->convocation_path);

        $this->actingAs($admin)
            ->get(route('settings.minutes.document', $minute))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('settings.minutes.convocation', $minute))
            ->assertOk();

        $this->actingAs($admin)
            ->delete(route('settings.minutes.destroy', $minute))
            ->assertRedirect(route('settings'));

        $this->assertDatabaseMissing('assembly_minutes', [
            'id' => $minute->id,
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

    private function createDocxTemplate(string $path, array $paragraphs): void
    {
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
        $body = collect($paragraphs)
            ->map(fn (string $paragraph): string => '<w:p><w:r><w:t xml:space="preserve">'.htmlspecialchars($paragraph, ENT_XML1).'</w:t></w:r></w:p>')
            ->implode('');
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'.$body.'<w:sectPr/></w:body></w:document>');
        $zip->close();
    }
}
