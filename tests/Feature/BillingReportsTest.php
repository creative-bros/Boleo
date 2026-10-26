<?php

namespace Tests\Feature;

use App\Models\CondominiumProfile;
use App\Models\ImportedResidentAccount;
use App\Models\Payment;
use App\Models\ResidentReceipt;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BillingReportsTest extends TestCase
{
    use RefreshDatabase;

    private CondominiumProfile $profile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-25 19:00:00', 'America/Mexico_City'));
        $this->profile = CondominiumProfile::create(['commercial_name' => 'REAL DE BOLEO II', 'ordinary_fee_amount' => 1000]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function unit(string $number = '101', array $attributes = []): Unit
    {
        return Unit::create(array_merge([
            'condominium_profile_id' => $this->profile->id, 'unit_number' => $number, 'tower' => 'A',
            'unit_type' => 'Departamento', 'owner_name' => 'Vecino '.$number, 'fee' => 1000,
            'ordinary_fee' => 1000, 'status' => 'Atrasado',
        ], $attributes));
    }

    private function receipt(Unit $unit, int $year, int $month, float $due = 1000, float $paid = 0): ResidentReceipt
    {
        return ResidentReceipt::create([
            'condominium_profile_id' => $unit->condominium_profile_id, 'unit_id' => $unit->id,
            'period_year' => $year, 'period_month' => $month, 'amount_due' => $due, 'amount_paid' => $paid,
        ]);
    }

    private function csv(string $type, array $parameters = []): array
    {
        $response = $this->get(route('billing.export', array_merge([
            'condominium_profile_id' => $this->profile->id, 'report' => $type,
        ], $parameters)))->assertOk();
        $contents = substr($response->streamedContent(), 3);
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $contents);
        rewind($stream);
        $rows = [];
        while (($row = fgetcsv($stream, null, ',', '"', '')) !== false) {
            $rows[] = $row;
        }
        fclose($stream);

        return $rows;
    }

    public function test_current_balance_includes_history_through_previous_month_and_excludes_current_and_future(): void
    {
        $unit = $this->unit();
        $this->receipt($unit, 2026, 9, 1000, 250);
        $this->receipt($unit, 2026, 10);
        $this->receipt($unit, 2026, 11, 9000);
        $this->get(route('billing', ['condominium_profile_id' => $this->profile->id, 'unit' => $unit->id]))
            ->assertOk()->assertViewHas('account', function ($account) {
                $this->assertSame('$750.00', $account['balance']);

                return true;
            })
            ->assertViewHas('receiptSummary', fn ($summary) => $summary['pending_amount'] == 750);
        $rows = $this->csv('debtors');
        $this->assertCount(2, $rows);
        $this->assertSame('750', $rows[1][4]);
    }

    public function test_explicit_combo_does_not_switch_condominium_when_search_matches_another(): void
    {
        $other = CondominiumProfile::create(['commercial_name' => 'Otro conjunto']);
        $foreign = $this->unit('999', ['condominium_profile_id' => $other->id, 'owner_name' => 'Persona ajena']);
        $this->get(route('billing', ['condominium_profile_id' => $this->profile->id, 'q' => 'Persona ajena', 'unit' => $foreign->id]))
            ->assertOk()->assertViewHas('selectedCondominiumProfileId', $this->profile->id)
            ->assertViewHas('selectedUnitId', null)->assertDontSee('Persona ajena</strong>', false);
    }

    public function test_current_month_only_receipts_do_not_make_a_department_a_debtor(): void
    {
        $unit = $this->unit();
        $this->receipt($unit, 2026, 10);
        $this->receipt($unit, 2026, 11);
        $this->get(route('billing', ['condominium_profile_id' => $this->profile->id, 'unit' => $unit->id]))
            ->assertOk()->assertViewHas('account', fn ($account) => $account['balance'] === '$0.00' && $account['status'] === 'Al corriente');
        $this->assertCount(1, $this->csv('debtors'));
        $this->assertSame('Mes corriente o futuro (fuera del adeudo vencido)', $this->csv('base')[1][10]);
    }

    public function test_previous_month_cutoff_rolls_back_to_december_in_january(): void
    {
        $this->travelTo(Carbon::parse('2027-01-01 00:30:00', 'America/Mexico_City'));
        $unit = $this->unit();
        $this->receipt($unit, 2026, 12, 1000, 400);
        $this->receipt($unit, 2027, 1, 1000);
        $rows = $this->csv('debtors');
        $this->assertSame('600', $rows[1][4]);
        $this->assertSame('2026-12', $rows[1][6]);
        $this->get(route('billing', ['condominium_profile_id' => $this->profile->id, 'unit' => $unit->id]))
            ->assertOk()->assertViewHas('receiptSummary', fn ($summary) => $summary['pending_amount'] == 600);
    }

    public function test_base_includes_paid_partial_future_and_units_without_receipts_without_mixing_condominiums(): void
    {
        $unit = $this->unit();
        $this->receipt($unit, 2026, 8, 1000, 1000);
        $this->receipt($unit, 2026, 9, 1000, 250);
        $this->receipt($unit, 2027, 1);
        $this->unit('102');
        $other = CondominiumProfile::create(['commercial_name' => 'Otro']);
        $this->unit('999', ['condominium_profile_id' => $other->id]);
        $rows = $this->csv('base');
        $this->assertCount(5, $rows);
        $this->assertSame('pagado', strtolower($rows[1][9]));
        $this->assertSame('750', $rows[2][8]);
        $this->assertSame('Mes corriente o futuro (fuera del adeudo vencido)', $rows[3][10]);
        $this->assertSame('Sin recibos registrados', $rows[4][5]);
        $this->assertNotContains('999', array_column($rows, 2));
    }

    public function test_overdue_requires_more_than_two_distinct_past_months_and_includes_partials(): void
    {
        $two = $this->unit('102');
        $three = $this->unit('103');
        foreach ([$two, $three] as $unit) {
            $this->receipt($unit, 2026, 8);
            $this->receipt($unit, 2026, 9);
            $this->receipt($unit, 2026, 10);
        }
        $this->receipt($three, 2026, 7, 1000, 999);
        $rows = $this->csv('overdue');
        $this->assertCount(2, $rows);
        $this->assertSame('103', $rows[1][2]);
        $this->assertSame('2001', $rows[1][4]);
        $this->assertSame('3', $rows[1][5]);
        $this->assertSame('2026-09', $rows[1][6]);
    }

    public function test_boleo_threshold_is_2000_and_uses_correct_year_and_previous_month(): void
    {
        $eligible = $this->unit('101', ['vehicle_tag' => 'TAG-101']);
        $below = $this->unit('102', ['vehicle_tag' => 'TAG-102']);
        foreach ([$eligible, $below] as $unit) {
            $this->receipt($unit, 2022, 12, 9000);
            $this->receipt($unit, 2023, 1, 1000);
            $this->receipt($unit, 2024, 1, 1000);
            $this->receipt($unit, 2026, 10, 9000);
        }
        $this->receipt($eligible, 2026, 9, 1000);
        $this->receipt($below, 2026, 9, 1000, 0.01);
        $maintenance = $this->csv('boleo-maintenance');
        $this->assertCount(3, $maintenance);
        $this->assertSame('3000', $maintenance[1][4]);
        $tags = $this->csv('boleo-tags');
        $this->assertCount(2, $tags);
        $this->assertSame('101', $tags[1][2]);
        $this->assertSame('2000', $tags[1][4]);
    }

    public function test_tag_report_excludes_units_without_tag_even_with_sufficient_debt(): void
    {
        $this->receipt($this->unit(), 2024, 1, 2000);
        $this->assertCount(1, $this->csv('boleo-tags'));
    }

    public function test_imported_months_are_reconciled_with_payments_and_not_duplicated(): void
    {
        $unit = $this->unit();
        $account = ImportedResidentAccount::create([
            'condominium_profile_id' => $this->profile->id, 'unit_id' => $unit->id,
            'unit_number' => $unit->unit_number, 'owner_name' => $unit->owner_name, 'tower' => 'A',
            'total_debt' => 2000, 'status' => 'adeudo', 'raw_payload' => ['2026-09' => '1000', '2026-10' => '1000'],
        ]);
        $this->receipt($unit, 2026, 9, 1000, 500);
        $rows = $this->csv('base');
        $this->assertCount(3, $rows);
        $this->assertSame('500', $rows[1][8]);
        $this->assertSame('500', $this->csv('debtors')[1][4]);
        $this->get(route('billing', ['condominium_profile_id' => $this->profile->id, 'unit' => $unit->id]))
            ->assertOk()->assertViewHas('account', fn ($value) => $value['balance'] === '$500.00');
        $this->get(route('billing.receipts-summary', ['account' => $account->id, 'type' => 'ordinarias']))
            ->assertOk()->assertViewHas('total', 500)->assertSee('Saldo hasta el mes anterior');
    }

    public function test_unlinked_imported_accounts_are_included(): void
    {
        ImportedResidentAccount::create([
            'condominium_profile_id' => $this->profile->id, 'unit_number' => '777', 'tower' => 'B',
            'owner_name' => 'Sin vincular', 'total_debt' => 1000, 'status' => 'adeudo',
            'raw_payload' => ['2026-09' => '1000'],
        ]);
        $this->assertSame('777', $this->csv('base')[1][2]);
        $this->assertSame('1000', $this->csv('debtors')[1][4]);
    }

    public function test_total_only_account_without_payload_is_not_silently_omitted(): void
    {
        ImportedResidentAccount::create([
            'condominium_profile_id' => $this->profile->id, 'unit_number' => '888', 'tower' => 'B',
            'owner_name' => 'Saldo histórico', 'total_debt' => 3000, 'status' => 'adeudo', 'raw_payload' => [],
        ]);
        $this->assertSame('3000', $this->csv('debtors')[1][4]);
        $this->assertSame('Saldo histórico sin desglose mensual', $this->csv('base')[1][5]);
        $this->assertCount(1, $this->csv('boleo-maintenance'));
    }

    public function test_special_reports_require_monthly_periods_to_avoid_assigning_cumulative_balances_to_wrong_dates(): void
    {
        ImportedResidentAccount::create([
            'condominium_profile_id' => $this->profile->id, 'unit_number' => '777', 'tower' => 'B',
            'owner_name' => 'Histórico anual', 'total_debt' => 22000, 'status' => 'adeudo',
            'raw_payload' => ['ADEUDO AL 2022' => 10000, 'ADEUDO AL 2023' => 2000, 'ADEUDO AL 2027' => 10000],
        ]);
        $rows = $this->csv('boleo-maintenance');
        $this->assertCount(1, $rows);
        $this->assertCount(4, $this->csv('base'));
    }

    public function test_imported_receipt_amount_edits_do_not_leave_phantom_debt_after_full_payment(): void
    {
        $unit = $this->unit();
        ImportedResidentAccount::create([
            'condominium_profile_id' => $this->profile->id, 'unit_id' => $unit->id,
            'unit_number' => '101', 'tower' => 'A', 'owner_name' => $unit->owner_name,
            'total_debt' => 1000, 'status' => 'adeudo', 'raw_payload' => ['2026-09' => 1000],
        ]);
        $this->receipt($unit, 2026, 9, 300, 300);
        $this->assertCount(1, $this->csv('debtors'));
        $row = $this->csv('base')[1];
        $this->assertSame('300', $row[6]);
        $this->assertSame('300', $row[7]);
        $this->assertSame('0', $row[8]);
    }

    public function test_existing_pdf_reports_work_with_imported_accounts(): void
    {
        ImportedResidentAccount::create([
            'condominium_profile_id' => $this->profile->id, 'unit_number' => '777', 'tower' => 'B',
            'owner_name' => 'Importado', 'total_debt' => 1000, 'status' => 'adeudo',
            'raw_payload' => ['2026-09' => 1000],
        ]);
        $this->withSession(['settings_condominium_profile_id' => $this->profile->id]);
        $this->get(route('billing.report.pdf'))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get(route('billing.debtors.pdf'))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_payment_reports_distinguish_capture_payment_and_application_dates(): void
    {
        $unit = $this->unit();
        $payment = Payment::create([
            'unit_id' => $unit->id, 'concept' => 'Abono', 'amount' => 500, 'status' => 'Completado',
            'payment_date' => '2026-09-30', 'paid_at' => '2026-10-01',
        ]);
        $this->assertCount(2, $this->csv('captured', ['date' => '2026-10-25']));
        $this->assertCount(1, $this->csv('captured', ['date' => '2026-10-01']));
        $this->assertCount(1, $this->csv('month-payments'));
        $payment->update(['payment_date' => '2026-10-01', 'paid_at' => '2026-11-01']);
        $this->assertCount(2, $this->csv('month-payments'));
        $payment->update(['status' => 'Cancelado']);
        $this->assertCount(1, $this->csv('month-payments'));
    }

    public function test_csv_neutralizes_formula_injection_in_names_and_unit_numbers(): void
    {
        $this->receipt($this->unit('=1+1', ['owner_name' => "\t@SUM(1,2)"]), 2026, 9);
        $rows = $this->csv('base');
        $this->assertSame("'=1+1", $rows[1][2]);
        $this->assertSame("'\t@SUM(1,2)", $rows[1][3]);
    }

    public function test_current_month_report_lists_each_department_once_with_total_of_its_payments(): void
    {
        $unit = $this->unit();
        foreach ([200, 300] as $amount) {
            Payment::create(['unit_id' => $unit->id, 'concept' => 'Abono', 'amount' => $amount,
                'status' => 'Completado', 'payment_date' => '2026-10-10', 'paid_at' => '2026-10-11']);
        }
        $rows = $this->csv('month-payments');
        $this->assertCount(2, $rows);
        $this->assertSame('500', $rows[1][4]);
        $this->assertSame('2', $rows[1][5]);
    }

    public function test_tag_report_recognizes_imported_tag_when_linked_unit_tag_is_empty(): void
    {
        $unit = $this->unit('101', ['vehicle_tag' => '']);
        ImportedResidentAccount::create([
            'condominium_profile_id' => $this->profile->id, 'unit_id' => $unit->id,
            'unit_number' => '101', 'tower' => 'A', 'owner_name' => $unit->owner_name,
            'total_debt' => 2000, 'status' => 'adeudo', 'raw_payload' => ['TAG Vehículo' => 'V101', '2024-01' => 2000],
        ]);
        $rows = $this->csv('boleo-tags');
        $this->assertCount(2, $rows);
        $this->assertSame('V101', $rows[1][8]);
    }

    public function test_exports_require_admin_and_validate_filters(): void
    {
        $this->getJson(route('billing.export', ['condominium_profile_id' => $this->profile->id, 'report' => 'captured', 'date' => '2026-99-99']))->assertUnprocessable();
        $this->getJson(route('billing.export', ['condominium_profile_id' => 999, 'report' => 'base']))->assertUnprocessable();
        $other = CondominiumProfile::create(['commercial_name' => 'Otro']);
        $this->get(route('billing.export', ['condominium_profile_id' => $other->id, 'report' => 'boleo-tags']))->assertUnprocessable();
        foreach (['user', 'resident'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get(route('billing.export', ['condominium_profile_id' => $this->profile->id, 'report' => 'base']))->assertForbidden();
            $this->post(route('billing.receipts.condominium.store'), [])->assertForbidden();
        }
    }

    public function test_2027_generation_covers_twelve_months_preserves_existing_payments_and_other_condominiums(): void
    {
        $unit = $this->unit();
        $this->receipt($unit, 2027, 1, 1000, 500);
        $other = CondominiumProfile::create(['commercial_name' => 'Otro']);
        $foreign = $this->unit('999', ['condominium_profile_id' => $other->id]);
        $payload = ['condominium_profile_id' => $this->profile->id, 'receipt_mode' => 'range',
            'start_period' => '2027-01', 'end_period' => '2027-12', 'condominium_amount_due' => 1200];
        $this->post(route('billing.receipts.condominium.store'), $payload)->assertRedirect();
        $this->post(route('billing.receipts.condominium.store'), $payload)->assertRedirect();
        $this->assertSame(12, ResidentReceipt::where('unit_id', $unit->id)->count());
        $this->assertSame(0, ResidentReceipt::where('unit_id', $foreign->id)->count());
        $this->assertDatabaseHas('resident_receipts', ['unit_id' => $unit->id, 'period_month' => 1, 'amount_paid' => 500, 'amount_due' => 1000]);
        $this->assertSame('1200.00', ResidentReceipt::where('unit_id', $unit->id)->where('period_month', 12)->value('amount_due'));
    }

    public function test_receipt_information_shows_each_partial_payment_and_escaped_notes(): void
    {
        $unit = $this->unit();
        $receipt = $this->receipt($unit, 2026, 9, 1000, 500);
        $receipt->update(['notes' => '<script>alert(1)</script>']);
        foreach ([200, 300] as $amount) {
            Payment::create(['unit_id' => $unit->id, 'resident_receipt_id' => $receipt->id,
                'concept' => 'Abono', 'amount' => $amount, 'status' => 'Completado',
                'payment_date' => '2026-09-20', 'paid_at' => '2026-09-21', 'payment_method' => 'transferencia']);
        }
        $this->get(route('billing', ['condominium_profile_id' => $this->profile->id, 'unit' => $unit->id]))
            ->assertOk()->assertSee('Ver información del recibo')->assertSee('$200.00')->assertSee('$300.00')
            ->assertSee('20/09/2026')->assertSee('21/09/2026')->assertDontSee('<script>alert(1)</script>', false);
    }
}
