<?php

namespace App\Support;

use App\Models\BillingBaseImport;
use App\Models\CondominiumProfile;
use App\Models\ImportedResidentAccount;
use App\Models\Unit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use SimpleXMLElement;
use Symfony\Component\Process\Process;
use ZipArchive;

class BillingExcelImporter
{
    public function import(string $path, CondominiumProfile $profile, ?BillingBaseImport $baseImport = null): int
    {
        $rows = $this->readSheetRows($path);
        $headerRow = $this->detectHeaderRow($rows);
        $headers = $this->headers($rows[$headerRow] ?? []);

        $unitColumn = $this->findHeader($headers, $this->residentColumnAliases('unit_number'));
        $towerColumn = $this->findHeader($headers, $this->residentColumnAliases('tower'));
        $subTowerColumn = $this->findHeader($headers, $this->residentColumnAliases('sub_tower'));
        $nameColumn = $this->findHeader($headers, $this->residentColumnAliases('owner_name'), ['INQUILINO']);
        $totalDebtColumn = $this->findHeader($headers, ['TOTAL ADEUDO']);
        $statusColumn = $this->findHeader($headers, ['ESTATUS']);
        $observationsColumn = $this->findHeader($headers, ['OBSERVACIONES']);

        $unitColumn ??= $this->firstHeaderColumn($headers) ?? 1;
        $nameColumn ??= $this->findLikelyNameColumn($headers) ?? $unitColumn;
        $totalDebtColumn ??= $this->findLastNumericColumn($rows, $headers, $headerRow);

        $yearColumns = collect($headers)
            ->filter(fn (string $header): bool => preg_match('/^20\d{2}$/', $header) === 1)
            ->all();

        $imported = 0;

        DB::transaction(function () use ($rows, $headers, $headerRow, $profile, $baseImport, $unitColumn, $towerColumn, $subTowerColumn, $nameColumn, $totalDebtColumn, $statusColumn, $observationsColumn, $yearColumns, &$imported): void {
            foreach ($rows as $rowNumber => $row) {
                if ($rowNumber <= $headerRow) {
                    continue;
                }

                $unitNumber = trim((string) ($row[$unitColumn] ?? ''));
                $ownerName = trim((string) ($row[$nameColumn] ?? ''));

                if ($this->isEmptyRow($row)) {
                    continue;
                }

                $unitNumber = $unitNumber !== '' ? $unitNumber : 'FILA-'.$rowNumber;
                $ownerName = $ownerName !== '' ? $ownerName : 'Registro fila '.$rowNumber;
                $tower = trim((string) ($row[$towerColumn] ?? ''));
                $totalDebt = $this->moneyValue($row[$totalDebtColumn] ?? 0);
                $yearStatuses = [];
                $rawPayload = $this->rowPayload($headers, $row);
                $unit = $this->syncUnit($profile, $unitNumber, $tower, $ownerName, $totalDebt, $rawPayload);

                foreach ($yearColumns as $column => $year) {
                    $value = trim((string) ($row[$column] ?? ''));

                    if ($value !== '') {
                        $yearStatuses[$year] = $value;
                    }
                }

                $lookup = $baseImport
                    ? [
                        'billing_base_import_id' => $baseImport->id,
                        'unit_number' => $unitNumber,
                        'tower' => $tower,
                    ]
                    : [
                        'condominium_profile_id' => $profile->id,
                        'unit_number' => $unitNumber,
                        'tower' => $tower,
                    ];

                ImportedResidentAccount::query()->updateOrCreate($lookup, [
                    'condominium_profile_id' => $profile->id,
                    'billing_base_import_id' => $baseImport?->id,
                    'unit_id' => $unit?->id,
                    'sub_tower' => trim((string) ($row[$subTowerColumn] ?? '')) ?: null,
                    'source_row_number' => $rowNumber,
                    'owner_name' => $ownerName,
                    'total_debt' => $totalDebt,
                    'status' => $totalDebt > 0 ? 'adeudo' : 'no_adeudo',
                    'year_statuses' => $yearStatuses,
                    'raw_payload' => $rawPayload,
                    'observations' => trim(implode(' ', array_filter([
                        $row[$statusColumn] ?? null,
                        $row[$observationsColumn] ?? null,
                    ], fn ($value): bool => filled($value)))) ?: null,
                    'imported_at' => Carbon::now(),
                ]);

                $imported++;
            }
        });

        return $imported;
    }

