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
        return [
            'Condominio',
            'Torre',
            'Sub Torre',
            'DEPT',
            'Nombre Dueño',
            'Correo electronico',
            'Telefono 1',
            'Telefono 2',
            'Nombre inquilino',
            'Correo electronico inquilino',
            'Telefono 1 inquilino',
            'Telefono 2 inquilino',
            'Cajon de estacionamenito',
            'Roof Garden',
            'Tag Vehiculo',
            'TAG Peatonal',
            'Bodega',
            'Mascotas',
        ];
    }

    public static function keyFields(): array
    {
        return [
            'Condominio',
            'DEPT',
            'Torre',
            'Sub Torre',
            'Nombre Dueño',
            'Correo electronico',
            'Telefono 1',
            'Telefono 2',
            'Nombre inquilino',
            'Correo electronico inquilino',
            'Telefono 1 inquilino',
            'Telefono 2 inquilino',
            'Cajon de estacionamenito',
            'Roof Garden',
            'Tag Vehiculo',
            'TAG Peatonal',
            'Bodega',
            'Mascotas',
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
