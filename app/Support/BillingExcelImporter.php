<?php

namespace App\Support;

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
    public function import(string $path, CondominiumProfile $profile): int
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

        if (! $unitColumn || ! $nameColumn || ! $totalDebtColumn) {
            throw new RuntimeException('No se encontraron las columnas requeridas: DEPT, Nombre y TOTAL ADEUDO.');
        }

        $yearColumns = collect($headers)
            ->filter(fn (string $header): bool => preg_match('/^20\d{2}$/', $header) === 1)
            ->all();

        $imported = 0;

        DB::transaction(function () use ($rows, $profile, $unitColumn, $towerColumn, $subTowerColumn, $nameColumn, $totalDebtColumn, $statusColumn, $observationsColumn, $yearColumns, &$imported): void {
            foreach ($rows as $rowNumber => $row) {
                if ($rowNumber <= 2) {
                    continue;
                }

                $unitNumber = trim((string) ($row[$unitColumn] ?? ''));
                $ownerName = trim((string) ($row[$nameColumn] ?? ''));

                if ($unitNumber === '' || $ownerName === '') {
                    continue;
                }

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
                    'unit_id' => $unit?->id,
                    'sub_tower' => trim((string) ($row[$subTowerColumn] ?? '')) ?: null,
                    'owner_name' => $ownerName,
                    'total_debt' => $totalDebt,
                    'status' => $totalDebt > 0 ? 'adeudo' : 'no_adeudo',
                    'year_statuses' => $yearStatuses,
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
                $value = isset($cell->v) ? (string) $cell->v : '';

                if ($type === 's') {
                    $value = $sharedStrings[(int) $value] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = (string) ($cell->is->t ?? '');
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

    private function matchUnit(string $unitNumber, string $tower, string $ownerName): ?Unit
    {
        return Unit::query()
            ->where('unit_number', $unitNumber)
            ->when($tower !== '', fn ($query) => $query->where('tower', $tower))
            ->first()
            ?? Unit::query()->where('owner_name', $ownerName)->first();
    }
}