    public function importResidentDirectory(string $path, CondominiumProfile $fallbackProfile): array
    {
        $rows = $this->readSheetRows($path);
        $headerRow = $this->detectHeaderRow($rows);
        $headers = $this->headers($rows[$headerRow] ?? []);

        $condominiumColumn = $this->findHeader($headers, $this->residentColumnAliases('condominium'));
        $towerColumn = $this->findHeader($headers, $this->residentColumnAliases('tower'));
        $subTowerColumn = $this->findHeader($headers, $this->residentColumnAliases('sub_tower'));
        $unitColumn = $this->findHeader($headers, $this->residentColumnAliases('unit_number'));
        $ownerNameColumn = $this->findHeader($headers, $this->residentColumnAliases('owner_name'), ['INQUILINO']);
        $ownerEmailColumn = $this->findHeader($headers, $this->residentColumnAliases('owner_email'), ['INQUILINO']);
        $ownerPhonePrimaryColumn = $this->findHeader($headers, $this->residentColumnAliases('owner_phone_primary'), ['INQUILINO']);
        $ownerPhoneSecondaryColumn = $this->findHeader($headers, $this->residentColumnAliases('owner_phone_secondary'), ['INQUILINO']);
        $tenantNameColumn = $this->findHeader($headers, $this->residentColumnAliases('tenant_name'));
        $tenantEmailColumn = $this->findHeader($headers, $this->residentColumnAliases('tenant_email'));
        $tenantPhonePrimaryColumn = $this->findHeader($headers, $this->residentColumnAliases('tenant_phone_primary'));
        $tenantPhoneSecondaryColumn = $this->findHeader($headers, $this->residentColumnAliases('tenant_phone_secondary'));
        $parkingAssignmentColumn = $this->findHeader($headers, $this->residentColumnAliases('parking_assignment'));
        $roofGardenColumn = $this->findHeader($headers, $this->residentColumnAliases('roof_garden'));
        $vehicleTagColumn = $this->findHeader($headers, $this->residentColumnAliases('vehicle_tag'));
        $pedestrianTagColumn = $this->findHeader($headers, $this->residentColumnAliases('pedestrian_tag'));
        $storageAssignmentColumn = $this->findHeader($headers, $this->residentColumnAliases('storage_assignment'));
        $petColumn = $this->findHeader($headers, $this->residentColumnAliases('pet'));

        if (! $unitColumn || ! $ownerNameColumn) {
            throw new RuntimeException('El archivo de residentes necesita columnas de departamento y nombre del dueño.');
        }

        $imported = 0;
        $skipped = 0;
        $firstProfileId = null;
        $currentCondominiumName = $fallbackProfile->commercial_name;
        $profileStats = [];

        DB::transaction(function () use (
            $rows,
            $headerRow,
            $fallbackProfile,
            $condominiumColumn,
            $towerColumn,
            $subTowerColumn,
            $unitColumn,
            $ownerNameColumn,
            $ownerEmailColumn,
            $ownerPhonePrimaryColumn,
            $ownerPhoneSecondaryColumn,
            $tenantNameColumn,
            $tenantEmailColumn,
            $tenantPhonePrimaryColumn,
            $tenantPhoneSecondaryColumn,
            $parkingAssignmentColumn,
            $roofGardenColumn,
            $vehicleTagColumn,
            $pedestrianTagColumn,
            $storageAssignmentColumn,
            $petColumn,
            &$currentCondominiumName,
            &$profileStats,
            &$imported,
            &$skipped,
            &$firstProfileId
        ): void {
            foreach ($rows as $rowNumber => $row) {
                if ($rowNumber <= $headerRow || $this->isEmptyRow($row)) {
                    continue;
                }

                $value = fn (?int $column): string => $column ? trim((string) ($row[$column] ?? '')) : '';
                $condominiumName = $value($condominiumColumn);

                if ($condominiumName !== '') {
                    $currentCondominiumName = $condominiumName;
                }

                $profile = $this->profileForResidentDirectory($currentCondominiumName, $fallbackProfile);
                $unitNumber = $value($unitColumn);
                $ownerName = $value($ownerNameColumn);

                if ($unitNumber === '' || $ownerName === '') {
                    $skipped++;

                    continue;
                }

                $parkingAssignment = $value($parkingAssignmentColumn);
                $storageAssignment = $value($storageAssignmentColumn);
                $roofGarden = $value($roofGardenColumn);
                $tower = $value($towerColumn);

                Unit::query()->updateOrCreate([
                    'condominium_profile_id' => $profile->id,
                    'unit_number' => $unitNumber,
                    'tower' => $tower,
                ], [
                    'sub_tower' => $value($subTowerColumn),
                    'unit_type' => 'Departamento',
                    'owner_name' => $ownerName,
                    'owner_email' => $value($ownerEmailColumn),
                    'owner_phone_primary' => $value($ownerPhonePrimaryColumn),
                    'owner_phone_secondary' => $value($ownerPhoneSecondaryColumn),
                    'tenant_name' => $value($tenantNameColumn),
                    'tenant_email' => $value($tenantEmailColumn),
                    'tenant_phone_primary' => $value($tenantPhonePrimaryColumn),
                    'tenant_phone_secondary' => $value($tenantPhoneSecondaryColumn),
                    'ordinary_fee' => 0,
                    'indiviso_percentage' => 0,
                    'extraordinary_fee' => 0,
                    'parking_rent' => 0,
                    'storage_rent' => 0,
                    'parking_spots' => $parkingAssignment !== '' ? 1 : 0,
                    'parking_assignment' => $parkingAssignment,
                    'roof_garden' => $roofGarden,
                    'vehicle_tag' => $value($vehicleTagColumn),
                    'pedestrian_tag' => $value($pedestrianTagColumn),
                    'storage_rooms' => $storageAssignment !== '' ? 1 : 0,
                    'storage_assignment' => $storageAssignment,
                    'pet' => $value($petColumn),
                    'clothesline_cages' => 0,
                    'fee' => 0,
                    'status' => 'Pagado',
                ]);

                $profileStats[$profile->id] ??= [
                    'departments' => 0,
                    'parking' => 0,
                    'storage' => 0,
                    'roof_garden' => false,
                ];
                $profileStats[$profile->id]['departments']++;
                $profileStats[$profile->id]['parking'] += $parkingAssignment !== '' ? 1 : 0;
                $profileStats[$profile->id]['storage'] += $storageAssignment !== '' ? 1 : 0;
                $profileStats[$profile->id]['roof_garden'] = $profileStats[$profile->id]['roof_garden'] || $roofGarden !== '';
                $firstProfileId ??= $profile->id;
                $imported++;
            }

            $this->refreshResidentDirectoryProfileStats($profileStats);
        });

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'profile_id' => $firstProfileId,
        ];
    }

    private function readSheetRows(string $path): array
    {
        $nodeRows = $this->readSheetRowsWithNode($path);

        if ($nodeRows !== []) {
            return $nodeRows;
        }

        $delimitedRows = $this->readDelimitedRows($path);

        if ($delimitedRows !== []) {
            return $delimitedRows;
        }

        if (class_exists(ZipArchive::class)) {
            return $this->readSheetRowsFromXlsx($path);
        }

        throw new RuntimeException('No fue posible leer el archivo como hoja de cálculo.');
    }

    private function readSheetRowsWithNode(string $path): array
    {
        $script = base_path('scripts/parse-billing-base.cjs');

        if (! is_file($script)) {
            return [];
        }

        try {
            $process = new Process(['node', $script, $path], base_path(), null, null, 90);
            $process->run();
        } catch (\Throwable $exception) {
            return [];
        }

        if (! $process->isSuccessful()) {
            return [];
        }

        $decoded = json_decode($process->getOutput(), true);

        if (! is_array($decoded) || ! isset($decoded['rows']) || ! is_array($decoded['rows'])) {
            return [];
        }

        $rows = [];

        foreach ($decoded['rows'] as $row) {
            $rowIndex = (int) ($row['index'] ?? 0);
            $cells = $row['cells'] ?? [];

            if ($rowIndex < 1 || ! is_array($cells)) {
                continue;
            }

            $rows[$rowIndex] = [];

            foreach ($cells as $column => $value) {
                $rows[$rowIndex][(int) $column] = trim((string) $value);
            }
        }

        return $rows;
    }

    private function readDelimitedRows(string $path): array
    {
        $contents = @file_get_contents($path);

        if ($contents === false || str_contains($contents, "\0")) {
            return [];
        }

        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents);
        $lines = preg_split('/\r\n|\r|\n/', trim((string) $contents));

        if (! $lines || count($lines) < 2) {
            return [];
        }

        $sample = implode("\n", array_slice($lines, 0, 5));
        $delimiter = collect([
            ',' => substr_count($sample, ','),
            ';' => substr_count($sample, ';'),
            "\t" => substr_count($sample, "\t"),
        ])->sortDesc()->keys()->first();
        $rows = [];

        foreach ($lines as $index => $line) {
            if (trim($line) === '') {
                continue;
            }

            $values = str_getcsv($line, (string) $delimiter);
            $rows[$index + 1] = [];

            foreach ($values as $column => $value) {
                $rows[$index + 1][$column + 1] = trim((string) $value);
            }
        }

        return $rows;
    }

    private function readSheetRowsFromXlsx(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('No fue posible abrir el archivo Excel.');
        }

        $sharedStrings = $this->sharedStrings($zip);
        $dateColumns = $this->dateColumns($zip);
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($xml === false) {
            throw new RuntimeException('No se encontró la primera hoja del archivo Excel.');
        }

        $sheet = new SimpleXMLElement($xml);
        $rows = [];

        foreach ($sheet->sheetData->row as $row) {
            $rowIndex = (int) $row['r'];
            $rows[$rowIndex] = [];

            foreach ($row->c as $cell) {
                $reference = (string) $cell['r'];
                $column = $this->columnIndex($reference);
                $type = (string) $cell['t'];
                $style = (int) ($cell['s'] ?? -1);
                $value = isset($cell->v) ? (string) $cell->v : '';

                if ($type === 's') {
                    $value = $sharedStrings[(int) $value] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = (string) ($cell->is->t ?? '');
                } elseif ($rowIndex === 2 && isset($dateColumns[$style]) && is_numeric($value)) {
                    $value = $this->excelDateHeader((float) $value);
                }

                $rows[$rowIndex][$column] = $value;
            }
        }

        return $rows;
    }

    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $strings = [];
        $shared = new SimpleXMLElement($xml);

        foreach ($shared->si as $item) {
            if (isset($item->t)) {
                $strings[] = (string) $item->t;

                continue;
            }

            $text = '';
            foreach ($item->r as $run) {
                $text .= (string) ($run->t ?? '');
            }
            $strings[] = $text;
        }

        return $strings;
    }

    private function dateColumns(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/styles.xml');

        if ($xml === false) {
            return [];
        }

        $styles = new SimpleXMLElement($xml);
        $dateNumFmtIds = [14, 15, 16, 17, 22, 27, 30, 36, 50, 57];
        $dateStyles = [];
        $index = 0;

        foreach ($styles->cellXfs->xf ?? [] as $xf) {
            $numFmtId = (int) ($xf['numFmtId'] ?? 0);

            if (in_array($numFmtId, $dateNumFmtIds, true)) {
                $dateStyles[$index] = true;
            }

            $index++;
        }

        return $dateStyles;
    }

    private function excelDateHeader(float $serial): string
    {
        return Carbon::create(1899, 12, 30)
            ->addDays((int) $serial)
            ->format('Y-m');
    }

    private function detectHeaderRow(array $rows): int
    {
        $firstNonEmpty = array_key_first($rows) ?? 1;

        foreach (array_slice($rows, 0, 25, true) as $rowNumber => $row) {
            if ($this->isEmptyRow($row)) {
                continue;
            }

            $headers = $this->headers($row);
            $hasKnownHeader = $this->findHeader($headers, [
                'TOTAL ADEUDO',
                'NOMBRE',
                'N O M B R E',
                'DEPT',
                'DEPTO',
                'DEPARTAMENTO',
            ]) !== null;
            $filledCells = collect($headers)->filter(fn (string $header): bool => $header !== '')->count();

            if ($hasKnownHeader || $filledCells >= 3) {
                return (int) $rowNumber;
            }
        }

        return (int) $firstNonEmpty;
    }

    private function headers(array $row): array
    {
        return collect($row)
            ->mapWithKeys(fn ($value, int $column): array => [$column => mb_strtoupper(trim((string) $value), 'UTF-8')])
            ->all();
    }

    private function findHeader(array $headers, array $needles, array $excludeNeedles = []): ?int
    {
        foreach ($headers as $column => $header) {
            $normalizedHeader = $this->normalizeHeader($header);
            $excluded = collect($excludeNeedles)
                ->contains(fn (string $needle): bool => str_contains($normalizedHeader, $this->normalizeHeader($needle)));

            if ($excluded) {
                continue;
            }

            foreach ($needles as $needle) {
                if (str_contains($normalizedHeader, $this->normalizeHeader($needle))) {
                    return $column;
                }
            }
        }

        return null;
    }

    private function firstHeaderColumn(array $headers): ?int
    {
        foreach ($headers as $column => $header) {
            if (trim((string) $header) !== '') {
                return $column;
            }
        }

        return null;
    }

    private function findLikelyNameColumn(array $headers): ?int
    {
        foreach ($headers as $column => $header) {
            $normalizedHeader = $this->normalizeHeader($header);

            if (
                ! str_contains($normalizedHeader, 'INQUILINO')
                && (str_contains($normalizedHeader, 'NOMBRE') || str_contains($normalizedHeader, 'PROPIETARIO') || str_contains($normalizedHeader, 'RESIDENTE'))
            ) {
                return $column;
            }
        }

        return null;
    }

    private function findLastNumericColumn(array $rows, array $headers, int $headerRow): int
    {
        $lastColumn = max(array_keys($headers ?: [1 => '']));

        foreach ($rows as $rowNumber => $row) {
            if ($rowNumber <= $headerRow) {
                continue;
            }

            foreach ($row as $column => $value) {
                if (is_numeric(str_replace([',', '$', ' '], '', (string) $value))) {
                    $lastColumn = max($lastColumn, $column);
                }
            }
        }

        return $lastColumn;
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function columnIndex(string $reference): int
    {
        $letters = preg_replace('/[^A-Z]/', '', strtoupper($reference));
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return $index;
    }

    private function moneyValue(mixed $value): float
    {
        return (float) str_replace([',', '$', ' '], '', (string) $value);
    }

    private function normalizeHeader(string $value): string
    {
        return preg_replace('/\s+/', ' ', Str::ascii(mb_strtoupper(trim($value), 'UTF-8'))) ?: '';
    }

    private function residentColumnAliases(string $field): array
    {
        return match ($field) {
            'condominium' => ['CONDOMINIO'],
            'tower' => ['TORRE'],
            'sub_tower' => ['SUB TORRE', 'SUBTORRE', 'SUB-TORRE'],
            'unit_number' => ['DEPT', 'DEPTO', 'DEPARTAMENTO', 'UNIDAD'],
            'owner_name' => ['NOMBRE DUENO', 'NOMBRE DUEÑO', 'NOMBRE PROPIETARIO', 'PROPIETARIO', 'NOMBRE', 'N O M B R E', 'RESIDENTE'],
            'owner_email' => ['CORREO ELECTRONICO DUENO', 'CORREO ELECTRÓNICO DUEÑO', 'CORREO DUENO', 'CORREO DUEÑO', 'CORREO PROPIETARIO', 'EMAIL PROPIETARIO', 'CORREO ELECTRONICO', 'CORREO ELECTRÓNICO', 'CORREO', 'EMAIL', 'E-MAIL', 'MAIL'],
            'owner_phone_primary' => ['TELEFONO 1 DUENO', 'TELÉFONO 1 DUEÑO', 'TELEFONO DUENO 1', 'TELÉFONO DUEÑO 1', 'TELEFONO PROPIETARIO 1', 'TELEFONO 1', 'TELÉFONO 1', 'TEL 1'],
            'owner_phone_secondary' => ['TELEFONO 2 DUENO', 'TELÉFONO 2 DUEÑO', 'TELEFONO DUENO 2', 'TELÉFONO DUEÑO 2', 'TELEFONO PROPIETARIO 2', 'TELEFONO 2', 'TELÉFONO 2', 'TEL 2'],
            'tenant_name' => ['NOMBRE INQUILINO', 'NOMBRE ARRENDATARIO', 'ARRENDATARIO'],
            'tenant_email' => ['CORREO ELECTRONICO INQUILINO', 'CORREO ELECTRÓNICO INQUILINO', 'CORREO INQUILINO', 'EMAIL INQUILINO', 'E-MAIL INQUILINO'],
            'tenant_phone_primary' => ['TELEFONO 1 INQUILINO', 'TELÉFONO 1 INQUILINO', 'TELEFONO INQUILINO 1', 'TEL INQUILINO 1'],
            'tenant_phone_secondary' => ['TELEFONO 2 INQUILINO', 'TELÉFONO 2 INQUILINO', 'TELEFONO INQUILINO 2', 'TEL INQUILINO 2'],
            'parking_assignment' => ['CAJON DE ESTACIONAMIENTO', 'CAJÓN DE ESTACIONAMIENTO', 'CAJON ESTACIONAMIENTO', 'CAJÓN ESTACIONAMIENTO', 'CAJON DE ESTACIONAMENITO', 'CAJON ESTACIONAMENITO', 'ESTACIONAMIENTO'],
            'roof_garden' => ['ROOF GARDEN', 'ROOF'],
            'vehicle_tag' => ['TAG VEHICULO', 'TAG VEHÍCULO', 'TAG VEHICULAR', 'TAG AUTO'],
            'pedestrian_tag' => ['TAG PEATONAL', 'TAG PEATON'],
            'storage_assignment' => ['BODEGA'],
            'pet' => ['MASCOTA', 'MASCOTAS'],
            default => [],
        };
    }

    private function rowPayload(array $headers, array $row): array
    {
        $payload = [];

        foreach ($row as $column => $value) {
            $header = trim((string) ($headers[$column] ?? ''));
            $key = $header !== '' ? $header : 'COLUMNA_'.$column;

            if (array_key_exists($key, $payload)) {
                $key .= '_'.$column;
            }

            $payload[$key] = trim((string) $value);
        }

        return $payload;
    }

    private function matchUnit(CondominiumProfile $profile, string $unitNumber, string $tower, string $ownerName): ?Unit
    {
        return Unit::query()
            ->where(function ($query) use ($profile): void {
                $query->where('condominium_profile_id', $profile->id)
                    ->orWhereNull('condominium_profile_id');
            })
            ->where('unit_number', $unitNumber)
            ->when($tower !== '', fn ($query) => $query->where('tower', $tower))
            ->first();
    }

    private function syncUnit(CondominiumProfile $profile, string $unitNumber, string $tower, string $ownerName, float $totalDebt, array $payload): Unit
    {
        $unit = $this->matchUnit($profile, $unitNumber, $tower, $ownerName);
        $email = $this->findPayloadValue($payload, $this->residentColumnAliases('owner_email'), ['INQUILINO']);
        $currentFee = $unit ? (float) $unit->fee : 0.0;
        $parkingAssignment = $this->findPayloadValue($payload, $this->residentColumnAliases('parking_assignment')) ?: $unit?->parking_assignment ?: '';
        $storageAssignment = $this->findPayloadValue($payload, $this->residentColumnAliases('storage_assignment')) ?: $unit?->storage_assignment ?: '';
        $parkingSpots = (int) ($unit?->parking_spots ?? 0);
        $storageRooms = (int) ($unit?->storage_rooms ?? 0);

        $values = [
            'condominium_profile_id' => $profile->id,
            'tower' => $tower,
            'sub_tower' => $this->findPayloadValue($payload, $this->residentColumnAliases('sub_tower')) ?: $unit?->sub_tower ?: '',
            'unit_type' => $unit?->unit_type ?: 'Departamento',
            'owner_name' => $ownerName,
            'owner_email' => $email ?: $unit?->owner_email,
            'owner_phone_primary' => $this->findPayloadValue($payload, $this->residentColumnAliases('owner_phone_primary'), ['INQUILINO']) ?: $unit?->owner_phone_primary ?: '',
            'owner_phone_secondary' => $this->findPayloadValue($payload, $this->residentColumnAliases('owner_phone_secondary'), ['INQUILINO']) ?: $unit?->owner_phone_secondary ?: '',
            'tenant_name' => $this->findPayloadValue($payload, $this->residentColumnAliases('tenant_name')) ?: $unit?->tenant_name ?: '',
            'tenant_email' => $this->findPayloadValue($payload, $this->residentColumnAliases('tenant_email')) ?: $unit?->tenant_email ?: '',
            'tenant_phone_primary' => $this->findPayloadValue($payload, $this->residentColumnAliases('tenant_phone_primary')) ?: $unit?->tenant_phone_primary ?: '',
            'tenant_phone_secondary' => $this->findPayloadValue($payload, $this->residentColumnAliases('tenant_phone_secondary')) ?: $unit?->tenant_phone_secondary ?: '',
            'ordinary_fee' => $unit ? (float) $unit->ordinary_fee : 0,
            'indiviso_percentage' => $unit ? (float) $unit->indiviso_percentage : 0,
            'extraordinary_fee' => $unit ? (float) $unit->extraordinary_fee : 0,
            'parking_rent' => $unit ? (float) $unit->parking_rent : 0,
            'storage_rent' => $unit ? (float) $unit->storage_rent : 0,
            'parking_spots' => max($parkingSpots, $parkingAssignment !== '' ? 1 : 0),
            'parking_assignment' => $parkingAssignment,
            'roof_garden' => $this->findPayloadValue($payload, $this->residentColumnAliases('roof_garden')) ?: $unit?->roof_garden ?: '',
            'vehicle_tag' => $this->findPayloadValue($payload, $this->residentColumnAliases('vehicle_tag')) ?: $unit?->vehicle_tag ?: '',
            'pedestrian_tag' => $this->findPayloadValue($payload, $this->residentColumnAliases('pedestrian_tag')) ?: $unit?->pedestrian_tag ?: '',
            'storage_rooms' => max($storageRooms, $storageAssignment !== '' ? 1 : 0),
            'storage_assignment' => $storageAssignment,
            'pet' => $this->findPayloadValue($payload, $this->residentColumnAliases('pet')) ?: $unit?->pet ?: '',
            'clothesline_cages' => $unit?->clothesline_cages ?? 0,
            'fee' => $currentFee,
            'status' => $totalDebt > 0 ? 'Atrasado' : 'Pagado',
        ];

        if ($unit) {
            $unit->update($values);

            return $unit;
        }

        return Unit::query()->create([
            'unit_number' => $unitNumber,
            ...$values,
        ]);
    }

    private function profileForResidentDirectory(string $name, CondominiumProfile $fallbackProfile): CondominiumProfile
    {
        $name = trim($name);

        if ($name === '') {
            return $fallbackProfile;
        }

        $normalizedName = $this->normalizeCondominiumLookup($name);
        $profile = CondominiumProfile::query()
            ->orderBy('commercial_name')
            ->get()
            ->first(fn (CondominiumProfile $profile): bool => $this->profileMatchesLookup($profile, $normalizedName));

        return $profile ?? CondominiumProfile::query()->create([
            'commercial_name' => $name,
        ]);
    }

    private function refreshResidentDirectoryProfileStats(array $profileStats): void
    {
        foreach (array_keys($profileStats) as $profileId) {
            $profile = CondominiumProfile::query()->find($profileId);

            if (! $profile) {
                continue;
            }

            $departmentCount = Unit::query()
                ->where('condominium_profile_id', $profile->id)
                ->count();
            $parkingCount = max(
                (int) Unit::query()->where('condominium_profile_id', $profile->id)->sum('parking_spots'),
                Unit::query()
                    ->where('condominium_profile_id', $profile->id)
                    ->where('parking_assignment', '!=', '')
                    ->count()
            );
            $storageCount = max(
                (int) Unit::query()->where('condominium_profile_id', $profile->id)->sum('storage_rooms'),
                Unit::query()
                    ->where('condominium_profile_id', $profile->id)
                    ->where('storage_assignment', '!=', '')
                    ->count()
            );
            $hasRoofGarden = Unit::query()
                ->where('condominium_profile_id', $profile->id)
                ->where('roof_garden', '!=', '')
                ->exists();

            $profile->update([
                'departments_count' => max((int) $profile->departments_count, $departmentCount),
                'parking_spaces_count' => max((int) $profile->parking_spaces_count, $parkingCount),
                'storage_rooms_count' => max((int) $profile->storage_rooms_count, $storageCount),
                'roof_garden_enabled' => (bool) $profile->roof_garden_enabled || $hasRoofGarden,
            ]);
        }
    }

    private function normalizeCondominiumLookup(string $value): string
    {
        $value = Str::ascii(mb_strtolower($value, 'UTF-8'));
        $value = preg_replace('/\bll\b/u', 'ii', $value) ?: $value;
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?: $value;

        return collect(explode(' ', $value))
            ->map(fn (string $token): string => trim($token))
            ->filter(fn (string $token): bool => $token !== '' && ! in_array($token, ['de', 'del', 'la', 'el', 'los', 'las'], true))
            ->implode(' ');
    }

    private function profileMatchesLookup(CondominiumProfile $profile, string $normalizedNeedle): bool
    {
        if ($normalizedNeedle === '') {
            return false;
        }

        $haystacks = collect([
            $profile->commercial_name,
            $profile->address,
            $profile->tax_id,
        ])
            ->map(fn (?string $value): string => $this->normalizeCondominiumLookup((string) $value))
            ->filter();
        $needleTokens = collect(explode(' ', $normalizedNeedle))->filter()->values();

        return $haystacks->contains(function (string $haystack) use ($normalizedNeedle, $needleTokens): bool {
            if ($haystack === $normalizedNeedle || str_contains($haystack, $normalizedNeedle)) {
                return true;
            }

            return $needleTokens->isNotEmpty()
                && $needleTokens->every(fn (string $token): bool => str_contains($haystack, $token));
        });
    }

    private function findPayloadValue(array $payload, array $needles, array $excludeNeedles = []): ?string
    {
        foreach ($payload as $header => $value) {
            if (blank($value)) {
                continue;
            }

            $normalizedHeader = $this->normalizeHeader((string) $header);
            $excluded = collect($excludeNeedles)
                ->contains(fn (string $needle): bool => str_contains($normalizedHeader, $this->normalizeHeader($needle)));

            if ($excluded) {
                continue;
            }

            foreach ($needles as $needle) {
                if (str_contains($normalizedHeader, $this->normalizeHeader($needle))) {
                    return trim((string) $value);
                }
            }
        }

        return null;
    }
}
