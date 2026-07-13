<?php

namespace App\Support;

use App\Models\BillingBaseImport;
use App\Models\CondominiumProfile;
use App\Models\ImportedResidentAccount;
use App\Models\Unit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class BillingExcelImporter
{
    public function import(string $path, CondominiumProfile $profile, ?BillingBaseImport $baseImport = null): int
    {
        $rows = $this->readSheetRows($path);
        $headers = $this->headers($rows[2] ?? []);

        $unitColumn = $this->findHeader($headers, ['DEPT', 'DEPTO', 'DEPARTAMENTO']);
        $towerColumn = $this->findHeader($headers, ['TORRE']);
        $subTowerColumn = $this->findHeader($headers, ['SUB TORRE', 'SUBTORRE']);
        $nameColumn = $this->findHeader($headers, ['NOMBRE', 'N O M B R E']);
        $totalDebtColumn = $this->findHeader($headers, ['TOTAL ADEUDO']);
        $statusColumn = $this->findHeader($headers, ['ESTATUS']);
        $observationsColumn = $this->findHeader($headers, ['OBSERVACIONES']);

        $unitColumn ??= $this->firstHeaderColumn($headers) ?? 1;
        $nameColumn ??= $this->findLikelyNameColumn($headers) ?? $unitColumn;
        $totalDebtColumn ??= $this->findLastNumericColumn($rows, $headers);

        $yearColumns = collect($headers)
            ->filter(fn (string $header): bool => preg_match('/^20\d{2}$/', $header) === 1)
            ->all();

        $imported = 0;

        DB::transaction(function () use ($rows, $headers, $profile, $baseImport, $unitColumn, $towerColumn, $subTowerColumn, $nameColumn, $totalDebtColumn, $statusColumn, $observationsColumn, $yearColumns, &$imported): void {
            foreach ($rows as $rowNumber => $row) {
                if ($rowNumber <= 2) {
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
                $unit = $this->matchUnit($unitNumber, $tower, $ownerName);
                $yearStatuses = [];

                foreach ($yearColumns as $column => $year) {
                    $value = trim((string) ($row[$column] ?? ''));

                    if ($value !== '') {
                        $yearStatuses[$year] = $value;
                    }
                }

                ImportedResidentAccount::query()->updateOrCreate([
                    'condominium_profile_id' => $profile->id,
                    'unit_number' => $unitNumber,
                    'tower' => $tower,
                ], [
                    'billing_base_import_id' => $baseImport?->id,
                    'unit_id' => $unit?->id,
                    'sub_tower' => trim((string) ($row[$subTowerColumn] ?? '')) ?: null,
                    'source_row_number' => $rowNumber,
                    'owner_name' => $ownerName,
                    'total_debt' => $totalDebt,
                    'status' => $totalDebt > 0 ? 'adeudo' : 'no_adeudo',
                    'year_statuses' => $yearStatuses,
                    'raw_payload' => $this->rowPayload($headers, $row),
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

    private function readSheetRows(string $path): array
    {
        $zip = new ZipArchive();

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

    private function headers(array $row): array
    {
        return collect($row)
            ->mapWithKeys(fn ($value, int $column): array => [$column => mb_strtoupper(trim((string) $value), 'UTF-8')])
            ->all();
    }

    private function findHeader(array $headers, array $needles): ?int
    {
        foreach ($headers as $column => $header) {
            foreach ($needles as $needle) {
                if (str_contains($header, mb_strtoupper($needle, 'UTF-8'))) {
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
            if (str_contains($header, 'NOMBRE') || str_contains($header, 'PROPIETARIO') || str_contains($header, 'RESIDENTE')) {
                return $column;
            }
        }

        return null;
    }

    private function findLastNumericColumn(array $rows, array $headers): int
    {
        $lastColumn = max(array_keys($headers ?: [1 => '']));

        foreach ($rows as $rowNumber => $row) {
            if ($rowNumber <= 2) {
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

    private function matchUnit(string $unitNumber, string $tower, string $ownerName): ?Unit
    {
        return Unit::query()
            ->where('unit_number', $unitNumber)
            ->when($tower !== '', fn ($query) => $query->where('tower', $tower))
            ->first()
            ?? Unit::query()->where('owner_name', $ownerName)->first();
    }
}
