<?php

namespace App\Support;

use App\Models\ImportedResidentAccount;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ResidentAccountStatement
{
    private const MONTHS = [
        'ENE' => 1,
        'FEB' => 2,
        'MAR' => 3,
        'ABR' => 4,
        'MAY' => 5,
        'JUN' => 6,
        'JUL' => 7,
        'AGO' => 8,
        'SEP' => 9,
        'OCT' => 10,
        'NOV' => 11,
        'DIC' => 12,
    ];

    public static function rows(?ImportedResidentAccount $account, float $monthlyFee = 0): array
    {
        if (! $account) {
            return [];
        }

        $payload = $account->raw_payload ?? [];

        if ($payload === []) {
            return [];
        }

        $verticalRow = self::verticalRow($payload);

        if ($verticalRow !== null) {
            return [$verticalRow];
        }

        $rows = [];
        $seen2026Months = [];

        foreach ($payload as $header => $value) {
            $statement = self::statementInfo((string) $header);

            if ($statement === null) {
                continue;
            }

            $hasValue = filled($value);
            $debt = self::moneyValue($value);

            if (! $hasValue && ! $statement['include_blank']) {
                continue;
            }

            if (! $statement['include_zero'] && abs($debt) < 0.01) {
                continue;
            }

            $year = $statement['year'];
            $month = $statement['month'];

            if ($year === 2026 && $month !== null) {
                $seen2026Months[$month] = true;
            }

            $rows[] = self::buildRow(
                $statement['label'],
                $debt,
                self::exigibleAmount($year, $month, $debt, $monthlyFee),
                $statement['sort_key'],
                $year,
                $month
            );
        }

        if ($seen2026Months !== []) {
            foreach (range(1, 12) as $month) {
                if (isset($seen2026Months[$month])) {
                    continue;
                }

                $rows[] = self::buildGeneratedMonthRow(2026, $month);
            }
        }

        if ($rows !== []) {
            usort($rows, fn (array $first, array $second): int => $first['sort_key'] <=> $second['sort_key']);

            return array_map(function (array $row): array {
                unset($row['sort_key']);

                return $row;
            }, $rows);
        }

        if ($rows === [] && abs((float) $account->total_debt) >= 0.01) {
            $debt = (float) $account->total_debt;

            return [[
                'name' => 'TOTAL ADEUDO',
                'status' => 'PENDIENTE',
                'status_key' => 'pendiente',
                'exigible_raw' => $debt,
                'paid_raw' => 0,
                'debt_raw' => $debt,
                'exigible' => self::money($debt),
                'paid' => '$0.00',
                'debt' => self::money($debt),
            ]];
        }

        if ($rows === [] && abs((float) $account->total_debt) < 0.01) {
            return [[
                'name' => 'TOTAL ADEUDO',
                'status' => 'PAGADO',
                'status_key' => 'pagado',
                'exigible_raw' => $monthlyFee,
                'paid_raw' => $monthlyFee,
                'debt_raw' => 0,
                'exigible' => self::money($monthlyFee),
                'paid' => self::money($monthlyFee),
                'debt' => '-',
            ]];
        }

        return $rows;
    }

    public static function summary(array $rows): array
    {
        $paidCount = collect($rows)->filter(fn (array $row): bool => ($row['status_key'] ?? '') === 'pagado')->count();
        $partialCount = collect($rows)->filter(fn (array $row): bool => ($row['status_key'] ?? '') === 'parcial')->count();
        $pendingCount = collect($rows)->filter(fn (array $row): bool => ($row['status_key'] ?? '') === 'pendiente')->count();

        return [
            'total' => count($rows),
            'paid_count' => $paidCount,
            'partial_count' => $partialCount,
            'pending_count' => $pendingCount,
            'pending_amount' => collect($rows)->sum(fn (array $row): float => (float) ($row['debt_raw'] ?? 0)),
        ];
    }

    private static function verticalRow(array $payload): ?array
    {
        $name = self::firstPayloadValue($payload, ['NOMBRE', 'CONCEPTO', 'PERIODO']);
        $exigible = self::firstPayloadValue($payload, ['EXIGIBLE']);
        $paid = self::firstPayloadValue($payload, ['PAGADO']);
        $debt = self::firstPayloadValue($payload, ['ADEUDO', 'SALDO']);

        if (blank($name) || (blank($exigible) && blank($paid))) {
            return null;
        }

        $exigibleAmount = self::moneyValue($exigible);
        $paidAmount = self::moneyValue($paid);
        $debtAmount = max(self::moneyValue($debt), max($exigibleAmount - $paidAmount, 0));
        $explicitStatus = self::firstPayloadValue($payload, ['ESTATUS']);
        $statement = self::statementInfo((string) $name);

        if ($statement !== null) {
            $exigibleAmount = self::exigibleAmount(
                $statement['year'],
                $statement['month'],
                abs($exigibleAmount) >= 0.01 ? $exigibleAmount : $debtAmount,
                $exigibleAmount
            );
            $paidAmount = max($exigibleAmount - $debtAmount, 0);
        }

        $status = $explicitStatus
            ? mb_strtoupper((string) $explicitStatus, 'UTF-8')
            : self::statusFor($debtAmount, $paidAmount);

        return [
            'name' => (string) $name,
            'status' => $status,
            'status_key' => Str::lower($status),
            'period_year' => $statement['year'] ?? null,
            'period_month' => $statement['month'] ?? null,
            'generated' => false,
            'exigible_raw' => $exigibleAmount,
            'paid_raw' => $paidAmount,
            'debt_raw' => $debtAmount,
            'exigible' => self::money($exigibleAmount),
            'paid' => $paidAmount > 0 ? self::money($paidAmount) : '$0.00',
            'debt' => $debtAmount > 0 ? self::money($debtAmount) : '-',
        ];
    }

    private static function firstPayloadValue(array $payload, array $needles): mixed
    {
        foreach ($payload as $header => $value) {
            $normalizedHeader = self::normalizeHeader((string) $header);

            foreach ($needles as $needle) {
                if (str_contains($normalizedHeader, self::normalizeHeader($needle)) && filled($value)) {
                    return $value;
                }
            }
        }

        return null;
    }

    private static function statementInfo(string $header): ?array
    {
        $normalized = self::normalizeHeader($header);

        if ($normalized === '') {
            return null;
        }

        if ($normalized === '2017') {
            return [
                'label' => 'Adeudo Al 2017',
                'year' => 2017,
                'month' => null,
                'sort_key' => 201700,
                'include_blank' => false,
                'include_zero' => false,
            ];
        }

        if (
            str_contains($normalized, 'TOTAL')
            || str_contains($normalized, 'DEPT')
            || str_contains($normalized, 'DEPTO')
            || str_contains($normalized, 'TAG')
            || str_contains($normalized, 'NOMBRE')
            || str_contains($normalized, 'TORRE')
            || str_contains($normalized, 'CORREO')
            || str_contains($normalized, 'TELEFONO')
            || str_contains($normalized, 'ESTACIONAMIENTO')
            || str_contains($normalized, 'BODEGA')
            || str_contains($normalized, 'MASCOTA')
            || str_contains($normalized, 'ESTATUS')
            || str_contains($normalized, 'OBSERV')
            || str_starts_with($normalized, 'COLUMNA_')
            || preg_match('/^20\d{2}$/', $normalized) === 1
        ) {
            return null;
        }

        if (preg_match('/^(20\d{2})[-\/. ](\d{1,2})$/', $normalized, $matches) === 1) {
            return self::monthStatement((int) $matches[1], (int) $matches[2]);
        }

        if (preg_match('/^(ENE|FEB|MAR|ABR|MAY|JUN|JUL|AGO|SEP|OCT|NOV|DIC)[-. \/]*(\d{2,4})$/', $normalized, $matches) === 1) {
            $year = (int) $matches[2];
            $year = $year < 100 ? 2000 + $year : $year;

            return self::monthStatement($year, self::MONTHS[$matches[1]]);
        }

        if (preg_match('/ADEUDO AL.*(20\d{2})/', $normalized, $matches) === 1) {
            $year = (int) $matches[1];

            return [
                'label' => mb_convert_case($normalized, MB_CASE_TITLE, 'UTF-8'),
                'year' => $year,
                'month' => null,
                'sort_key' => $year * 100,
                'include_blank' => false,
                'include_zero' => false,
            ];
        }

        if (str_contains($normalized, 'ADEUDO') || str_contains($normalized, 'SALDO') || str_contains($normalized, 'CUOTA')) {
            $year = preg_match('/(20\d{2})/', $normalized, $matches) === 1 ? (int) $matches[1] : null;

            return [
                'label' => mb_convert_case($normalized, MB_CASE_TITLE, 'UTF-8'),
                'year' => $year,
                'month' => null,
                'sort_key' => $year ? $year * 100 + 99 : 999999,
                'include_blank' => false,
                'include_zero' => false,
            ];
        }

        return null;
    }

    private static function monthStatement(int $year, int $month): ?array
    {
        if ($month < 1 || $month > 12) {
            return null;
        }

        return [
            'label' => Carbon::create($year, $month, 1)
                ->locale('es_MX')
                ->translatedFormat('M-y'),
            'year' => $year,
            'month' => $month,
            'sort_key' => $year * 100 + $month,
            'include_blank' => true,
            'include_zero' => true,
        ];
    }

    private static function buildRow(string $label, float $debt, float $exigible, int $sortKey, ?int $year = null, ?int $month = null): array
    {
        $paid = max($exigible - $debt, 0);
        $status = self::statusFor($debt, $paid);

        return [
            'name' => $label,
            'status' => $status,
            'status_key' => Str::lower($status),
            'period_year' => $year,
            'period_month' => $month,
            'generated' => false,
            'sort_key' => $sortKey,
            'exigible_raw' => $exigible,
            'paid_raw' => $paid,
            'debt_raw' => max($debt, 0),
            'exigible' => self::money($exigible),
            'paid' => $paid > 0 ? self::money($paid) : '$0.00',
            'debt' => $debt > 0 ? self::money($debt) : '-',
        ];
    }

    private static function statusFor(float $debt, float $paid): string
    {
        return $debt <= 0
            ? 'PAGADO'
            : ($paid > 0 ? 'PARCIAL' : 'PENDIENTE');
    }

    private static function buildGeneratedMonthRow(int $year, int $month): array
    {
        $exigible = self::exigibleAmount($year, $month, 0, 0);

        return [
            'name' => Carbon::create($year, $month, 1)
                ->locale('es_MX')
                ->translatedFormat('M-y'),
            'status' => 'SIN DATO',
            'status_key' => 'sin_dato',
            'period_year' => $year,
            'period_month' => $month,
            'generated' => true,
            'sort_key' => $year * 100 + $month,
            'exigible_raw' => $exigible,
            'paid_raw' => 0,
            'debt_raw' => 0,
            'exigible' => self::money($exigible),
            'paid' => '$0.00',
            'debt' => '-',
        ];
    }

    private static function exigibleAmount(?int $year, ?int $month, float $importedAmount, float $monthlyFee): float
    {
        if ($year === 2017) {
            return abs($importedAmount) >= 0.01 ? $importedAmount : $monthlyFee;
        }

        if ($year !== null && $year >= 2018 && $year <= 2022) {
            return 380;
        }

        if ($year !== null && $year >= 2023 && $year <= 2024) {
            return 400;
        }

        if ($year === 2025) {
            return 600;
        }

        if ($year === 2026) {
            return 500;
        }

        return abs($importedAmount) >= 0.01 ? $importedAmount : $monthlyFee;
    }

    private static function normalizeHeader(string $header): string
    {
        return preg_replace('/\s+/', ' ', Str::ascii(mb_strtoupper(trim($header), 'UTF-8'))) ?: '';
    }

    private static function moneyValue(mixed $value): float
    {
        $value = trim((string) $value);

        if ($value === '' || $value === '-') {
            return 0.0;
        }

        $normalized = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', $value)) ?: '0';

        return (float) $normalized;
    }

    private static function money(float $amount): string
    {
        return '$'.number_format(max($amount, 0), 2);
    }
}
