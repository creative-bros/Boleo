<?php

namespace App\Support;

use App\Models\CondominiumProfile;
use App\Models\ImportedResidentAccount;

class BillingBaseSchema
{
    public static function headersForProfile(CondominiumProfile $profile): array
    {
        $payload = ImportedResidentAccount::query()
            ->where('condominium_profile_id', $profile->id)
            ->whereNotNull('raw_payload')
            ->latest('imported_at')
            ->value('raw_payload');

        if (is_array($payload) && $payload !== []) {
            return collect(array_keys($payload))
                ->reject(fn (string $header): bool => str_starts_with($header, 'COLUMNA_'))
                ->values()
                ->all();
        }

        return self::defaultHeaders();
    }

    public static function defaultHeaders(): array
    {
        $headers = [
            'DEPT',
            'LUZ AREA COMUN 980100903258',
            'TAG',
            'Torre',
            'Sub Torre',
            'Nombre',
            'ADEUDO AL 2017',
        ];

        foreach (range(2018, 2026) as $year) {
            $lastMonth = $year === 2026 ? 7 : 12;

            foreach (range(1, $lastMonth) as $month) {
                $headers[] = sprintf('%d-%02d', $year, $month);
            }

            if ($year === 2025) {
                $headers[] = 'CUOTA EXTRA';
            }
        }

        return array_merge($headers, [
            'TOTAL ADEUDO',
            'N O M B R E',
            '2020',
            '2021',
            '2022',
            '2023',
            '2024',
            '2025',
            '2026',
            'ESTATUS',
            'OBSERVACIONES DEL 18 DE MARZO AL 8 DE MAYO DE 2023',
        ]);
    }

    public static function keyFields(): array
    {
        return [
            'DEPT',
            'Torre',
            'Sub Torre',
            'Nombre',
            'TOTAL ADEUDO',
            'ESTATUS',
            'OBSERVACIONES DEL 18 DE MARZO AL 8 DE MAYO DE 2023',
        ];
    }

    public static function editableExtraHeaders(array $headers): array
    {
        return collect($headers)
            ->reject(fn (string $header): bool => in_array($header, self::keyFields(), true))
            ->values()
            ->all();
    }
}
