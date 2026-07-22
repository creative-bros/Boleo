<?php

namespace Tests\Unit;

use App\Models\ImportedResidentAccount;
use App\Support\ResidentAccountStatement;
use PHPUnit\Framework\TestCase;

class ResidentAccountStatementTest extends TestCase
{
    public function test_statement_uses_historical_exigible_rules_and_completes_2026(): void
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
                '2026-07' => '500',
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

        $rows2026 = collect($rows)->where('period_year', 2026);

        $this->assertCount(12, $rows2026);
        $this->assertTrue($rows2026->every(fn (array $row): bool => $row['exigible_raw'] === 500.0));
        $this->assertFalse($row(2026, 7)['generated']);
        $this->assertSame('PENDIENTE', $row(2026, 7)['status']);
        $this->assertTrue($row(2026, 1)['generated']);
        $this->assertSame('SIN DATO', $row(2026, 1)['status']);
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
}
