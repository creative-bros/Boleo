<?php

namespace Tests\Unit;

use App\Models\ImportedResidentAccount;
use App\Support\AccountStatusLetterDocx;
use App\Support\ResidentAccountStatement;
use PHPUnit\Framework\TestCase;

class ResidentAccountStatementTest extends TestCase
{
    public function test_statement_uses_historical_exigible_rules_without_generating_missing_months(): void
    {
        $account = new ImportedResidentAccount([
            'total_debt' => 13108,
            'raw_payload' => [
                'DEPT' => '101',
                'NOMBRE' => 'Residente Prueba',
                'ADEUDO AL 2017' => '11038',
                'ene-18' => '380',
                'feb-19' => '0',
                '2022-12' => '190',
                '2023-01' => '400',
                '2025-03' => '600',
                'CUOTA EXTRA' => '200',
                'JAN-26' => '0',
                'APR-26' => '0',
                '2026-07' => '500',
                'AUG-26' => '500',
                'TOTAL ADEUDO' => '13108',
            ],
        ]);

        $rows = ResidentAccountStatement::rows($account);

        $row = fn (?int $year, ?int $month) => collect($rows)->first(
            fn (array $row): bool => $row['period_year'] === $year && $row['period_month'] === $month
        );

        $this->assertSame(11038.0, $row(2017, null)['exigible_raw']);
        $this->assertSame(11038.0, $row(2017, null)['debt_raw']);

        $this->assertSame(380.0, $row(2018, 1)['exigible_raw']);
        $this->assertSame('PENDIENTE', $row(2018, 1)['status']);

        $this->assertSame(380.0, $row(2019, 2)['exigible_raw']);
        $this->assertSame(380.0, $row(2019, 2)['paid_raw']);
        $this->assertSame('PAGADO', $row(2019, 2)['status']);

        $this->assertSame(380.0, $row(2022, 12)['exigible_raw']);
        $this->assertSame(190.0, $row(2022, 12)['paid_raw']);
        $this->assertSame('PARCIAL', $row(2022, 12)['status']);

        $this->assertSame(400.0, $row(2023, 1)['exigible_raw']);
        $this->assertSame(400.0, $row(2025, 3)['exigible_raw']);

        $extraFeeRow = collect($rows)->firstWhere('name', 'Cuota Extra 2025');
        $this->assertNotNull($extraFeeRow);
        $this->assertSame(200.0, $extraFeeRow['exigible_raw']);
        $this->assertSame(200.0, $extraFeeRow['debt_raw']);

        $rows2026 = collect($rows)->where('period_year', 2026)->values();

        $this->assertCount(4, $rows2026);
        $this->assertTrue($rows2026->every(fn (array $row): bool => $row['exigible_raw'] === 500.0));
        $this->assertSame('PAGADO', $row(2026, 1)['status']);
        $this->assertSame('PAGADO', $row(2026, 4)['status']);
        $this->assertFalse($row(2026, 7)['generated']);
        $this->assertSame('PENDIENTE', $row(2026, 7)['status']);
        $this->assertFalse($row(2026, 8)['generated']);
        $this->assertSame('PENDIENTE', $row(2026, 8)['status']);
        $this->assertNull($row(2026, 12));
    }

    public function test_vertical_statement_row_uses_period_exigible_rule(): void
    {
        $account = new ImportedResidentAccount([
            'total_debt' => 190,
            'raw_payload' => [
                'Nombre' => '2022-12',
                'EXIGIBLE' => '0',
                'PAGADO' => '0',
                'ADEUDO' => '190',
            ],
        ]);

        $rows = ResidentAccountStatement::rows($account);

        $this->assertCount(1, $rows);
        $this->assertSame(2022, $rows[0]['period_year']);
        $this->assertSame(12, $rows[0]['period_month']);
        $this->assertSame(380.0, $rows[0]['exigible_raw']);
        $this->assertSame(190.0, $rows[0]['paid_raw']);
        $this->assertSame('PARCIAL', $rows[0]['status']);
    }

    public function test_debt_letter_rows_are_grouped_by_year_and_mark_paid_years(): void
    {
        $account = new ImportedResidentAccount([
            'total_debt' => 700,
            'year_statuses' => [
                '2024' => 'SIN ADEUDO',
            ],
            'raw_payload' => [
                'DEPT' => '101',
                'NOMBRE' => 'Residente Prueba',
                '2025-01' => '500',
                '2025-02' => '0',
                'CUOTA EXTRA 2025' => '200',
                '2026-01' => '0',
                '2026-02' => '0',
                'TOTAL ADEUDO' => '700',
            ],
        ]);

        $rows = AccountStatusLetterDocx::debtRows($account);

        $this->assertSame(['TOTAL 2024', 'TOTAL 2025', 'TOTAL 2026'], array_column($rows, 'concept'));
        $this->assertSame('Sin adeudo', $rows[0]['amount_label']);
        $this->assertSame(700.0, $rows[1]['amount']);
        $this->assertSame('$700.00', $rows[1]['amount_label']);
        $this->assertSame('Sin adeudo', $rows[2]['amount_label']);
    }

    public function test_debt_letter_rows_prefer_explicit_annual_amounts_over_monthly_sum(): void
    {
        $account = new ImportedResidentAccount([
            'total_debt' => 200,
            'year_statuses' => [
                '2025' => '200',
            ],
            'raw_payload' => [
                'DEPT' => '101',
                'NOMBRE' => 'Residente Prueba',
                '2025-01' => '500',
                '2025-02' => '300',
                '2025' => '200',
                'TOTAL ADEUDO' => '200',
            ],
        ]);

        $rows = AccountStatusLetterDocx::debtRows($account);
        $row2025 = collect($rows)->firstWhere('concept', 'TOTAL 2025');

        $this->assertNotNull($row2025);
        $this->assertSame(200.0, $row2025['amount']);
        $this->assertSame('$200.00', $row2025['amount_label']);
    }
}
